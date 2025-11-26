<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/tplfile.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsTplfileTest extends TestCase
{
    public function testConstructorInitializesVariables(): void
    {
        $tplfile = new XoopsTplfile();

        $this->assertNull($tplfile->getVar('tpl_id'));
        $this->assertSame(0, $tplfile->getVar('tpl_refid'));
        $this->assertNull($tplfile->getVar('tpl_tplset'));
        $this->assertNull($tplfile->getVar('tpl_file'));
        $this->assertNull($tplfile->getVar('tpl_desc'));
        $this->assertSame(0, $tplfile->getVar('tpl_lastmodified'));
        $this->assertSame(0, $tplfile->getVar('tpl_lastimported'));
        $this->assertNull($tplfile->getVar('tpl_module'));
        $this->assertNull($tplfile->getVar('tpl_type'));
        $this->assertNull($tplfile->getVar('tpl_source'));
    }

    public function testHelperMethodsReturnValues(): void
    {
        $tplfile = new XoopsTplfile();
        $tplfile->setVar('tpl_id', 99);
        $tplfile->setVar('tpl_refid', 3);
        $tplfile->setVar('tpl_tplset', 'default');
        $tplfile->setVar('tpl_file', 'index.tpl');
        $tplfile->setVar('tpl_desc', 'desc');
        $tplfile->setVar('tpl_lastmodified', 111);
        $tplfile->setVar('tpl_lastimported', 222);
        $tplfile->setVar('tpl_module', 'system');
        $tplfile->setVar('tpl_type', 'block');
        $tplfile->setVar('tpl_source', '<tpl>');

        $this->assertSame(99, $tplfile->id());
        $this->assertSame(99, $tplfile->tpl_id());
        $this->assertSame(3, $tplfile->tpl_refid());
        $this->assertSame('default', $tplfile->tpl_tplset());
        $this->assertSame('index.tpl', $tplfile->tpl_file());
        $this->assertSame('desc', $tplfile->tpl_desc());
        $this->assertSame(111, $tplfile->tpl_lastmodified());
        $this->assertSame(222, $tplfile->tpl_lastimported());
        $this->assertSame('system', $tplfile->tpl_module());
        $this->assertSame('block', $tplfile->tpl_type());
        $this->assertSame('<tpl>', $tplfile->tpl_source());
        $this->assertSame('<tpl>', $tplfile->getSource());
        $this->assertSame(111, $tplfile->getLastModified());
    }
}

trait TplfileDatabaseMockTrait
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

class XoopsTplfileHandlerTest extends TestCase
{
    use TplfileDatabaseMockTrait;

    public function testCreateReturnsNewOrExistingTplfile(): void
    {
        $handler = new XoopsTplfileHandler($this->createDatabaseMock());

        $fresh = $handler->create();
        $this->assertInstanceOf(XoopsTplfile::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());
    }

    public function testGetRetrievesTplfileWithAndWithoutSource(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->exactly(2))
            ->method('query')
            ->withConsecutive(
                ['SELECT * FROM pref_tplfile WHERE tpl_id=7'],
                ['SELECT f.*, s.tpl_source FROM pref_tplfile f LEFT JOIN pref_tplsource s  ON s.tpl_id=f.tpl_id WHERE f.tpl_id=7']
            )
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('getRowsNum')->willReturn(1);
        $database->method('fetchArray')->willReturn([
            'tpl_id'          => 7,
            'tpl_refid'       => 9,
            'tpl_tplset'      => 'default',
            'tpl_file'        => 'index.tpl',
            'tpl_desc'        => 'desc',
            'tpl_lastmodified'=> 10,
            'tpl_lastimported'=> 11,
            'tpl_module'      => 'system',
            'tpl_type'        => 'block',
            'tpl_source'      => '<tpl>',
        ]);

        $handler = new XoopsTplfileHandler($database);
        $basic   = $handler->get(7);
        $withSrc = $handler->get(7, true);

