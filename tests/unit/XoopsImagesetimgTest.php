<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/imagesetimg.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsImagesetimgTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $image = new XoopsImagesetimg();

        $this->assertNull($image->getVar('imgsetimg_id'));
        $this->assertNull($image->getVar('imgsetimg_file'));
        $this->assertNull($image->getVar('imgsetimg_body'));
        $this->assertNull($image->getVar('imgsetimg_imgset'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $image = new XoopsImagesetimg();
        $image->setVar('imgsetimg_id', 12);
        $image->setVar('imgsetimg_file', 'logo.png');
        $image->setVar('imgsetimg_body', 'binary-data');
        $image->setVar('imgsetimg_imgset', 7);

        $this->assertSame(12, $image->id());
        $this->assertSame(12, $image->imgsetimg_id());
        $this->assertSame('logo.png', $image->imgsetimg_file());
        $this->assertSame('binary-data', $image->imgsetimg_body());
        $this->assertSame(7, $image->imgsetimg_imgset());
    }
}

trait ImagesetimgDatabaseMockTrait
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

class XoopsImagesetimgHandlerTest extends TestCase
{
    use ImagesetimgDatabaseMockTrait;

    public function testCreateReturnsNewOrExistingImage(): void
    {
        $handler = new XoopsImagesetimgHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsImagesetimg::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesImageFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_imgsetimg WHERE imgsetimg_id=5')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'imgsetimg_id' => 5,
            'imgsetimg_file' => 'icon.gif',
            'imgsetimg_body' => 'binary',
            'imgsetimg_imgset' => 2,
        ]);

        $handler = new XoopsImagesetimgHandler($database);
        $image = $handler->get(5);

        $this->assertInstanceOf(XoopsImagesetimg::class, $image);
        $this->assertSame('icon.gif', $image->getVar('imgsetimg_file'));
        $this->assertFalse($image->isNew());
    }

    public function testInsertCreatesNewImage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(30);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('INSERT INTO pref_imgsetimg'))
            ->willReturn(true);

        $handler = new XoopsImagesetimgHandler($database);
        $image = $handler->create();
        $image->setVar('imgsetimg_file', 'background.jpg');
        $image->setVar('imgsetimg_body', 'data');
        $image->setVar('imgsetimg_imgset', 8);

        $this->assertTrue($handler->insert($image));
        $this->assertSame(30, $image->getVar('imgsetimg_id'));
    }

    public function testInsertUpdatesExistingImage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_imgsetimg SET imgsetimg_file ='))
            ->willReturn(true);

        $handler = new XoopsImagesetimgHandler($database);
        $image = $handler->create(false);
        $image->setNew(false);
        $image->setVar('imgsetimg_id', 9);
        $image->setVar('imgsetimg_file', 'updated.gif');
        $image->setVar('imgsetimg_body', 'content');
        $image->setVar('imgsetimg_imgset', 4);

        $this->assertTrue($handler->insert($image));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsImagesetimgHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesImage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_imgsetimg WHERE imgsetimg_id = 10')
            ->willReturn(true);

        $handler = new XoopsImagesetimgHandler($database);
        $image = $handler->create(false);
        $image->setVar('imgsetimg_id', 10);

        $this->assertTrue($handler->delete($image));
    }

    public function testGetObjectsReturnsResults(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT DISTINCT i.* FROM pref_imgsetimg i LEFT JOIN pref_imgset_tplset_link l ON l.imgset_id=i.imgsetimg_imgset LEFT JOIN pref_imgset s ON s.imgset_id=l.imgset_id WHERE(imgsetimg_imgset > 0) ORDER BY imgsetimg_id ASC', 3, 2)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'imgsetimg_id' => 1,
                'imgsetimg_file' => 'logo.png',
                'imgsetimg_body' => 'content',
                'imgsetimg_imgset' => 3,
            ],
            false
        );

        $handler = new XoopsImagesetimgHandler($database);
        $criteria = new Criteria('imgsetimg_imgset', 0, '>');
        $criteria->setLimit(3);
        $criteria->setStart(2);

        $images = $handler->getObjects($criteria, true);

        $this->assertCount(1, $images);
        $this->assertArrayHasKey(1, $images);
        $this->assertSame('logo.png', $images[1]->getVar('imgsetimg_file'));
    }

    public function testGetCountReturnsNumberOfImages(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT COUNT(i.imgsetimg_id) FROM pref_imgsetimg i LEFT JOIN pref_imgset_tplset_link l ON l.imgset_id=i.imgsetimg_imgset')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([5]);

        $handler = new XoopsImagesetimgHandler($database);

        $this->assertSame(5, $handler->getCount());
    }

    public function testGetByImagesetDelegatesToGetObjects(): void
    {
        $handler = $this->getMockBuilder(XoopsImagesetimgHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $expected = ['result'];
        $handler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(Criteria::class), false)
            ->willReturn($expected);

        $this->assertSame($expected, $handler->getByImageset(4));
    }

    public function testImageExistsUsesCount(): void
    {
        $handler = $this->getMockBuilder(XoopsImagesetimgHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getCount'])
            ->getMock();

        $handler->expects($this->once())
            ->method('getCount')
            ->with($this->isInstanceOf(CriteriaCompo::class))
            ->willReturn(2);

        $this->assertTrue($handler->imageExists('logo.png', 7));
    }
}
