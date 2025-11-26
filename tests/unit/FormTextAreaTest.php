<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/textarea/textarea.php';

class FormTextAreaTest extends TestCase
{
    public function testInheritsXoopsEditorAndAppliesConfig(): void
    {
        $editor = new FormTextArea('Caption', 'name', 'value', 8, 12, ['extra' => 'setting']);

        $this->assertInstanceOf(XoopsEditor::class, $editor);
        $this->assertSame(8, $editor->_rows);
        $this->assertSame(12, $editor->_cols);
        $this->assertTrue($editor->isEnabled);
        $this->assertSame('setting', $editor->configs['extra']);
    }
}
