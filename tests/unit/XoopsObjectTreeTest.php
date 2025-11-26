<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/tree.php';

class DummyTreeObject
{
    private $vars;

    public function __construct($id, $parentId, $name, $rootId = null)
    {
        $this->vars = [
            'id'     => $id,
            'parent' => $parentId,
            'name'   => $name,
            'root'   => $rootId,
        ];
    }

    public function getVar($key)
    {
        return $this->vars[$key];
    }
}

class LoggerTreeStub
{
    public $deprecated = [];
    public $extra = [];

    public function addDeprecated($message)
    {
        $this->deprecated[] = $message;
    }

    public function addExtra($channel, $message)
    {
        $this->extra[] = [$channel, $message];
    }
}

class SelectTreeStub
{
    public $name;
    public $caption;
    public $selected;
    public $extra = '';
    public $options = [];

    public function __construct($caption, $name, $selected)
    {
        $this->caption  = $caption;
        $this->name     = $name;
        $this->selected = $selected;
    }

    public function setExtra($extra)
    {
        $this->extra = $extra;
    }

    public function addOption($value, $name)
    {
        $this->options[$value] = $name;
    }
}

if (!function_exists('xoops_load')) {
    function xoops_load($name)
    {
        if ($name === 'xoopsformselect' && !class_exists('XoopsFormSelect')) {
            class_alias(SelectTreeStub::class, 'XoopsFormSelect');
        }
    }
}

class XoopsObjectTreeTest extends TestCase
{
    /** @var LoggerTreeStub */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger            = new LoggerTreeStub();
        $GLOBALS['xoopsLogger']  = $this->logger;
    }

    private function buildTree(): XoopsObjectTree
    {
        $objects = [
            new DummyTreeObject(1, 0, 'root', 1),
            new DummyTreeObject(2, 1, 'child', 1),
            new DummyTreeObject(3, 1, 'child2', 1),
            new DummyTreeObject(4, 2, 'grandchild', 1),
        ];

        return new XoopsObjectTree($objects, 'id', 'parent', 'root');
    }

    public function testTreeConstructionAndRetrieval(): void
    {
        $tree = $this->buildTree();
        $data = $tree->getTree();

        $this->assertArrayHasKey(1, $data);
        $this->assertSame(1, $data[1]['obj']->getVar('id'));
        $this->assertSame(0, $data[1]['parent']);
        $this->assertSame([2, 3], $data[1]['child']);
        $this->assertSame(1, $data[1]['root']);
    }

    public function testGetByKeyAndChildren(): void
    {
        $tree = $this->buildTree();

        $this->assertSame(2, $tree->getByKey(2)->getVar('id'));
        $firstChildren = $tree->getFirstChild(1);
        $this->assertSame([2, 3], array_keys($firstChildren));
        $this->assertSame('child2', $firstChildren[3]->getVar('name'));

        $allChildren = $tree->getAllChild(1);
        $this->assertEqualsCanonicalizing([2, 3, 4], array_keys($allChildren));
        $this->assertSame('grandchild', $allChildren[4]->getVar('name'));
    }

    public function testGetAllParentReturnsAncestors(): void
    {
        $tree    = $this->buildTree();
        $parents = $tree->getAllParent(4);

        $this->assertSame('child', $parents[1]->getVar('name'));
        $this->assertSame('root', $parents[2]->getVar('name'));
    }

    public function testMakeSelBoxBuildsMarkupAndLogsDeprecation(): void
    {
        $tree   = $this->buildTree();
        $output = $tree->makeSelBox('tree', 'name', '-', 2, true, 1, 'class="sel"');

        $this->assertStringContainsString('<select name="tree" id="tree" class="sel">', $output);
        $this->assertStringContainsString('<option value="0"></option>', $output);
        $this->assertStringContainsString('<option value="1">root</option>', $output);
        $this->assertStringContainsString('<option value="2" selected>-child</option>', $output);
        $this->assertStringContainsString('<option value="4">--grandchild</option>', $output);
        $this->assertNotEmpty($this->logger->deprecated);
    }

    public function testMakeSelectElementAddsOptions(): void
    {
        $tree    = $this->buildTree();
        $element = $tree->makeSelectElement('tree', 'name', '*', 3, true, 1, 'data-test', 'Tree');

        $this->assertInstanceOf(SelectTreeStub::class, $element);
        $this->assertSame('Tree', $element->caption);
        $this->assertSame('tree', $element->name);
        $this->assertSame('data-test', $element->extra);
        $this->assertSame('root', $element->options[1]);
        $this->assertSame('-child2', $element->options[3]);
        $this->assertArrayHasKey('0', $element->options);
    }

    public function testMagicGetLogsDeprecatedTreeAndExtra(): void
    {
        $tree = $this->buildTree();

        $treeData = $tree->_tree;
        $this->assertSame($tree->getTree(), $treeData);
        $this->assertNotEmpty($this->logger->deprecated);

        $unknown = $tree->undefined;
        $this->assertNull($unknown);
        $this->assertNotEmpty($this->logger->extra);
    }
}
