<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/blockinstance.php';

class XoopsBlockInstanceTest extends TestCase
{
    private $previousLogger;
    private array $messages;

    protected function setUp(): void
    {
        $this->previousLogger = $GLOBALS['xoopsLogger'] ?? null;
        $this->messages       = [];
        $GLOBALS['xoopsLogger'] = new class {
            public array $messages = [];

            public function addDeprecated($message): void
            {
                $this->messages[] = $message;
            }
        };
        $GLOBALS['xoopsLogger']->messages =& $this->messages;
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoopsLogger'] = $this->previousLogger;
    }

    public function testMagicCallLogsDeprecatedMessageAndReturnsNull(): void
    {
        $instance = new XoopsBlockInstance();

        $this->assertNull($instance->dynamicMethod('foo', 'bar'));
        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString("XoopsBlockInstance", $this->messages[0]);
        $this->assertStringContainsString("dynamicMethod", $this->messages[0]);
        $this->assertStringContainsString('not executed', $this->messages[0]);
    }

    public function testMagicSetLogsDeprecatedMessage(): void
    {
        $instance = new XoopsBlockInstance();

        $instance->property = 'value';
        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString("XoopsBlockInstance", $this->messages[0]);
        $this->assertStringContainsString('property', $this->messages[0]);
        $this->assertStringContainsString('not set', $this->messages[0]);
    }

    public function testMagicGetLogsDeprecatedMessageAndReturnsNull(): void
    {
        $instance = new XoopsBlockInstance();

        $this->assertNull($instance->missing);
        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString("XoopsBlockInstance", $this->messages[0]);
        $this->assertStringContainsString('missing', $this->messages[0]);
        $this->assertStringContainsString('not available', $this->messages[0]);
    }
}

class XoopsBlockInstanceHandlerTest extends TestCase
{
    private $previousLogger;
    private array $messages;

    protected function setUp(): void
    {
        $this->previousLogger = $GLOBALS['xoopsLogger'] ?? null;
        $this->messages       = [];
        $GLOBALS['xoopsLogger'] = new class {
            public array $messages = [];

            public function addDeprecated($message): void
            {
                $this->messages[] = $message;
            }
        };
        $GLOBALS['xoopsLogger']->messages =& $this->messages;
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoopsLogger'] = $this->previousLogger;
    }

    public function testMagicCallLogsDeprecatedMessageAndReturnsNull(): void
    {
        $handler = new XoopsBlockInstanceHandler();

        $this->assertNull($handler->perform('foo'));
        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString("XoopsBlockInstanceHandler", $this->messages[0]);
        $this->assertStringContainsString("perform", $this->messages[0]);
        $this->assertStringContainsString('not executed', $this->messages[0]);
    }

    public function testMagicSetLogsDeprecatedMessage(): void
    {
        $handler = new XoopsBlockInstanceHandler();

        $handler->property = 'value';
        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString("XoopsBlockInstanceHandler", $this->messages[0]);
        $this->assertStringContainsString('property', $this->messages[0]);
        $this->assertStringContainsString('not set', $this->messages[0]);
    }

    public function testMagicGetLogsDeprecatedMessageAndReturnsNull(): void
    {
        $handler = new XoopsBlockInstanceHandler();

        $this->assertNull($handler->missing);
        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString("XoopsBlockInstanceHandler", $this->messages[0]);
        $this->assertStringContainsString('missing', $this->messages[0]);
        $this->assertStringContainsString('not available', $this->messages[0]);
    }
}
