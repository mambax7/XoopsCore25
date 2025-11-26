<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/logger/xoopslogger.php';

if (!defined('_XOOPS_FATAL_MESSAGE')) {
    define('_XOOPS_FATAL_MESSAGE', 'Fatal: %s');
}
if (!defined('_XOOPS_FATAL_BACKTRACE')) {
    define('_XOOPS_FATAL_BACKTRACE', 'Backtrace');
}
if (!defined('_LOGGER_INCLUDED_FILES')) {
    define('_LOGGER_INCLUDED_FILES', 'Included');
}
if (!defined('_LOGGER_FILES')) {
    define('_LOGGER_FILES', 'Files: %d');
}
if (!defined('_LOGGER_MEM_ESTIMATED')) {
    define('_LOGGER_MEM_ESTIMATED', 'Estimated: %s');
}
if (!defined('_LOGGER_MEM_USAGE')) {
    define('_LOGGER_MEM_USAGE', 'Memory');
}
if (!defined('_LOGGER_DEBUG')) {
    define('_LOGGER_DEBUG', 'Debug');
}
if (!defined('_LANGCODE')) {
    define('_LANGCODE', 'en');
}
if (!defined('_CHARSET')) {
    define('_CHARSET', 'utf-8');
}
if (!defined('_CLOSE')) {
    define('_CLOSE', 'Close');
}
if (!defined('_LOGGER_NONE')) {
    define('_LOGGER_NONE', 'None');
}
if (!defined('_LOGGER_ALL')) {
    define('_LOGGER_ALL', 'All');
}
if (!defined('_LOGGER_ERRORS')) {
    define('_LOGGER_ERRORS', 'Errors');
}
if (!defined('_LOGGER_E_USER_NOTICE')) {
    define('_LOGGER_E_USER_NOTICE', 'User notice');
}
if (!defined('_LOGGER_E_USER_WARNING')) {
    define('_LOGGER_E_USER_WARNING', 'User warning');
}
if (!defined('_LOGGER_E_USER_ERROR')) {
    define('_LOGGER_E_USER_ERROR', 'User error');
}
if (!defined('_LOGGER_E_NOTICE')) {
    define('_LOGGER_E_NOTICE', 'Notice');
}
if (!defined('_LOGGER_E_WARNING')) {
    define('_LOGGER_E_WARNING', 'Warning');
}
if (!defined('_LOGGER_UNKNOWN')) {
    define('_LOGGER_UNKNOWN', 'Unknown');
}
if (!defined('_LOGGER_FILELINE')) {
    define('_LOGGER_FILELINE', '%s in %s (%s)');
}
if (!defined('_LOGGER_DEPRECATED')) {
    define('_LOGGER_DEPRECATED', 'Deprecated');
}
if (!defined('_LOGGER_TIMERS')) {
    define('_LOGGER_TIMERS', 'Timers');
}
if (!defined('_LOGGER_TIME')) {
    define('_LOGGER_TIME', 'Time');
}
if (!defined('_LOGGER_QUERIES')) {
    define('_LOGGER_QUERIES', 'Queries');
}
if (!defined('_LOGGER_BLOCKS')) {
    define('_LOGGER_BLOCKS', 'Blocks');
}
if (!defined('_LOGGER_EXTRA')) {
    define('_LOGGER_EXTRA', 'Extra');
}
if (!defined('_LOGGER_CAPTION')) {
    define('_LOGGER_CAPTION', 'Caption');
}
if (!defined('_LOGGER_SEPARATOR')) {
    define('_LOGGER_SEPARATOR', 'Separator');
}
if (!defined('_LOGGER_TYPE')) {
    define('_LOGGER_TYPE', 'Type');
}
if (!defined('_XOOPS_SIDEBLOCK_LEFT')) {
    define('_XOOPS_SIDEBLOCK_LEFT', 0);
}
if (!defined('_XOOPS_SIDEBLOCK_RIGHT')) {
    define('_XOOPS_SIDEBLOCK_RIGHT', 0);
}
if (!defined('_XOOPS_CENTERBLOCK_LEFT')) {
    define('_XOOPS_CENTERBLOCK_LEFT', 0);
}
if (!defined('_XOOPS_CENTERBLOCK_RIGHT')) {
    define('_XOOPS_CENTERBLOCK_RIGHT', 0);
}
if (!defined('_XOOPS_CENTERBLOCK_CENTER')) {
    define('_XOOPS_CENTERBLOCK_CENTER', 0);
}
if (!defined('_XOOPS_LOGGER_OBJECT')) {
    define('_XOOPS_LOGGER_OBJECT', 'obj');
}
if (!defined('_XOOPS_LOGGER_INCLUDE_FILES')) {
    define('_XOOPS_LOGGER_INCLUDE_FILES', 'include');
}
if (!defined('XOOPS_DB_PREFIX')) {
    define('XOOPS_DB_PREFIX', 'pref');
}
if (!defined('XOOPS_DB_NAME')) {
    define('XOOPS_DB_NAME', 'dbname');
}

