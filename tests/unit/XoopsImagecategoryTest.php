<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/imagecategory.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsImagecategoryTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $category = new XoopsImagecategory();

        $this->assertNull($category->getVar('imgcat_id'));
        $this->assertNull($category->getVar('imgcat_name'));
        $this->assertSame(1, $category->getVar('imgcat_display'));
        $this->assertSame(0, $category->getVar('imgcat_weight'));
        $this->assertSame(0, $category->getVar('imgcat_maxsize'));
        $this->assertSame(0, $category->getVar('imgcat_maxwidth'));
        $this->assertSame(0, $category->getVar('imgcat_maxheight'));
        $this->assertNull($category->getVar('imgcat_type'));
        $this->assertNull($category->getVar('imgcat_storetype'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $category = new XoopsImagecategory();
        $category->setVar('imgcat_id', 5);
        $category->setVar('imgcat_name', 'Icons');
        $category->setVar('imgcat_display', 0);
        $category->setVar('imgcat_weight', 2);
        $category->setVar('imgcat_maxsize', 1024);
        $category->setVar('imgcat_maxwidth', 80);
        $category->setVar('imgcat_maxheight', 60);
        $category->setVar('imgcat_type', 'C');
        $category->setVar('imgcat_storetype', 'db');

        $this->assertSame(5, $category->id());
        $this->assertSame(5, $category->imgcat_id());
        $this->assertSame('Icons', $category->imgcat_name());
        $this->assertSame(0, $category->imgcat_display());
        $this->assertSame(2, $category->imgcat_weight());
        $this->assertSame(1024, $category->imgcat_maxsize());
        $this->assertSame(80, $category->imgcat_maxwidth());
        $this->assertSame(60, $category->imgcat_maxheight());
        $this->assertSame('C', $category->imgcat_type());
        $this->assertSame('db', $category->imgcat_storetype());
    }

    public function testImageCountHelpers(): void
    {
        $category = new XoopsImagecategory();
        $category->setImageCount(7);

        $this->assertSame(7, $category->getImageCount());
    }
}

trait ImageCategoryDatabaseMockTrait
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

class XoopsImagecategoryHandlerTest extends TestCase
{
    use ImageCategoryDatabaseMockTrait;

    public function testCreateReturnsNewOrExistingCategory(): void
    {
        $handler = new XoopsImagecategoryHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsImagecategory::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesCategoryFromDatabase(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_imagecategory WHERE imgcat_id=3')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'imgcat_id'        => 3,
            'imgcat_name'      => 'Banners',
            'imgcat_display'   => 1,
            'imgcat_weight'    => 0,
            'imgcat_maxsize'   => 2000,
            'imgcat_maxwidth'  => 400,
            'imgcat_maxheight' => 300,
            'imgcat_type'      => 'A',
            'imgcat_storetype' => 'db',
        ]);

        $handler  = new XoopsImagecategoryHandler($database);
        $category = $handler->get(3);

