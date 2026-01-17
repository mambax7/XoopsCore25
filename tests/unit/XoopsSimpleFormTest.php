<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/simpleform.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';

if (!class_exists('XoopsSecurity')) {
    class XoopsSecurity
    {
        public function createToken($timeout = 0, $name = 'XOOPS_TOKEN')
        {
            return 'token';
        }
    }
}

if (!class_exists('DummySimpleFormElement')) {
    class DummySimpleFormElement extends XoopsFormElement
    {
        private bool $hidden;
        private string $body;

        public function __construct(string $name, string $caption, string $body, bool $hidden = false)
        {
            $this->hidden  = $hidden;
            $this->body    = $body;
            $this->setName($name);
            $this->setCaption($caption);
        }

        public function isContainer()
        {
            return false;
        }

        public function isHidden()
        {
            return $this->hidden;
        }

        public function render()
        {
            return $this->body;
        }
    }
}

class XoopsSimpleFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['xoopsSecurity'] = new XoopsSecurity();
    }

    public function testRenderOutputsMinimalFormattedForm(): void
    {
        $form = new XoopsSimpleForm('My Title', 'my-form', '/submit.php', 'post', false);

        $visible = new DummySimpleFormElement('visible', 'Visible caption', '<input name="visible" />');
        $hidden  = new DummySimpleFormElement('hidden', 'Hidden caption', '<input type="hidden" name="hidden" />', true);

        $form->addElement($visible);
        $form->addElement($hidden);

        $rendered = $form->render();

        $expected  = "My Title\n";
        $expected .= "<form name='my-form' id='my-form' action='/submit.php' method='post'>\n";
        $expected .= "<strong>Visible caption</strong><br><input name=\"visible\" /><br>\n";
        $expected .= "<input type=\"hidden\" name=\"hidden\" />\n";
        $expected .= "</form>\n";

        $this->assertSame($expected, $rendered);
    }
}
