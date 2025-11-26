<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xml/xmltaghandler.php';

class XmlTagHandlerTest extends TestCase
{
    public function testGetNameDefaultsToEmptyString(): void
    {
        $handler = new XmlTagHandler();

        $this->assertSame('', $handler->getName());
    }

    public function testDefaultHandlersAreNoOps(): void
    {
        $handler = new XmlTagHandler();
        $parser  = new stdClass();
        $attributes = ['foo' => 'bar'];
        $data       = 'content';

        $this->assertNull($handler->handleBeginElement($parser, $attributes));
        $this->assertSame(['foo' => 'bar'], $attributes, 'Attributes are unchanged');

        $this->assertNull($handler->handleCharacterData($parser, $data));
        $this->assertSame('content', $data, 'Data is unchanged');

        $this->assertNull($handler->handleEndElement($parser));
    }
}
