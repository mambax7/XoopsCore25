<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/form.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/tableform.php';

if (!defined('NWLINE')) {
    define('NWLINE', "\n");
}

if (!class_exists('TableDummyElement')) {
    class TableDummyElement extends XoopsFormElement
    {
        private string $rendered;

        public function __construct(string $name, string $caption = '', string $rendered = '', string $description = '')
        {
            $this->setName($name);
            $this->setCaption($caption);
            $this->rendered = $rendered ?: '<input name="' . $name . '" />';
            if ($description !== '') {
                $this->setDescription($description);
            }
        }

        public function isContainer()
        {
            return false;
        }

        public function render()
        {
            return $this->rendered;
        }
    }
}

class XoopsTableFormTest extends TestCase
{
    public function testRenderRendersTableWithColspanAndHiddenElements(): void
    {
        $form = new XoopsTableForm('Table Title', 'tbl', '/submit.php', 'post', false);
        $first = new TableDummyElement('first', 'First caption', '<input id="first" />', 'First description');
        $second = new TableDummyElement('second', 'Second caption', '<textarea id="second"></textarea>');
        $second->setNocolspan(true);
        $hidden = new TableDummyElement('hid', 'Hidden caption', '<input type="hidden" name="hid" />');
        $hidden->setHidden();

        $form->addElement($first);
        $form->addElement($second);
        $form->addElement($hidden);

        $output = $form->render();

        $this->assertStringContainsString('<form name="tbl" id="tbl" action="/submit.php" method="post">', $output);
        $this->assertStringContainsString('<table border="0" width="100%">', $output);
        $this->assertStringContainsString('<td>First caption', $output);
        $this->assertStringContainsString('<span style="font-weight: normal;">First description</span>', $output);
        $this->assertStringContainsString('</td><td><input id="first" /></td></tr>', $output);
        $this->assertStringContainsString('<td colspan="2">Second caption</td></tr><tr valign="top" align="left"><td><textarea id="second"></textarea></td></tr>', $output);
        $this->assertStringContainsString('<input type="hidden" name="hid" />', $output);

        $tableEndPos = strpos($output, '</table>');
        $hiddenPos   = strpos($output, '<input type="hidden" name="hid" />');
        $this->assertNotFalse($tableEndPos);
        $this->assertNotFalse($hiddenPos);
        $this->assertGreaterThan($tableEndPos, $hiddenPos);
    }
}
