<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/object.php';
require_once XOOPS_ROOT_PATH . '/class/database/database.php';
require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';

class XoopsObjectTest extends TestCase
{
    public function testNewAndDirtyFlagsToggle(): void
    {
        $object = new XoopsObject();

        $this->assertFalse($object->isNew());
        $object->setNew();
        $this->assertTrue($object->isNew());
        $object->unsetNew();
        $this->assertFalse($object->isNew());

        $this->assertFalse($object->isDirty());
        $object->setDirty();
        $this->assertTrue($object->isDirty());
        $object->unsetDirty();
        $this->assertFalse($object->isDirty());
    }

    public function testInitAndSetVarMarksChange(): void
    {
        $object = new XoopsObject();
        $object->initVar('int_field', XOBJ_DTYPE_INT, 1, true, 8, 'options');

        $this->assertArrayHasKey('int_field', $object->vars);
        $this->assertSame(1, $object->vars['int_field']['value']);
        $this->assertFalse($object->isDirty());

        $object->setVar('int_field', 5, true);

        $this->assertTrue($object->vars['int_field']['changed']);
        $this->assertTrue($object->vars['int_field']['not_gpc']);
        $this->assertSame(5, $object->vars['int_field']['value']);
        $this->assertTrue($object->isDirty());
    }

    public function testDestroyVarsResetsChangeFlags(): void
    {
        $object = new XoopsObject();
        $object->initVar('to_unset', XOBJ_DTYPE_INT, 2);
        $object->setVar('to_unset', 3);

        $this->assertTrue($object->vars['to_unset']['changed']);

        $this->assertTrue($object->destroyVars('to_unset'));
        $this->assertNull($object->vars['to_unset']['changed']);
    }

    public function testGetVarCastsIntegerValues(): void
    {
        $object = new XoopsObject();
        $object->initVar('int_value', XOBJ_DTYPE_INT, '7');

        $this->assertSame(7, $object->getVar('int_value'));
    }
}

class XoopsObjectHandlerTest extends TestCase
{
    public function testConstructorStoresDatabaseReference(): void
    {
        $database = $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->getMock();

        $handler = new XoopsObjectHandler($database);

        $this->assertSame($database, $handler->db);
    }
}

class XoopsPersistableObjectHandlerTest extends TestCase
{
    public function testCreateAndGetReturnNewObjects(): void
    {
        $database = $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->getMock();

        $handler = new TestPersistableObjectHandler($database);

        $fresh = $handler->create();
        $this->assertInstanceOf(TestPersistableObject::class, $fresh);
        $this->assertTrue($fresh->isNew());

        $existing = $handler->create(false);
        $this->assertFalse($existing->isNew());

        $fromNull = $handler->get(null);
        $this->assertInstanceOf(TestPersistableObject::class, $fromNull);
        $this->assertTrue($fromNull->isNew());
    }

    public function testInsertDelegatesToWriteHandler(): void
    {
        $database = $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->getMock();

        $handler = new TestPersistableObjectHandler($database);
        $object  = $handler->create();

        $writeHandler = new class {
            public array $captured = [];

            public function insert($object, $force)
            {
                $this->captured = [$object, $force];

                return 'saved';
            }
        };

        $handler->registerHandler('write', $writeHandler);

        $this->assertSame('saved', $handler->insert($object, false));
        $this->assertSame([$object, false], $writeHandler->captured);
    }

    public function testMagicCallDelegatesToCustomHandlers(): void
    {
        $database = $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->getMock();

        $handler = new TestPersistableObjectHandler($database);
        $handler->handler = new class {
            public function customMethod($value)
            {
                return strtoupper($value);
            }
        };

        $this->assertSame('VALUE', $handler->customMethod('value'));

        $handler->handler = null;
        $handler->handlers = ['read' => null];

        $handler->registerHandler('read', new class {
            public function customMethod($value)
            {
                return $value . '_from_read';
            }
        });

        $this->assertSame('alt_from_read', $handler->customMethod('alt'));
    }
}

class TestPersistableObjectHandler extends XoopsPersistableObjectHandler
{
    /** @var array<string, object> */
    private $handlerMap = [];

    public function __construct(XoopsDatabase $db)
    {
        $this->db        = $db;
        $this->table     = 'unit_table';
        $this->keyName   = 'id';
        $this->className = TestPersistableObject::class;
    }

    public function registerHandler(string $name, object $handler): void
    {
        $this->handlerMap[$name] = $handler;
    }

    public function loadHandler($name, $args = null)
    {
        return $this->handlerMap[$name];
    }
}

class TestPersistableObject extends XoopsObject
{
}
