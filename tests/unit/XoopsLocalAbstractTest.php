<?php
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class XoopsLocalAbstractTest extends TestCase
{
    private function ensureDependenciesLoaded(): void
    {
        if (!defined('XOOPS_ROOT_PATH')) {
            define('XOOPS_ROOT_PATH', sys_get_temp_dir());
        }
        if (!defined('_CHARSET')) {
            define('_CHARSET', 'UTF-8');
        }

        if (!function_exists('xoops_getUserTimestamp')) {
            function xoops_getUserTimestamp($time, $timeoffset = 0)
            {
                return (int)$time + (float)$timeoffset * 3600;
            }
        }

        require_once __DIR__ . '/../../htdocs/class/xoopslocal.php';
    }

    private function defineTimeConstants(): void
    {
        $constants = [
            '_TIMEFORMAT_DESC' => 'Time format description',
            '_SHORTDATESTRING' => 'm/d/y',
            '_MEDIUMDATESTRING' => 'M d, Y',
            '_DATESTRING' => 'Y-m-d H:i:s',
            '_TODAY' => 'Today',
            '_YESTERDAY' => 'Yesterday',
            '_YEARMONTHDAY' => 'Y-m-d',
            '_MONTHDAY' => 'm-d',
            '_ELAPSE' => '%s ago',
            '_DAYS' => '%d days',
            '_DAY' => 'a day',
            '_HOURS' => '%d hours',
            '_HOUR' => 'an hour',
            '_MINUTES' => '%d minutes',
            '_MINUTE' => 'a minute',
            '_SECONDS' => '%d seconds',
            '_SECOND' => 'a second',
        ];

        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    public function testSubstrTrimsWithMarkerWhenNotMultibyte(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->ensureDependenciesLoaded();

        $result = XoopsLocalAbstract::substr('hello world', 0, 5, '...');
        $this->assertSame('he...', $result);
    }

    public function testSubstrUsesMbFunctionsWhenAvailable(): void
    {
        if (!function_exists('mb_internal_encoding') || !function_exists('mb_strcut') || !function_exists('mb_strlen')) {
            $this->markTestSkipped('Multibyte string functions not available');
        }

        define('XOOPS_USE_MULTIBYTES', true);
        $this->ensureDependenciesLoaded();

        $text = '你好世界';
        $result = XoopsLocalAbstract::substr($text, 0, 6, '...');

        $this->assertStringEndsWith('...', $result);
        $this->assertNotSame($text, $result);
    }

    public function testConvertEncodingReturnsOriginalWhenEmpty(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->ensureDependenciesLoaded();

        $this->assertSame('', XoopsLocalAbstract::convert_encoding(''));
    }

    public function testConvertEncodingUsesMultibyteConversion(): void
    {
        define('XOOPS_USE_MULTIBYTES', true);
        $this->ensureDependenciesLoaded();

        $latin1 = "\xe9"; // "é" in ISO-8859-1
        $converted = XoopsLocalAbstract::convert_encoding($latin1, 'UTF-8', 'ISO-8859-1');

        $this->assertSame('é', $converted);
    }

    public function testConvertEncodingFallsBackToOriginalOnFailure(): void
    {
        define('XOOPS_USE_MULTIBYTES', true);
        $this->ensureDependenciesLoaded();

        $text = 'unchanged';
        $this->assertSame($text, XoopsLocalAbstract::convert_encoding($text, 'utf-8', 'invalid-charset'));
    }

    public function testTrimDelegatesToPhpTrim(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->ensureDependenciesLoaded();

        $this->assertSame('value', XoopsLocalAbstract::trim("  value  \n"));
    }

    public function testGetTimeFormatDescReturnsConstant(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->defineTimeConstants();
        $this->ensureDependenciesLoaded();

        $this->assertSame(_TIMEFORMAT_DESC, XoopsLocalAbstract::getTimeFormatDesc());
    }

    public function testFormatTimestampRssUsesGmdate(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->defineTimeConstants();
        $this->ensureDependenciesLoaded();

        $GLOBALS['xoopsConfig'] = ['server_TZ' => 0, 'default_TZ' => '0'];

        $this->assertSame('Thu, 01 Jan 1970 00:00:00 +0000', XoopsLocalAbstract::formatTimestamp(0, 'rss'));
    }

    public function testFormatTimestampElapseDescribesElapsedMinutes(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->defineTimeConstants();
        $this->ensureDependenciesLoaded();

        $GLOBALS['xoopsConfig'] = ['server_TZ' => 0, 'default_TZ' => '0'];
        $time = time() - 120;

        $this->assertSame('2 minutes ago', XoopsLocalAbstract::formatTimestamp($time, 'e'));
    }

    public function testFormatTimestampCustomReturnsTodayLabel(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->defineTimeConstants();
        $this->ensureDependenciesLoaded();

        $GLOBALS['xoopsConfig'] = ['server_TZ' => 0, 'default_TZ' => '0'];
        $now = time();

        $this->assertSame('Today', XoopsLocalAbstract::formatTimestamp($now, 'c'));
    }

    public function testNumberAndMoneyFormatReturnInput(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->ensureDependenciesLoaded();

        $instance = new XoopsLocalAbstract();
        $this->assertSame(1234, $instance->number_format(1234));
        $this->assertSame(12.34, $instance->money_format('%i', 12.34));
    }

    public function testCallDelegatesToExistingFunctionOrReturnsNull(): void
    {
        define('XOOPS_USE_MULTIBYTES', false);
        $this->ensureDependenciesLoaded();

        $instance = new XoopsLocalAbstract();
        $this->assertSame('lower', $instance->strtolower('LOWER'));
        $this->assertNull($instance->nonexistent_function('value'));
    }
}
