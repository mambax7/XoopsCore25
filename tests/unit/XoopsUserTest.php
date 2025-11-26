<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/user.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

if (!class_exists('MyTextSanitizer')) {
    class MyTextSanitizer
    {
        public static function getInstance()
        {
            return new self();
        }

        public function htmlSpecialChars($text)
        {
            return htmlspecialchars((string) $text, ENT_QUOTES);
        }
    }
}

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return $GLOBALS['user_test_handlers'][$name] ?? null;
    }
}

class XoopsUserTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['user_test_handlers'] = [];
        $GLOBALS['xoopsLogger']        = null;
        $GLOBALS['xoopsConfig']['anonymous'] = 'anonymous';

        if (!defined('XOOPS_URL')) {
            define('XOOPS_URL', 'https://xoops.example.com');
        }
    }

    public function testConstructorInitializesDefaults(): void
    {
        $user = new XoopsUser();

        $this->assertNull($user->getVar('uid'));
        $this->assertNull($user->getVar('uname'));
        $this->assertNull($user->getVar('email'));
        $this->assertSame(0, $user->getVar('user_viewemail'));
        $this->assertSame(0, $user->getVar('attachsig'));
        $this->assertSame(0, $user->getVar('rank'));
        $this->assertSame(0, $user->getVar('level'));
        $this->assertSame('0.0', $user->getVar('timezone_offset'));
        $this->assertSame(0, $user->getVar('last_login'));
        $this->assertSame(1, $user->getVar('uorder'));
        $this->assertSame(XOOPS_NOTIFICATION_METHOD_PM, $user->getVar('notify_method'));
        $this->assertSame(XOOPS_NOTIFICATION_MODE_SENDALWAYS, $user->getVar('notify_mode'));
        $this->assertSame(1, $user->getVar('user_mailok'));
    }

    public function testConstructorAssignsArrayValues(): void
    {
        $user = new XoopsUser([
            'uid'   => 42,
            'uname' => 'tester',
            'email' => 'test@example.com',
        ]);

        $this->assertSame(42, $user->getVar('uid'));
        $this->assertSame('tester', $user->getVar('uname'));
        $this->assertSame('test@example.com', $user->getVar('email'));
    }

    public function testIsGuestAndGroups(): void
    {
        $user = new XoopsUser();
        $user->setGroups([1, 2]);

        $groups = $user->getGroups();
        $this->assertSame([1, 2], $groups);
        $this->assertSame($groups, $user->groups());
        $this->assertFalse($user->isGuest());

        $guest = new XoopsGuestUser();
        $this->assertTrue($guest->isGuest());
    }

    public function testIsActiveAndOnline(): void
    {
        $onlineHandler = $this->getMockBuilder(stdClass::class)
            ->addMethods(['getCount'])
            ->getMock();
        $GLOBALS['user_test_handlers']['online'] = $onlineHandler;

        $user = new XoopsUser();
        $user->setVar('uid', 5);
        $user->setVar('level', 0);

        $onlineHandler->expects($this->once())
            ->method('getCount')
            ->with($this->callback(static function ($criteria) {
                return $criteria instanceof Criteria && $criteria->column === 'online_uid' && $criteria->value === 5;
            }))
            ->willReturn(1);

        $this->assertFalse($user->isActive());
        $this->assertTrue($user->isOnline());

        $user->setVar('level', 1);
        $this->assertTrue($user->isActive());
    }

    public function testIsAdminUsesGroupPermissionHandler(): void
    {
        $groupPermHandler = $this->getMockBuilder(stdClass::class)
            ->addMethods(['checkRight'])
            ->getMock();
        $GLOBALS['user_test_handlers']['groupperm'] = $groupPermHandler;

        $user = new XoopsUser();
        $user->setGroups([3, 4]);

        $groupPermHandler->expects($this->once())
            ->method('checkRight')
            ->with('module_admin', 1, [3, 4])
            ->willReturn(true);

        $this->assertTrue($user->isAdmin());
    }

    public function testGetUnameFromIdReturnsFormattedUserName(): void
    {
        $memberHandler                           = new class {
            public function getUser($uid)
            {
                $user = new XoopsUser();
                $user->setVar('uid', $uid);
                $user->setVar('uname', 'user' . $uid);
                $user->setVar('name', 'Real & Name');

                return $user;
            }
        };
        $GLOBALS['user_test_handlers']['member'] = $memberHandler;

        $this->assertSame('Real &amp; Name', XoopsUser::getUnameFromId(7, 1, false));
        $this->assertSame('user7', XoopsUser::getUnameFromId(7, 0, false));
        $this->assertSame('<a href="https://xoops.example.com/userinfo.php?uid=7" title="user7">user7</a>', XoopsUser::getUnameFromId(7, 0, true));
    }

    public function testGetUnameFromIdFallsBackToAnonymous(): void
    {
        $GLOBALS['user_test_handlers']['member'] = new class {
            public function getUser($uid)
            {
                return null;
            }
        };

        $this->assertSame('anonymous', XoopsUser::getUnameFromId(0));
    }

    public function testIncrementPostDelegatesToMemberHandler(): void
    {
        $calls = [];
        $GLOBALS['user_test_handlers']['member'] = new class($calls) {
            public array $calls;

            public function __construct(& $calls)
            {
                $this->calls = & $calls;
            }

            public function updateUserByField(...$args)
            {
                $this->calls[] = $args;

                return 'updated';
            }
        };

        $user = new XoopsUser();
        $user->setVar('posts', 3);

        $this->assertSame('updated', $user->incrementPost());
        $this->assertSame([['posts', 4]], $GLOBALS['user_test_handlers']['member']->calls);
    }
}

class XoopsUserHandlerTest extends TestCase
{
    public function testDeprecatedMethodsLogAndReturnFalse(): void
    {
        $db = $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prefix'])
            ->getMock();
        $db->method('prefix')->willReturn('pref_users');

        $handler = new class($db) extends XoopsUserHandler {
            public function __construct($db)
            {
                XoopsObjectHandler::__construct($db);
                $this->table         = $db->prefix('users');
                $this->keyName       = 'uid';
                $this->className     = 'XoopsUser';
                $this->identifierName = 'uname';
            }
        };

        $logger = new class {
            public array $deprecated = [];

            public function addDeprecated($message)
            {
                $this->deprecated[] = $message;
            }
        };
        $GLOBALS['xoopsLogger'] = $logger;

        $this->assertFalse($handler->loginUser('name', 'pw', true));
        $this->assertFalse($handler->updateUserByField('field', 'value', 1));
        $this->assertCount(2, $logger->deprecated);
        $this->assertStringContainsString('loginUser', $logger->deprecated[0]);
        $this->assertStringContainsString('updateUserByField', $logger->deprecated[1]);
    }
}
