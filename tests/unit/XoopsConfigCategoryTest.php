<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/configcategory.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsConfigCategoryTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $category = new XoopsConfigCategory();

        $this->assertNull($category->getVar('confcat_id'));
        $this->assertNull($category->getVar('confcat_name'));
        $this->assertSame(0, $category->getVar('confcat_order'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $category = new XoopsConfigCategory();
        $category->setVar('confcat_id', 4);
        $category->setVar('confcat_name', 'general');
        $category->setVar('confcat_order', 9);

        $this->assertSame(4, $category->id());
        $this->assertSame(4, $category->confcat_id());
        $this->assertSame('general', $category->confcat_name());
        $this->assertSame(9, $category->confcat_order());
    }
}

class XoopsConfigCategoryHandlerTest extends TestCase
{
    public function testCreateReturnsNewOrExistingCategory(): void
    {
        $handler = new XoopsConfigCategoryHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsConfigCategory::class, $fresh);
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
            ->with('SELECT * FROM pref_configcategory WHERE confcat_id=2')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'confcat_id'    => 2,
            'confcat_name'  => 'General',
            'confcat_order' => 1,
        ]);

        $handler  = new XoopsConfigCategoryHandler($database);
        $category = $handler->get(2);

        $this->assertInstanceOf(XoopsConfigCategory::class, $category);
        $this->assertSame('General', $category->getVar('confcat_name'));
        $this->assertFalse($category->isNew());
    }

    public function testInsertCreatesNewCategory(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(10);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->callback(static function ($sql) {
                return strpos($sql, 'INSERT INTO pref_configcategory') !== false
                    && strpos($sql, "'Mail'") !== false
                    && strpos($sql, '4') !== false;
            }))
            ->willReturn(true);

        $handler = new XoopsConfigCategoryHandler($database);

        $category = $handler->create();
        $category->setVar('confcat_name', 'Mail');
        $category->setVar('confcat_order', 4);

        $this->assertSame(10, $handler->insert($category));
        $this->assertSame(10, $category->getVar('confcat_id'));
    }

    public function testInsertUpdatesExistingCategory(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('UPDATE pref_configcategory SET confcat_name ='))
            ->willReturn(true);

        $handler = new XoopsConfigCategoryHandler($database);

        $category = $handler->create(false);
        $category->setNew(false);
        $category->setVar('confcat_id', 3);
        $category->setVar('confcat_name', 'Updated');
        $category->setVar('confcat_order', 2);

        $this->assertSame(3, $handler->insert($category));
    }

    public function testDeleteRemovesCategory(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_configcategory WHERE confcat_id = 5')
            ->willReturn(true);

        $handler = new XoopsConfigCategoryHandler($database);

        $category = $handler->create(false);
        $category->setVar('confcat_id', 5);

        $this->assertTrue($handler->delete($category));
    }

    public function testGetObjectsReturnsCategoriesUsingCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM pref_configcategory WHERE (confcat_order > 2) ORDER BY confcat_name DESC', 5, 1)
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'confcat_id'    => 6,
                'confcat_name'  => 'Display',
                'confcat_order' => 3,
            ],
            [
                'confcat_id'    => 7,
                'confcat_name'  => 'Mail',
                'confcat_order' => 4,
            ],
            false
        );

        $handler  = new XoopsConfigCategoryHandler($database);
        $criteria = new Criteria('confcat_order', 2, '>');
        $criteria->setSort('confcat_name');
        $criteria->setOrder('DESC');
        $criteria->setLimit(5);
        $criteria->setStart(1);

        $categories = $handler->getObjects($criteria);

        $this->assertCount(2, $categories);
        $this->assertSame('Display', $categories[0]->getVar('confcat_name'));
        $this->assertSame('Mail', $categories[1]->getVar('confcat_name'));
    }

    public function testGetCatByModuleLogsDeprecated(): void
    {
        $logger = new class {
            public array $messages = [];

            public function addDeprecated($message): void
            {
                $this->messages[] = $message;
            }
        };

        $previousLogger       = $GLOBALS['xoopsLogger'] ?? null;
        $GLOBALS['xoopsLogger'] = $logger;

        try {
            $handler = new XoopsConfigCategoryHandler($this->createDatabaseMock());

            $this->assertFalse($handler->getCatByModule(1));
            $this->assertNotEmpty($logger->messages);
            $this->assertStringContainsString('deprecated', $logger->messages[0]);
        } finally {
            $GLOBALS['xoopsLogger'] = $previousLogger;
        }
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
                'quote',
                'genId',
            ])
            ->getMock();
    }
}
