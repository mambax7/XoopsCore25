<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

if (!function_exists('xoops_loadLanguage')) {
    function xoops_loadLanguage($name, $domain = '', $language = null)
    {
        return true;
    }
}

if (!function_exists('xoops_load')) {
    function xoops_load($name)
    {
        return true;
    }
}

if (!class_exists('Xmf\\Request')) {
    class Request
    {
        public static array $post = [];

        public static function getString($key, $default = '', $method = 'POST')
        {
            return $method === 'POST' ? (self::$post[$key] ?? $default) : $default;
        }
    }
}

if (!class_exists('Xmf\\IPAddress')) {
    class IPAddress
    {
        public static function fromRequest()
        {
            return new self();
        }

        public function asReadable()
        {
            return '127.0.0.1';
        }
    }
}

if (!class_exists('XoopsPreload')) {
    class XoopsPreload
    {
        public array $events = [];

        public static function getInstance()
        {
            static $instance;
            $instance ??= new self();

            return $instance;
        }

        public function triggerEvent($name, $result)
        {
            $this->events[] = [$name, $result];
        }
    }
}

if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'http://localhost');
}

if (!defined('_CAPTCHA_CAPTION')) {
    define('_CAPTCHA_CAPTION', 'captcha');
    define('_CAPTCHA_INVALID_CODE', 'invalid');
    define('_CAPTCHA_TOOMANYATTEMPTS', 'too many');
    define('_CAPTCHA_RULE_TEXT', 'rule text');
    define('_CAPTCHA_MAXATTEMPTS', 'max %d');
    define('_CAPTCHA_RULE_IMAGE', 'image rule');
    define('_CAPTCHA_RULE_CASEINSENSITIVE', 'case insensitive');
    define('_CAPTCHA_RULE_CASESENSITIVE', 'case sensitive');
    define('_CAPTCHA_REFRESH', 'refresh');
}

require_once XOOPS_ROOT_PATH . '/class/captcha/xoopscaptcha.php';
require_once XOOPS_ROOT_PATH . '/class/captcha/text.php';
require_once XOOPS_ROOT_PATH . '/class/captcha/image.php';
require_once XOOPS_ROOT_PATH . '/class/captcha/recaptcha2.php';
require_once XOOPS_ROOT_PATH . '/class/captcha/image/scripts/image.php';

class DummyCaptchaHandler extends XoopsCaptchaMethod
{
    public bool $verifyResult = true;
    public bool $garbageDestroyed = false;

    public function __construct()
    {
        parent::__construct();
        $this->config = ['name' => 'dummy'];
    }

    public function verify($sessionName = null)
    {
        return $this->verifyResult;
    }

    public function destroyGarbage()
    {
        $this->garbageDestroyed = true;
    }

    public function render()
    {
        return '<input name="' . $this->config['name'] . '">';
    }
}

class RecaptchaStub extends XoopsCaptchaRecaptcha2
{
    public array $mockResponse = ['success' => true];

    public function verify($sessionName = null)
    {
        if (isset($this->mockResponse['success']) && true === $this->mockResponse['success']) {
            return true;
        }
        $captchaInstance = \XoopsCaptcha::getInstance();
        foreach ($this->mockResponse['error-codes'] ?? [] as $msg) {
            $captchaInstance->message[] = $msg;
        }

        return false;
    }
}

final class XoopsCaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Request::$post = [];
    }

    public function testCaptchaLoadsHandlerAndRenders(): void
    {
        $captcha = XoopsCaptcha::getInstance();
        $captcha->setConfigs([
            'name' => 'mycaptcha',
            'mode' => 'text',
            'maxattempts' => 2,
            'skipmember' => false,
        ]);
        $captcha->active = true;
        $handler = $captcha->loadHandler();

        $this->assertInstanceOf(XoopsCaptchaText::class, $handler);
        $rendered = $captcha->render();
        $this->assertStringContainsString('name="mycaptcha"', $rendered);
        $this->assertSame(0, $_SESSION['mycaptcha_attempt']);
        $this->assertSame(2, $_SESSION['mycaptcha_maxattempts']);
    }

    public function testVerifyTracksAttemptsAndMessages(): void
    {
        $captcha = XoopsCaptcha::getInstance();
        $captcha->name = 'mycaptcha';
        $captcha->active = true;
        $captcha->handler = new DummyCaptchaHandler();
        $captcha->config = ['maxattempts' => 1];
        $_SESSION['mycaptcha_attempt'] = 0;

        $this->assertFalse($captcha->verify());
        $this->assertSame(1, $_SESSION['mycaptcha_attempt']);
        $this->assertStringContainsString(_CAPTCHA_INVALID_CODE, $captcha->getMessage());
    }

    public function testCaptchaMethodComparison(): void
    {
        $method = new XoopsCaptchaMethod();
        $method->config = ['casesensitive' => false];
        $_SESSION['code_attempt_code'] = 'AbC';
        Request::$post['code_attempt'] = 'abc';

        $this->assertTrue($method->verify('code_attempt'));
    }

    public function testImageRenderProducesMarkup(): void
    {
        $handler = new XoopsCaptcha();
        $image = new XoopsCaptchaImage($handler);
        $image->config = [
            'name' => 'imgcaptcha',
            'num_chars' => 5,
            'casesensitive' => false,
            'maxattempts' => 3,
        ];
        $html = $image->render();

        $this->assertStringContainsString('xoops_captcha_refresh', $html);
        $this->assertStringContainsString('name="imgcaptcha"', $html);
    }

    public function testImageHandlerGeneratesCode(): void
    {
        $imageHandler = new XoopsCaptchaImageHandler();
        $imageHandler->config['num_chars'] = 6;
        $imageHandler->config['casesensitive'] = false;
        $imageHandler->config['skip_characters'] = 'abc';
        $imageHandler->invalid = false;

        $this->assertTrue($imageHandler->generateCode());
        $this->assertSame(6, strlen($imageHandler->code));
        $this->assertSame($_SESSION['captcha_name_code'] ?? null, null);
    }

    public function testImageHandlerCreateImageInvalid(): void
    {
        $imageHandler = new XoopsCaptchaImageHandler();
        $imageHandler->invalid = true;

        $this->assertNull($imageHandler->createImage());
    }

    public function testRecaptchaRenderAndErrorHandling(): void
    {
        $recaptcha = new RecaptchaStub();
        $recaptcha->config = [
            'website_key' => 'site',
            'secret_key' => 'secret',
        ];
        $rendered = $recaptcha->render();
        $this->assertStringContainsString('g-recaptcha', $rendered);

        $recaptcha->mockResponse = ['success' => false, 'error-codes' => ['bad-request']];
        $this->assertFalse($recaptcha->verify());
        $this->assertContains('bad-request', XoopsCaptcha::getInstance()->message);
    }
}
