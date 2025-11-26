<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/form.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelementtray.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formbutton.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formhiddentoken.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formhidden.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/grouppermform.php';

if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'http://example.com');
}

if (!defined('XOOPS_GROUP_ANONYMOUS')) {
    define('XOOPS_GROUP_ANONYMOUS', 0);
}

if (!defined('_SUBMIT')) {
    define('_SUBMIT', 'Submit');
}

if (!defined('_CANCEL')) {
    define('_CANCEL', 'Cancel');
}

if (!defined('_ALL')) {
    define('_ALL', 'All');
}

if (!function_exists('xoops_load')) {
    function xoops_load($name)
    {
        return true;
    }
}

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        global $mockHandlers;

        return $mockHandlers[$name] ?? null;
    }
}

class MockGroupPermHandler
{
    public array $calls = [];
    private array $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function getItemIds($permName, $groupId, $moduleId)
    {
        $this->calls[] = [$permName, $groupId, $moduleId];

        return $this->map[$groupId] ?? [];
    }
}

class MockMemberHandler
{
    public function getGroupList()
    {
        return [1 => 'Admins', XOOPS_GROUP_ANONYMOUS => 'Guests'];
    }
}

class XoopsGroupPermFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $mockHandlers;
        $mockHandlers = [
            'groupperm' => new MockGroupPermHandler([1 => [2]]),
            'member' => new MockMemberHandler(),
        ];
    }

    public function testConstructorAndItemAddition(): void
    {
        $form = new XoopsGroupPermForm('My Permission', 42, 'module_admin', 'desc text', '/return');
        $form->addItem(1, 'Top');
        $form->addItem(2, 'Child', 1);

        $this->assertSame(42, $form->_modid);
        $this->assertSame('module_admin', $form->_permName);
        $this->assertSame('desc text', $form->_permDesc);
        $this->assertArrayHasKey(0, $form->_itemTree);
        $this->assertSame([1], $form->_itemTree[0]['children']);
        $this->assertSame(1, $form->_itemTree[2]['parent']);
    }

    public function testRenderBuildsFormForGroups(): void
    {
        global $mockHandlers;
        $mockHandlers['groupperm'] = new MockGroupPermHandler([1 => [1, 2]]);

        $form = new XoopsGroupPermForm('Perm Title', 7, 'read', 'perm description', '', false);
        $form->addItem(1, 'Section');
        $form->addItem(2, 'Subsection', 1);

        $output = $form->render();

        $this->assertStringContainsString('<h4>Perm Title</h4>', $output);
        $this->assertStringContainsString('perm description', $output);
        $this->assertStringContainsString('Admins', $output);
        $this->assertStringNotContainsString('Guests', $output);
        $this->assertStringContainsString("name=\"perms[read][groups][1][1]\"", $output);
        $this->assertStringContainsString("name=\"perms[read][groups][1][2]\"", $output);
        $this->assertStringContainsString('xoopsCheckAllElements', $output);
        $this->assertSame([['read', 1, 7]], $mockHandlers['groupperm']->calls);
    }
}

class XoopsGroupFormCheckBoxTest extends TestCase
{
    public function testValueAndRenderingWithOptionTree(): void
    {
        $tree = [
            0 => ['children' => [1]],
            1 => ['id' => 1, 'name' => 'Root', 'children' => [2], 'allchild' => [2]],
            2 => ['id' => 2, 'name' => 'Child', 'children' => [], 'allchild' => []],
        ];

        $checkbox = new XoopsGroupFormCheckBox('Caption', 'perms[test]', 99, [2]);
        $checkbox->setOptionTree($tree);

        $output = $checkbox->render();

        $this->assertContains(2, $checkbox->_value);
        $this->assertStringContainsString('Caption', $checkbox->getCaption());
        $this->assertStringContainsString("perms[test][groups][99][1]", $output);
        $this->assertStringContainsString("perms[test][groups][99][2]", $output);
        $this->assertStringContainsString("value=\"1\" checked", $output);
        $this->assertStringContainsString('xoopsGetElementById', $output);
        $this->assertStringContainsString('xoopsCheckAllElements', $output);
    }
}
