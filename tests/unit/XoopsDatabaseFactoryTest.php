<?php
use PHPUnit\Framework\TestCase;

if (!class_exists('XoopsPreload', false)) {
    class XoopsPreload
    {
        public array $events = [];
        private static $instance;

        public static function getInstance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function triggerEvent($name, $args)
        {
            $this->events[] = [$name, $args];
        }
    }
}

if (!class_exists('XoopsLogger', false)) {
    class XoopsLogger
    {
        public static $instance;

        public static function getInstance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }
}

if (!class_exists('XoopsmysqlDatabaseBase', false)) {
    class XoopsmysqlDatabaseBase
    {
        public static $shouldConnect = true;
        public $logger;
        public $prefix;
        public $connected = false;

        public function setLogger($logger)
        {
            $this->logger = $logger;
        }

        public function setPrefix($prefix)
        {
            $this->prefix = $prefix;
        }

        public function connect()
        {
            $this->connected = true;
            return static::$shouldConnect;
        }
    }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class XoopsDatabaseFactoryTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/xoops_db_factory_' . uniqid();
        mkdir($this->rootPath . '/class/database', 0777, true);
        if (!defined('XOOPS_ROOT_PATH')) {
            define('XOOPS_ROOT_PATH', $this->rootPath);
        }
        if (!defined('XOOPS_DB_TYPE')) {
            define('XOOPS_DB_TYPE', 'mysql');
        }
        if (!defined('XOOPS_DB_PREFIX')) {
            define('XOOPS_DB_PREFIX', 'xoops');
        }
        $this->createStubDatabaseFile();
        require_once dirname(__DIR__, 2) . '/htdocs/class/database/databasefactory.php';
    }

    private function createStubDatabaseFile(): void
    {
        $file = $this->rootPath . '/class/database/' . XOOPS_DB_TYPE . 'database.php';
        $contents = <<<'PHP'
<?php
class XoopsmysqlDatabaseSafe extends XoopsmysqlDatabaseBase {}
class XoopsmysqlDatabaseProxy extends XoopsmysqlDatabaseBase {}
PHP;
        file_put_contents($file, $contents);
    }

    public function testGetDatabaseConnectionCreatesAndConnects(): void
    {
        $db = XoopsDatabaseFactory::getDatabaseConnection();

        $this->assertInstanceOf(XoopsmysqlDatabaseBase::class, $db);
        $this->assertTrue($db->connected);
        $this->assertSame(XoopsLogger::getInstance(), $db->logger);
        $this->assertSame('xoops', $db->prefix);

        $events = XoopsPreload::getInstance()->events;
        $this->assertCount(1, $events);
        $this->assertSame('core.class.database.databasefactory.connection', $events[0][0]);
        $this->assertSame('XoopsmysqlDatabaseSafe', $events[0][1][0]);
    }

    public function testGetDatabaseConnectionThrowsOnFailedConnect(): void
    {
        XoopsmysqlDatabaseBase::$shouldConnect = false;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to connect to database');

        XoopsDatabaseFactory::getDatabaseConnection();
    }

    public function testGetDatabaseConnectionWarnsWhenFileMissing(): void
    {
        unlink($this->rootPath . '/class/database/' . XOOPS_DB_TYPE . 'database.php');

        $message = null;
        set_error_handler(static function ($errno, $errstr) use (&$message) {
            $message = $errstr;
            return true;
        });
        $db = XoopsDatabaseFactory::getDatabaseConnection();
        restore_error_handler();

        $this->assertNull($db);
        $this->assertStringContainsString('Failed to load database of type: mysql', $message);
    }

    public function testGetDatabaseReturnsDatabaseInstance(): void
    {
        $db = XoopsDatabaseFactory::getDatabase();

        $this->assertInstanceOf(XoopsmysqlDatabaseBase::class, $db);
        $this->assertFalse($db->connected);
        $this->assertSame($db, XoopsDatabaseFactory::getDatabase());
    }

    public function testGetDatabaseUsesProxyWhenDefined(): void
    {
        define('XOOPS_DB_PROXY', true);

        $db = XoopsDatabaseFactory::getDatabase();

        $this->assertInstanceOf(XoopsmysqlDatabaseProxy::class, $db);
    }
}
