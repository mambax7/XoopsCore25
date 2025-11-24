<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/comment.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsCommentTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $comment = new XoopsComment();

        $this->assertNull($comment->getVar('com_id'));
        $this->assertSame(0, $comment->getVar('com_pid'));
        $this->assertSame(0, $comment->getVar('com_uid'));
        $this->assertSame(0, $comment->getVar('com_status'));
        $this->assertSame(0, $comment->getVar('dohtml'));
    }

    public function testIdHelperReturnsStoredValue(): void
    {
        $comment = new XoopsComment();
        $comment->setVar('com_id', 15);

        $this->assertSame(15, $comment->id());
        $this->assertSame(15, $comment->com_id());
    }

    public function testIsRootComparesCommentAndRootId(): void
    {
        $comment = new XoopsComment();
        $comment->setVar('com_id', 3);
        $comment->setVar('com_rootid', 3);

        $this->assertTrue($comment->isRoot());

        $comment->setVar('com_rootid', 4);
        $this->assertFalse($comment->isRoot());
    }
}

class XoopsCommentHandlerTest extends TestCase
{
    public function testCreateReturnsFreshOrExistingComments(): void
    {
        $database = $this->createDatabaseMock();
        $handler  = new XoopsCommentHandler($database);

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsComment::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetLoadsCommentFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_xoopscomments WHERE com_id=7')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'com_id'     => 7,
            'com_title'  => 'Hello',
            'com_text'   => 'World',
            'com_rootid' => 1,
            'com_pid'    => 0,
        ]);

        $handler = new XoopsCommentHandler($database);
        $comment = $handler->get(7);

        $this->assertInstanceOf(XoopsComment::class, $comment);
        $this->assertSame('Hello', $comment->getVar('com_title'));
        $this->assertFalse($comment->isNew());
    }

    public function testInsertAssignsIdentifierToNewComment(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(fn($value) => "'{$value}'");
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(21);
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(function ($sql) {
                return strpos($sql, 'INSERT INTO pref_xoopscomments') !== false
                    && strpos($sql, "'Sample title'") !== false
                    && strpos($sql, "'Sample text'") !== false;
            }))
            ->willReturn(true);

        $handler = new XoopsCommentHandler($database);

        $comment = new XoopsComment();
        $comment->setVar('com_pid', 0);
        $comment->setVar('com_modid', 1);
        $comment->setVar('com_title', 'Sample title');
        $comment->setVar('com_text', 'Sample text');
        $comment->setVar('com_created', 123);
        $comment->setVar('com_modified', 0);
        $comment->setVar('com_uid', 11);
        $comment->setDirty();
        $comment->setNew();

        $this->assertTrue($handler->insert($comment));
        $this->assertSame(21, $comment->getVar('com_id'));
    }

    public function testInsertUpdatesExistingComment(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(fn($value) => "'{$value}'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(function ($sql) {
                return strpos($sql, 'UPDATE pref_xoopscomments SET') === 0
                    && strpos($sql, 'WHERE com_id = 8') !== false;
            }))
            ->willReturn(true);

        $handler = new XoopsCommentHandler($database);

        $comment = new XoopsComment();
        $comment->setVar('com_id', 8);
        $comment->setVar('com_pid', 0);
        $comment->setVar('com_modid', 1);
        $comment->setVar('com_title', 'Updated title');
        $comment->setVar('com_text', 'Updated text');
        $comment->setVar('com_created', 10);
        $comment->setVar('com_modified', 20);
        $comment->setVar('com_uid', 9);
        $comment->unsetNew();
        $comment->setDirty();

        $this->assertTrue($handler->insert($comment));
    }

    public function testDeleteExecutesDeleteQuery(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_xoopscomments WHERE com_id = 5')
            ->willReturn(true);

        $handler = new XoopsCommentHandler($database);
        $comment = new XoopsComment();
        $comment->setVar('com_id', 5);

        $this->assertTrue($handler->delete($comment));
    }

    public function testGetObjectsReturnsListOfComments(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls([
            'com_id'     => 11,
            'com_title'  => 'First',
            'com_text'   => 'Text',
            'com_rootid' => 1,
            'com_pid'    => 0,
        ], false);

        $handler = new XoopsCommentHandler($database);
        $results = $handler->getObjects();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(XoopsComment::class, $results[0]);
        $this->assertSame('First', $results[0]->getVar('com_title'));
    }

    public function testGetCountReturnsRowCount(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT COUNT(*) FROM pref_xoopscomments')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([4]);

        $handler = new XoopsCommentHandler($database);

        $this->assertSame(4, $handler->getCount());
    }

    public function testDeleteAllAppliesCriteriaWhenProvided(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_xoopscomments WHERE com_status=1')
            ->willReturn(true);

        $criteria = $this->getMockBuilder(CriteriaElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['renderWhere'])
            ->getMock();
        $criteria->method('renderWhere')->willReturn('WHERE com_status=1');

        $handler = new XoopsCommentHandler($database);

        $this->assertTrue($handler->deleteAll($criteria));
    }

    public function testGetListUsesTitleMapping(): void
    {
        $handler = $this->getMockBuilder(XoopsCommentHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $comment = new XoopsComment();
        $comment->setVar('com_id', 2);
        $comment->setVar('com_title', 'Comment title');

        $handler->expects($this->once())
            ->method('getObjects')
            ->with(null, true)
            ->willReturn([
                2 => $comment,
            ]);

        $this->assertSame([2 => 'Comment title'], $handler->getList());
    }

    public function testGetByItemIdDelegatesToGetObjects(): void
    {
        $handler = $this->getMockBuilder(XoopsCommentHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(CriteriaCompo::class))
            ->willReturn(['result']);

        $this->assertSame(['result'], $handler->getByItemId(1, 2, 'ASC', 3, 5, 0));
    }

    public function testGetCountByItemIdDelegatesToGetCount(): void
    {
        $handler = $this->getMockBuilder(XoopsCommentHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getCount'])
            ->getMock();

        $handler->expects($this->once())
            ->method('getCount')
            ->with($this->isInstanceOf(CriteriaCompo::class))
            ->willReturn(6);

        $this->assertSame(6, $handler->getCountByItemId(1, 2, 0));
    }

    public function testGetTopCommentsDelegatesToGetObjects(): void
    {
        $handler = $this->getMockBuilder(XoopsCommentHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(CriteriaCompo::class))
            ->willReturn(['top']);

        $this->assertSame(['top'], $handler->getTopComments(1, 2, 'DESC', 0));
    }

    public function testGetThreadDelegatesToGetObjects(): void
    {
        $handler = $this->getMockBuilder(XoopsCommentHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(CriteriaCompo::class))
            ->willReturn(['thread']);

        $this->assertSame(['thread'], $handler->getThread(1, 2, 0));
    }

    public function testUpdateByFieldMarksCommentDirtyAndInserts(): void
    {
        $handler = $this->getMockBuilder(XoopsCommentHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['insert'])
            ->getMock();

        $comment = new XoopsComment();
        $comment->unsetNew();
        $comment->setVar('com_title', 'before');

        $handler->expects($this->once())
            ->method('insert')
            ->with($this->isInstanceOf(XoopsComment::class))
            ->willReturn(true);

        $this->assertTrue($handler->updateByField($comment, 'com_title', 'after'));
        $this->assertSame('after', $comment->getVar('com_title'));
        $this->assertTrue($comment->isDirty());
    }

    public function testDeleteByModuleUsesDeleteAll(): void
    {
        $handler = $this->getMockBuilder(XoopsCommentHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['deleteAll'])
            ->getMock();

        $handler->expects($this->once())
            ->method('deleteAll')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(true);

        $this->assertTrue($handler->deleteByModule(3));
    }

    private function createDatabaseMock()
    {
        return $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'prefix',
                'query',
                'isResultSet',
                'getRowsNum',
                'fetchArray',
                'exec',
                'getInsertId',
                'error',
                'fetchRow',
                'quote',
                'genId',
            ])
            ->getMock();
    }
}
