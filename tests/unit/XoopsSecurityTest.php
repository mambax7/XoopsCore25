<?php
use PHPUnit\Framework\TestCase;

if (!class_exists('XoopsSecurity')) {
    require_once dirname(__DIR__, 2) . '/htdocs/class/xoopssecurity.php';
}

if (!function_exists('xoops_getenv')) {
    function xoops_getenv($key)
    {
        return $_SERVER[$key] ?? '';
    }
}

if (!defined('XOOPS_DB_PREFIX')) {
    define('XOOPS_DB_PREFIX', 'pref');
}

if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'http://example.com');
}

class XoopsSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        $_REQUEST = [];
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-agent';
        $GLOBALS['xoopsLogger'] = new class {
            public $extras = [];
            public function addExtra($name, $msg)
            {
                $this->extras[] = [$name, $msg];
            }
        };
    }

    public function testCreateTokenStoresSessionDataAndHashesId()
    {
        $security = new XoopsSecurity();
        $token = $security->createToken(10, 'TOKEN');

        $sessionName = 'TOKEN_SESSION';
        $this->assertArrayHasKey($sessionName, $_SESSION);
        $this->assertCount(1, $_SESSION[$sessionName]);
        $stored = $_SESSION[$sessionName][0];

        $expected = md5($stored['id'] . $_SERVER['HTTP_USER_AGENT'] . XOOPS_DB_PREFIX);
        $this->assertSame($expected, $token);
        $this->assertGreaterThanOrEqual(time(), $stored['expire']);
    }

    public function testValidateTokenClearsWhenValid()
    {
        $security = new XoopsSecurity();
        $token = $security->createToken(10, 'TOKEN');

        $result = $security->validateToken($token, true, 'TOKEN');

        $this->assertTrue($result);
        $this->assertSame([], $_SESSION['TOKEN_SESSION']);
        $this->assertContains(['Token Validation', 'Valid token found'], $GLOBALS['xoopsLogger']->extras);
    }

    public function testValidateTokenExpiredSetsError()
    {
        $security = new XoopsSecurity();
        $_SESSION['TOKEN_SESSION'] = [
            [
                'id'     => 'expired',
                'expire' => time() - 5,
            ],
        ];
        $token = md5('expired' . $_SERVER['HTTP_USER_AGENT'] . XOOPS_DB_PREFIX);

        $result = $security->validateToken($token, true, 'TOKEN');

        $this->assertFalse($result);
        $errors = $security->getErrors();
        $this->assertContains('Valid token expired', $errors);
        $this->assertSame([], $_SESSION['TOKEN_SESSION']);
    }

    public function testCheckRefererHonorsDocheckFlag()
    {
        $security = new XoopsSecurity();

        $_SERVER['HTTP_REFERER'] = '';
        $this->assertFalse($security->checkReferer(1));

        $_SERVER['HTTP_REFERER'] = XOOPS_URL . '/path';
        $this->assertTrue($security->checkReferer(1));

        $_SERVER['HTTP_REFERER'] = '';
        $this->assertTrue($security->checkReferer(0));
    }
}
