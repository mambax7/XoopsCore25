<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/image.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsImageTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $image = new XoopsImage();

        $this->assertNull($image->getVar('image_id'));
        $this->assertNull($image->getVar('image_name'));
        $this->assertNull($image->getVar('image_nicename'));
        $this->assertNull($image->getVar('image_mimetype'));
        $this->assertNull($image->getVar('image_created'));
        $this->assertSame(1, $image->getVar('image_display'));
        $this->assertSame(0, $image->getVar('image_weight'));
        $this->assertNull($image->getVar('image_body'));
        $this->assertSame(0, $image->getVar('imgcat_id'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $image = new XoopsImage();
        $image->setVar('image_id', 7);
        $image->setVar('image_name', 'logo.png');
        $image->setVar('image_nicename', 'Logo');
        $image->setVar('image_mimetype', 'image/png');
        $image->setVar('image_created', 123);
        $image->setVar('image_display', 0);
        $image->setVar('image_weight', 3);
        $image->setVar('image_body', 'bin');
        $image->setVar('imgcat_id', 2);

        $this->assertSame(7, $image->id());
        $this->assertSame(7, $image->image_id());
        $this->assertSame('logo.png', $image->image_name());
        $this->assertSame('Logo', $image->image_nicename());
        $this->assertSame('image/png', $image->image_mimetype());
        $this->assertSame(123, $image->image_created());
        $this->assertSame(0, $image->image_display());
        $this->assertSame(3, $image->image_weight());
        $this->assertSame('bin', $image->image_body());
        $this->assertSame(2, $image->imgcat_id());
    }
}

trait ImageDatabaseMockTrait
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

class XoopsImageHandlerTest extends TestCase
{
    use ImageDatabaseMockTrait;

    public function testCreateReturnsNewOrExistingImage(): void
    {
        $handler = new XoopsImageHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsImage::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesImageWithBody(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT i.*, b.image_body FROM pref_image i LEFT JOIN pref_imagebody b ON b.image_id=i.image_id WHERE i.image_id=5')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'image_id'        => 5,
            'image_name'      => 'file.png',
            'image_nicename'  => 'File',
            'image_mimetype'  => 'image/png',
            'image_created'   => 123,
            'image_display'   => 1,
            'image_weight'    => 0,
            'image_body'      => 'data',
            'imgcat_id'       => 9,
        ]);

        $handler = new XoopsImageHandler($database);
        $image   = $handler->get(5);

        $this->assertInstanceOf(XoopsImage::class, $image);
        $this->assertSame('file.png', $image->getVar('image_name'));
        $this->assertFalse($image->isNew());
    }

    public function testInsertCreatesNewImage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(11);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                [$this->stringContains('INSERT INTO pref_image')],
                [$this->stringContains('INSERT INTO pref_imagebody')]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsImageHandler($database);

        $image = $handler->create();
        $image->setVar('image_name', 'new.png');
        $image->setVar('image_nicename', 'New');
        $image->setVar('image_mimetype', 'image/png');
        $image->setVar('image_display', 1);
        $image->setVar('image_weight', 0);
        $image->setVar('image_body', 'abc');
        $image->setVar('imgcat_id', 1);

        $this->assertTrue($handler->insert($image));
        $this->assertSame(11, $image->getVar('image_id'));
    }

    public function testInsertUpdatesExistingImage(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                [$this->stringContains('UPDATE pref_image SET')],
                [$this->stringContains('UPDATE pref_imagebody SET')]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsImageHandler($database);

        $image = $handler->create(false);
        $image->setNew(false);
        $image->setVar('image_id', 4);
        $image->setVar('image_name', 'update.png');
        $image->setVar('image_nicename', 'Update');
        $image->setVar('image_display', 1);
        $image->setVar('image_weight', 2);
        $image->setVar('image_body', 'def');
        $image->setVar('imgcat_id', 3);

        $this->assertTrue($handler->insert($image));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsImageHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesImageAndBody(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                ['DELETE FROM pref_image WHERE image_id = 8'],
                ['DELETE FROM pref_imagebody WHERE image_id = 8']
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsImageHandler($database);

        $image = $handler->create(false);
        $image->setVar('image_id', 8);

        $this->assertTrue($handler->delete($image));
    }

    public function testGetObjectsReturnsImagesWithCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT i.*, b.image_body FROM pref_image i LEFT JOIN pref_imagebody b ON b.image_id=i.image_id WHERE (image_id > 0) ORDER BY image_weight ASC', 5, 2)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'image_id'        => 1,
                'image_name'      => 'one.png',
                'image_nicename'  => 'One',
                'image_mimetype'  => 'image/png',
                'image_display'   => 1,
                'image_weight'    => 0,
                'imgcat_id'       => 1,
                'image_body'      => 'body',
            ],
            false
        );

        $handler  = new XoopsImageHandler($database);
        $criteria = new Criteria('image_id', 0, '>');
        $criteria->setLimit(5);
        $criteria->setStart(2);

        $images = $handler->getObjects($criteria, true, true);

        $this->assertCount(1, $images);
        $this->assertArrayHasKey(1, $images);
        $this->assertSame('One', $images[1]->getVar('image_nicename'));
    }

    public function testGetCountReturnsValue(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT COUNT(*) FROM pref_image WHERE (imgcat_id = 2)')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([3]);

        $handler  = new XoopsImageHandler($database);
        $criteria = new Criteria('imgcat_id', 2);

        $this->assertSame(3, $handler->getCount($criteria));
    }

    public function testGetListReturnsNameToNicenameMapping(): void
    {
        $imageA = new XoopsImage();
        $imageA->setVar('image_name', 'a');
        $imageA->setVar('image_nicename', 'Alpha');
        $imageB = new XoopsImage();
        $imageB->setVar('image_name', 'b');
        $imageB->setVar('image_nicename', 'Beta');

        $handler = $this->getMockBuilder(XoopsImageHandler::class)
            ->setConstructorArgs([$this->createDatabaseMock()])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->method('getObjects')->willReturn([$imageA, $imageB]);

        $list = $handler->getList(1, 1);

        $this->assertSame(['a' => 'Alpha', 'b' => 'Beta'], $list);
    }
}
