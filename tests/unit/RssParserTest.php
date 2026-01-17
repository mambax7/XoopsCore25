<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xml/rss/xmlrss2parser.php';

class RssParserTest extends TestCase
{
    private function createParser(): object
    {
        return new class('') extends XoopsXmlRss2Parser {
            private $parentTag = null;

            public function setParentTagOverride(string $tag): void
            {
                $this->parentTag = $tag;
            }

            public function getParentTag()
            {
                return $this->parentTag;
            }
        };
    }

    public function testChannelImageAndTempStateHelpers(): void
    {
        $parser = $this->createParser();
        $first  = 'one';
        $second = 'two';

        $parser->setChannelData('title', $first);
        $parser->setChannelData('title', $second);
        $this->assertSame('onetwo', $parser->getChannelData('title'));
        $this->assertIsArray($parser->getChannelData());

        $parser->setImageData('url', $first);
        $this->assertSame('one', $parser->getImageData('url'));
        $this->assertFalse($parser->getImageData('missing'));

        $parser->setTempArr('category', $first);
        $parser->setTempArr('category', $second, ', ');
        $this->assertSame(['category' => 'one, two'], $parser->getTempArr());

        $parser->resetTempArr();
        $this->assertSame([], $parser->getTempArr());
    }

    public function testItemStorage(): void
    {
        $parser = $this->createParser();
        $item   = ['title' => 'Example'];

        $parser->setItems($item);
        $items = $parser->getItems();
        $this->assertCount(1, $items);
        $this->assertSame($item, $items[0]);
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
            [new RssChannelHandler(), 'channel'],
            [new RssTitleHandler(), 'title'],
            [new RssLinkHandler(), 'link'],
            [new RssDescriptionHandler(), 'description'],
            [new RssGeneratorHandler(), 'generator'],
            [new RssCopyrightHandler(), 'copyright'],
            [new RssNameHandler(), 'name'],
            [new RssManagingEditorHandler(), 'managingEditor'],
            [new RssLanguageHandler(), 'language'],
            [new RssWebMasterHandler(), 'webMaster'],
            [new RssDocsHandler(), 'docs'],
            [new RssTtlHandler(), 'ttl'],
            [new RssTextInputHandler(), 'textInput'],
            [new RssLastBuildDateHandler(), 'lastBuildDate'],
            [new RssImageHandler(), 'image'],
            [new RssUrlHandler(), 'url'],
            [new RssWidthHandler(), 'width'],
            [new RssHeightHandler(), 'height'],
            [new RssItemHandler(), 'item'],
            [new RssCategoryHandler(), 'category'],
            [new RssCommentsHandler(), 'comments'],
            [new RssPubDateHandler(), 'pubDate'],
            [new RssGuidHandler(), 'guid'],
            [new RssAuthorHandler(), 'author'],
            [new RssSourceHandler(), 'source'],
        ];
    }

    /**
     * @dataProvider channelCharacterHandlersProvider
     */
    public function testChannelCharacterHandlers(XmlTagHandler $handler, string $parentTag, string $key): void
    {
        $parser = $this->createParser();
        $parser->setParentTagOverride($parentTag);
        $value = 'value';

        $handler->handleCharacterData($parser, $value);
        $this->assertSame($value, $parser->getChannelData($key));
    }

    public function channelCharacterHandlersProvider(): array
    {
        return [
            [new RssTitleHandler(), 'channel', 'title'],
            [new RssLinkHandler(), 'channel', 'link'],
            [new RssDescriptionHandler(), 'channel', 'description'],
            [new RssGeneratorHandler(), 'channel', 'generator'],
            [new RssCopyrightHandler(), 'channel', 'copyright'],
            [new RssManagingEditorHandler(), 'channel', 'editor'],
            [new RssLanguageHandler(), 'channel', 'language'],
            [new RssWebMasterHandler(), 'channel', 'webmaster'],
            [new RssDocsHandler(), 'channel', 'docs'],
            [new RssTtlHandler(), 'channel', 'ttl'],
            [new RssLastBuildDateHandler(), 'channel', 'lastbuilddate'],
            [new RssCategoryHandler(), 'channel', 'category'],
            [new RssPubDateHandler(), 'channel', 'pubdate'],
            [new RssTextInputHandler(), 'channel', 'textinput'],
        ];
    }

    /**
     * @dataProvider imageCharacterHandlersProvider
     */
    public function testImageCharacterHandlers(XmlTagHandler $handler, string $key): void
    {
        $parser = $this->createParser();
        $parser->setParentTagOverride('image');
        $value = 'content';

        $handler->handleCharacterData($parser, $value);
        $this->assertSame($value, $parser->getImageData($key));
    }

    public function imageCharacterHandlersProvider(): array
    {
        return [
            [new RssTitleHandler(), 'title'],
            [new RssLinkHandler(), 'link'],
            [new RssDescriptionHandler(), 'description'],
            [new RssUrlHandler(), 'url'],
            [new RssWidthHandler(), 'width'],
            [new RssHeightHandler(), 'height'],
        ];
    }

    /**
     * @dataProvider itemCharacterHandlersProvider
     */
    public function testItemCharacterHandlers(XmlTagHandler $handler, string $key): void
    {
        $parser = $this->createParser();
        $parser->setParentTagOverride('item');
        $value = 'payload';

        $handler->handleCharacterData($parser, $value);
        $this->assertSame($value, $parser->getTempArr()[$key]);
    }

    public function itemCharacterHandlersProvider(): array
    {
        return [
            [new RssTitleHandler(), 'title'],
            [new RssLinkHandler(), 'link'],
            [new RssDescriptionHandler(), 'description'],
            [new RssCategoryHandler(), 'category'],
            [new RssCommentsHandler(), 'comments'],
            [new RssPubDateHandler(), 'pubdate'],
            [new RssGuidHandler(), 'guid'],
            [new RssAuthorHandler(), 'author'],
        ];
    }

    public function testCategoryHandlerAppendsWithDelimiter(): void
    {
        $parser = $this->createParser();
        $parser->setParentTagOverride('item');
        $handler = new RssCategoryHandler();

        $first = 'news';
        $handler->handleCharacterData($parser, $first);
        $second = 'tech';
        $handler->handleCharacterData($parser, $second);

        $this->assertSame('news, tech', $parser->getTempArr()['category']);
    }

    public function testTextInputHandlerTransfersTempArray(): void
    {
        $parser = $this->createParser();
        $handler = new RssTextInputHandler();

        $parser->setTempArr('title', 'old');
        $handler->handleBeginElement($parser, $attributes = []);
        $parser->setTempArr('title', 'new');
        $handler->handleEndElement($parser);

        $this->assertSame(['title' => 'new'], $parser->getChannelData('textinput'));
    }

    public function testItemHandlerResetsAndStoresItems(): void
    {
        $parser = $this->createParser();
        $handler = new RssItemHandler();

        $parser->setTempArr('title', 'stale');
        $handler->handleBeginElement($parser, $attributes = []);
        $this->assertSame([], $parser->getTempArr());

        $parser->setTempArr('title', 'fresh');
        $handler->handleEndElement($parser);

        $items = $parser->getItems();
        $this->assertCount(1, $items);
        $this->assertSame('fresh', $items[0]['title']);
    }

    public function testSourceHandlerSetsUrlAndTitle(): void
    {
        $parser = $this->createParser();
        $parser->setParentTagOverride('item');
        $handler = new RssSourceHandler();

        $attributes = ['url' => 'https://example.com'];
        $handler->handleBeginElement($parser, $attributes);
        $title = 'Example Source';
        $handler->handleCharacterData($parser, $title);

        $temp = $parser->getTempArr();
        $this->assertSame($attributes['url'], $temp['source_url']);
        $this->assertSame($title, $temp['source']);
    }
}