        $this->assertInstanceOf(XoopsTplfile::class, $basic);
        $this->assertNull($basic->getVar('tpl_source'));
        $this->assertInstanceOf(XoopsTplfile::class, $withSrc);
        $this->assertSame('<tpl>', $withSrc->getVar('tpl_source'));
    }

    public function testLoadSourceFetchesWhenMissing(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with('SELECT tpl_source FROM pref_tplsource WHERE tpl_id=4')
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturn(['tpl_source' => '<tpl>']);

        $tplfile = new XoopsTplfile();
        $tplfile->setVar('tpl_id', 4);

        $handler = new XoopsTplfileHandler($database);
        $this->assertTrue($handler->loadSource($tplfile));
        $this->assertSame('<tpl>', $tplfile->getVar('tpl_source'));
    }

    public function testInsertCreatesNewTplfileWithSource(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('genId')->willReturn(0);
        $database->method('getInsertId')->willReturn(13);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                [$this->stringContains('INSERT INTO pref_tplfile')],
                [$this->stringContains('INSERT INTO pref_tplsource')]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsTplfileHandler($database);

        $tplfile = $handler->create();
        $tplfile->setVar('tpl_module', 'system');
        $tplfile->setVar('tpl_refid', 1);
        $tplfile->setVar('tpl_tplset', 'default');
        $tplfile->setVar('tpl_file', 'index.tpl');
        $tplfile->setVar('tpl_desc', 'desc');
        $tplfile->setVar('tpl_lastmodified', 1);
        $tplfile->setVar('tpl_lastimported', 2);
        $tplfile->setVar('tpl_type', 'block');
        $tplfile->setVar('tpl_source', '<tpl>');

        $this->assertTrue($handler->insert($tplfile));
        $this->assertSame(13, $tplfile->getVar('tpl_id'));
    }

    public function testInsertUpdatesExistingTplfile(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                [$this->stringContains('UPDATE pref_tplfile SET')],
                [$this->stringContains('UPDATE pref_tplsource SET')]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsTplfileHandler($database);

        $tplfile = $handler->create(false);
        $tplfile->setNew(false);
        $tplfile->setVar('tpl_id', 5);
        $tplfile->setVar('tpl_tplset', 'default');
        $tplfile->setVar('tpl_file', 'index.tpl');
        $tplfile->setVar('tpl_desc', 'desc');
        $tplfile->setVar('tpl_lastmodified', 3);
        $tplfile->setVar('tpl_lastimported', 4);
        $tplfile->setVar('tpl_source', '<tpl>');

        $this->assertTrue($handler->insert($tplfile));
    }

    public function testInsertRejectsInvalidObject(): void
    {
        $handler = new XoopsTplfileHandler($this->createDatabaseMock());

        $this->assertFalse($handler->insert(new stdClass()));
    }

    public function testForceUpdatePersistsChanges(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->method('quote')->willReturnCallback(static fn($value) => "'" . $value . "'");
        $database->expects($this->exactly(2))
            ->method('exec')
            ->withConsecutive(
                [$this->stringContains('UPDATE pref_tplfile SET')],
                [$this->stringContains('UPDATE pref_tplsource SET')]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $handler = new XoopsTplfileHandler($database);

        $tplfile = $handler->create(false);
        $tplfile->setNew(false);
        $tplfile->setVar('tpl_id', 6);
        $tplfile->setVar('tpl_tplset', 'default');
        $tplfile->setVar('tpl_file', 'main.tpl');
        $tplfile->setVar('tpl_desc', 'desc');
        $tplfile->setVar('tpl_lastmodified', 5);
        $tplfile->setVar('tpl_lastimported', 6);
        $tplfile->setVar('tpl_source', '<tpl>');

        $this->assertTrue($handler->forceUpdate($tplfile));
    }

    public function testDeleteRemovesTplfile(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('exec')
            ->with('DELETE FROM pref_tplfile WHERE tpl_id = 8')
            ->willReturn(true);

        $handler = new XoopsTplfileHandler($database);

        $tplfile = $handler->create(false);
        $tplfile->setVar('tpl_id', 8);

        $this->assertTrue($handler->delete($tplfile));
    }

    public function testGetObjectsReturnsResultsWithCriteria(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT * FROM pref_tplfile WHERE (tpl_module = 'system') ORDER BY tpl_refid")
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            [
                'tpl_id'          => 1,
                'tpl_refid'       => 9,
                'tpl_tplset'      => 'default',
                'tpl_file'        => 'index.tpl',
                'tpl_desc'        => 'desc',
                'tpl_lastmodified'=> 0,
                'tpl_lastimported'=> 0,
                'tpl_module'      => 'system',
                'tpl_type'        => 'block',
            ],
            false
        );

        $handler  = new XoopsTplfileHandler($database);
        $criteria = new Criteria('tpl_module', 'system');
        $objects  = $handler->getObjects($criteria);

        $this->assertCount(1, $objects);
        $this->assertInstanceOf(XoopsTplfile::class, $objects[0]);
        $this->assertSame(1, $objects[0]->getVar('tpl_id'));
    }

    public function testGetCountReturnsNumberOfRows(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT COUNT(*) FROM pref_tplfile WHERE (tpl_module = 'system')")
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchRow')->willReturn([7]);

        $handler  = new XoopsTplfileHandler($database);
        $criteria = new Criteria('tpl_module', 'system');

        $this->assertSame(7, $handler->getCount($criteria));
    }

    public function testGetModuleTplCountAggregatesResults(): void
    {
        $database = $this->createDatabaseMock();
        $database->method('prefix')->willReturnCallback(static fn($table) => 'pref_' . $table);
        $database->expects($this->once())
            ->method('query')
            ->with("SELECT tpl_module, COUNT(tpl_id) AS count FROM pref_tplfile WHERE tpl_tplset='default' GROUP BY tpl_module")
            ->willReturn('result');
        $database->method('isResultSet')->willReturn(true);
        $database->method('fetchArray')->willReturnOnConsecutiveCalls(
            ['tpl_module' => 'system', 'count' => 2],
            ['tpl_module' => '', 'count' => 1],
            false
        );

        $handler = new XoopsTplfileHandler($database);
        $this->assertSame(['system' => 2], $handler->getModuleTplCount('default'));
    }

    public function testFindBuildsCriteriaAndDelegatesToGetObjects(): void
    {
        $database = $this->createDatabaseMock();
        $handler = $this->getMockBuilder(XoopsTplfileHandler::class)
            ->setConstructorArgs([$database])
            ->onlyMethods(['getObjects'])
            ->getMock();

        $handler->expects($this->once())
            ->method('getObjects')
            ->with($this->callback(static function ($criteria) {
                $rendered = $criteria->renderWhere();
                return str_contains($rendered, "tpl_tplset = 'custom'")
                    && str_contains($rendered, "tpl_module = 'system'")
                    && str_contains($rendered, "tpl_refid = 5")
                    && str_contains($rendered, "tpl_file = 'index.tpl'")
                    && str_contains($rendered, "tpl_type = 'block'");
            }), true, false)
            ->willReturn(['result']);

        $this->assertSame(['result'], $handler->find('custom', 'block', 5, 'system', 'index.tpl', true));
    }

    public function testTemplateExistsUsesCount(): void
    {
        $database = $this->createDatabaseMock();
        $handler  = $this->getMockBuilder(XoopsTplfileHandler::class)
            ->setConstructorArgs([$database])
            ->onlyMethods(['getCount'])
            ->getMock();

        $handler->method('getCount')->willReturnOnConsecutiveCalls(0, 1);

        $this->assertFalse($handler->templateExists('index.tpl', 'default'));
        $this->assertTrue($handler->templateExists('index.tpl', 'default'));
    }
}
