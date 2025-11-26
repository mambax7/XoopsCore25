<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/session.php';

class XoopsSessionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $GLOBALS['xoopsConfig'] = [
            'use_mysession' => true,
            'session_name' => 'xoops_session',
            'session_expire' => 10,
        ];

        if (!defined('XOOPS_PROT')) {
            define('XOOPS_PROT', 'https://');
        }
        if (!defined('XOOPS_URL')) {
            define('XOOPS_URL', 'https://example.com');
        }
        if (!defined('XOOPS_COOKIE_DOMAIN')) {
            define('XOOPS_COOKIE_DOMAIN', 'example.com');
        }
    }

    public function testConstructorSetsCookieParameters(): void
    {
        $database = $this->createDatabaseMock();

        $handler = new XoopsSessionHandler($database);

        $params = session_get_cookie_params();
        $this->assertSame('example.com', $params['domain']);
        $this->assertSame('/', $params['path']);
        $this->assertTrue($params['httponly']);
    }

    public function testOpenReturnsTrue(): void
    {
        $handler = new XoopsSessionHandler($this->createDatabaseMock());
        $this->assertTrue($handler->open('', 'sid'));
    }

    public function testCloseCallsGcForce(): void
    {
        $handler = new class($this->createDatabaseMock()) extends XoopsSessionHandler {
            public $closed = false;
            public function gc_force()
            {
                $this->closed = true;
            }
        };

        $this->assertTrue($handler->close());
        $this->assertTrue($handler->closed);
    }

    public function testReadReturnsDataWhenSubnetMatches(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.10';
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_session');
        $database->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");
        $database->expects($this->once())->method('queryF')
            ->with($this->stringContains('pref_session'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn(['payload', '192.168.1.5']);

        $handler = new XoopsSessionHandler($database);

        $this->assertSame('payload', $handler->read('abc123'));
    }

    public function testReadReturnsEmptyWhenSubnetDoesNotMatch(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_session');
        $database->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");
        $database->expects($this->once())->method('queryF')
            ->with($this->stringContains('pref_session'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn(['payload', '192.168.1.5']);

        $handler = new XoopsSessionHandler($database);

        $this->assertSame('', $handler->read('abc123'));
    }

    public function testWritePersistsSessionAndUpdatesCookie(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_session');
        $database->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");
        $database->expects($this->once())->method('exec')
            ->with($this->stringContains('INSERT INTO pref_session'))
            ->willReturn(true);

        $handler = new class($database) extends XoopsSessionHandler {
            public $updatedCookie = false;
            public function update_cookie($sess_id = null, $expire = null)
            {
                $this->updatedCookie = true;
            }
        };

        $this->assertTrue($handler->write('abc', 'data'));
        $this->assertTrue($handler->updatedCookie);
    }

    public function testDestroyReturnsExecutionResult(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_session');
        $database->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");
        $database->expects($this->exactly(2))->method('exec')
            ->with($this->stringContains('DELETE FROM pref_session'))
            ->willReturnOnConsecutiveCalls(false, true);

        $handler = new XoopsSessionHandler($database);

        $this->assertFalse($handler->destroy('id1'));
        $this->assertTrue($handler->destroy('id2'));
    }

    public function testGcReturnsAffectedRows(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_session');
        $database->expects($this->once())->method('exec')->willReturn(true);
        $database->method('getAffectedRows')->willReturn(5);

        $handler = new XoopsSessionHandler($database);

        $this->assertSame(5, $handler->gc(100));
    }

    public function testGcReturnsZeroForEmptyExpire(): void
    {
        $handler = new XoopsSessionHandler($this->createDatabaseMock());
        $this->assertSame(0, $handler->gc(0));
    }

    public function testGcReturnsFalseOnFailure(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_session');
        $database->expects($this->once())->method('exec')->willReturn(false);

        $handler = new XoopsSessionHandler($database);

        $this->assertFalse($handler->gc(50));
    }

    public function testRegenerateIdRespectsEnableFlag(): void
    {
        $database = $this->createDatabaseMock();
        $handler = new class($database) extends XoopsSessionHandler {
            public $updatedCookie = false;
            public function update_cookie($sess_id = null, $expire = null)
            {
                $this->updatedCookie = true;
            }
        };
        $handler->enableRegenerateId = false;

        $this->assertTrue($handler->regenerate_id(true));
        $this->assertTrue($handler->updatedCookie);
    }

    private function createDatabaseMock(): XoopsDatabase
    {
        return $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'prefix',
                'quote',
                'queryF',
                'isResultSet',
                'fetchRow',
                'exec',
                'getAffectedRows',
            ])
            ->getMock();
    }
}
