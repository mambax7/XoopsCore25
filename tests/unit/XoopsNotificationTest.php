<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/notification.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';
require_once XOOPS_ROOT_PATH . '/class/criteria/compo.php';

class XoopsNotificationTest extends TestCase
{
    public function testConstructorInitializesVars(): void
    {
        $notification = new XoopsNotification();

        $this->assertNull($notification->getVar('not_id'));
        $this->assertNull($notification->getVar('not_modid'));
        $this->assertNull($notification->getVar('not_category'));
        $this->assertSame(0, $notification->getVar('not_itemid'));
        $this->assertNull($notification->getVar('not_event'));
        $this->assertSame(0, $notification->getVar('not_uid'));
        $this->assertSame(0, $notification->getVar('not_mode'));
    }

    public function testAccessorMethodsReturnValues(): void
    {
        $notification = new XoopsNotification();
        $notification->setVar('not_id', 5);
        $notification->setVar('not_modid', 7);
        $notification->setVar('not_category', 'cat');
        $notification->setVar('not_itemid', 11);
        $notification->setVar('not_event', 'event');
        $notification->setVar('not_uid', 13);
        $notification->setVar('not_mode', 3);

        $this->assertSame(5, $notification->id());
        $this->assertSame(5, $notification->not_id());
        $this->assertSame(7, $notification->not_modid());
        $this->assertSame('cat', $notification->not_category());
        $this->assertSame(11, $notification->not_itemid());
        $this->assertSame('event', $notification->not_event());
        $this->assertSame(13, $notification->not_uid());
        $this->assertSame(3, $notification->not_mode());
    }

    public function testNotifyUserSkipsInactiveUser(): void
    {
        $notification = new XoopsNotification();
        $notification->setVar('not_uid', 99);

        $memberHandler = new class {
            public function getUser($uid)
            {
                return new class {
                    public function isActive()
                    {
                        return false;
                    }
                };
            }
        };
        $GLOBALS['notification_test_member_handler'] = $memberHandler;

        $this->assertTrue($notification->notifyUser('dir', 'template', 'subject', []));
    }
}

trait NotificationDatabaseMockTrait
{
    protected function createDatabaseMock(): XoopsDatabase
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
                'escape',
                'fetchRow',
            ])
            ->getMock();
    }
}

class XoopsNotificationHandlerTest extends TestCase
{
    use NotificationDatabaseMockTrait;

    public function testCreateReturnsNotification(): void
    {
        $handler = new XoopsNotificationHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsNotification::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetReturnsNotificationWhenFound(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_xoopsnotifications');
        $database->expects($this->once())->method('query')->with($this->stringContains('WHERE not_id=5'))->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'not_id'       => 5,
            'not_modid'    => 3,
            'not_category' => 'cat',
            'not_itemid'   => 9,
            'not_event'    => 'event',
            'not_uid'      => 17,
            'not_mode'     => 1,
        ]);

