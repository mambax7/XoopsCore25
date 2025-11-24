<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/block.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsBlockTest extends TestCase
{
    public function testConstructorSetsDefaultVariables(): void
    {
        $block = new XoopsBlock();

        $this->assertNull($block->getVar('bid'));
        $this->assertSame(0, $block->getVar('mid'));
        $this->assertSame(0, $block->getVar('func_num'));
        $this->assertSame(0, $block->getVar('weight'));
        $this->assertSame(0, $block->getVar('visible'));
    }

    public function testIdHelperReturnsIdentifier(): void
    {
        $block = new XoopsBlock();
        $block->setVar('bid', 42);

        $this->assertSame(42, $block->id());
        $this->assertSame(42, $block->bid());
    }

    public function testIsCustomDetectsCustomBlockTypes(): void
    {
        $block = new XoopsBlock();

        $block->setVar('block_type', 'C');
        $this->assertTrue($block->isCustom());

        $block->setVar('block_type', 'E');
        $this->assertTrue($block->isCustom());

        $block->setVar('block_type', 'S');
        $this->assertFalse($block->isCustom());
    }

    public function testBuildContentAlignsOutput(): void
    {
        $block = new XoopsBlock();

        $this->assertSame('dbfirstcontent', $block->buildContent(0, 'content', 'dbfirst'));
        $this->assertSame('contentdb', $block->buildContent(1, 'content', 'db'));
        $this->assertNull($block->buildContent(2, 'content', 'db'));
    }

    public function testBuildTitlePrefersNewTitleWhenProvided(): void
    {
        $block = new XoopsBlock();

        $this->assertSame('original', $block->buildTitle('original'));
        $this->assertSame('new', $block->buildTitle('original', 'new'));
    }
}

class XoopsBlockHandlerTest extends TestCase
{
    public function testCreateReturnsNewOrExistingBlock(): void
    {
        $database = $this->createDatabaseMock();
        $handler  = new XoopsBlockHandler($database);

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsBlock::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesBlockFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_newblocks WHERE bid=4')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'bid'        => 4,
            'name'       => 'Test block',
            'block_type' => 'S',
        ]);

        $handler = new XoopsBlockHandler($database);
        $block   = $handler->get(4);

        $this->assertInstanceOf(XoopsBlock::class, $block);
        $this->assertSame('Test block', $block->getVar('name'));
        $this->assertFalse($block->isNew());
    }

    public function testInsertPersistsNewBlockAndAssignsId(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(33);
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(static function ($sql) {
                return strpos($sql, 'INSERT INTO pref_newblocks') !== false
                    && strpos($sql, "'sidebar'") !== false
                    && strpos($sql, "'My Block'") !== false;
            }))
            ->willReturn(true);

        $handler = new XoopsBlockHandler($database);

        $block = new XoopsBlock();
        $block->setVar('mid', 2);
        $block->setVar('func_num', 1);
        $block->setVar('options', 'opt1');
        $block->setVar('name', 'My Block');
        $block->setVar('title', 'Title');
        $block->setVar('content', 'Content');
        $block->setVar('side', 1);
        $block->setVar('weight', 0);
        $block->setVar('visible', 1);
        $block->setVar('block_type', 'S');
        $block->setVar('c_type', 'H');
        $block->setVar('isactive', 1);
        $block->setVar('dirname', 'sidebar');
        $block->setVar('func_file', 'block.php');
        $block->setVar('show_func', 'show');
        $block->setVar('edit_func', 'edit');
        $block->setVar('template', 'block.tpl');
        $block->setVar('bcachetime', 30);
        $block->setDirty();
        $block->setNew();

        $this->assertTrue($handler->insert($block));
        $this->assertSame(33, $block->getVar('bid'));
    }

    public function testDeleteRemovesBlockAndLinks(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                ['DELETE FROM pref_newblocks WHERE bid = 8'],
                ['DELETE FROM pref_block_module_link WHERE block_id = 8']
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsBlockHandler($database);
        $block   = new XoopsBlock();
        $block->setVar('bid', 8);

        $this->assertTrue($handler->delete($block));
    }

    public function testGetObjectsReturnsBlocks(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with(
                'SELECT DISTINCT(b.bid), b.* FROM pref_newblocks b LEFT JOIN pref_block_module_link l ON b.bid=l.block_id WHERE visible=1',
                5,
                2
            )
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls([
            'bid'        => 9,
            'name'       => 'Block name',
            'block_type' => 'S',
        ], false);

        $handler  = new XoopsBlockHandler($database);
        $criteria = $this->createMock(CriteriaElement::class);
        $criteria->method('renderWhere')->willReturn('WHERE visible=1');
        $criteria->method('getLimit')->willReturn(5);
        $criteria->method('getStart')->willReturn(2);

        $blocks = $handler->getObjects($criteria, true);

        $this->assertCount(1, $blocks);
        $this->assertArrayHasKey(9, $blocks);
        $this->assertInstanceOf(XoopsBlock::class, $blocks[9]);
    }

    public function testGetListUsesCustomTitles(): void
    {
        $handler = $this->getMockBuilder(XoopsBlockHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $customBlock = new XoopsBlock();
        $customBlock->setVar('bid', 7);
        $customBlock->setVar('block_type', 'C');
        $customBlock->setVar('title', 'Custom Title');

        $regularBlock = new XoopsBlock();
        $regularBlock->setVar('bid', 8);
        $regularBlock->setVar('block_type', 'S');
        $regularBlock->setVar('name', 'Regular Name');

        $handler->expects($this->once())
            ->method('getObjects')
            ->with(null, true)
            ->willReturn([
                7 => $customBlock,
                8 => $regularBlock,
            ]);

        $list = $handler->getList();

        $this->assertSame([
            7 => 'Custom Title',
            8 => 'Regular Name',
        ], $list);
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
