<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/configoption.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsConfigOptionTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $option = new XoopsConfigOption();

        $this->assertNull($option->getVar('confop_id'));
        $this->assertNull($option->getVar('confop_name'));
        $this->assertNull($option->getVar('confop_value'));
        $this->assertSame(0, $option->getVar('conf_id'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $option = new XoopsConfigOption();
        $option->setVar('confop_id', 9);
        $option->setVar('confop_name', 'option_name');
        $option->setVar('confop_value', 'value');
        $option->setVar('conf_id', 3);

        $this->assertSame(9, $option->id());
        $this->assertSame(9, $option->confop_id());
        $this->assertSame('option_name', $option->confop_name());
        $this->assertSame('value', $option->confop_value());
        $this->assertSame(3, $option->conf_id());
    }
}

class XoopsConfigOptionHandlerTest extends TestCase
{
    public function testCreateReturnsNewOrExistingOption(): void
    {
        $handler = new XoopsConfigOptionHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsConfigOption::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesOptionFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_configoption WHERE confop_id=7')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'confop_id'    => 7,
            'confop_name'  => 'name',
            'confop_value' => 'val',
            'conf_id'      => 2,
        ]);

        $handler = new XoopsConfigOptionHandler($database);

        $option = $handler->get(7);

        $this->assertInstanceOf(XoopsConfigOption::class, $option);
        $this->assertSame('name', $option->getVar('confop_name'));
        $this->assertFalse($option->isNew());
    }

    public function testInsertCreatesNewOption(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(12);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(static function ($sql) {
                return strpos($sql, 'INSERT INTO pref_configoption') !== false
                    && strpos($sql, "'name'") !== false
                    && strpos($sql, "'value'") !== false
                    && strpos($sql, '3') !== false;
            }))
            ->willReturn(true);

        $handler = new XoopsConfigOptionHandler($database);

        $option = $handler->create();
        $option->setVar('confop_name', 'name');
        $option->setVar('confop_value', 'value');
        $option->setVar('conf_id', 3);

        $this->assertSame(12, $handler->insert($option));
        $this->assertSame(12, $option->getVar('confop_id'));
    }

    public function testInsertUpdatesExistingOption(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_configoption SET confop_name ='))
            ->willReturn(true);

        $handler = new XoopsConfigOptionHandler($database);

        $option = $handler->create(false);
        $option->setNew(false);
        $option->setVar('confop_id', 4);
        $option->setVar('confop_name', 'updated');
        $option->setVar('confop_value', 'changed');
        $option->setVar('conf_id', 1);

        $this->assertSame(4, $handler->insert($option));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsConfigOptionHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesOption(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_configoption WHERE confop_id = 8')
            ->willReturn(true);

        $handler = new XoopsConfigOptionHandler($database);

        $option = $handler->create(false);
        $option->setVar('confop_id', 8);

        $this->assertTrue($handler->delete($option));
    }

    public function testGetObjectsReturnsOptionsUsingCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_configoption WHERE (conf_id = 2) ORDER BY confop_id ASC', 5, 0)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'confop_id'    => 1,
                'confop_name'  => 'first',
                'confop_value' => 'one',
                'conf_id'      => 2,
            ],
            [
                'confop_id'    => 2,
                'confop_name'  => 'second',
                'confop_value' => 'two',
                'conf_id'      => 2,
            ],
            false
        );

        $handler  = new XoopsConfigOptionHandler($database);
        $criteria = new Criteria('conf_id', 2);
        $criteria->setOrder('ASC');
        $criteria->setLimit(5);

        $options = $handler->getObjects($criteria);

        $this->assertCount(2, $options);
        $this->assertSame('first', $options[0]->getVar('confop_name'));
        $this->assertSame('second', $options[1]->getVar('confop_name'));
    }

    public function testGetCountReturnsNumberOfRows(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT COUNT(*) as `count` FROM pref_configoption WHERE (conf_id = 5)')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturn(['count' => 4]);
        $database->expects($this->once())->method('freeRecordSet')->with('result');

        $handler  = new XoopsConfigOptionHandler($database);
        $criteria = new Criteria('conf_id', 5);

        $this->assertSame(4, $handler->getCount($criteria));
    }

    public function testGetCountThrowsRuntimeExceptionWhenQueryFails(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_configoption');
        $database->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(false);
        $database->method('error')->willReturn('db error');

        $handler = new XoopsConfigOptionHandler($database);

        $this->expectException(RuntimeException::class);
        $handler->getCount();
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
                'genId',
                'getInsertId',
                'quote',
                'freeRecordSet',
                'error',
            ])
            ->getMock();
    }
}
