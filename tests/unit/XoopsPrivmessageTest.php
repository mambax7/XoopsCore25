<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/privmessage.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';
require_once XOOPS_ROOT_PATH . '/class/criteria/compo.php';

class XoopsPrivmessageTest extends TestCase
{
    public function testConstructorInitializesVars(): void
    {
        $message = new XoopsPrivmessage();

        $this->assertNull($message->getVar('msg_id'));
        $this->assertNull($message->getVar('msg_image'));
        $this->assertNull($message->getVar('subject'));
        $this->assertNull($message->getVar('from_userid'));
        $this->assertNull($message->getVar('to_userid'));
        $this->assertNull($message->getVar('msg_time'));
        $this->assertNull($message->getVar('msg_text'));
        $this->assertSame(0, $message->getVar('read_msg'));
    }

    public function testAccessorMethodsReturnValues(): void
    {
        $message = new XoopsPrivmessage();
        $message->setVar('msg_id', 10);
        $message->setVar('msg_image', 'icon.png');
        $message->setVar('subject', 'Hello');
        $message->setVar('from_userid', 5);
        $message->setVar('to_userid', 7);
        $message->setVar('msg_time', 123456789);
        $message->setVar('msg_text', 'Body');
        $message->setVar('read_msg', 1);

        $this->assertSame(10, $message->id());
        $this->assertSame(10, $message->msg_id());
        $this->assertSame('icon.png', $message->msg_image());
        $this->assertSame('Hello', $message->subject());
        $this->assertSame(5, $message->from_userid());
        $this->assertSame(7, $message->to_userid());
        $this->assertSame(123456789, $message->msg_time());
        $this->assertSame('Body', $message->msg_text());
        $this->assertSame(1, $message->read_msg());
    }
}

trait PrivmessageDatabaseMockTrait
{
    protected function createDatabaseMock(): XoopsDatabase
    {
        return $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'prefix',
                'query',
                'queryF',
                'isResultSet',
                'getRowsNum',
                'fetchArray',
                'exec',
                'genId',
                'getInsertId',
                'quote',
                'fetchRow',
            ])
            ->getMock();
    }
}

class XoopsPrivmessageHandlerTest extends TestCase
{
    use PrivmessageDatabaseMockTrait;

    public function testCreateReturnsPrivmessage(): void
    {
        $handler = new XoopsPrivmessageHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsPrivmessage::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetReturnsMessageWhenFound(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('query')->with($this->stringContains('WHERE msg_id=5'))->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'msg_id'       => 5,
            'msg_image'    => 'icon.png',
            'subject'      => 'Hello',
            'from_userid'  => 11,
            'to_userid'    => 13,
            'msg_time'     => 123456,
            'msg_text'     => 'Message body',
            'read_msg'     => 1,
        ]);
        $handler = new XoopsPrivmessageHandler($database);

        $message = $handler->get(5);

        $this->assertInstanceOf(XoopsPrivmessage::class, $message);
        $this->assertSame(5, $message->getVar('msg_id'));
        $this->assertSame('Message body', $message->getVar('msg_text'));
    }

    public function testGetReturnsFalseWhenNoResult(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(false);
        $handler = new XoopsPrivmessageHandler($database);

        $this->assertFalse($handler->get(99));
    }

    public function testInsertRejectsInvalidType(): void
    {
        $database = $this->createDatabaseMock();
        $handler  = new XoopsPrivmessageHandler($database);

        $this->assertFalse($handler->insert(new XoopsObject()));
    }

    public function testInsertInsertsNewMessage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->method('quote')->willReturnCallback(static function ($value) {
            return "'{$value}'";
        });
        $database->method('genId')->willReturn(123);
        $database->expects($this->once())->method('query')->with($this->stringContains('INSERT INTO pref_priv_msgs'))->willReturn(true);
        $handler = new XoopsPrivmessageHandler($database);

        $message = new XoopsPrivmessage();
        $message->setVar('msg_image', 'icon.png');
        $message->setVar('subject', 'Hello');
        $message->setVar('from_userid', 5);
        $message->setVar('to_userid', 7);
        $message->setVar('msg_text', 'Body text');

        $result = $handler->insert($message);

        $this->assertTrue($result);
        $this->assertSame(123, $message->getVar('msg_id'));
    }

    public function testInsertUpdatesExistingMessage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->method('quote')->willReturnCallback(static function ($value) {
            return "'{$value}'";
        });
        $database->expects($this->once())->method('query')->with($this->stringContains('UPDATE pref_priv_msgs'))->willReturn(true);
        $handler = new XoopsPrivmessageHandler($database);

        $message = new XoopsPrivmessage();
        $message->setVar('msg_id', 9);
        $message->setVar('msg_image', 'icon.png');
        $message->setVar('subject', 'Updated');
        $message->setVar('from_userid', 5);
        $message->setVar('to_userid', 7);
        $message->setVar('msg_text', 'Updated text');
        $message->setVar('read_msg', 1);
        $message->unsetNew();
        $message->setDirty();

        $this->assertTrue($handler->insert($message));
    }

    public function testDeleteRemovesMessage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('query')->with($this->stringContains('DELETE FROM pref_priv_msgs'))
            ->willReturn(true);
        $handler = new XoopsPrivmessageHandler($database);

        $message = new XoopsPrivmessage();
        $message->setVar('msg_id', 15);

        $this->assertTrue($handler->delete($message));
    }

    public function testGetObjectsReturnsListWithCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('query')->with($this->stringContains('ORDER BY msg_id ASC'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls([
            'msg_id'      => 21,
            'subject'     => 'First',
            'from_userid' => 1,
            'to_userid'   => 2,
            'msg_text'    => 'Hello',
            'read_msg'    => 0,
        ], false);
        $handler = new XoopsPrivmessageHandler($database);

        $criteria = new Criteria('msg_id', 21);
        $criteria->setSort('msg_id');
        $criteria->setOrder('ASC');

        $messages = $handler->getObjects($criteria, true);

        $this->assertCount(1, $messages);
        $this->assertArrayHasKey(21, $messages);
        $this->assertInstanceOf(XoopsPrivmessage::class, $messages[21]);
    }

    public function testGetObjectsReturnsEmptyWhenNotResultSet(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(false);
        $handler = new XoopsPrivmessageHandler($database);

        $this->assertSame([], $handler->getObjects());
    }

    public function testGetCountReturnsCount(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([7]);
        $handler = new XoopsPrivmessageHandler($database);

        $this->assertSame(7, $handler->getCount());
    }

    public function testGetCountReturnsZeroWhenNotResultSet(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(false);
        $handler = new XoopsPrivmessageHandler($database);

        $this->assertSame(0, $handler->getCount(new Criteria('read_msg', 0)));
    }

    public function testSetReadMarksMessage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_priv_msgs');
        $database->expects($this->once())->method('exec')->with($this->stringContains('read_msg = 1 WHERE msg_id = 33'))
            ->willReturn(true);
        $handler = new XoopsPrivmessageHandler($database);

        $message = new XoopsPrivmessage();
        $message->setVar('msg_id', 33);

        $this->assertTrue($handler->setRead($message));
    }
}
