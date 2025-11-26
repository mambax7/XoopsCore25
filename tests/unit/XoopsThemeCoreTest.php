<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'http://example.com');
}
if (!defined('XOOPS_UPLOAD_URL')) {
    define('XOOPS_UPLOAD_URL', XOOPS_URL . '/uploads');
}

// Basic XOOPS constants used by theme logic
if (!defined('XOOPS_SIDEBLOCK_LEFT')) {
    define('XOOPS_SIDEBLOCK_LEFT', 0);
    define('XOOPS_SIDEBLOCK_RIGHT', 1);
    define('XOOPS_CENTERBLOCK_LEFT', 2);
    define('XOOPS_CENTERBLOCK_CENTER', 3);
    define('XOOPS_CENTERBLOCK_RIGHT', 4);
    define('XOOPS_CENTERBLOCK_BOTTOMLEFT', 5);
    define('XOOPS_CENTERBLOCK_BOTTOM', 6);
    define('XOOPS_CENTERBLOCK_BOTTOMRIGHT', 7);
    define('XOOPS_FOOTERBLOCK_LEFT', 8);
    define('XOOPS_FOOTERBLOCK_CENTER', 9);
    define('XOOPS_FOOTERBLOCK_RIGHT', 10);
    define('XOOPS_FOOTERBLOCK_ALL', 11);
}
if (!defined('XOOPS_BLOCK_VISIBLE')) {
    define('XOOPS_BLOCK_VISIBLE', 1);
}
if (!defined('XOOPS_CONF_SEARCH')) {
    define('XOOPS_CONF_SEARCH', 5);
}
if (!defined('XOOPS_CONF_METAFOOTER')) {
    define('XOOPS_CONF_METAFOOTER', 6);
}
if (!defined('XOOPS_GROUP_ANONYMOUS')) {
    define('XOOPS_GROUP_ANONYMOUS', 3);
}
if (!defined('_XO_ER_FILENOTFOUND')) {
    define('_XO_ER_FILENOTFOUND', 'FILE_NOT_FOUND');
}

// Cache/config helpers
if (!function_exists('xoops_setConfigOption')) {
    $GLOBALS['xoopsConfigOptions'] = [];
    function xoops_setConfigOption($name, $value)
    {
        $GLOBALS['xoopsConfigOptions'][$name] = $value;
    }
}
if (!function_exists('xoops_getConfigOption')) {
    function xoops_getConfigOption($name)
    {
        return $GLOBALS['xoopsConfigOptions'][$name] ?? null;
    }
}
if (!function_exists('xoops_load')) {
    function xoops_load($name)
    {
        return true;
    }
}
if (!function_exists('xoops_getbanner')) {
    function xoops_getbanner()
    {
        return 'banner';
    }
}
if (!function_exists('xoops_getcss')) {
    function xoops_getcss($theme)
    {
        return "{$theme}/style.css";
    }
}
if (!function_exists('xoops_getHandler')) {
    class XoopsConfigHandler
    {
        public function getConfigsByCat($cat)
        {
            return ['enable_search' => 1];
        }

        public function getConfigs($criteria, $id_as_key = true)
        {
            return [
                new class {
                    public function getVar($name, $format = 'n')
                    {
                        if ('conf_name' === $name) {
                            return 'meta_description';
                        }
                        if ('conf_value' === $name) {
                            return 'Site description';
                        }

                        return null;
                    }
                },
            ];
        }
    }

    function xoops_getHandler($name)
    {
        return new XoopsConfigHandler();
    }
}

if (!class_exists('Criteria')) {
    class Criteria
    {
        public function __construct($field, $value = null) {}
    }
}
if (!class_exists('CriteriaCompo')) {
    class CriteriaCompo
    {
        public function __construct($criteria = null) {}
        public function add($criteria) {}
    }
}

if (!class_exists('Xmf\\Request')) {
    class Request
    {
        public static function getString($name, $default = '', $source = 'REQUEST')
        {
            return $_SERVER[$name] ?? $default;
        }
    }
    class_alias('Request', 'Xmf\\Request');
}

class TestLogger
{
    public $triggered = [];
    public $blocks    = [];

    public static function getInstance()
    {
        if (!isset($GLOBALS['testLogger'])) {
            $GLOBALS['testLogger'] = new self();
        }

        return $GLOBALS['testLogger'];
    }

