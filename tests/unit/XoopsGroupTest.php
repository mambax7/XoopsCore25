<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/group.php';
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

class XoopsGroupTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $group = new XoopsGroup();

        $this->assertNull($group->getVar('groupid'));
        $this->assertNull($group->getVar('name'));
        $this->assertNull($group->getVar('description'));
        $this->assertNull($group->getVar('group_type'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $group = new XoopsGroup();
        $group->setVar('groupid', 15);
        $group->setVar('name', 'Registered Users');
        $group->setVar('description', 'General members');
        $group->setVar('group_type', 'User');

        $this->assertSame(15, $group->id());
        $this->assertSame(15, $group->groupid());
        $this->assertSame('Registered Users', $group->name());
        $this->assertSame('General members', $group->description());
        $this->assertSame('User', $group->group_type());
    }
}

class XoopsGroupHandlerTest extends TestCase
{
    use DatabaseMockTrait;

    public function testCreateReturnsNewOrExistingGroup(): void
    {
        $handler = new XoopsGroupHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsGroup::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesGroup(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_groups WHERE groupid=5')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'groupid'     => 5,
            'name'        => 'Admins',
            'description' => 'Site administrators',
            'group_type'  => 'Admin',
        ]);

        $handler = new XoopsGroupHandler($database);
        $group   = $handler->get(5);

        $this->assertInstanceOf(XoopsGroup::class, $group);
        $this->assertSame('Admins', $group->getVar('name'));
        $this->assertFalse($group->isNew());
    }

    public function testInsertCreatesNewGroup(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('INSERT INTO pref_groups'))
            ->willReturn(true);
        $database->method('getInsertId')->willReturn(9);

        $handler = new XoopsGroupHandler($database);

        $group = $handler->create();
        $group->setVar('name', 'Guests');
        $group->setVar('description', 'Unregistered users');
        $group->setVar('group_type', 'Anonymous');

        $this->assertTrue($handler->insert($group));
        $this->assertSame(9, $group->getVar('groupid'));
    }

    public function testInsertUpdatesExistingGroup(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_groups SET name ='))
            ->willReturn(true);

        $handler = new XoopsGroupHandler($database);

        $group = $handler->create(false);
        $group->setNew(false);
        $group->setVar('groupid', 7);
        $group->setVar('name', 'Members');
        $group->setVar('description', 'Registered users');
        $group->setVar('group_type', 'User');

        $this->assertTrue($handler->insert($group));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsGroupHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesGroup(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_groups WHERE groupid = 11')
            ->willReturn(true);

        $handler = new XoopsGroupHandler($database);

        $group = $handler->create(false);
        $group->setVar('groupid', 11);

        $this->assertTrue($handler->delete($group));
    }

    public function testGetObjectsReturnsGroupsWithCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_groups WHERE (groupid > 0)', 2, 0)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'groupid'     => 1,
                'name'        => 'Webmasters',
                'description' => 'Admins',
                'group_type'  => 'Admin',
            ],
            [
                'groupid'     => 2,
                'name'        => 'Users',
                'description' => 'Members',
                'group_type'  => 'User',
            ],
            false
        );

        $handler  = new XoopsGroupHandler($database);
        $criteria = new Criteria('groupid', 0, '>');
        $criteria->setLimit(2);

        $groups = $handler->getObjects($criteria, true);

        $this->assertCount(2, $groups);
        $this->assertSame('Webmasters', $groups[1]->getVar('name'));
        $this->assertSame('Users', $groups[2]->getVar('name'));
    }
}

class XoopsMembershipTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $membership = new XoopsMembership();

        $this->assertNull($membership->getVar('linkid'));
        $this->assertNull($membership->getVar('groupid'));
        $this->assertNull($membership->getVar('uid'));
    }
}

class XoopsMembershipHandlerTest extends TestCase
{
    use DatabaseMockTrait;

    public function testCreateReturnsNewOrExistingMembership(): void
    {
        $handler = new XoopsMembershipHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsMembership::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesMembership(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_groups_users_link WHERE linkid=3')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'linkid'  => 3,
            'groupid' => 2,
            'uid'     => 5,
        ]);

        $handler    = new XoopsMembershipHandler($database);
        $membership = $handler->get(3);

        $this->assertInstanceOf(XoopsMembership::class, $membership);
        $this->assertSame(2, $membership->getVar('groupid'));
    }

    public function testInsertCreatesNewMembership(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('INSERT INTO pref_groups_users_link'))
            ->willReturn(true);
        $database->method('getInsertId')->willReturn(10);

        $handler = new XoopsMembershipHandler($database);

        $membership = $handler->create();
        $membership->setVar('groupid', 4);
        $membership->setVar('uid', 7);

        $this->assertTrue($handler->insert($membership));
        $this->assertSame(10, $membership->getVar('linkid'));
    }

    public function testInsertUpdatesExistingMembership(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_groups_users_link SET groupid ='))
            ->willReturn(true);

        $handler = new XoopsMembershipHandler($database);

        $membership = $handler->create(false);
        $membership->setNew(false);
        $membership->setVar('linkid', 6);
        $membership->setVar('groupid', 8);
        $membership->setVar('uid', 12);

        $this->assertTrue($handler->insert($membership));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsMembershipHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesMembership(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_groups_users_link WHERE linkid = 13')
            ->willReturn(true);

        $handler = new XoopsMembershipHandler($database);

        $membership = $handler->create(false);
        $membership->setVar('linkid', 13);

        $this->assertTrue($handler->delete($membership));
    }

    public function testGetObjectsReturnsMemberships(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_groups_users_link WHERE (uid = 1)', 0, 0)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'linkid'  => 1,
                'groupid' => 2,
                'uid'     => 1,
            ],
            [
                'linkid'  => 2,
                'groupid' => 3,
                'uid'     => 1,
            ],
            false
        );

        $handler  = new XoopsMembershipHandler($database);
        $criteria = new Criteria('uid', 1);

        $memberships = $handler->getObjects($criteria, true);

        $this->assertCount(2, $memberships);
        $this->assertSame(2, $memberships[1]->getVar('groupid'));
        $this->assertSame(3, $memberships[2]->getVar('groupid'));
    }

    public function testGetCountReturnsNumberOfMemberships(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT COUNT(*) FROM pref_groups_users_link WHERE (groupid = 9)')
            ->willReturn('result');
        $database->method('fetchRow')->willReturn([5]);

        $handler  = new XoopsMembershipHandler($database);
        $criteria = new Criteria('groupid', 9);

        $this->assertSame(5, $handler->getCount($criteria));
    }

    public function testDeleteAllRemovesMemberships(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('DELETE FROM pref_groups_users_link WHERE (uid = 2)')
            ->willReturn(true);

        $handler  = new XoopsMembershipHandler($database);
        $criteria = new Criteria('uid', 2);

        $this->assertTrue($handler->deleteAll($criteria));
    }

    public function testGetGroupsByUserReturnsIds(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT groupid FROM pref_groups_users_link WHERE uid=4')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            ['groupid' => 1],
            ['groupid' => 3],
            false
        );

        $handler = new XoopsMembershipHandler($database);

        $this->assertSame([1, 3], $handler->getGroupsByUser(4));
    }

    public function testGetUsersByGroupReturnsIds(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT uid FROM pref_groups_users_link WHERE groupid=6', 5, 0)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            ['uid' => 7],
            ['uid' => 8],
            false
        );

        $handler = new XoopsMembershipHandler($database);

        $this->assertSame([7, 8], $handler->getUsersByGroup(6, 5));
    }
}