class XoopsLoggerTest extends TestCase
{
    /** @var callable|null */
    private $originalErrorHandler;
    /** @var callable|null */
    private $originalExceptionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalErrorHandler = set_error_handler(function () {
        });
        set_error_handler($this->originalErrorHandler);
        $this->originalExceptionHandler = set_exception_handler(function () {
        });
        set_exception_handler($this->originalExceptionHandler);
    }

    protected function tearDown(): void
    {
        set_error_handler($this->originalErrorHandler);
        set_exception_handler($this->originalExceptionHandler);
        unset($GLOBALS['xoopsLogger']);
        parent::tearDown();
    }

    public function testSingletonRegistersHandlers(): void
    {
        $logger = XoopsLogger::getInstance();

        $previousHandler = set_error_handler($this->originalErrorHandler);
        $this->assertSame('XoopsErrorHandler_HandleError', $previousHandler);
        set_error_handler($this->originalErrorHandler);

        $previousException = set_exception_handler($this->originalExceptionHandler);
        $this->assertSame([$logger, 'handleException'], $previousException);
        set_exception_handler($this->originalExceptionHandler);

        $this->assertSame($logger, XoopsLogger::getInstance());
    }

    public function testLoggingStorageAndTimers(): void
    {
        $logger = new XoopsLogger();
        $GLOBALS['xoopsLogger'] = $logger;

        $logger->addQuery('SELECT *', 'oops', 5, 0.5);
        $logger->addBlock('block', true, 30);
        $logger->addExtra('note', 'message');
        $logger->addDeprecated('deprecated call');

        $this->assertCount(1, $logger->queries);
        $this->assertSame('SELECT *', $logger->queries[0]['sql']);
        $this->assertCount(1, $logger->blocks);
        $this->assertSame('block', $logger->blocks[0]['name']);
        $this->assertCount(1, $logger->extra);
        $this->assertSame('note', $logger->extra[0]['name']);
        $this->assertCount(1, $logger->deprecated);
        $this->assertStringContainsString('deprecated call', $logger->deprecated[0]);
        $this->assertStringContainsString('trace:', $logger->deprecated[0]);

        $logger->startTime('t');
        usleep(1000);
        $logger->stopTime('t');

        $elapsed = $logger->dumpTime('t');
        $this->assertGreaterThan(0, $elapsed);

        $logger->dumpTime('t', true);
        $this->assertArrayNotHasKey('t', $logger->logstart);
    }

    public function testTriggerErrorSanitizesPath(): void
    {
        $logger = new XoopsLogger();
        $GLOBALS['xoopsLogger'] = $logger;

        $logger->triggerError(5, 'Issue %s', XOOPS_ROOT_PATH . '/file.php', 123, E_USER_WARNING);

        $this->assertCount(1, $logger->deprecated);
        $this->assertCount(1, $logger->errors);
        $error = $logger->errors[0];
        $this->assertSame(E_USER_WARNING, $error['errno']);
        $this->assertSame('Issue 5', $error['errstr']);
        $this->assertSame('/file.php', $error['errfile']);
        $this->assertSame(123, $error['errline']);
    }

    public function testHandleExceptionUsesSanitizedMessage(): void
    {
        $logger = new class extends XoopsLogger {
            public array $handled = [];
            public function handleError($errno, $errstr, $errfile, $errline, $trace = null)
            {
                $this->handled = func_get_args();
            }
            public function exposeSanitizePath($path)
            {
                return $this->sanitizePath($path);
            }
        };

        $GLOBALS['xoopsLogger'] = $logger;
        $exception = new Exception('pref_table error in dbname.table');
        $logger->handleException($exception);

        $this->assertSame(E_USER_ERROR, $logger->handled[0]);
        $this->assertSame('Exception: table error in table', $logger->handled[1]);
        $this->assertSame($exception->getLine(), $logger->handled[3]);

        $sanitized = $logger->exposeSanitizePath(XOOPS_ROOT_PATH . '/dir/file.php');
        $this->assertSame('/dir/file.php', $sanitized);
    }
}
