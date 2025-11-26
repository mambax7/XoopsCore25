<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/member.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

trait MemberDatabaseMockTrait
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

class XoopsMemberHandlerTest extends TestCase
{
    use MemberDatabaseMockTrait;

    private function injectHandler(object $target, string $property, object $handler): void
    {
        $ref = new ReflectionProperty($target, $property);
        $ref->setAccessible(true);
        $ref->setValue($target, $handler);
    }

    public function testConstructorInitializesHandlers(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $this->assertInstanceOf(XoopsGroupHandler::class, $this->getPropertyValue($handler, 'groupHandler'));
        $this->assertInstanceOf(XoopsUserHandler::class, $this->getPropertyValue($handler, 'userHandler'));
        $this->assertInstanceOf(XoopsMembershipHandler::class, $this->getPropertyValue($handler, 'membershipHandler'));
    }

    public function testCreateAndGetForwardToHandlers(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $groupHandler = $this->createMock(XoopsGroupHandler::class);
        $groupHandler->expects($this->once())->method('create')->willReturn(new XoopsGroup());
        $groupHandler->expects($this->once())->method('get')->with(12)->willReturn(new XoopsGroup());

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())->method('create')->willReturn(new XoopsUser());

        $this->injectHandler($handler, 'groupHandler', $groupHandler);
        $this->injectHandler($handler, 'userHandler', $userHandler);

