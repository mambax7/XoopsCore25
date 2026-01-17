<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/utility/xoopsutility.php';

class UtilityMethodInvoker
{
    public static function double($value)
    {
        return $value * 2;
    }
}

class XoopsUtilityTest extends TestCase
{
    public function testRecursiveAppliesHandlersAcrossArray(): void
    {
        $handlers = ['strtoupper', 'strtolower'];
        $data = ['Mixed', 'VALUE'];

        $result = XoopsUtility::recursive($handlers, $data);

        $this->assertSame(['MIXED', 'value'], $result);
    }

    public function testRecursiveInvokesNamedFunction(): void
    {
        $this->assertSame('HELLO', XoopsUtility::recursive('strtoupper', 'hello'));
    }

    public function testRecursiveReturnsInputWhenFunctionMissing(): void
    {
        $this->assertSame('unchanged', XoopsUtility::recursive('not_a_function', 'unchanged'));
    }

    public function testRecursiveInvokesClassMethod(): void
    {
        $this->assertSame(10, XoopsUtility::recursive([UtilityMethodInvoker::class, 'double'], 5));
    }

    public function testRecursiveReturnsInputWhenHandlerUnsupported(): void
    {
        $this->assertSame('data', XoopsUtility::recursive(new stdClass(), 'data'));
    }
}