        $handler      = new XoopsNotificationHandler($database);
        $notification = $handler->get(5);
        $this->assertInstanceOf(XoopsNotification::class, $notification);
        $this->assertSame(3, $notification->getVar('not_modid'));
        $this->assertFalse($notification->isNew());
    }

    public function testInsertCreatesNewNotification(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->method('genId')->willReturn(0);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->method('exec')->with($this->stringContains('INSERT INTO pref'))->willReturn(true);
        $database->method('getInsertId')->willReturn(10);

        $handler      = new XoopsNotificationHandler($database);
        $notification = $handler->create();
        $notification->setVar('not_modid', 2);
        $notification->setVar('not_itemid', 3);
        $notification->setVar('not_category', 'cat');
        $notification->setVar('not_uid', 5);
        $notification->setVar('not_event', 'event');
        $notification->setVar('not_mode', 1);

        $this->assertTrue($handler->insert($notification));
        $this->assertSame(10, $notification->getVar('not_id'));
    }

    public function testInsertUpdatesExistingNotification(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->method('exec')->with($this->stringContains('UPDATE pref'))->willReturn(true);

        $handler      = new XoopsNotificationHandler($database);
        $notification = $handler->create(false);
        $notification->assignVar('not_id', 4);
        $notification->setVar('not_modid', 2);
        $notification->setVar('not_itemid', 3);
        $notification->setVar('not_category', 'cat');
        $notification->setVar('not_uid', 5);
        $notification->setVar('not_event', 'event');
        $notification->setVar('not_mode', 2);

        $this->assertTrue($handler->insert($notification));
    }

    public function testDeleteRemovesNotification(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->expects($this->once())->method('exec')->with($this->stringContains('DELETE FROM pref'))
            ->willReturn(true);

        $handler      = new XoopsNotificationHandler($database);
        $notification = $handler->create(false);
        $notification->assignVar('not_id', 6);

        $this->assertTrue($handler->delete($notification));
    }

    public function testGetObjectsReturnsNotifications(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->expects($this->once())->method('query')->with($this->stringContains('SELECT * FROM pref'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'not_id'       => 1,
                'not_modid'    => 2,
                'not_category' => 'cat',
                'not_itemid'   => 3,
                'not_event'    => 'event',
                'not_uid'      => 4,
                'not_mode'     => 1,
            ],
            false
        );

        $criteria = $this->getMockBuilder(CriteriaElement::class)
            ->onlyMethods(['renderWhere', 'getSort', 'getOrder', 'getLimit', 'getStart'])
            ->getMockForAbstractClass();
        $criteria->method('renderWhere')->willReturn('WHERE 1=1');
        $criteria->method('getSort')->willReturn('not_id');
        $criteria->method('getOrder')->willReturn('ASC');
        $criteria->method('getLimit')->willReturn(5);
        $criteria->method('getStart')->willReturn(0);

        $handler = new XoopsNotificationHandler($database);
        $objects = $handler->getObjects($criteria, true);

        $this->assertCount(1, $objects);
        $this->assertArrayHasKey(1, $objects);
        $this->assertSame(2, $objects[1]->getVar('not_modid'));
    }

    public function testGetCountReturnsNumberOfNotifications(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->expects($this->once())->method('query')->with($this->stringContains('COUNT(*)'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([7]);

        $handler  = new XoopsNotificationHandler($database);
        $criteria = $this->getMockBuilder(CriteriaElement::class)
            ->onlyMethods(['renderWhere', 'getGroupby'])
            ->getMockForAbstractClass();
        $criteria->method('renderWhere')->willReturn('WHERE not_uid = 1');
        $criteria->method('getGroupby')->willReturn('');

        $this->assertSame(7, $handler->getCount($criteria));
    }

    public function testDeleteAllClearsRecords(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->expects($this->once())->method('exec')->with($this->stringContains('DELETE FROM pref'))
            ->willReturn(true);

        $handler  = new XoopsNotificationHandler($database);
        $criteria = $this->getMockBuilder(CriteriaElement::class)
            ->onlyMethods(['renderWhere'])
            ->getMockForAbstractClass();
        $criteria->method('renderWhere')->willReturn('WHERE not_uid = 1');

        $this->assertTrue($handler->deleteAll($criteria));
    }

    public function testGetNotificationReturnsSingleMatch(): void
    {
        $notification = new XoopsNotification();
        $handler      = new class($this->createDatabaseMock(), $notification) extends XoopsNotificationHandler {
            private $notification;

            public function __construct($db, $notification)
            {
                parent::__construct($db);
                $this->notification = $notification;
            }

            public function getObjects(?CriteriaElement $criteria = null, $id_as_key = false)
            {
                return [$this->notification];
            }
        };

        $result = $handler->getNotification(1, 'cat', 2, 'event', 3);

        $this->assertSame($notification, $result);
    }

    public function testIsSubscribedDelegatesToCount(): void
    {
        $handler = $this->getMockBuilder(XoopsNotificationHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getCount'])
            ->getMock();
        $handler->expects($this->once())->method('getCount')->with($this->callback(function ($criteria) {
            return $criteria instanceof CriteriaCompo;
        }))->willReturn(2);

        $this->assertSame(2, $handler->isSubscribed('cat', 1, 'event', 2, 3));
    }

    public function testSubscribeUpdatesExistingNotification(): void
    {
        $existing = new XoopsNotification();
        $existing->setVar('not_mode', 0);

        $handler = new class($this->createDatabaseMock(), $existing) extends XoopsNotificationHandler {
            public array $updated = [];

            private $existing;

            public function __construct($db, $existing)
            {
                parent::__construct($db);
                $this->existing = $existing;
            }

            public function &getNotification($module_id, $category, $item_id, $event, $user_id)
            {
                return $this->existing;
            }

            public function updateByField(XoopsNotification $notification, $field_name, $field_value)
            {
                $this->updated[] = [$field_name, $field_value];

                return true;
            }
        };

        $this->assertTrue($handler->subscribe('cat', 1, 'event', 2, 3, 4));
        $this->assertSame([['not_mode', 2]], $handler->updated);
    }

    public function testSubscribeInsertsWhenNotFound(): void
    {
        $handler = new class($this->createDatabaseMock()) extends XoopsNotificationHandler {
            public array $inserted = [];

            public function &getNotification($module_id, $category, $item_id, $event, $user_id)
            {
                $inst = false;

                return $inst;
            }

            public function insert(XoopsObject $object)
            {
                $this->inserted[] = $object;

                return true;
            }
        };

        $this->assertTrue($handler->subscribe('cat', 1, ['first', 'second'], 2, 3, 4));
        $this->assertCount(2, $handler->inserted);
        foreach ($handler->inserted as $object) {
            $this->assertInstanceOf(XoopsNotification::class, $object);
            $this->assertSame(3, $object->getVar('not_modid'));
        }
    }

    public function testGetByUserUsesCriteria(): void
    {
        $handler = new class($this->createDatabaseMock()) extends XoopsNotificationHandler {
            public $capturedCriteria;

            public function getObjects(?CriteriaElement $criteria = null, $id_as_key = false)
            {
                $this->capturedCriteria = $criteria;

                return ['item'];
            }
        };

        $result = $handler->getByUser(12);

        $this->assertSame('item', $result[0]);
        $this->assertInstanceOf(Criteria::class, $handler->capturedCriteria);
        $this->assertSame(12, $handler->capturedCriteria->value);
    }

    public function testGetSubscribedEventsReturnsEventList(): void
    {
        $handler = new class($this->createDatabaseMock()) extends XoopsNotificationHandler {
            public function getObjects(?CriteriaElement $criteria = null, $id_as_key = false)
            {
                $one = new XoopsNotification();
                $one->setVar('not_event', 'first');
                $two = new XoopsNotification();
                $two->setVar('not_event', 'second');

                return [1 => $one, 2 => $two];
            }
        };

        $events = $handler->getSubscribedEvents('cat', 1, 2, 3);

        $this->assertSame(['first', 'second'], $events);
    }

    public function testGetByItemIdReturnsObjects(): void
    {
        $handler = new class($this->createDatabaseMock()) extends XoopsNotificationHandler {
            public $capturedCriteria;

            public function getObjects(?CriteriaElement $criteria = null, $id_as_key = false)
            {
                $this->capturedCriteria = $criteria;

                return ['item'];
            }
        };

        $result = $handler->getByItemId(1, 2, 'DESC', 3);

        $this->assertSame(['item'], $result);
        $this->assertInstanceOf(CriteriaCompo::class, $handler->capturedCriteria);
    }

    public function testTriggerEventsDelegatesToTriggerEvent(): void
    {
        $handler = $this->getMockBuilder(XoopsNotificationHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['triggerEvent'])
            ->getMock();
        $handler->expects($this->exactly(2))->method('triggerEvent')
            ->withConsecutive(
                ['cat', 1, 'first', [], [], null, null],
                ['cat', 1, 'second', [], [], null, null]
            );

        $handler->triggerEvents('cat', 1, ['first', 'second']);
    }

    public function testUnsubscribeHelpersCallDeleteAll(): void
    {
        $handler = new class($this->createDatabaseMock()) extends XoopsNotificationHandler {
            public array $criteria = [];

            public function deleteAll(?CriteriaElement $criteria = null)
            {
                $this->criteria[] = $criteria;

                return true;
            }
        };

        $this->assertTrue($handler->unsubscribeByUser(5));
        $this->assertTrue($handler->unsubscribe('cat', 1, ['evt'], 2, 3));
        $this->assertTrue($handler->unsubscribeByModule(7));
        $this->assertTrue($handler->unsubscribeByItem(7, 'cat', 10));
        $this->assertCount(4, $handler->criteria);
    }

    public function testDoLoginMaintenanceUpdatesWaitingNotifications(): void
    {
        $handler = new class($this->createDatabaseMock()) extends XoopsNotificationHandler {
            public array $inserted = [];

            public function getObjects(?CriteriaElement $criteria = null, $id_as_key = false)
            {
                $waiting = new XoopsNotification();
                $waiting->setVar('not_mode', XOOPS_NOTIFICATION_MODE_WAITFORLOGIN);

                return [1 => $waiting];
            }

            public function insert(XoopsObject $notification)
            {
                $this->inserted[] = $notification;

                return true;
            }
        };

        $handler->doLoginMaintenance(3);

        $this->assertCount(1, $handler->inserted);
        $this->assertSame(XOOPS_NOTIFICATION_MODE_SENDONCETHENWAIT, $handler->inserted[0]->getVar('not_mode'));
    }
}

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return $GLOBALS['notification_test_' . $name . '_handler'] ?? null;
    }
}

if (!function_exists('xoops_getMailer')) {
    function xoops_getMailer()
    {
        return $GLOBALS['notification_test_mailer'] ?? null;
    }
}
