<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/form.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelementtray.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtext.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formhiddentoken.php';

if (!class_exists('XoopsSecurity')) {
    class XoopsSecurity
    {
        public array $calls = [];

        public function createToken($timeout = 0, $name = 'XOOPS_TOKEN')
        {
            $this->calls[] = [$timeout, $name];

            return 'generated-token';
        }
    }
}

if (!class_exists('DummyFormElement')) {
    class DummyFormElement extends XoopsFormElement
    {
        private $value;
        private $validationJs;

        public function __construct(string $name, string $caption = '', string $validationJs = '')
        {
            $this->setName($name);
            $this->setCaption($caption);
            $this->validationJs = $validationJs ?: "if (!myform.{$name}.value) { return false; }";
        }

        public function isContainer()
        {
            return false;
        }

        public function setValue($value): void
        {
            $this->value = $value;
        }

        public function getValue($encode = false)
        {
            return $encode ? htmlspecialchars((string) $this->value, ENT_QUOTES | ENT_HTML5) : $this->value;
        }

        public function render()
        {
            return '<input name="' . $this->_name . '" />';
        }

        public function renderValidationJS()
        {
            return $this->validationJs;
        }
    }
}

if (!class_exists('DummyTpl')) {
    class DummyTpl
    {
        public array $assigned = [];

        public function assign($key, $value): void
        {
            $this->assigned[$key] = $value;
        }
    }
}

class XoopsFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['xoopsSecurity'] = new XoopsSecurity();
    }

    public function testConstructorSetsDefaultsAndAddsToken(): void
    {
        $form = new XoopsForm('My © title', 'myform', '/submit.php?foo=bar', 'GET', true, 'summary & more');

        $this->assertSame('My © title', $form->getTitle());
        $this->assertSame('My &copy; title', $form->getTitle(true));
        $this->assertSame('myform', $form->getName(false));
        $this->assertSame('myform', $form->getName());
        $this->assertSame('/submit.php?foo=bar', $form->getAction(false));
        $this->assertSame('/submit.php?foo=bar', htmlspecialchars_decode($form->getAction()));
        $this->assertSame('get', $form->getMethod());
        $this->assertSame('summary & more', $form->getSummary());
        $this->assertSame('summary &amp; more', $form->getSummary(true));

        $elements = $form->getElements();
        $this->assertNotEmpty($elements);
        $this->assertInstanceOf(XoopsFormHiddenToken::class, $elements[0]);
        $this->assertNotEmpty($GLOBALS['xoopsSecurity']->calls);
    }

    public function testAddElementTracksRequiredAndStrings(): void
    {
        $form = new XoopsForm('title', 'simple', '/action.php', 'post', false);
        $element = new DummyFormElement('field', 'Caption');

        $form->addElement($element, true);
        $form->addElement('literal-break');

        $elements = $form->getElements();
        $this->assertSame($element, $elements[0]);
        $this->assertSame('literal-break', $elements[1]);

        $required = $form->getRequired();
        $this->assertSame([$element], $required);
        $this->assertTrue($element->isRequired());
    }

    public function testGetElementsRecursesThroughContainers(): void
    {
        $form = new XoopsForm('title', 'recurse', '/action.php', 'post', false);
        $tray = new XoopsFormElementTray('tray', '|', 'trayname');
        $child = new DummyFormElement('child', 'Child');
        $tray->addElement($child, true);

        $form->addElement($tray);

        $flat = $form->getElements(true);
        $this->assertSame([$child], $flat);

        $required = $form->getRequired();
        $this->assertSame([$child], $required);
    }

    public function testSetAndGetElementValues(): void
    {
        $form = new XoopsForm('title', 'values', '/action.php', 'post', false);
        $one = new DummyFormElement('one');
        $two = new DummyFormElement('two');
        $form->addElement($one);
        $form->addElement($two);

        $form->setElementValue('one', 'first');
        $form->setElementValues(['one' => 'alpha', 'two' => 'beta']);

        $this->assertSame('alpha', $form->getElementValue('one'));
        $this->assertSame(['one' => 'alpha', 'two' => 'beta'], $form->getElementValues());
        $this->assertSame(['one' => 'alpha', 'two' => 'beta'], $form->getElementValues(true));
    }

    public function testClassExtraAndSummaryHelpers(): void
    {
        $form = new XoopsForm('title', 'css', '/action.php', 'post', false, 'initial');
        $form->setClass(' primary ');
        $form->setClass('secondary');
        $form->setExtra('data-one="1"');
        $form->setExtra('checked');

        $this->assertSame('primary secondary', $form->getClass());
        $this->assertSame(' data-one="1" checked', $form->getExtra());
        $this->assertSame('initial', $form->getSummary());
    }

    public function testRenderValidationJsIncludesElementSnippets(): void
    {
        $form = new XoopsForm('title', 'validate', '/action.php', 'post', false);
        $form->addElement(new DummyFormElement('field', 'Caption', 'if (!myform.field.value) { return false; }'));

        $js = $form->renderValidationJS();

        $this->assertStringContainsString('xoopsFormValidate_validate', $js);
        $this->assertStringContainsString('if (!myform.field.value)', $js);
        $this->assertStringContainsString('<script type=\'text/javascript\'>', $js);
    }

    public function testAssignBuildsTemplateArray(): void
    {
        $form = new XoopsForm('title', 'assign', '/action.php', 'post', false);
        $element = new DummyFormElement('assign_name', 'Caption');
        $element->_required = true;
        $form->addElement($element);
        $form->addElement('raw-chunk');
        $form->setExtra('data-extra="1"');

        $tpl = new DummyTpl();
        $form->assign($tpl);

        $this->assertArrayHasKey('assign', $tpl->assigned);
        $assigned = $tpl->assigned['assign'];
        $this->assertSame('title', $assigned['title']);
        $this->assertSame('assign', $assigned['name']);
        $this->assertStringContainsString('xoopsFormValidate_assign', $assigned['extra']);
        $this->assertSame('<input name="assign_name" />', $assigned['elements']['assign_name']['body']);
        $this->assertTrue($assigned['elements']['assign_name']['required']);
        $this->assertSame('raw-chunk', $assigned['elements'][1]['body']);
    }
}
