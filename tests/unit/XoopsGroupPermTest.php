<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/groupperm.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

trait DatabaseMockTrait
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

class XoopsGroupPermTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $perm = new XoopsGroupPerm();

        $this->assertNull($perm->getVar('gperm_id'));
        $this->assertNull($perm->getVar('gperm_groupid'));
        $this->assertNull($perm->getVar('gperm_itemid'));
        $this->assertSame(0, $perm->getVar('gperm_modid'));
        $this->assertNull($perm->getVar('gperm_name'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $perm = new XoopsGroupPerm();
        $perm->setVar('gperm_id', 3);
        $perm->setVar('gperm_groupid', 5);
        $perm->setVar('gperm_itemid', 9);
        $perm->setVar('gperm_modid', 2);
        $perm->setVar('gperm_name', 'module_read');

        $this->assertSame(3, $perm->id());
        $this->assertSame(3, $perm->gperm_id());
        $this->assertSame(5, $perm->gperm_groupid());
        $this->assertSame(9, $perm->gperm_itemid());
        $this->assertSame(2, $perm->gperm_modid());
        $this->assertSame('module_read', $perm->gperm_name());
    }
}

class XoopsGroupPermHandlerTest extends TestCase
{
    use DatabaseMockTrait;

    public function testCreateReturnsNewOrExistingPermission(): void
    {
        $handler = new XoopsGroupPermHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsGroupPerm::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesPermission(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_group_permission WHERE gperm_id = 7')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'gperm_id'     => 7,
            'gperm_groupid'=> 1,
            'gperm_itemid' => 10,
            'gperm_modid'  => 3,
            'gperm_name'   => 'view',
        ]);

        $handler = new XoopsGroupPermHandler($database);
        $perm    = $handler->get(7);

