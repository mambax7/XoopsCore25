<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/model/xoopsmodel.php';
require_once XOOPS_ROOT_PATH . '/class/model/read.php';
require_once XOOPS_ROOT_PATH . '/class/model/stats.php';
require_once XOOPS_ROOT_PATH . '/class/model/joint.php';
require_once XOOPS_ROOT_PATH . '/class/model/sync.php';
require_once XOOPS_ROOT_PATH . '/class/model/write.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

if (!class_exists('MyTextSanitizer')) {
    class MyTextSanitizer
    {
        public static function getInstance()
        {
            return new self();
        }

        public function htmlSpecialChars($value)
        {
            return htmlspecialchars($value, ENT_QUOTES);
        }

        public function censorString($value)
        {
            return $value;
        }
    }
}

class DummyPersistableHandler extends XoopsPersistableObjectHandler
{
    public $db;

    public function __construct($db)
    {
        $this->db          = $db;
        $this->table       = 'pref_table';
        $this->keyName     = 'id';
        $this->identifierName = 'name';
        $this->table_link  = 'pref_link';
        $this->field_link  = 'link_id';
        $this->field_object = 'object_id';
    }

    public function create($isNew = true)
    {
        return new ModelObjectStub();
    }
}

class ModelObjectStub
{
    public $cleanVars = [];
    private $vars = [];

    public function __construct(array $vars = [])
    {
        $this->vars = $vars;
    }

    public function getVars()
    {
        return $this->vars;
    }

    public function assignVars($vars): void
    {
        $this->vars = $vars;
    }

    public function getValues($keys)
    {
        return array_intersect_key($this->vars, array_flip($keys));
    }
}

class XoopsModelTest extends TestCase
{
    public function testFactorySingleton(): void
    {
        $first  = XoopsModelFactory::getInstance();
        $second = XoopsModelFactory::getInstance();

        $this->assertSame($first, $second);
    }

    public function testFactoryLoadsHandlerAndSetsVars(): void
    {
        $db = $this->createMock(stdClass::class);
        $handler = new DummyPersistableHandler($db);

        $model = XoopsModelFactory::loadHandler($handler, 'read', ['extra' => 'value']);

        $this->assertInstanceOf(XoopsModelRead::class, $model);
        $this->assertSame($handler, $model->handler);
        $this->assertSame('value', $model->extra);
    }

    public function testAbstractSetHandlerRejectsInvalid(): void
    {
        $abstract = new XoopsModelAbstract();

        $this->assertFalse($abstract->setHandler(new stdClass()));
    }

    public function testReadGetIdsReturnsValues(): void
    {
        $db = $this->createMock(stdClass::class);
        $db->method('query')->willReturn('result');
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchArray')->willReturnOnConsecutiveCalls(['id' => 7], false);

        $handler      = new DummyPersistableHandler($db);
        $handler->keyName = 'id';
        $model        = new XoopsModelRead(null, $handler);

        $ids = $model->getIds();
        $this->assertSame([7], $ids);
    }

    public function testStatsGetCountWithGrouping(): void
    {
        $db = $this->createMock(stdClass::class);
        $db->method('query')->willReturn('result');
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchRow')->willReturnOnConsecutiveCalls(['cat', 3], false);

        $handler = new DummyPersistableHandler($db);
        $criteria = $this->createMock(CriteriaElement::class);
        $criteria->groupby = 'cat';
        $criteria->method('renderWhere')->willReturn('WHERE 1=1');
        $criteria->method('getGroupby')->willReturn(' GROUP BY cat');

        $model = new XoopsModelStats(null, $handler);

        $counts = $model->getCount($criteria);
        $this->assertSame(['cat' => 3], $counts);
    }

    public function testJointValidateLinksWarnsOnMissing(): void
    {
        $this->expectWarning();
        $db = $this->createMock(stdClass::class);
        $handler = new DummyPersistableHandler($db);
        $handler->table_link = '';

        $model = new XoopsModelJoint(null, $handler);

        $this->assertNull($model->getByLink());
    }

    public function testJointGetByLinkReturnsObjects(): void
    {
        $db = $this->createMock(stdClass::class);
        $db->method('query')->willReturn('result');
        $db->method('isResultSet')->willReturn(true);
        $db->method('fetchArray')->willReturnOnConsecutiveCalls([
            'id' => 5,
            'name' => 'first',
            'link_id' => 9,
        ], false);

        $handler = new DummyPersistableHandler($db);
        $model   = new XoopsModelJoint(null, $handler);

        $objects = $model->getByLink();
        $this->assertArrayHasKey(5, $objects);
        $this->assertSame('first', $objects[5]->getValues(['name'])['name']);
    }

    public function testSyncCleanOrphanChoosesSqlForVersion(): void
    {
        $db        = $this->createMock(stdClass::class);
        $db->conn  = '4.2.0';
        $db->expects($this->once())->method('exec')->with($this->stringContains('DELETE FROM `pref_table`'))
            ->willReturn(true);

        $handler = new DummyPersistableHandler($db);
        $model   = new XoopsModelSync(null, $handler);

        $this->assertTrue($model->cleanOrphan());
    }

    public function testWriteCleanVarsPopulatesCleanVars(): void
    {
        $db = $this->createMock(stdClass::class);
        $db->method('quote')->willReturnCallback(static fn($value) => "'{$value}'");

        $handler = new DummyPersistableHandler($db);
        $object  = new ModelObjectStub([
            'title' => [
                'changed' => true,
                'value' => 'hello',
                'data_type' => XOBJ_DTYPE_TXTBOX,
                'required' => false,
                'maxlength' => 255,
            ],
        ]);

        $model = new XoopsModelWrite(null, $handler);
        $this->assertTrue($model->cleanVars($object));
        $this->assertSame(['title' => 'hello'], $object->cleanVars);
    }
}
