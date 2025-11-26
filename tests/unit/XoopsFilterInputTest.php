<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/xoops_lib/vendor/autoload.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsfilterinput.php';

class XoopsFilterInputTest extends TestCase
{
    public function testGetInstanceReturnsSubclassSingleton(): void
    {
        $instanceOne = XoopsFilterInput::getInstance();
        $instanceTwo = XoopsFilterInput::getInstance();

        $this->assertInstanceOf(XoopsFilterInput::class, $instanceOne);
        $this->assertSame($instanceOne, $instanceTwo);
    }

    public function testGetInstanceSeparatesBySignature(): void
    {
        $allowLinks = XoopsFilterInput::getInstance(['a']);
        $allowImages = XoopsFilterInput::getInstance(['img']);

        $this->assertNotSame($allowLinks, $allowImages);
    }

    public function testProcessRemovesScriptTags(): void
    {
        $filter = XoopsFilterInput::getInstance();

        $this->assertSame('alert(1)ok', $filter->process('<script>alert(1)</script>ok'));
    }

    public function testCleanCastsToInteger(): void
    {
        $this->assertSame(42, XoopsFilterInput::clean('42cats', 'int'));
    }
}