        $this->assertInstanceOf(XoopsGroup::class, $handler->createGroup());
        $this->assertInstanceOf(XoopsUser::class, $handler->createUser());
        $this->assertInstanceOf(XoopsGroup::class, $handler->getGroup(12));
    }

    public function testGetUserCachesResult(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $user = new XoopsUser();
        $user->setVar('uid', 7);

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())->method('get')->with(7)->willReturn($user);

        $this->injectHandler($handler, 'userHandler', $userHandler);

        $first = $handler->getUser(7);
        $second = $handler->getUser(7);

        $this->assertSame($user, $first);
        $this->assertSame($first, $second);
    }

    public function testDeleteGroupAndUserDelegateToHandlers(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $group = new XoopsGroup();
        $group->setVar('groupid', 3);
        $user = new XoopsUser();
        $user->setVar('uid', 5);

        $membershipHandler = $this->createMock(XoopsMembershipHandler::class);
        $membershipHandler->expects($this->exactly(2))->method('deleteAll')
            ->with($this->isInstanceOf(CriteriaElement::class))
            ->willReturn(true);

        $groupHandler = $this->createMock(XoopsGroupHandler::class);
        $groupHandler->expects($this->once())->method('delete')->with($group)->willReturn(true);

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())->method('delete')->with($user)->willReturn(true);

        $this->injectHandler($handler, 'membershipHandler', $membershipHandler);
        $this->injectHandler($handler, 'groupHandler', $groupHandler);
        $this->injectHandler($handler, 'userHandler', $userHandler);

        $this->assertTrue($handler->deleteGroup($group));
        $this->assertTrue($handler->deleteUser($user));
    }

    public function testInsertAndUpdateHelpers(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $group = new XoopsGroup();
        $user = new XoopsUser();

        $groupHandler = $this->createMock(XoopsGroupHandler::class);
        $groupHandler->expects($this->once())->method('insert')->with($group)->willReturn(true);

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->exactly(2))->method('insert')->willReturn(true);
        $userHandler->expects($this->once())->method('updateAll')->with('level', 2, null)->willReturn(true);

        $this->injectHandler($handler, 'groupHandler', $groupHandler);
        $this->injectHandler($handler, 'userHandler', $userHandler);

        $this->assertTrue($handler->insertGroup($group));
        $this->assertTrue($handler->insertUser($user));
        $this->assertTrue($handler->updateUserByField($user, 'email', 'me@example.com'));
        $this->assertTrue($handler->updateUsersByField('level', 2));
    }

    public function testListsAreBuiltFromHandlers(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $group1 = new XoopsGroup();
        $group1->setVar('groupid', 1);
        $group1->setVar('name', 'Admins');
        $group2 = new XoopsGroup();
        $group2->setVar('groupid', 2);
        $group2->setVar('name', 'Users');

        $user1 = new XoopsUser();
        $user1->setVar('uid', 11);
        $user1->setVar('uname', 'alice');
        $user2 = new XoopsUser();
        $user2->setVar('uid', 12);
        $user2->setVar('uname', 'bob');

        $groupHandler = $this->createMock(XoopsGroupHandler::class);
        $groupHandler->expects($this->once())->method('getObjects')->with(null, true)->willReturn([
            1 => $group1,
            2 => $group2,
        ]);

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())->method('getObjects')->with(null, true)->willReturn([
            11 => $user1,
            12 => $user2,
        ]);

        $this->injectHandler($handler, 'groupHandler', $groupHandler);
        $this->injectHandler($handler, 'userHandler', $userHandler);

        $this->assertSame([1 => 'Admins', 2 => 'Users'], $handler->getGroupList());
        $this->assertSame([11 => 'alice', 12 => 'bob'], $handler->getUserList());
    }

    public function testAddUserToGroupCreatesMembership(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $membership = new XoopsMembership();
        $membershipHandler = $this->createMock(XoopsMembershipHandler::class);
        $membershipHandler->expects($this->once())->method('create')->willReturn($membership);
        $membershipHandler->expects($this->once())->method('insert')->with($membership)->willReturn(true);

        $this->injectHandler($handler, 'membershipHandler', $membershipHandler);

        $result = $handler->addUserToGroup(4, 9);

        $this->assertInstanceOf(XoopsMembership::class, $result);
        $this->assertSame(4, $result->getVar('groupid'));
        $this->assertSame(9, $result->getVar('uid'));
    }

    public function testRemoveUsersFromGroupBuildsCriteria(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $membershipHandler = $this->createMock(XoopsMembershipHandler::class);
        $membershipHandler->expects($this->once())
            ->method('deleteAll')
            ->with($this->callback(function ($criteria) {
                return $criteria instanceof CriteriaCompo
                    && str_contains($criteria->render(), 'groupid')
                    && str_contains($criteria->render(), 'uid IN (5,6)');
            }))
            ->willReturn(true);

        $this->injectHandler($handler, 'membershipHandler', $membershipHandler);

        $this->assertTrue($handler->removeUsersFromGroup(3, [5, 6]));
    }

    public function testGetUsersAndGroupsByLink(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $membershipHandler = $this->createMock(XoopsMembershipHandler::class);
        $membershipHandler->expects($this->once())->method('getUsersByGroup')->with(2, 0, 0)->willReturn([7, 8]);
        $membershipHandler->expects($this->once())->method('getGroupsByUser')->with(10)->willReturn([2, 3]);

        $user1 = new XoopsUser();
        $user1->setVar('uid', 7);
        $user2 = new XoopsUser();
        $user2->setVar('uid', 8);
        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(Criteria::class), true)
            ->willReturn([7 => $user1, 8 => $user2]);

        $group1 = new XoopsGroup();
        $group1->setVar('groupid', 2);
        $group2 = new XoopsGroup();
        $group2->setVar('groupid', 3);
        $groupHandler = $this->createMock(XoopsGroupHandler::class);
        $groupHandler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(Criteria::class), true)
            ->willReturn([2 => $group1, 3 => $group2]);

        $this->injectHandler($handler, 'membershipHandler', $membershipHandler);
        $this->injectHandler($handler, 'userHandler', $userHandler);
        $this->injectHandler($handler, 'groupHandler', $groupHandler);

        $users = $handler->getUsersByGroup(2, true);
        $groups = $handler->getGroupsByUser(10, true);

        $this->assertSame([$user1, $user2], $users);
        $this->assertSame([$group1, $group2], $groups);
    }

    public function testLoginUserValidatesPassword(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $password = 'secret123';
        $user = new XoopsUser();
        $user->setVar('uid', 15);
        $user->setVar('uname', 'tester');
        $user->setVar('pass', password_hash($password, PASSWORD_DEFAULT));

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(Criteria::class), false)
            ->willReturn([$user]);

        $userHandler->expects($this->never())->method('insert');

        $this->injectHandler($handler, 'userHandler', $userHandler);

        $loggedIn = $handler->loginUser('tester', $password);
        $this->assertSame($user, $loggedIn);
    }

    public function testLoginUserFailsWhenNotUnique(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(Criteria::class), false)
            ->willReturn([]);

        $this->injectHandler($handler, 'userHandler', $userHandler);

        $this->assertFalse($handler->loginUser('tester', 'anything'));
    }

    public function testActivateUserAndCounts(): void
    {
        $db = $this->createDatabaseMock();
        $handler = new XoopsMemberHandler($db);

        $user = new XoopsUser();
        $user->setVar('uid', 22);
        $user->setVar('level', 0);
        $user->setVar('pass', 'legacy');

        $userHandler = $this->createMock(XoopsUserHandler::class);
        $userHandler->expects($this->once())->method('insert')->with($user, true)->willReturn(true);
        $userHandler->expects($this->once())->method('getCount')->with(null)->willReturn(4);

        $membershipHandler = $this->createMock(XoopsMembershipHandler::class);
        $membershipHandler->expects($this->once())
            ->method('getCount')
            ->with($this->isInstanceOf(Criteria::class))
            ->willReturn(2);

        $this->injectHandler($handler, 'userHandler', $userHandler);
        $this->injectHandler($handler, 'membershipHandler', $membershipHandler);

        $this->assertTrue($handler->activateUser($user));
        $this->assertSame(4, $handler->getUserCount());
        $this->assertSame(2, $handler->getUserCountByGroup(1));
    }

    private function getPropertyValue(object $target, string $property)
    {
        $ref = new ReflectionProperty($target, $property);
        $ref->setAccessible(true);
        return $ref->getValue($target);
    }
}
