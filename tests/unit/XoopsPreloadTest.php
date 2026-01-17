<?php
use PHPUnit\Framework\TestCase;

if (!class_exists('XoopsLoad', false)) {
    class XoopsLoad
    {
        public static $loaded = [];

        public static function load($name)
        {
            self::$loaded[] = $name;
            return true;
        }
    }
}

if (!class_exists('XoopsLists', false)) {
    class XoopsLists
    {
        public static $files = [];

        public static function getFileListAsArray($dir)
        {
            return self::$files ?: [];
        }
    }
}

if (!class_exists('XoopsCache', false)) {
    class XoopsCache
    {
        public static $data = [];

        public static function read($name)
        {
            return self::$data[$name] ?? false;
        }
    }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class XoopsPreloadTest extends TestCase
{
    private $rootPath;

    protected function setUp()
    {
        $this->rootPath = sys_get_temp_dir() . '/xoops_preload_' . uniqid('', true);
        mkdir($this->rootPath . '/modules/mod1/preloads', 0777, true);
    }

    private function writePreloadFile($className = 'Mod1CustomPreload')
    {
        $template = <<<'PHPFILE'
<?php
class %s
{
    public static $events = array();
    public static function eventTest($args)
    {
        self::$events[] = $args;
    }
}
PHPFILE;
        file_put_contents($this->rootPath . '/modules/mod1/preloads/custom.php', sprintf($template, $className));
    }

    private function includePreload()
    {
        if (!defined('XOOPS_ROOT_PATH')) {
            define('XOOPS_ROOT_PATH', $this->rootPath);
        }

        XoopsLists::$files = ['custom.php'];
        XoopsCache::$data['system_modules_active'] = ['mod1'];

        require_once __DIR__ . '/../../htdocs/class/preload.php';
    }

    public function testSingletonBuildsPreloadList()
    {
        $this->writePreloadFile();
        $this->includePreload();

        $instance = XoopsPreload::getInstance();

        $this->assertSame($instance, XoopsPreload::getInstance(), 'getInstance should return singleton');
        $this->assertSame([
            ['module' => 'mod1', 'file' => 'custom'],
        ], $instance->_preloads);
    }

    public function testEventsRegisteredAndTriggered()
    {
        $this->writePreloadFile();
        $this->includePreload();

        $instance = XoopsPreload::getInstance();
        $this->assertArrayHasKey('test', $instance->_events);
        $this->assertSame('Mod1CustomPreload', $instance->_events['test'][0]['class_name']);

        $instance->triggerEvent('test', ['foo' => 'bar']);

        $this->assertSame([
            ['foo' => 'bar'],
        ], Mod1CustomPreload::$events);
    }

    public function testIgnoresMissingModules()
    {
        if (!defined('XOOPS_ROOT_PATH')) {
            define('XOOPS_ROOT_PATH', $this->rootPath);
        }

        XoopsCache::$data['system_modules_active'] = [];
        XoopsLists::$files = [];

        require_once __DIR__ . '/../../htdocs/class/preload.php';

        $instance = XoopsPreload::getInstance();
        $this->assertSame([], $instance->_preloads);
        $this->assertSame([], $instance->_events);
    }
}
