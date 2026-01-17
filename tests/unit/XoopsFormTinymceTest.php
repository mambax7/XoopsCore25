<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce/formtinymce.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce5/formtinymce.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce7/formtinymce.php';

if (!defined('_LANGCODE')) {
    define('_LANGCODE', 'en');
}
if (!defined('_CHARSET')) {
    define('_CHARSET', 'utf-8');
}
if (!defined('_FORM_ENTER')) {
    define('_FORM_ENTER', 'Enter %s');
}

class XoopsFormTinymceTest extends TestCase
{
    public function testTinymceConfigDefaultsAndFonts(): void
    {
        if (!defined('_XOOPS_EDITOR_TINYMCE_FONTS')) {
            define('_XOOPS_EDITOR_TINYMCE_FONTS', 'Arial');
        }

        $editor = new XoopsFormTinymce(['name' => 'content', 'caption' => 'Caption']);

        $this->assertInstanceOf(XoopsEditor::class, $editor);
        $this->assertSame('content', $editor->configs['elements']);
        $this->assertSame('en_utf8', $editor->configs['language']);
        $this->assertSame('/class/xoopseditor/tinymce', $editor->configs['rootpath']);
        $this->assertSame('100%', $editor->configs['area_width']);
        $this->assertSame('500px', $editor->configs['area_height']);
        $this->assertSame('Arial', $editor->configs['fonts']);
        $this->assertInstanceOf(TinyMCE::class, $editor->editor);
        $this->assertTrue($editor->isActive());
    }

    public function testTinymce5LanguageOverrideAndValidation(): void
    {
        if (!defined('_XOOPS_EDITOR_TINYMCE5_LANGUAGE')) {
            define('_XOOPS_EDITOR_TINYMCE5_LANGUAGE', 'ES');
        }

        $editor = new XoopsFormTinymce5(['name' => 'body', 'caption' => 'Body']);
        $editor->_required = true;

        $this->assertSame('body', $editor->configs['elements']);
        $this->assertSame('es', $editor->configs['language']);
        $this->assertSame('100%', $editor->configs['area_width']);
        $this->assertSame('500px', $editor->configs['area_height']);
        $this->assertInstanceOf(TinyMCE::class, $editor->editor);
        $this->assertStringContainsString("tinymce.get('body')", $editor->renderValidationJS());
        $this->assertStringContainsString('Enter Body', $editor->renderValidationJS());
        $this->assertTrue($editor->isActive());
    }

    public function testTinymce7SelectorAndDimensions(): void
    {
        $editor = new XoopsFormTinymce7([
            'name' => 'field',
            'caption' => 'Field',
            'width' => '250px',
            'height' => '150px',
        ]);

        $this->assertSame('#field', $editor->configs['selector']);
        $this->assertSame('en_utf8', $editor->configs['language']);
        $this->assertSame('/class/xoopseditor/tinymce7', $editor->configs['rootpath']);
        $this->assertSame('250px', $editor->configs['area_width']);
        $this->assertSame('150px', $editor->configs['area_height']);
        $this->assertInstanceOf(TinyMCE::class, $editor->editor);
        $this->assertTrue($editor->isActive());
    }
}
