<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/online.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';
require_once XOOPS_ROOT_PATH . '/class/criteria/compo.php';

class XoopsOnlineHandlerTest extends TestCase
{
    public function testConstructorSetsTablePrefix(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->with('online')->willReturn('pref_online');

        $handler = new XoopsOnlineHandler($database);

        $this->assertSame('pref_online', $handler->table);
    }

    public function testWriteUpdatesExistingRecord(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");
        $database->expects($this->once())->method('queryF')->with($this->stringContains('WHERE online_uid=5'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([1]);
        $database->expects($this->once())->method('exec')->with($this->stringContains('UPDATE pref_online'))
            ->willReturn(true);

        $handler = new XoopsOnlineHandler($database);

        $this->assertTrue($handler->write(5, 'user', 123, 9, '127.0.0.1'));
    }

    public function testWriteInsertsNewGuestRecord(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");
        $database->expects($this->once())->method('queryF')
            ->with($this->stringContains('online_uid=0 AND online_ip'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([0]);
        $database->expects($this->once())->method('exec')
            ->with($this->stringContains('INSERT INTO pref_online'))
            ->willReturn(true);

        $handler = new XoopsOnlineHandler($database);

        $this->assertTrue($handler->write(0, 'guest', 123, 1, '8.8.8.8'));
    }

    public function testWriteCleansGuestRowWhenUserSignsIn(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");
        $database->expects($this->once())->method('queryF')
            ->with($this->stringContains('WHERE online_uid=7'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([0]);
        $database->expects($this->exactly(2))->method('exec')->withConsecutive(
            [$this->stringContains('DELETE FROM pref_online WHERE online_uid = 0')],
            [$this->stringContains('INSERT INTO pref_online')]
        )->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsOnlineHandler($database);

        $this->assertTrue($handler->write(7, 'member', 200, 3, '10.0.0.1'));
    }

    public function testDestroyRemovesUserEntries(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->expects($this->once())->method('exec')
            ->with($this->stringContains('DELETE FROM pref_online WHERE online_uid = 11'))
            ->willReturn(true);

        $handler = new XoopsOnlineHandler($database);

        $this->assertTrue($handler->destroy(11));
    }

    public function testGcDeletesExpiredEntries(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->expects($this->once())->method('exec')
            ->with($this->stringContains('online_updated < '));

        $handler = new XoopsOnlineHandler($database);

        $handler->gc(100);
    }

    public function testGetAllReturnsOnlineRows(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->expects($this->once())->method('query')->with($this->stringContains('SELECT * FROM pref_online'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(['id' => 1], ['id' => 2], false);

        $handler = new XoopsOnlineHandler($database);

        $this->assertSame([
            ['id' => 1],
            ['id' => 2],
        ], $handler->getAll());
    }

    public function testGetAllAppliesCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->expects($this->once())->method('query')
            ->with($this->stringContains('WHERE (`online_uid` = 3)'), 5, 2)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturn(false);

        $criteria = new CriteriaCompo();
        $criteria->add(new Criteria('online_uid', 3));
        $criteria->setLimit(5);
        $criteria->setStart(2);

        $handler = new XoopsOnlineHandler($database);

        $this->assertSame([], $handler->getAll($criteria));
    }

    public function testGetCountReturnsRowCount(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->expects($this->once())->method('query')->with($this->stringContains('SELECT COUNT(*) FROM pref_online'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([4]);

        $handler = new XoopsOnlineHandler($database);

        $this->assertSame(4, $handler->getCount());
    }

    public function testGetCountReturnsZeroWhenQueryFails(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_online');
        $database->method('isResultSet')->willReturn(false);
        $database->expects($this->once())->method('query')->with($this->stringContains('SELECT COUNT(*) FROM pref_online'))
            ->willReturn(false);

        $handler = new XoopsOnlineHandler($database);

        $this->assertSame(0, $handler->getCount());
    }

    private function createDatabaseMock(): XoopsDatabase
    {
        return $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'prefix',
                'quote',
                'queryF',
                'isResultSet',
                'fetchRow',
                'exec',
                'query',
                'fetchArray',
            ])
            ->getMock();
    }
}
