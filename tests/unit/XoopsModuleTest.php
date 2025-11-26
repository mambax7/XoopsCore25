<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/module.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsModuleTest extends TestCase
{
    private $previousLogger;
    private array $messages;

    protected function setUp(): void
    {
        $this->previousLogger   = $GLOBALS['xoopsLogger'] ?? null;
        $this->messages         = [];
        $GLOBALS['xoopsLogger'] = new class {
            public array $messages = [];

            public function addDeprecated($message): void
            {
                $this->messages[] = $message;
            }
        };
        $GLOBALS['xoopsLogger']->messages =& $this->messages;
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoopsLogger'] = $this->previousLogger;
    }

    public function testConstructorInitializesVars(): void
    {
        $module = new XoopsModule();

        $this->assertNull($module->getVar('mid'));
        $this->assertNull($module->getVar('name'));
        $this->assertNull($module->getVar('version'));
        $this->assertNull($module->getVar('last_update'));
        $this->assertSame(0, $module->getVar('weight'));
        $this->assertSame(1, $module->getVar('isactive'));
        $this->assertNull($module->getVar('dirname'));
        $this->assertSame(0, $module->getVar('hasmain'));
        $this->assertSame(0, $module->getVar('hasadmin'));
        $this->assertSame(0, $module->getVar('hassearch'));
        $this->assertSame(0, $module->getVar('hasconfig'));
        $this->assertSame(0, $module->getVar('hascomments'));
        $this->assertSame(0, $module->getVar('hasnotification'));
    }

    public function testLoadInfoAsVarSetsFlagsFromModinfo(): void
    {
        $module           = new class extends XoopsModule {
            public function setFakeInfo(array $info): void
            {
                $this->modinfo = $info;
            }
        };
        $module->setFakeInfo([
            'name'            => 'Example',
            'version'         => '1.2.3',
            'dirname'         => 'sample',
            'hasMain'         => 1,
            'hasAdmin'        => 0,
            'hasSearch'       => 1,
            'config'          => ['opt'],
            'hasComments'     => 0,
            'hasNotification' => 1,
        ]);

        $module->loadInfoAsVar('sample');

        $this->assertSame('Example', $module->getVar('name'));
        $this->assertSame('1.2.3', $module->getVar('version'));
        $this->assertSame('sample', $module->getVar('dirname'));
        $this->assertSame(1, $module->getVar('hasmain'));
        $this->assertSame(0, $module->getVar('hasadmin'));
        $this->assertSame(1, $module->getVar('hassearch'));
        $this->assertSame(1, $module->getVar('hasconfig'));
        $this->assertSame(0, $module->getVar('hascomments'));
        $this->assertSame(1, $module->getVar('hasnotification'));
    }

    public function testMessageHelpersTrimAndReturnMessages(): void
    {
        $module = new XoopsModule();
        $module->setMessage(' first ');
        $module->setMessage("second\n");

        $this->assertSame(['first', 'second'], $module->getMessages());
    }

    public function testSetInfoAndGetInfo(): void
    {
        $module = new XoopsModule();
        $module->setInfo('', ['name' => 'full']);
        $module->setInfo('version', '1.0');

        $this->assertSame('1.0', $module->getInfo('version'));
        $this->assertSame(['name' => 'full', 'version' => '1.0'], $module->getInfo());
        $this->assertFalse($module->getInfo('missing'));
    }

    public function testStatusAndVersionCompare(): void
    {
        $module = new XoopsModule();
        $module->setVar('version', '1.0-beta');

        $this->assertSame('beta', $module->getStatus());
        $this->assertTrue($module->versionCompare('1.1-stable', '1.0-stable', '>'));
        $this->assertFalse($module->versionCompare('1.0', '1.0-stable', '>'));
    }

    public function testLinkHelpers(): void
    {
        $module = new XoopsModule();
        $module->setVar('dirname', 'testmod');
        $module->setVar('name', 'Module');
        $module->setVar('hasmain', 1);
        $module->setInfo('sub', [
            ['id' => 1, 'name' => 'Sub', 'url' => 'page.php', 'icon' => 'icon.png'],
            ['id' => 2, 'name' => 'Second', 'url' => 'next.php'],
        ]);

        $this->assertStringContainsString('/modules/testmod/', $module->mainLink());
        $subs = $module->subLink();
        $this->assertCount(2, $subs);
        $this->assertSame('Sub', $subs[0]['name']);
        $this->assertSame('', $subs[1]['icon']);
    }

    public function testDeprecatedMethodsLogMessages(): void
    {
        $module = new XoopsModule();

        $module->update();
        $module->insert();
        $module->executeSQL();
        $module->insertTemplates();
        $module->gettemplate('file.tpl');
        $module->insertBlocks();
        $module->insertConfigCategories();
        $module->insertConfig();
        $module->insertProfileFields();
        $module->executeScript('type');
        $module->insertGroupPermissions([], 'type');
        $module->checkAccess();
        $module->setMessage('msg');
        $module->printErrors();

        $this->assertGreaterThanOrEqual(11, \count($this->messages));
    }
}

trait ModuleDatabaseMockTrait
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
                'getInsertId',
                'genId',
                'quote',
                'escape',
            ])
            ->getMock();
    }
}

class XoopsModuleHandlerTest extends TestCase
{
    use ModuleDatabaseMockTrait;

    public function testCreateReturnsNewOrExisting(): void
    {
        $handler = new XoopsModuleHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsModule::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetFetchesAndCachesModule(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_modules');
        $database->expects($this->once())->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'mid'             => 3,
            'name'            => 'Module',
            'version'         => '1.0',
            'last_update'     => 0,
            'weight'          => 0,
            'isactive'        => 1,
            'dirname'         => 'mod',
            'hasmain'         => 1,
            'hasadmin'        => 0,
            'hassearch'       => 0,
            'hasconfig'       => 0,
            'hascomments'     => 0,
            'hasnotification' => 0,
        ]);

