<?php
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class XoopsLoadTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/xoops_load_' . uniqid();
        mkdir($this->rootPath . '/class/logger', 0777, true);
        mkdir($this->rootPath . '/Frameworks', 0777, true);
        mkdir($this->rootPath . '/modules', 0777, true);

        if (!defined('XOOPS_ROOT_PATH')) {
            define('XOOPS_ROOT_PATH', $this->rootPath);
        }

        require_once __DIR__ . '/../../htdocs/class/xoopsload.php';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);
    }

    public function testLoadReturnsTrueForExistingClass(): void
    {
        $this->assertFalse(class_exists('ExistingClass', false));
        class_exists('ExistingClass') || eval('class ExistingClass {}');

        $this->assertTrue(XoopsLoad::load('ExistingClass'));
    }

    public function testLoadMapsDeprecatedNamesAndLogsMessage(): void
    {
        $logger = new class {
            public array $messages = [];
            public function addDeprecated($message): void
            {
                $this->messages[] = $message;
            }
        };
        $GLOBALS['xoopsLogger'] = $logger;

        file_put_contents($this->rootPath . '/class/uploader.php', "<?php\nclass xoopsmediauploader {}\n");

        $this->assertTrue(XoopsLoad::load('uploader'));
        $this->assertSame(["xoops_load('uploader') is deprecated, use xoops_load('xoopsmediauploader')"], $logger->messages);
    }

    public function testLoadCoreCallsAutoloadWhenDeclared(): void
    {
        $file = $this->rootPath . '/class/logger/xoopslogger.php';
        file_put_contents($file, <<<'LOGGER'
<?php
class xoopslogger
{
    public static $autoloadCalled = false;
    public static function __autoload()
    {
        self::$autoloadCalled = true;
    }
}
LOGGER
        );

        $this->assertTrue(XoopsLoad::load('xoopslogger'));
        $this->assertTrue(xoopslogger::$autoloadCalled);
    }

    public function testLoadFrameworkReturnsLoadedClassName(): void
    {
        mkdir($this->rootPath . '/Frameworks/foo', 0777, true);
        file_put_contents($this->rootPath . '/Frameworks/foo/xoopsfoo.php', "<?php\nclass XoopsFoo {}\n");

        $this->assertSame('XoopsFoo', XoopsLoad::load('foo', 'framework'));
    }

    public function testLoadModuleLoadsClassFromModuleDirectory(): void
    {
        mkdir($this->rootPath . '/modules/demo/class', 0777, true);
        file_put_contents($this->rootPath . '/modules/demo/class/sample.php', "<?php\nclass DemoSample {}\n");

        $this->assertTrue(XoopsLoad::load('sample', 'demo'));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        @rmdir($directory);
    }
}
