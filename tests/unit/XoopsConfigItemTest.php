<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/configitem.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsConfigItemTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $config = new XoopsConfigItem();

        $this->assertNull($config->getVar('conf_id'));
        $this->assertNull($config->getVar('conf_modid'));
        $this->assertNull($config->getVar('conf_catid'));
        $this->assertNull($config->getVar('conf_name'));
        $this->assertNull($config->getVar('conf_title'));
        $this->assertNull($config->getVar('conf_value'));
        $this->assertNull($config->getVar('conf_desc'));
        $this->assertNull($config->getVar('conf_formtype'));
        $this->assertNull($config->getVar('conf_valuetype'));
        $this->assertSame(0, $config->getVar('conf_order'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $config = new XoopsConfigItem();
        $config->setVar('conf_id', 11);
        $config->setVar('conf_modid', 22);
        $config->setVar('conf_catid', 33);
        $config->setVar('conf_name', 'sitename');
        $config->setVar('conf_title', 'Site Name');
        $config->setVar('conf_value', 'XOOPS');
        $config->setVar('conf_desc', 'Description');
        $config->setVar('conf_formtype', 'textbox');
        $config->setVar('conf_valuetype', 'text');
        $config->setVar('conf_order', 44);

        $this->assertSame(11, $config->id());
        $this->assertSame(11, $config->conf_id());
        $this->assertSame(22, $config->conf_modid());
        $this->assertSame(33, $config->conf_catid());
        $this->assertSame('sitename', $config->conf_name());
        $this->assertSame('Site Name', $config->conf_title());
        $this->assertSame('XOOPS', $config->conf_value());
        $this->assertSame('Description', $config->conf_desc());
        $this->assertSame('textbox', $config->conf_formtype());
        $this->assertSame('text', $config->conf_valuetype());
        $this->assertSame(44, $config->conf_order());
    }

    public function testGetConfValueForOutputCastsTypes(): void
    {
        $config = new XoopsConfigItem();

        $config->setVar('conf_valuetype', 'int');
        $config->setVar('conf_value', '7');
        $this->assertSame(7, $config->getConfValueForOutput());

        $config->setVar('conf_valuetype', 'array');
        $config->setVar('conf_value', serialize(['one' => 1]));
        $this->assertSame(['one' => 1], $config->getConfValueForOutput());

        $config->setVar('conf_valuetype', 'array');
        $config->setVar('conf_value', 'not-serialized');
        $this->assertSame([], $config->getConfValueForOutput());

        $config->setVar('conf_valuetype', 'float');
        $config->setVar('conf_value', '3.25');
        $this->assertSame(3.25, $config->getConfValueForOutput());

        $config->setVar('conf_valuetype', 'textarea');
        $config->setVar('conf_value', "multi\nline");
        $this->assertSame("multi\nline", $config->getConfValueForOutput());

        $config->setVar('conf_valuetype', 'text');
        $config->setVar('conf_value', 'raw');
        $this->assertSame('raw', $config->getConfValueForOutput());
    }

    public function testSetConfValueForInputStoresExpectedRepresentation(): void
    {
        $config = new XoopsConfigItem();

        $config->setVar('conf_valuetype', 'array');
        $value = 'a|b|c';
        $config->setConfValueForInput($value);
        $this->assertSame(serialize(['a', 'b', 'c']), $config->getVar('conf_value', 'n'));

        $config->setVar('conf_valuetype', 'text');
        $value = '  trimmed  ';
        $config->setConfValueForInput($value);
        $this->assertSame('trimmed', $config->getVar('conf_value', 'n'));

        $config->setVar('conf_valuetype', 'other');
        $value = 'kept';
        $config->setConfValueForInput($value);
        $this->assertSame('kept', $config->getVar('conf_value', 'n'));
    }

    public function testConfOptionsCanBeManaged(): void
    {
        $config  = new XoopsConfigItem();
        $option1 = new stdClass();
        $option2 = new stdClass();

        $config->setConfOptions([$option1, $option2]);
        $options = $config->getConfOptions();

        $this->assertSame([$option1, $option2], $options);

        $config->clearConfOptions();
        $this->assertSame([], $config->getConfOptions());
    }
}

