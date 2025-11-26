<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xml/themesetparser.php';

if (!class_exists('ThemeSetThemeNameHandler')) {
    class ThemeSetThemeNameHandler extends XmlTagHandler
    {
        public function __construct() {}

        public function getName()
        {
            return 'themeset';
        }
    }
}

class XoopsThemeSetParserClassTest extends TestCase
{
    public function testConstructorRegistersHandlers(): void
    {
        $parser = new XoopsThemeSetParser('');

        $this->assertCount(
            12,
            $parser->tagHandlers,
            'Constructor should register all theme set tag handlers'
        );

        $expectedClasses = [
            ThemeSetThemeNameHandler::class,
            ThemeSetDateCreatedHandler::class,
            ThemeSetAuthorHandler::class,
            ThemeSetDescriptionHandler::class,
            ThemeSetGeneratorHandler::class,
            ThemeSetNameHandler::class,
            ThemeSetEmailHandler::class,
            ThemeSetLinkHandler::class,
            ThemeSetTemplateHandler::class,
            ThemeSetImageHandler::class,
            ThemeSetModuleHandler::class,
            ThemeSetFileTypeHandler::class,
            ThemeSetTagHandler::class,
        ];

        $actualClasses = array_map(function ($handler) {
            return get_class($handler);
        }, $parser->tagHandlers);

        foreach ($expectedClasses as $expected) {
            $this->assertContains($expected, $actualClasses);
        }
    }

    public function testThemeSetDataHelpers(): void
    {
        $parser = new XoopsThemeSetParser('');
        $value  = 'XOOPS Modern';

        $parser->setThemeSetData('name', $value);

        $this->assertSame($value, $parser->getThemeSetData('name'));
        $this->assertArrayHasKey('name', $parser->getThemeSetData());
        $this->assertFalse($parser->getThemeSetData('missing'));
    }

    public function testImageDataStoredByReference(): void
    {
        $parser = new XoopsThemeSetParser('');
        $image  = ['name' => 'logo.png'];

        $parser->setImagesData($image);
        $image['name'] = 'logo-updated.png';

        $images = $parser->getImagesData();
        $this->assertSame('logo-updated.png', $images[0]['name']);
    }

    public function testTemplateDataStoredByReference(): void
    {
        $parser   = new XoopsThemeSetParser('');
        $template = ['name' => 'theme.html'];

        $parser->setTemplatesData($template);
        $template['name'] = 'theme-new.html';

        $templates = $parser->getTemplatesData();
        $this->assertSame('theme-new.html', $templates[0]['name']);
    }

    public function testTempArrayAccumulationAndReset(): void
    {
        $parser = new XoopsThemeSetParser('');

        $first  = 'first';
        $second = 'second';

        $parser->setTempArr('value', $first);
        $parser->setTempArr('value', $second, ',');

        $this->assertSame(['value' => 'first,second'], $parser->getTempArr());

        $parser->resetTempArr();
        $this->assertSame([], $parser->getTempArr());
    }
}