        $this->assertInstanceOf(XoopsGroupPerm::class, $perm);
        $this->assertSame('view', $perm->getVar('gperm_name'));
        $this->assertFalse($perm->isNew());
    }

    public function testInsertCreatesNewPermission(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('INSERT INTO pref_group_permission'))
            ->willReturn(true);
        $database->method('getInsertId')->willReturn(12);

        $handler = new XoopsGroupPermHandler($database);

        $perm = $handler->create();
        $perm->setVar('gperm_groupid', 1);
        $perm->setVar('gperm_itemid', 2);
        $perm->setVar('gperm_modid', 3);
        $perm->setVar('gperm_name', 'read');

        $this->assertTrue($handler->insert($perm));
        $this->assertSame(12, $perm->getVar('gperm_id'));
    }

    public function testInsertUpdatesExistingPermission(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_group_permission SET'))
            ->willReturn(true);

        $handler = new XoopsGroupPermHandler($database);

        $perm = $handler->create(false);
        $perm->setNew(false);
        $perm->setVar('gperm_id', 4);
        $perm->setVar('gperm_groupid', 2);
        $perm->setVar('gperm_itemid', 9);
        $perm->setVar('gperm_modid', 1);
        $perm->setVar('gperm_name', 'edit');

        $this->assertTrue($handler->insert($perm));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsGroupPermHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesPermission(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_group_permission WHERE gperm_id = 8')
            ->willReturn(true);

        $handler = new XoopsGroupPermHandler($database);

        $perm = $handler->create(false);
        $perm->setVar('gperm_id', 8);

        $this->assertTrue($handler->delete($perm));
    }

    public function testGetObjectsReturnsPermissionsWithCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_group_permission WHERE (gperm_id > 0)', 1, 0)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'gperm_id'      => 1,
                'gperm_groupid' => 2,
                'gperm_itemid'  => 3,
                'gperm_modid'   => 4,
                'gperm_name'    => 'module_admin',
            ],
            false
        );

        $handler  = new XoopsGroupPermHandler($database);
        $criteria = new Criteria('gperm_id', 0, '>');
        $criteria->setLimit(1);

        $objects = $handler->getObjects($criteria, true);

        $this->assertCount(1, $objects);
        $this->assertArrayHasKey(1, $objects);
        $this->assertSame('module_admin', $objects[1]->getVar('gperm_name'));
    }

    public function testGetCountReturnsValue(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT COUNT(*) FROM pref_group_permission WHERE (gperm_modid = 1)')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([5]);

        $handler  = new XoopsGroupPermHandler($database);
        $criteria = new Criteria('gperm_modid', 1);

        $this->assertSame(5, $handler->getCount($criteria));
    }

    public function testDeleteAllExecutes(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_group_permission WHERE (gperm_groupid = 2)')
            ->willReturn(true);

        $handler  = new XoopsGroupPermHandler($database);
        $criteria = new Criteria('gperm_groupid', 2);

        $this->assertTrue($handler->deleteAll($criteria));
    }

    public function testDeleteByGroupBuildsCriteria(): void
    {
        $handler = $this->getMockBuilder(XoopsGroupPermHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['deleteAll'])
            ->getMock();

        $handler->expects($this->once())
            ->method('deleteAll')
            ->with($this->callback(static function ($criteria) {
                return $criteria instanceof CriteriaCompo
                    && str_contains($criteria->renderWhere(), 'gperm_groupid = 4')
                    && str_contains($criteria->renderWhere(), 'gperm_modid = 1');
            }))
            ->willReturn(true);

        $this->assertTrue($handler->deleteByGroup(4, 1));
    }

    public function testDeleteByModuleBuildsCriteria(): void
    {
        $handler = $this->getMockBuilder(XoopsGroupPermHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['deleteAll'])
            ->getMock();

        $handler->expects($this->once())
            ->method('deleteAll')
            ->with($this->callback(static function ($criteria) {
                return $criteria instanceof CriteriaCompo
                    && str_contains($criteria->renderWhere(), "gperm_modid = 3")
                    && str_contains($criteria->renderWhere(), "gperm_name = 'access'")
                    && str_contains($criteria->renderWhere(), 'gperm_itemid = 7');
            }))
            ->willReturn(true);

        $this->assertTrue($handler->deleteByModule(3, 'access', 7));
    }

    public function testCheckRightReturnsTrueForAdminGroup(): void
    {
        $handler = $this->getMockBuilder(XoopsGroupPermHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getCount'])
            ->getMock();

        $handler->expects($this->never())->method('getCount');

        $this->assertTrue($handler->checkRight('read', 0, [XOOPS_GROUP_ADMIN], 1, true));
    }

    public function testCheckRightEvaluatesPermissions(): void
    {
        $handler = $this->getMockBuilder(XoopsGroupPermHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getCount'])
            ->getMock();

        $handler->expects($this->once())
            ->method('getCount')
            ->with($this->callback(static function ($criteria) {
                return $criteria instanceof CriteriaCompo
                    && str_contains($criteria->renderWhere(), 'gperm_modid = 2')
                    && str_contains($criteria->renderWhere(), "gperm_name = 'write'")
                    && str_contains($criteria->renderWhere(), 'gperm_itemid = 5')
                    && str_contains($criteria->renderWhere(), 'gperm_groupid = 4');
            }))
            ->willReturn(1);

        $this->assertTrue($handler->checkRight('write', 5, 4, 2, false));
    }

    public function testAddRightCreatesPermission(): void
    {
        $createdPerm = new XoopsGroupPerm();

        $handler = $this->getMockBuilder(XoopsGroupPermHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['create', 'insert'])
            ->getMock();

        $handler->method('create')->willReturn($createdPerm);
        $handler->expects($this->once())
            ->method('insert')
            ->with($createdPerm)
            ->willReturn(true);

        $this->assertTrue($handler->addRight('comment', 11, 2, 1));
        $this->assertSame('comment', $createdPerm->getVar('gperm_name'));
        $this->assertSame(11, $createdPerm->getVar('gperm_itemid'));
        $this->assertSame(2, $createdPerm->getVar('gperm_groupid'));
        $this->assertSame(1, $createdPerm->getVar('gperm_modid'));
    }

    public function testGetItemIdsCollectsUniqueValues(): void
    {
        $permA = new XoopsGroupPerm();
        $permA->setVar('gperm_itemid', 3);
        $permB = new XoopsGroupPerm();
        $permB->setVar('gperm_itemid', 3);
        $permC = new XoopsGroupPerm();
        $permC->setVar('gperm_itemid', 4);

        $handler = $this->getMockBuilder(XoopsGroupPermHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->method('getObjects')->willReturn([
            1 => $permA,
            2 => $permB,
            3 => $permC,
        ]);

        $items = $handler->getItemIds('view', [1, 2], 5);

        $this->assertSame([3, 4], $items);
    }

    public function testGetGroupIdsCollectsValues(): void
    {
        $permA = new XoopsGroupPerm();
        $permA->setVar('gperm_groupid', 2);
        $permB = new XoopsGroupPerm();
        $permB->setVar('gperm_groupid', 4);

        $handler = $this->getMockBuilder(XoopsGroupPermHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->method('getObjects')->willReturn([
            1 => $permA,
            2 => $permB,
        ]);

        $groups = $handler->getGroupIds('edit', 7, 1);

        $this->assertSame([2, 4], $groups);
    }
}
