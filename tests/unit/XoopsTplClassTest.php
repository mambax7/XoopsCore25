<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/xoops_lib/vendor/smarty/smarty/libs/Smarty.class.php';

if (!defined('_LANGCODE')) {
    define('_LANGCODE', 'en');
}
if (!defined('_CHARSET')) {
    define('_CHARSET', 'UTF-8');
}
if (!defined('XOOPS_VERSION')) {
    define('XOOPS_VERSION', '2.5.11');
}
if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'https://example.com');
}
if (!defined('XOOPS_UPLOAD_URL')) {
    define('XOOPS_UPLOAD_URL', XOOPS_URL . '/uploads');
}
if (!defined('XOOPS_THEME_PATH')) {
    define('XOOPS_THEME_PATH', sys_get_temp_dir() . '/xoops_themes');
}

if (!class_exists(XoopsPreload::class)) {
    class XoopsPreload
    {
        public $events = [];
        private static $instance;

        public static function getInstance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function triggerEvent($name, $args = [])
        {
            $this->events[] = [$name, $args];
        }
    }
}

if (!isset($GLOBALS['xoopsLogger'])) {
    $GLOBALS['xoopsLogger'] = new class () {
        public $deprecated = [];

        public function addDeprecated($message)
        {
            $this->deprecated[] = $message;
        }
    };
}

$GLOBALS['xoopsConfig'] = [
    'debug_mode'   => 0,
    'template_set' => 'default',
    'theme_set'    => 'default',
];

require_once XOOPS_ROOT_PATH . '/class/template.php';

class XoopsTplClassTest extends TestCase
{
    protected function setUp(): void
    {
        if (!is_dir(XOOPS_THEME_PATH)) {
            mkdir(XOOPS_THEME_PATH, 0777, true);
        }
    }

    public function testConstructorInitializesSmartySettings(): void
    {
        $tpl = new XoopsTpl();

        $this->assertSame('<{', $tpl->left_delimiter);
        $this->assertSame('}>', $tpl->right_delimiter);
        $this->assertContains(XOOPS_THEME_PATH, (array) $tpl->template_dir);
        $this->assertSame(XOOPS_VAR_PATH . '/caches/smarty_cache', $tpl->cache_dir);
        $this->assertSame(XOOPS_VAR_PATH . '/caches/smarty_compile', $tpl->compile_dir);
        $this->assertSame(Smarty::COMPILECHECK_ON, $tpl->compile_check);
        $this->assertContains(XOOPS_ROOT_PATH . '/class/smarty3_plugins', $tpl->plugins_dir);

        $expectedCompileId = substr(md5(XOOPS_URL), 0, 8) . '-system-default-default';
        $this->assertSame($expectedCompileId, $tpl->compile_id);

        $this->assertSame(XOOPS_URL, $tpl->getTemplateVars('xoops_url'));
        $this->assertSame(XOOPS_ROOT_PATH, $tpl->getTemplateVars('xoops_rootpath'));
        $this->assertSame(_LANGCODE, $tpl->getTemplateVars('xoops_langcode'));
        $this->assertSame(_CHARSET, $tpl->getTemplateVars('xoops_charset'));
        $this->assertSame(XOOPS_VERSION, $tpl->getTemplateVars('xoops_version'));
        $this->assertSame(XOOPS_UPLOAD_URL, $tpl->getTemplateVars('xoops_upload_url'));

        $preload = XoopsPreload::getInstance();
        $this->assertSame([
            ['core.class.template.new', [$tpl]],
        ], $preload->events);
    }

    public function testFetchFromDataUsesTemporaryAssignments(): void
    {
        $tpl = new XoopsTpl();
        $tpl->assign('existing', 'keep');

        $output = $tpl->fetchFromData('Hello <{$name}>', false, ['name' => 'World']);

        $this->assertSame('Hello World', trim($output));
        $this->assertSame('keep', $tpl->getTemplateVars('existing'));
    }

    public function testSetCompileIdHonorsParameters(): void
    {
        $tpl = new XoopsTpl();
        $tpl->setCompileId('news', 'theme1', 'tpl1');

        $expected = substr(md5(XOOPS_URL), 0, 8) . '-news-theme1-tpl1';
        $this->assertSame($expected, $tpl->compile_id);
    }

    public function testXoopsClearCacheResetsCompileIdAndClearsTemplates(): void
    {
        $tpl = new class () extends XoopsTpl {
            public $clearCalls;

            public function clearCompiledTemplate($tpl_file = null, $compile_id = null, $exp_time = null)
            {
                $this->clearCalls[] = [$tpl_file, $compile_id, $exp_time];
                return true;
            }
        };

        $tpl->compile_id = 'original';
        $tpl->xoopsClearCache('mod', 'themeX', 'tplX');

        $expectedId = substr(md5(XOOPS_URL), 0, 8) . '-mod-tplX-themeX';
        $this->assertSame($expectedId, $tpl->compile_id);
        $this->assertSame([[null, $expectedId, null]], $tpl->clearCalls);
    }

    public function testDeprecatedMethodsLogWarnings(): void
    {
        $logger = $GLOBALS['xoopsLogger'];
        $logger->deprecated = [];

        $tpl = new XoopsTpl();
        $tpl->xoops_setCaching(2);

        $this->assertSame(2, $tpl->caching);
        $this->assertNotEmpty($logger->deprecated);
        $this->assertStringContainsString('xoops_setCaching', $logger->deprecated[0]);
    }
}
