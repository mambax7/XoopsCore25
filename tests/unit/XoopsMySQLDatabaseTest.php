<?php
use PHPUnit\Framework\TestCase;

if (!class_exists('DummyLogger', false)) {
    class DummyLogger
    {
        public array $queries = [];
        public array $extra = [];

        public function startTime($name)
        {
        }

        public function stopTime($name)
        {
        }

        public function dumpTime($name, $decimals = false)
        {
            return 0;
        }

        public function addQuery($sql, $error, $errno, $time)
        {
            $this->queries[] = [$sql, $error, $errno, $time];
        }

        public function addExtra($channel, $message)
        {
            $this->extra[] = [$channel, $message];
        }
    }
}

if (!defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', __DIR__);
}
if (!defined('XOOPS_DB_PREFIX')) {
    define('XOOPS_DB_PREFIX', 'xoops');
}
if (!defined('XOOPS_DB_HOST')) {
    define('XOOPS_DB_HOST', 'localhost');
}
if (!defined('XOOPS_DB_USER')) {
    define('XOOPS_DB_USER', 'root');
}
if (!defined('XOOPS_DB_PASS')) {
    define('XOOPS_DB_PASS', '');
}
if (!defined('XOOPS_DB_NAME')) {
    define('XOOPS_DB_NAME', 'xoops');
}
if (!defined('XOOPS_DB_PCONNECT')) {
    define('XOOPS_DB_PCONNECT', 0);
}

$GLOBALS['xoopsConfig'] = [];

require_once XOOPS_ROOT_PATH . '/../../htdocs/class/database/database.php';
require_once XOOPS_ROOT_PATH . '/../../htdocs/class/database/mysqldatabase.php';

class MySQLProxyDouble extends XoopsMySQLDatabaseProxy
{
    public array $calls = [];
    public $returnValue = true;

    public function queryF($sql, $limit = 0, $start = 0)
    {
        $this->calls[] = [$sql, $limit, $start];
        return $this->returnValue;
    }
}

class MySQLSafeDouble extends XoopsMySQLDatabaseSafe
{
    public function setConnection($conn)
    {
        $this->conn = $conn;
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class XoopsMySQLDatabaseTest extends TestCase
{
    public function testProxyBlocksNonSelectWhenWebChangesNotAllowed(): void
    {
        $proxy = new MySQLProxyDouble();
        $proxy->allowWebChanges = false;

        $message = null;
        set_error_handler(static function ($errno, $errstr) use (&$message) {
            $message = $errstr;
            return true;
        });
        $result = $proxy->query('UPDATE table SET x=1');
        restore_error_handler();

        $this->assertFalse($result);
        $this->assertSame('Database updates are not allowed during processing of a GET request', $message);
        $this->assertSame([], $proxy->calls);
    }

    public function testProxyUsesQueryFWithPagination(): void
    {
        $proxy = new MySQLProxyDouble();
        $proxy->allowWebChanges = true;

        $result = $proxy->query('SELECT * FROM table', 5, -3);

        $this->assertTrue($result);
        $this->assertCount(1, $proxy->calls);
        $this->assertSame(['SELECT * FROM table', 5, 0], $proxy->calls[0]);
    }

    public function testProxyUsesQueryFWithoutLimit(): void
    {
        $proxy = new MySQLProxyDouble();
        $proxy->allowWebChanges = true;

        $result = $proxy->query('SELECT * FROM table');

        $this->assertTrue($result);
        $this->assertCount(1, $proxy->calls);
        $this->assertSame(['SELECT * FROM table', 0, 0], $proxy->calls[0]);
    }

    public function testSafeDelegatesWithNullLimitWhenZero(): void
    {
        $logger = new DummyLogger();
        $safe = new MySQLSafeDouble();
        $safe->setLogger($logger);
        $safe->setConnection(mysqli_init());

        set_error_handler(static function () {
            return true;
        });
        $safe->query('SELECT * FROM table', 0, 10);
        restore_error_handler();

        $this->assertCount(1, $logger->queries);
        $this->assertSame('SELECT * FROM table', $logger->queries[0][0]);
    }

    public function testSafeAppendsLimitAndOffset(): void
    {
        $logger = new DummyLogger();
        $safe = new MySQLSafeDouble();
        $safe->setLogger($logger);
        $safe->setConnection(mysqli_init());

        set_error_handler(static function () {
            return true;
        });
        $safe->query('SELECT * FROM table', 2, 3);
        restore_error_handler();

        $this->assertCount(1, $logger->queries);
        $this->assertSame('SELECT * FROM table LIMIT 2 OFFSET 3', $logger->queries[0][0]);
    }
}
