<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/avatar.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsAvatarTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $avatar = new XoopsAvatar();

        $this->assertNull($avatar->getVar('avatar_id'));
        $this->assertSame(1, $avatar->getVar('avatar_display'));
        $this->assertSame(0, $avatar->getVar('avatar_weight'));
        $this->assertSame(0, $avatar->getVar('avatar_type'));
    }

    public function testUserCountAccessorsCastToInt(): void
    {
        $avatar = new XoopsAvatar();
        $avatar->setUserCount(7.9);

        $this->assertSame(7, $avatar->getUserCount());
    }

    public function testIdHelperReturnsStoredValue(): void
    {
        $avatar = new XoopsAvatar();
        $avatar->setVar('avatar_id', 12);

        $this->assertSame(12, $avatar->id());
    }
}

class XoopsAvatarHandlerTest extends TestCase
{
    public function testCreateReturnsFreshOrExistingObjects(): void
    {
        $database = $this->createDatabaseMock();
        $handler  = new XoopsAvatarHandler($database);

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsAvatar::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetLoadsAvatarFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_avatar WHERE avatar_id=7')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'avatar_id'       => 7,
            'avatar_file'     => 'avatar.png',
            'avatar_name'     => 'Example Avatar',
            'avatar_mimetype' => 'image/png',
            'avatar_created'  => 123,
            'avatar_display'  => 1,
            'avatar_weight'   => 2,
            'avatar_type'     => 'S',
        ]);

        $handler = new XoopsAvatarHandler($database);
        $avatar  = $handler->get(7);

        $this->assertInstanceOf(XoopsAvatar::class, $avatar);
        $this->assertSame('avatar.png', $avatar->getVar('avatar_file'));
        $this->assertSame('Example Avatar', $avatar->getVar('avatar_name'));
        $this->assertFalse($avatar->isNew());
    }

    public function testInsertAssignsIdentifierToNewAvatar(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(fn($value) => "'{$value}'");
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(21);
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(function ($sql) {
                return strpos($sql, 'INSERT INTO pref_avatar') !== false
                    && strpos($sql, "'avatar.png'") !== false
                    && strpos($sql, "'Avatar Name'") !== false;
            }))
            ->willReturn(true);

        $handler = new XoopsAvatarHandler($database);

        $avatar = new XoopsAvatar();
        $avatar->setVar('avatar_file', 'avatar.png');
        $avatar->setVar('avatar_name', 'Avatar Name');
        $avatar->setVar('avatar_mimetype', 'image/png');
        $avatar->setVar('avatar_display', 1);
        $avatar->setVar('avatar_weight', 3);
        $avatar->setVar('avatar_type', 'S');
        $avatar->setDirty();
        $avatar->setNew();

        $this->assertTrue($handler->insert($avatar));
        $this->assertSame(21, $avatar->getVar('avatar_id'));
    }

    public function testDeleteExecutesCleanupQueries(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                ['DELETE FROM pref_avatar WHERE avatar_id = 5'],
                ['DELETE FROM pref_avatar_user_link WHERE avatar_id = 5']
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsAvatarHandler($database);
        $avatar  = new XoopsAvatar();
        $avatar->setVar('avatar_id', 5);

        $this->assertTrue($handler->delete($avatar));
    }

    public function testGetObjectsReturnsListOfAvatars(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls([
            'avatar_id'       => 11,
            'avatar_file'     => 'first.png',
            'avatar_name'     => 'First',
            'avatar_mimetype' => 'image/png',
            'avatar_created'  => 456,
            'avatar_display'  => 1,
            'avatar_weight'   => 4,
            'avatar_type'     => 'S',
            'count'           => 2,
        ], false);

        $handler = new XoopsAvatarHandler($database);
        $results = $handler->getObjects();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(XoopsAvatar::class, $results[0]);
        $this->assertSame(2, $results[0]->getUserCount());
    }

    public function testGetCountReturnsRowCount(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT COUNT(*) FROM pref_avatar')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([4]);

        $handler = new XoopsAvatarHandler($database);

        $this->assertSame(4, $handler->getCount());
    }

    public function testAddUserReplacesExistingEntries(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                ['DELETE FROM pref_avatar_user_link WHERE user_id = 9'],
                ['INSERT INTO pref_avatar_user_link (avatar_id, user_id) VALUES (7, 9)']
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsAvatarHandler($database);

        $this->assertTrue($handler->addUser(7, 9));
    }

    public function testGetUserReturnsLinkedIdentifiers(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT user_id FROM pref_avatar_user_link WHERE avatar_id=13')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(['user_id' => 3], false);

        $handler = new XoopsAvatarHandler($database);
        $avatar  = new XoopsAvatar();
        $avatar->setVar('avatar_id', 13);

        $this->assertSame([3], $handler->getUser($avatar));
    }

    public function testGetListUsesCriteriaToFilterAvatars(): void
    {
        if (!defined('_NONE')) {
            define('_NONE', '_NONE');
        }

        $database = $this->createDatabaseMock();
        $handler  = $this->getMockBuilder(XoopsAvatarHandler::class)
            ->setConstructorArgs([$database])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $avatar = new XoopsAvatar();
        $avatar->setVar('avatar_id', 2);
        $avatar->setVar('avatar_file', 'custom.png');
        $avatar->setVar('avatar_name', 'Custom');

        $handler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(CriteriaCompo::class), true)
            ->willReturn([
                2 => $avatar,
            ]);

        $list = $handler->getList('C', true);

        $this->assertSame([
            'blank.gif' => _NONE,
            'custom.png' => 'Custom',
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