class XoopsConfigItemHandlerTest extends TestCase
{
    public function testCreateReturnsNewOrExistingItem(): void
    {
        $handler = new XoopsConfigItemHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsConfigItem::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesItemFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_config WHERE conf_id=12')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'conf_id'       => 12,
            'conf_modid'    => 1,
            'conf_catid'    => 2,
            'conf_name'     => 'theme',
            'conf_title'    => 'Theme',
            'conf_value'    => 'default',
            'conf_desc'     => 'desc',
            'conf_formtype' => 'select',
            'conf_valuetype'=> 'text',
            'conf_order'    => 3,
        ]);

        $handler = new XoopsConfigItemHandler($database);

        $item = $handler->get(12);

        $this->assertInstanceOf(XoopsConfigItem::class, $item);
        $this->assertSame('theme', $item->getVar('conf_name'));
        $this->assertFalse($item->isNew());
    }

    public function testInsertCreatesNewItem(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(9);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(static function ($sql) {
                return strpos($sql, 'INSERT INTO pref_config') !== false
                    && strpos($sql, "'sitename'") !== false
                    && strpos($sql, "'Site Name'") !== false
                    && strpos($sql, "'text'") !== false;
            }))
            ->willReturn(true);

        $handler = new XoopsConfigItemHandler($database);

        $item = $handler->create();
        $item->setVar('conf_modid', 1);
        $item->setVar('conf_catid', 2);
        $item->setVar('conf_name', 'sitename');
        $item->setVar('conf_title', 'Site Name');
        $item->setVar('conf_value', 'XOOPS');
        $item->setVar('conf_desc', 'description');
        $item->setVar('conf_formtype', 'textbox');
        $item->setVar('conf_valuetype', 'text');
        $item->setVar('conf_order', 4);

        $this->assertTrue($handler->insert($item));
        $this->assertSame(9, $item->getVar('conf_id'));
    }

    public function testInsertUpdatesExistingItem(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_config SET conf_modid ='))
            ->willReturn(true);

        $handler = new XoopsConfigItemHandler($database);

        $item = $handler->create(false);
        $item->setNew(false);
        $item->setVar('conf_id', 4);
        $item->setVar('conf_modid', 1);
        $item->setVar('conf_catid', 2);
        $item->setVar('conf_name', 'updated');
        $item->setVar('conf_title', 'Updated');
        $item->setVar('conf_value', 'value');
        $item->setVar('conf_desc', 'desc');
        $item->setVar('conf_formtype', 'textbox');
        $item->setVar('conf_valuetype', 'text');
        $item->setVar('conf_order', 5);

        $this->assertTrue($handler->insert($item));
    }

    public function testDeleteRemovesItem(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_config WHERE conf_id = 8')
            ->willReturn(true);

        $handler = new XoopsConfigItemHandler($database);

        $item = $handler->create(false);
        $item->setVar('conf_id', 8);

        $this->assertTrue($handler->delete($item));
    }

    public function testGetObjectsReturnsItemsUsingCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_config WHERE (conf_order > 1) ORDER BY conf_order ASC', 3, 0)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'conf_id'       => 1,
                'conf_modid'    => 1,
                'conf_catid'    => 1,
                'conf_name'     => 'first',
                'conf_title'    => 'First',
                'conf_value'    => 'one',
                'conf_desc'     => 'desc',
                'conf_formtype' => 'textbox',
                'conf_valuetype'=> 'text',
                'conf_order'    => 2,
            ],
            [
                'conf_id'       => 2,
                'conf_modid'    => 1,
                'conf_catid'    => 1,
                'conf_name'     => 'second',
                'conf_title'    => 'Second',
                'conf_value'    => 'two',
                'conf_desc'     => 'desc',
                'conf_formtype' => 'textbox',
                'conf_valuetype'=> 'text',
                'conf_order'    => 3,
            ],
            false
        );

        $handler  = new XoopsConfigItemHandler($database);
        $criteria = new Criteria('conf_order', 1, '>');
        $criteria->setOrder('ASC');
        $criteria->setLimit(3);

        $items = $handler->getObjects($criteria);

        $this->assertCount(2, $items);
        $this->assertSame('first', $items[0]->getVar('conf_name'));
        $this->assertSame('second', $items[1]->getVar('conf_name'));
    }

    public function testGetCountReturnsNumberOfRows(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_config WHERE (conf_modid = 1)')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([5]);

        $handler  = new XoopsConfigItemHandler($database);
        $criteria = new Criteria('conf_modid', 1);

        $this->assertSame(5, $handler->getCount($criteria));
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
                'quote',
                'genId',
                'fetchRow',
            ])
            ->getMock();
    }
}
