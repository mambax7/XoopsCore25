<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../htdocs/class/xoopsform/renderer/XoopsFormRendererInterface.php';
require_once __DIR__ . '/../../htdocs/class/xoopsform/renderer/XoopsFormRendererBootstrap3.php';
require_once __DIR__ . '/../../htdocs/class/xoopsform/renderer/XoopsFormRendererBootstrap4.php';
require_once __DIR__ . '/../../htdocs/class/xoopsform/renderer/XoopsFormRendererBootstrap5.php';

if (!defined('_DELETE')) {
    define('_DELETE', 'Delete Me');
}
if (!defined('_CANCEL')) {
    define('_CANCEL', 'Cancel Now');
}
if (!defined('_RESET')) {
    define('_RESET', 'Reset All');
}

if (!class_exists('XoopsFormButton')) {
    class XoopsFormButton
    {
        protected $type;
        protected $name;
        protected $value;
        protected $extra;

        public function __construct($type, $name, $value, $extra = '')
        {
            $this->type = $type;
            $this->name = $name;
            $this->value = $value;
            $this->extra = $extra;
        }

        public function getType()
        {
            return $this->type;
        }

        public function getName()
        {
            return $this->name;
        }

        public function getValue()
        {
            return $this->value;
        }

        public function getExtra()
        {
            return $this->extra;
        }
    }
}

if (!class_exists('XoopsFormButtonTray')) {
    class XoopsFormButtonTray extends XoopsFormButton
    {
        public $_showDelete = false;
    }
}

if (!class_exists('XoopsFormCheckBox')) {
    class XoopsFormCheckBox
    {
        public $columns = 0;
        protected $name;
        protected $options;
        protected $value;
        protected $extra;
        protected $delimeter;

        public function __construct($name, array $options, $value, $columns = 0, $extra = '', $delimeter = '')
        {
            $this->name = $name;
            $this->options = $options;
            $this->value = $value;
            $this->columns = $columns;
            $this->extra = $extra;
            $this->delimeter = $delimeter;
        }

        public function getName()
        {
            return $this->name;
        }

        public function setName($name)
        {
            $this->name = $name;
        }

        public function getOptions()
        {
            return $this->options;
        }

        public function getValue()
        {
            return $this->value;
        }

        public function getExtra()
        {
            return $this->extra;
        }

        public function getDelimeter()
        {
            return $this->delimeter;
        }
    }
}

/**
 * @covers XoopsFormRendererBootstrap3
 * @covers XoopsFormRendererBootstrap4
 * @covers XoopsFormRendererBootstrap5
 */
class XoopsFormRendererBootstrapTest extends TestCase
{
    public function buttonProvider()
    {
        return [
            ['XoopsFormRendererBootstrap3', 'btn btn-default'],
            ['XoopsFormRendererBootstrap4', 'btn btn-secondary'],
            ['XoopsFormRendererBootstrap5', 'btn btn-secondary'],
        ];
    }

    /**
     * @dataProvider buttonProvider
     */
    public function testRenderFormButtonAddsExpectedBootstrapClass($className, $expectedClass)
    {
        $renderer = new $className();
        $button = new XoopsFormButton('submit', 'save', 'Save', ' data-extra="yes"');

        $html = $renderer->renderFormButton($button);

        $this->assertStringContainsString($expectedClass, $html);
        $this->assertMatchesRegularExpression('/type=["\']submit["\']/', $html);
        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('data-extra="yes"', $html);
    }

    public function buttonTrayProvider()
    {
        return [
            ['XoopsFormRendererBootstrap3', 'btn btn-danger', 'btn btn-success', "type=\"submit\""],
            ['XoopsFormRendererBootstrap4', 'btn btn-danger', 'btn btn-success', "type=\"submit\""],
            ['XoopsFormRendererBootstrap5', 'btn btn-danger', 'btn btn-success', "type=\"submit\""],
        ];
    }

    /**
     * @dataProvider buttonTrayProvider
     */
    public function testRenderFormButtonTrayShowsDeleteAndSubmit($className, $deleteClass, $submitClass, $submitType)
    {
        $renderer = new $className();
        $tray = new XoopsFormButtonTray('submit', 'go', 'Go!', ' data-extra="tray"');
        $tray->_showDelete = true;

        $html = $renderer->renderFormButtonTray($tray);

        $this->assertStringContainsString($deleteClass, $html);
        $this->assertStringContainsString(_DELETE, $html);
        $this->assertStringContainsString(_CANCEL, $html);
        $this->assertStringContainsString($submitClass, $html);
        $this->assertMatchesRegularExpression('/type=["\']submit["\']/', $html);
        $this->assertStringContainsString('data-extra="tray"', $html);
    }

    public function checkBoxProvider()
    {
        $options = [
            'one' => 'First Option',
            'two' => 'Second Option',
        ];

        return [
            ['XoopsFormRendererBootstrap3', $options],
            ['XoopsFormRendererBootstrap4', $options],
            ['XoopsFormRendererBootstrap5', $options],
        ];
    }

    /**
     * @dataProvider checkBoxProvider
     */
    public function testRenderFormCheckBoxRespectsColumnsAndChecksValues($className, $options)
    {
        $renderer = new $className();
        $element = new XoopsFormCheckBox('choices', $options, ['two'], 0, ' data-extra="check"', ' | ');

        $html = $renderer->renderFormCheckBox($element);
        $this->assertStringContainsString('checkbox-inline', $html);
        $this->assertStringContainsString('choices[]', $html);
        $this->assertStringContainsString('data-extra="check"', $html);
        $this->assertStringContainsString('checked', $html);

        $element->columns = 2;
        $html = $renderer->renderFormCheckBox($element);
        $this->assertStringContainsString('col-md-2', $html);
    }
}