        $handler = new XoopsModuleHandler($database);
        $module  = $handler->get(3);
        $this->assertInstanceOf(XoopsModule::class, $module);
        $this->assertFalse($module->isNew());
        $this->assertSame('Module', $module->getVar('name'));

        $this->assertSame($module, $handler->get(3));
    }

    public function testGetByDirnameUsesPreparedStatement(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_modules');
        $database->method('escape')->willReturnArgument(0);

        $result = 'result';
        $statement = $this->getMockBuilder(stdClass::class)
            ->addMethods(['bind_param', 'execute', 'get_result'])
            ->getMock();
        $statement->expects($this->once())->method('bind_param')->with('s', 'mod');
        $statement->expects($this->once())->method('execute')->willReturn(true);
        $statement->expects($this->once())->method('get_result')->willReturn($result);

        $database->conn = $this->getMockBuilder(stdClass::class)
            ->addMethods(['prepare'])
            ->getMock();
        $database->conn->expects($this->once())->method('prepare')->with($this->stringContains('WHERE dirname = ?'))
            ->willReturn($statement);

        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'mid'             => 7,
            'name'            => 'Dir Module',
            'version'         => '2.0',
            'last_update'     => 0,
            'weight'          => 0,
            'isactive'        => 1,
            'dirname'         => 'mod',
            'hasmain'         => 1,
            'hasadmin'        => 0,
            'hassearch'       => 0,
            'hasconfig'       => 0,
            'hascomments'     => 0,
            'hasnotification' => 0,
        ]);

        $handler = new XoopsModuleHandler($database);
        $module  = $handler->getByDirname('mod');

        $this->assertInstanceOf(XoopsModule::class, $module);
        $this->assertSame(7, $module->getVar('mid'));
        $this->assertSame($module, $handler->getByDirname('mod'));
    }

    public function testInsertCreatesNewModule(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_modules');
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(42);
        $database->expects($this->once())->method('exec')->with($this->stringContains('INSERT INTO pref_modules'))->willReturn(true);

        $handler = new XoopsModuleHandler($database);
        $module  = $handler->create();
        $module->setVar('name', 'Module');
        $module->setVar('version', '1.0');
        $module->setVar('dirname', 'mod');
        $module->setVar('weight', 2);
        $module->setVar('hasmain', 1);
        $module->setVar('hasadmin', 0);
        $module->setVar('hassearch', 0);
        $module->setVar('hasconfig', 1);
        $module->setVar('hascomments', 0);
        $module->setVar('hasnotification', 0);

        $this->assertTrue($handler->insert($module));
        $this->assertSame(42, $module->getVar('mid'));
    }

    public function testInsertUpdatesExistingModule(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref_modules');
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->once())->method('exec')->with($this->stringContains('UPDATE pref_modules SET'))->willReturn(true);

        $handler = new XoopsModuleHandler($database);
        $module  = $handler->create(false);
        $module->setVar('mid', 5);
        $module->setVar('name', 'Module');
        $module->setVar('version', '1.1');
        $module->setVar('dirname', 'mod');
        $module->setVar('weight', 2);
        $module->setVar('hasmain', 1);
        $module->setVar('hasadmin', 0);
        $module->setVar('hassearch', 0);
        $module->setVar('hasconfig', 1);
        $module->setVar('hascomments', 0);
        $module->setVar('hasnotification', 0);

        $this->assertTrue($handler->insert($module));
    }

    public function testDeleteRemovesCaches(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->expects($this->exactly(3))->method('exec')->willReturn(true);
        $database->expects($this->once())->method('query')->willReturn('result');
        $database->method('isResultSet')->willReturn(false);

        $handler = new XoopsModuleHandler($database);
        $module  = $handler->create(false);
        $module->assignVar('mid', 9);
        $module->assignVar('dirname', 'mod');

        $handler->_cachedModule_dirname['mod'] = $module;
        $handler->_cachedModule_mid[9]         = $module;

        $this->assertTrue($handler->delete($module));
        $this->assertArrayNotHasKey('mod', $handler->_cachedModule_dirname);
        $this->assertArrayNotHasKey(9, $handler->_cachedModule_mid);
    }

    public function testGetObjectsLoadsModules(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturn('pref');
        $database->expects($this->once())->method('query')->with($this->stringContains('SELECT * FROM pref'))
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'mid'             => 1,
                'name'            => 'One',
                'version'         => '1.0',
                'last_update'     => 0,
                'weight'          => 0,
                'isactive'        => 1,
                'dirname'         => 'one',
                'hasmain'         => 1,
                'hasadmin'        => 0,
                'hassearch'       => 0,
                'hasconfig'       => 0,
                'hascomments'     => 0,
                'hasnotification' => 0,
            ],
            false
        );

        $criteria = $this->getMockBuilder(CriteriaElement::class)
            ->onlyMethods(['renderWhere', 'getOrder', 'getLimit', 'getStart'])
            ->getMockForAbstractClass();
        $criteria->method('renderWhere')->willReturn('WHERE 1=1');
        $criteria->method('getOrder')->willReturn('ASC');
        $criteria->method('getLimit')->willReturn(5);
        $criteria->method('getStart')->willReturn(0);

        $handler = new XoopsModuleHandler($database);
        $objects = $handler->getObjects($criteria, true);

        $this->assertCount(1, $objects);
        $this->assertInstanceOf(XoopsModule::class, $objects[1]);
        $this->assertSame('One', $objects[1]->getVar('name'));
    }
}