        $this->assertInstanceOf(XoopsImagecategory::class, $category);
        $this->assertSame('Banners', $category->getVar('imgcat_name'));
        $this->assertFalse($category->isNew());
    }

    public function testInsertCreatesNewCategory(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(15);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(static function ($sql) {
                return str_contains($sql, 'INSERT INTO pref_imagecategory')
                    && str_contains($sql, "'Icons'")
                    && str_contains($sql, 'imgcat_display')
                    && str_contains($sql, 'imgcat_weight');
            }))
            ->willReturn(true);

        $handler  = new XoopsImagecategoryHandler($database);
        $category = $handler->create();
        $category->setVar('imgcat_name', 'Icons');
        $category->setVar('imgcat_display', 1);
        $category->setVar('imgcat_weight', 5);
        $category->setVar('imgcat_maxsize', 1024);
        $category->setVar('imgcat_maxwidth', 80);
        $category->setVar('imgcat_maxheight', 60);
        $category->setVar('imgcat_type', 'C');
        $category->setVar('imgcat_storetype', 'db');

        $this->assertTrue($handler->insert($category));
        $this->assertSame(15, $category->getVar('imgcat_id'));
    }

    public function testInsertUpdatesExistingCategory(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_imagecategory SET imgcat_name ='))
            ->willReturn(true);

        $handler  = new XoopsImagecategoryHandler($database);
        $category = $handler->create(false);
        $category->setNew(false);
        $category->setVar('imgcat_id', 9);
        $category->setVar('imgcat_name', 'Updated');
        $category->setVar('imgcat_display', 0);
        $category->setVar('imgcat_weight', 2);
        $category->setVar('imgcat_maxsize', 0);
        $category->setVar('imgcat_maxwidth', 0);
        $category->setVar('imgcat_maxheight', 0);
        $category->setVar('imgcat_type', 'S');
        $category->setVar('imgcat_storetype', 'db');

        $this->assertTrue($handler->insert($category));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsImagecategoryHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testDeleteRemovesCategory(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_imagecategory WHERE imgcat_id = 4')
            ->willReturn(true);

        $handler  = new XoopsImagecategoryHandler($database);
        $category = $handler->create(false);
        $category->setVar('imgcat_id', 4);

        $this->assertTrue($handler->delete($category));
    }

    public function testGetObjectsBuildsCriteriaAndReturnsCategories(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT DISTINCT c.* FROM pref_imagecategory c LEFT JOIN pref_group_permission l ON l.gperm_itemid=c.imgcat_id WHERE (l.gperm_name = 'imgcat_read' OR l.gperm_name = 'imgcat_write') AND (imgcat_display = 1) ORDER BY imgcat_weight, imgcat_id ASC", 3, 2)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'imgcat_id'        => 1,
                'imgcat_name'      => 'Avatars',
                'imgcat_display'   => 1,
                'imgcat_weight'    => 0,
                'imgcat_maxsize'   => 0,
                'imgcat_maxwidth'  => 0,
                'imgcat_maxheight' => 0,
                'imgcat_type'      => 'C',
                'imgcat_storetype' => 'db',
            ],
            [
                'imgcat_id'        => 2,
                'imgcat_name'      => 'Icons',
                'imgcat_display'   => 1,
                'imgcat_weight'    => 1,
                'imgcat_maxsize'   => 0,
                'imgcat_maxwidth'  => 0,
                'imgcat_maxheight' => 0,
                'imgcat_type'      => 'C',
                'imgcat_storetype' => 'file',
            ],
            false
        );

        $handler  = new XoopsImagecategoryHandler($database);
        $criteria = new Criteria('imgcat_display', 1);
        $criteria->setLimit(3);
        $criteria->setStart(2);

        $categories = $handler->getObjects($criteria);

        $this->assertCount(2, $categories);
        $this->assertSame('Avatars', $categories[0]->getVar('imgcat_name'));
        $this->assertSame('Icons', $categories[1]->getVar('imgcat_name'));
    }

    public function testGetCountUsesCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT COUNT(*) FROM pref_imagecategory i LEFT JOIN pref_group_permission l ON l.gperm_itemid=i.imgcat_id WHERE (l.gperm_name = 'imgcat_read' OR l.gperm_name = 'imgcat_write') AND (imgcat_display = 1)")
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([4]);

        $handler  = new XoopsImagecategoryHandler($database);
        $criteria = new Criteria('imgcat_display', 1);

        $this->assertSame(4, $handler->getCount($criteria));
    }

    public function testGetListBuildsCriteriaAndReturnsNames(): void
    {
        $database = $this->createDatabaseMock();
        $handler  = $this->getMockBuilder(XoopsImagecategoryHandler::class)
            ->onlyMethods(['getObjects'])
            ->setConstructorArgs([$database])
            ->getMock();

        $categoryOne = new XoopsImagecategory();
        $categoryOne->assignVar('imgcat_id', 1);
        $categoryOne->assignVar('imgcat_name', 'Icons');

        $categoryTwo = new XoopsImagecategory();
        $categoryTwo->assignVar('imgcat_id', 2);
        $categoryTwo->assignVar('imgcat_name', 'Banners');

        $handler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(CriteriaCompo::class), true)
            ->willReturn([
                1 => $categoryOne,
                2 => $categoryTwo,
            ]);

        $groups = [1, 2];
        $list   = $handler->getList($groups, 'imgcat_write', 1, 'db');

        $this->assertSame([
            1 => 'Icons',
            2 => 'Banners',
        ], $list);
    }
}
