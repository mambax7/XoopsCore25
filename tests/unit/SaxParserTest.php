<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xml/saxparser.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xml/xmltaghandler.php';

class RecordingTagHandler extends XmlTagHandler
{
    private $name;
    public $begins = [];
    public $ends = 0;
    public $characters = [];

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function handleBeginElement($parser, &$attributes)
    {
        $this->begins[] = $attributes;
    }

    public function handleEndElement($parser)
    {
        $this->ends++;
    }

    public function handleCharacterData($parser, &$data)
    {
        $this->characters[] = $data;
    }
}

class SaxParserTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $parser = new SaxParser('<root />');

        $this->assertSame(0, $parser->getCurrentLevel());
        $this->assertFalse($parser->isCaseFolding);
        $this->assertSame('UTF-8', $parser->targetEncoding);
        $this->assertSame('UTF-8', xml_parser_get_option($parser->parser, XML_OPTION_TARGET_ENCODING));
    }

    public function testCaseFoldingAndEncodingOptions(): void
    {
        $parser = new SaxParser('<root />');

        $parser->setCaseFolding(true);
        $this->assertTrue($parser->isCaseFolding);
        $this->assertSame(1, xml_parser_get_option($parser->parser, XML_OPTION_CASE_FOLDING));

        $parser->useIsoEncoding();
        $this->assertSame('ISO-8859-1', $parser->targetEncoding);
        $this->assertSame('ISO-8859-1', xml_parser_get_option($parser->parser, XML_OPTION_TARGET_ENCODING));

        $parser->useAsciiEncoding();
        $this->assertSame('US-ASCII', $parser->targetEncoding);
        $this->assertSame('US-ASCII', xml_parser_get_option($parser->parser, XML_OPTION_TARGET_ENCODING));
    }

    public function testAddTagHandlerRegistersNames(): void
    {
        $arrayHandler = new RecordingTagHandler(['FIRST', 'SECOND']);
        $singleHandler = new RecordingTagHandler('THIRD');
        $parser = new SaxParser('<root />');

        $parser->addTagHandler($arrayHandler);
        $parser->addTagHandler($singleHandler);

        $this->assertSame($arrayHandler, $parser->tagHandlers['FIRST']);
        $this->assertSame($arrayHandler, $parser->tagHandlers['SECOND']);
        $this->assertSame($singleHandler, $parser->tagHandlers['THIRD']);
    }

    public function testTagStackAndHandlerRouting(): void
    {
        $handler = new RecordingTagHandler('TAG');
        $parser = new SaxParser('<root />');
        $parser->addTagHandler($handler);

        $parser->handleBeginElement($parser->parser, 'PARENT', []);
        $parser->handleBeginElement($parser->parser, 'TAG', ['id' => '1']);

        $this->assertSame('TAG', $parser->getCurrentTag());
        $this->assertSame('PARENT', $parser->getParentTag());
        $this->assertSame(2, $parser->getCurrentLevel());

        $parser->handleCharacterData($parser->parser, 'content');
        $parser->handleEndElement($parser->parser, 'TAG');
        $parser->handleEndElement($parser->parser, 'PARENT');

        $this->assertSame([['id' => '1']], $handler->begins);
        $this->assertSame(['content'], $handler->characters);
        $this->assertSame(1, $handler->ends);
        $this->assertSame(0, $parser->getCurrentLevel());
        $this->assertFalse($parser->getParentTag());
    }

    public function testParseStringInvokesHandlers(): void
    {
        $handler = new RecordingTagHandler('ITEM');
        $parser = new SaxParser('<root><ITEM attr="1">text</ITEM></root>');
        $parser->addTagHandler($handler);

        $this->assertTrue($parser->parse());
        $this->assertSame([['ATTR' => '1']], $handler->begins);
        $this->assertContains('text', $handler->characters);
        $this->assertSame(1, $handler->ends);
    }

    public function testParseResourceStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "<root><target attr='v'>body</target></root>");
        rewind($stream);

        $handler = new RecordingTagHandler('target');
        $parser = new SaxParser($stream);
        $parser->addTagHandler($handler);

        $this->assertTrue($parser->parse());
        $this->assertSame([['attr' => 'v']], $handler->begins);
        $this->assertSame(['body'], $handler->characters);
        $this->assertSame(1, $handler->ends);
    }

    public function testParseStoresErrorsWhenXmlInvalid(): void
    {
        $parser = new SaxParser('<root><broken></root>');
        $this->assertFalse($parser->parse());

        $rawErrors = $parser->getErrors(false);
        $this->assertNotEmpty($rawErrors);
        $this->assertStringContainsString('XmlParse error', $rawErrors[0]);

        $htmlErrors = $parser->getErrors();
        $this->assertStringContainsString('<br>', $htmlErrors);
    }

    public function testSetErrorsTrimsMessages(): void
    {
        $parser = new SaxParser('<root />');
        $parser->setErrors('  message  ');

        $this->assertSame(['message'], $parser->getErrors(false));
    }
}
