<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xml/themesetparser.php';

class ThemeSetParserHandlersTest extends TestCase
{
    private function createParser(): object
    {
        return new class('') extends XoopsThemeSetParser {
            private $parentTag = null;
            private $creditsData = null;

            public function setParentTagOverride(string $tag): void
            {
                $this->parentTag = $tag;
            }

            public function getParentTag()
            {
                return $this->parentTag;
            }

            public function setCreditsData($data): void
            {
                $this->creditsData = $data;
            }

            public function getCreditsData()
            {
                return $this->creditsData;
            }
        };
    }

    /**
     * @dataProvider handlerNameProvider
     */
    public function testHandlerNames(XmlTagHandler $handler, string $expected): void
    {
        $this->assertSame($expected, $handler->getName());
    }

    public function handlerNameProvider(): array
    {
        return [
            [new ThemeSetAuthorHandler(), 'author'],
            [new ThemeSetDateCreatedHandler(), 'dateCreated'],
            [new ThemeSetDescriptionHandler(), 'description'],
            [new ThemeSetEmailHandler(), 'email'],
            [new ThemeSetFileTypeHandler(), 'fileType'],
            [new ThemeSetGeneratorHandler(), 'generator'],
            [new ThemeSetImageHandler(), 'image'],
            [new ThemeSetLinkHandler(), 'link'],
            [new ThemeSetModuleHandler(), 'module'],
            [new ThemeSetNameHandler(), 'name'],
            [new ThemeSetTagHandler(), 'tag'],
            [new ThemeSetTemplateHandler(), 'template'],
        ];
    }

    /**
     * @dataProvider themeSetFieldProvider
     */
    public function testThemeSetLevelCharacterHandlers(XmlTagHandler $handler, string $parent, string $key): void
    {
        $parser = $this->createParser();
        $parser->setParentTagOverride($parent);
        $value = 'value';

        $handler->handleCharacterData($parser, $value);
        $this->assertSame($value, $parser->getThemeSetData($key));
    }

    public function themeSetFieldProvider(): array
    {
        return [
            [new ThemeSetDateCreatedHandler(), 'themeset', 'date'],
            [new ThemeSetGeneratorHandler(), 'themeset', 'generator'],
            [new ThemeSetNameHandler(), 'themeset', 'name'],
        ];
    }

    public function testAuthorHandlersResetAndStoreCredits(): void
    {
        $parser  = $this->createParser();
        $handler = new ThemeSetAuthorHandler();

        $parser->setTempArr('name', 'stale');
        $handler->handleBeginElement($parser, $attributes = []);
        $this->assertSame([], $parser->getTempArr());

        $parser->setParentTagOverride('author');
        (new ThemeSetNameHandler())->handleCharacterData($parser, $name = 'John Doe');
        (new ThemeSetEmailHandler())->handleCharacterData($parser, $email = 'john@example.com');
        (new ThemeSetLinkHandler())->handleCharacterData($parser, $link = 'https://example.com');

        $handler->handleEndElement($parser);

        $this->assertSame(
            ['name' => $name, 'email' => $email, 'link' => $link],
            $parser->getCreditsData()
        );
    }

    /**
     * @dataProvider descriptionHandlerProvider
     */
    public function testDescriptionHandlerRoutesToTemp(XmlTagHandler $handler, string $parent): void
    {
        $parser = $this->createParser();
        $parser->setParentTagOverride($parent);
        $value = 'details';

        $handler->handleCharacterData($parser, $value);

        $this->assertSame(['description' => $value], $parser->getTempArr());
    }

    public function descriptionHandlerProvider(): array
    {
        return [
            [new ThemeSetDescriptionHandler(), 'template'],
            [new ThemeSetDescriptionHandler(), 'image'],
        ];
    }

    public function testTemplateHandlerResetsAndStoresTemplateData(): void
    {
        $parser  = $this->createParser();
        $handler = new ThemeSetTemplateHandler();

        $parser->setTempArr('name', 'stale');
        $handler->handleBeginElement($parser, $attributes = ['name' => 'theme.html']);
        (new ThemeSetModuleHandler())->handleCharacterData($parser, $module = 'system');
        (new ThemeSetFileTypeHandler())->handleCharacterData($parser, $type = 'module');
        (new ThemeSetDescriptionHandler())->handleCharacterData($parser, $desc = 'Main template');

        $handler->handleEndElement($parser);

        $templates = $parser->getTemplatesData();
        $this->assertCount(1, $templates);
        $this->assertSame(
            ['name' => 'theme.html', 'module' => $module, 'type' => $type, 'description' => $desc],
            $templates[0]
        );
    }

    public function testImageHandlerResetsAndStoresImageData(): void
    {
        $parser  = $this->createParser();
        $handler = new ThemeSetImageHandler();

        $parser->setTempArr('name', 'old');
        $handler->handleBeginElement($parser, [0 => 'logo.png']);
        $parser->setParentTagOverride('image');
        (new ThemeSetModuleHandler())->handleCharacterData($parser, $module = 'system');
        (new ThemeSetDescriptionHandler())->handleCharacterData($parser, $desc = 'Logo');
        (new ThemeSetTagHandler())->handleCharacterData($parser, $tag = 'main');

        $handler->handleEndElement($parser);

        $images = $parser->getImagesData();
        $this->assertCount(1, $images);
        $this->assertSame(
            ['name' => 'logo.png', 'module' => $module, 'description' => $desc, 'tag' => $tag],
            $images[0]
        );
    }
}
