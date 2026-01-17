<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/tplset.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsTplsetTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $tplset = new XoopsTplset();

        $this->assertNull($tplset->getVar('tplset_id'));
        $this->assertNull($tplset->getVar('tplset_name'));
        $this->assertNull($tplset->getVar('tplset_desc'));
        $this->assertNull($tplset->getVar('tplset_credits'));
        $this->assertSame(0, $tplset->getVar('tplset_created'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $tplset = new XoopsTplset();
        $tplset->setVar('tplset_id', 3);
        $tplset->setVar('tplset_name', 'default');
        $tplset->setVar('tplset_desc', 'desc');
        $tplset->setVar('tplset_credits', 'credits');
        $tplset->setVar('tplset_created', 123);

        $this->assertSame(3, $tplset->id());
        $this->assertSame(3, $tplset->tplset_id());
        $this->assertSame('default', $tplset->tplset_name());
        $this->assertSame('desc', $tplset->tplset_desc());
        $this->assertSame('credits', $tplset->tplset_credits());
        $this->assertSame(123, $tplset->tplset_created());
    }
}

trait TplsetDatabaseMockTrait
{
    protected function createDatabaseMock()
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
                'genId',
                'quote',
                'fetchRow',
            ])
            ->getMock();
    }
}

class XoopsTplsetHandlerTest extends TestCase
{
    use TplsetDatabaseMockTrait;

    public function testCreateReturnsNewOrExistingTplset(): void
    {
        $handler = new XoopsTplsetHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsTplset::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesTplset(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_tplset WHERE tplset_id=7')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'tplset_id'      => 7,
            'tplset_name'    => 'default',
            'tplset_desc'    => 'desc',
            'tplset_credits' => 'credits',
            'tplset_created' => 99,
        ]);

        $handler = new XoopsTplsetHandler($database);
        $tplset  = $handler->get(7);

        $this->assertInstanceOf(XoopsTplset::class, $tplset);
        $this->assertSame('default', $tplset->getVar('tplset_name'));
    }

    public function testGetByNameRetrievesTplset(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT * FROM pref_tplset WHERE tplset_name='default'")
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'tplset_id'      => 5,
            'tplset_name'    => 'default',
            'tplset_desc'    => 'desc',
            'tplset_credits' => 'credits',
            'tplset_created' => 9,
        ]);

        $handler = new XoopsTplsetHandler($database);
        $tplset  = $handler->getByName('default');

        $this->assertInstanceOf(XoopsTplset::class, $tplset);
        $this->assertSame(5, $tplset->getVar('tplset_id'));
    }

    public function testInsertCreatesNewTplset(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(13);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with("INSERT INTO pref_tplset (tplset_id, tplset_name, tplset_desc, tplset_credits, tplset_created) VALUES (0, 'default', 'desc', 'credits', 1)")
            ->willReturn(true);

        $handler = new XoopsTplsetHandler($database);

        $tplset = $handler->create();
        $tplset->setVar('tplset_name', 'default');
        $tplset->setVar('tplset_desc', 'desc');
        $tplset->setVar('tplset_credits', 'credits');
        $tplset->setVar('tplset_created', 1);

        $this->assertTrue($handler->insert($tplset));
        $this->assertSame(13, $tplset->getVar('tplset_id'));
    }

    public function testInsertUpdatesExistingTplset(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with("UPDATE pref_tplset SET tplset_name = 'default', tplset_desc = 'desc', tplset_credits = 'credits', tplset_created = 2 WHERE tplset_id = 4")
            ->willReturn(true);

        $handler = new XoopsTplsetHandler($database);

        $tplset = $handler->create(false);
        $tplset->setNew(false);
        $tplset->setVar('tplset_id', 4);
        $tplset->setVar('tplset_name', 'default');
        $tplset->setVar('tplset_desc', 'desc');
        $tplset->setVar('tplset_credits', 'credits');
        $tplset->setVar('tplset_created', 2);

        $this->assertTrue($handler->insert($tplset));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsTplsetHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesTplsetAndLinks(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->exactly(2))
            ->method('query')
            ->withConsecutive(
                ['DELETE FROM pref_tplset WHERE tplset_id = 5'],
                ["DELETE FROM pref_imgset_tplset_link WHERE tplset_name = 'default'"]
            )
            ->willReturn(true);

        $handler = new XoopsTplsetHandler($database);

        $tplset = $handler->create(false);
        $tplset->setVar('tplset_id', 5);
        $tplset->setVar('tplset_name', 'default');

        $this->assertTrue($handler->delete($tplset));
    }

    public function testGetObjectsReturnsResultsWithCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT * FROM pref_tplset WHERE (tplset_name = 'default') ORDER BY tplset_id")
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'tplset_id'      => 1,
                'tplset_name'    => 'default',
                'tplset_desc'    => 'desc',
                'tplset_credits' => 'credits',
                'tplset_created' => 1,
            ],
            false
        );

        $handler  = new XoopsTplsetHandler($database);
        $criteria = new Criteria('tplset_name', 'default');
        $objects  = $handler->getObjects($criteria);

        $this->assertCount(1, $objects);
        $this->assertInstanceOf(XoopsTplset::class, $objects[0]);
        $this->assertSame(1, $objects[0]->getVar('tplset_id'));
    }

    public function testGetCountReturnsNumberOfRows(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT COUNT(*) FROM pref_tplset WHERE (tplset_name = 'default')")
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([7]);

        $handler  = new XoopsTplsetHandler($database);
        $criteria = new Criteria('tplset_name', 'default');

        $this->assertSame(7, $handler->getCount($criteria));
    }

    public function testGetListMapsNamesToNames(): void
    {
        $handler = $this->getMockBuilder(XoopsTplsetHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $tplset = new XoopsTplset();
        $tplset->assignVar('tplset_id', 1);
        $tplset->assignVar('tplset_name', 'default');

        $handler->method('getObjects')->willReturn([1 => $tplset]);

        $this->assertSame(['default' => 'default'], $handler->getList());
    }
}
