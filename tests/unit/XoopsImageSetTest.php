<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/imageset.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsImageSetTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $imageSet = new XoopsImageSet();

        $this->assertNull($imageSet->getVar('imgset_id'));
        $this->assertNull($imageSet->getVar('imgset_name'));
        $this->assertSame(0, $imageSet->getVar('imgset_refid'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $imageSet = new XoopsImageSet();
        $imageSet->setVar('imgset_id', 11);
        $imageSet->setVar('imgset_name', 'Modern');
        $imageSet->setVar('imgset_refid', 2);

        $this->assertSame(11, $imageSet->id());
        $this->assertSame(11, $imageSet->imgset_id());
        $this->assertSame('Modern', $imageSet->imgset_name());
        $this->assertSame(2, $imageSet->imgset_refid());
    }
}

trait ImageSetDatabaseMockTrait
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

class XoopsImageSetHandlerTest extends TestCase
{
    use ImageSetDatabaseMockTrait;

    public function testCreateReturnsNewOrExistingSet(): void
    {
        $handler = new XoopsImageSetHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsImageSet::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesSetFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_imgset WHERE imgset_id=4')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'imgset_id'   => 4,
            'imgset_name' => 'Classic',
            'imgset_refid' => 7,
        ]);

        $handler = new XoopsImageSetHandler($database);
        $imageSet = $handler->get(4);

        $this->assertInstanceOf(XoopsImageSet::class, $imageSet);
        $this->assertSame('Classic', $imageSet->getVar('imgset_name'));
        $this->assertFalse($imageSet->isNew());
    }

    public function testInsertCreatesNewSet(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(21);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('INSERT INTO pref_imgset'))
            ->willReturn(true);

        $handler = new XoopsImageSetHandler($database);
        $imageSet = $handler->create();
        $imageSet->setVar('imgset_name', 'Modern');
        $imageSet->setVar('imgset_refid', 3);

        $this->assertTrue($handler->insert($imageSet));
        $this->assertSame(21, $imageSet->getVar('imgset_id'));
    }

    public function testInsertUpdatesExistingSet(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_imgset SET imgset_name ='))
            ->willReturn(true);

        $handler = new XoopsImageSetHandler($database);
        $imageSet = $handler->create(false);
        $imageSet->setNew(false);
        $imageSet->setVar('imgset_id', 6);
        $imageSet->setVar('imgset_name', 'Updated');
        $imageSet->setVar('imgset_refid', 4);

        $this->assertTrue($handler->insert($imageSet));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsImageSetHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesSet(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                ['DELETE FROM pref_imgset WHERE imgset_id = 6'],
                ['DELETE FROM pref_imgset_tplset_link WHERE imgset_id = 6']
            )
            ->willReturn(true);

        $handler = new XoopsImageSetHandler($database);
        $imageSet = $handler->create(false);
        $imageSet->setVar('imgset_id', 6);

        $this->assertTrue($handler->delete($imageSet));
    }

    public function testGetObjectsReturnsResults(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT DISTINCT i.* FROM pref_imgset i LEFT JOIN pref_imgset_tplset_link l ON l.imgset_id=i.imgset_id WHERE (imgset_id > 0)', 5, 1)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'imgset_id' => 1,
                'imgset_name' => 'Modern',
                'imgset_refid' => 2,
            ],
            false
        );

        $handler = new XoopsImageSetHandler($database);
        $criteria = new Criteria('imgset_id', 0, '>');
        $criteria->setLimit(5);
        $criteria->setStart(1);

        $sets = $handler->getObjects($criteria, true);

        $this->assertCount(1, $sets);
        $this->assertArrayHasKey(1, $sets);
        $this->assertSame('Modern', $sets[1]->getVar('imgset_name'));
    }

    public function testLinkAndUnlinkThemeset(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                ['DELETE FROM pref_imgset_tplset_link WHERE imgset_id = 3 AND tplset_name = \'default\''],
                ['INSERT INTO pref_imgset_tplset_link (imgset_id, tplset_name) VALUES (3, \'default\')']
            )
            ->willReturn(true);

        $handler = new XoopsImageSetHandler($database);

        $this->assertTrue($handler->linkThemeset(3, 'default'));
    }

    public function testLinkThemesetRejectsInvalidInput(): void
    {
        $handler = new XoopsImageSetHandler($this->createDatabaseMock());

        $this->assertFalse($handler->linkThemeset(0, ''));
    }

    public function testUnlinkThemeset(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_imgset_tplset_link WHERE imgset_id = 3 AND tplset_name = \'legacy\'')
            ->willReturn(true);

        $handler = new XoopsImageSetHandler($database);

        $this->assertTrue($handler->unlinkThemeset(3, 'legacy'));
    }

    public function testUnlinkThemesetRejectsInvalidInput(): void
    {
        $handler = new XoopsImageSetHandler($this->createDatabaseMock());

        $this->assertFalse($handler->unlinkThemeset(0, ''));
    }

    public function testGetListReturnsIdToNameMapping(): void
    {
        $setA = new XoopsImageSet();
        $setA->setVar('imgset_id', 1);
        $setA->setVar('imgset_name', 'Default');
        $setB = new XoopsImageSet();
        $setB->setVar('imgset_id', 2);
        $setB->setVar('imgset_name', 'Custom');

        $handler = $this->getMockBuilder(XoopsImageSetHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->method('getObjects')->willReturn([
            1 => $setA,
            2 => $setB,
        ]);

        $this->assertSame([
            1 => 'Default',
            2 => 'Custom',
        ], $handler->getList(5, 'default'));
    }
}