    public function triggerError($path, $code, $file, $line, $type)
    {
        $this->triggered[] = [$path, $code, $type];
    }

    public function addBlock($name, $cached = false, $ttl = null)
    {
        $this->blocks[] = [$name, $cached, $ttl];
    }

    public function stopTime($label)
    {
        $this->stopped[] = $label;
    }
}

class XoopsCache
{
    private static $instance;
    public $data = [];

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function read($id)
    {
        return $this->data[$id] ?? false;
    }

    public function write($id, $value)
    {
        $this->data[$id] = $value;
    }
}

class XoopsPreload
{
    private static $instance;

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function triggerEvent($event, $args)
    {
        $this->lastEvent = [$event, $args];
    }
}

$stubBase = sys_get_temp_dir() . '/xoops_theme_stubs';
if (!is_dir($stubBase . '/class')) {
    mkdir($stubBase . '/class', 0777, true);
}
file_put_contents(
    $stubBase . '/class/xoopsblock.php',
    <<<'PHPBLOCK'
<?php
class XoopsBlock
{
    public function getAllByGroupModule($groups, $mid, $isStart, $visible)
    {
        return [new XoopsBlockStub()];
    }
}
class XoopsBlockStub
{
    public function getVar($name, $format = 'n')
    {
        $map = [
            'bid'            => 99,
            'dirname'        => 'system',
            'title'          => 'Block title',
            'weight'         => 2,
            'last_modified'  => 123,
            'bcachetime'     => 0,
            'template'       => 'dummy.tpl',
            'name'           => 'Dummy block',
            'side'           => XOOPS_SIDEBLOCK_LEFT,
        ];

        return $map[$name];
    }

    public function buildBlock()
    {
        return ['data' => 'ok'];
    }
}
PHPBLOCK
);
file_put_contents(
    $stubBase . '/class/template.php',
    <<<'PHPTPL'
<?php
class XoopsTpl
{
    public $assigned = [];
    public $caching = 0;
    public $cache_lifetime = 0;
    public $currentTheme;
    public $compileId;

    public function assignByRef($name, &$value)
    {
        $this->assigned[$name] =& $value;
    }

    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->assigned[$key] = $val;
            }
        } else {
            $this->assigned[$name] = $value;
        }
    }

    public function setCompileId($id = null)
    {
        $this->compileId = $id;
    }

    public function fetch($tpl, $cacheId = null)
    {
        return "tpl:{$tpl}:{$cacheId}";
    }

    public function isCached($tpl, $cacheId = null)
    {
        return false;
    }

    public function display($tpl)
    {
        $this->lastDisplayed = $tpl;
    }

    public function getTemplateVars($name)
    {
        return $this->assigned[$name] ?? '';
    }
}
PHPTPL
);

$GLOBALS['xoops'] = new class($stubBase)
{
    private $base;
    public function __construct($base)
    {
        $this->base = $base;
    }

    public function path($path)
    {
        return $this->base . '/' . $path;
    }

    public function url($path)
    {
        return XOOPS_URL . '/' . $path;
    }
};

if (!defined('XOOPS_THEME_PATH')) {
    define('XOOPS_THEME_PATH', sys_get_temp_dir() . '/xoops_themes');
}
if (!defined('XOOPS_THEME_URL')) {
    define('XOOPS_THEME_URL', XOOPS_URL . '/themes');
}
if (!defined('XOOPS_ADMINTHEME_PATH')) {
    define('XOOPS_ADMINTHEME_PATH', sys_get_temp_dir() . '/xoops_admin_themes');
}
if (!defined('XOOPS_ADMINTHEME_URL')) {
    define('XOOPS_ADMINTHEME_URL', XOOPS_URL . '/adminthemes');
}

foreach ([XOOPS_THEME_PATH, XOOPS_ADMINTHEME_PATH] as $dir) {
    if (!is_dir($dir . '/alpha')) {
        mkdir($dir . '/alpha', 0777, true);
    }
    file_put_contents($dir . '/alpha/theme.tpl', '<h1>Theme</h1>');
}

$GLOBALS['xoopsLogger'] = TestLogger::getInstance();

require_once XOOPS_TU_ROOT_PATH . '/class/xoopskernel.php';
require_once XOOPS_TU_ROOT_PATH . '/class/theme_blocks.php';
require_once XOOPS_TU_ROOT_PATH . '/class/theme.php';

class XoopsThemeCoreTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['xoopsConfig'] = [
            'startpage' => '--',
            'theme_set' => 'alpha',
            'banners'   => false,
            'language'  => 'english',
            'sitename'  => 'XOOPS',
            'slogan'    => 'Just test',
        ];
        $GLOBALS['xoopsOption'] = [];
        $GLOBALS['xoopsUser']   = null;
        $GLOBALS['xoopsUserIsAdmin'] = false;
        $GLOBALS['xoopsModule'] = null;
        $_SERVER['REQUEST_URI'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = XOOPS_ROOT_PATH . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['PATH_TRANSLATED'] = null;
        $_SESSION = [];
        $GLOBALS['xoopsConfigOptions'] = ['theme_set_allowed' => ['alpha']];
        $GLOBALS['testLogger']->triggered = [];
    }

    public function testKernelPathAndUrlHelpers(): void
    {
        $kernel = new xos_kernel_Xoops2();

        $this->assertSame(XOOPS_ROOT_PATH . DS . 'modules', $kernel->path('modules'));
        $this->assertSame('https://example.com', $kernel->url('https://example.com'));

        $built = $kernel->buildUrl('index.php?foo=bar', ['baz' => 'qux']);
        $this->assertSame('index.php?foo=bar&baz=qux', $built);
    }

    public function testKernelPathExistsLogsMissing(): void
    {
        $kernel = new xos_kernel_Xoops2();
        $existing = tempnam(sys_get_temp_dir(), 'xoops');

        $this->assertSame($existing, $kernel->pathExists($existing, E_USER_WARNING));

        unlink($existing);
        $this->assertFalse($kernel->pathExists($existing, E_USER_WARNING));
        $this->assertSame($existing, $GLOBALS['testLogger']->triggered[0][0]);
    }

    public function testKernelThemeSelectAndGzip(): void
    {
        $kernel = new xos_kernel_Xoops2();
        $_POST['xoops_theme_select'] = 'alpha';

        xoops_setConfigOption('gzip_compression', 1);
        $kernel->themeSelect();
        $this->assertSame('alpha', xoops_getConfigOption('theme_set'));
        $this->assertSame('alpha', $_SESSION['xoopsUserTheme']);

        $kernel->gzipCompression();
        $this->assertSame(0, xoops_getConfigOption('gzip_compression'));
    }

    public function testPageBuilderRetrievesBlocks(): void
    {
        $builder = new xos_logos_PageBuilder();
        $builder->theme = new class {
            public $template;
            public function __construct()
            {
                $this->template = new XoopsTpl();
            }
        };
        $builder->xoInit();

        $this->assertArrayHasKey('canvas_left', $builder->blocks);
        $left = $builder->blocks['canvas_left'];
        $this->assertArrayHasKey(99, $left);
        $this->assertSame('tpl:db:dummy.tpl:blk_99', $left[99]['content']);
    }

    public function testPageBuilderCacheIdGeneratedByTheme(): void
    {
        $builder      = new xos_logos_PageBuilder();
        $builder->theme = new class {
            public function generateCacheId($id)
            {
                return 'prefix-' . $id;
            }
        };

        $this->assertSame('prefix-abc', $builder->generateCacheId('abc'));
    }

    public function testThemeFactoryCreatesFromRequest(): void
    {
        $_REQUEST['xoops_theme_select'] = 'alpha';
        $_SESSION = [];
        ob_start();
        $factory = new xos_opal_ThemeFactory();
        $theme   = $factory->createInstance();
        ob_end_clean();

        $this->assertInstanceOf(xos_opal_Theme::class, $theme);
        $this->assertSame('alpha', $theme->folderName);
        $this->assertSame(XOOPS_THEME_URL . '/alpha', $theme->url);
        $this->assertSame($theme, $theme->template->assigned['xoTheme']);
    }

    public function testAdminThemeFactoryOverridesPaths(): void
    {
        $_REQUEST['xoops_theme_select'] = 'alpha';
        ob_start();
        $factory = new xos_opal_AdminThemeFactory();
        $theme   = $factory->createInstance();
        ob_end_clean();

        $this->assertFalse($theme->renderBanner);
        $this->assertSame(XOOPS_ADMINTHEME_PATH . '/alpha', $theme->path);
        $this->assertSame(XOOPS_ADMINTHEME_URL . '/alpha', $theme->url);
        $this->assertSame($theme->path, $theme->template->assigned['theme_path']);
    }
}
