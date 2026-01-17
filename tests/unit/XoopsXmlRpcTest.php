<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpctag.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpcparser.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpcapi.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rss/xmlrss2parser.php';

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return $GLOBALS['xml_rpc_test_handlers'][$name] ?? null;
    }
}

class XoopsXmlRpcTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['xml_rpc_test_handlers'] = [];
    }

    public function testTagEncodingAndRendering(): void
    {
        $fault    = new XoopsXmlRpcFault(104, ' details');
        $int      = new XoopsXmlRpcInt('5');
        $double   = new XoopsXmlRpcDouble(1.5);
        $boolean  = new XoopsXmlRpcBoolean(true);
        $string   = new XoopsXmlRpcString('<test>&value');
        $datetime = new XoopsXmlRpcDatetime('2002-01-01 00:00:00');
        $base64   = new XoopsXmlRpcBase64('abc');

        $array = new XoopsXmlRpcArray();
        $array->add($int);
        $array->add($string);

        $struct = new XoopsXmlRpcStruct();
        $struct->add('a', $double);
        $struct->add('b', $boolean);

        $response = new XoopsXmlRpcResponse();
        $response->add($int);
        $response->add($string);

        $faultResponse = new XoopsXmlRpcResponse();
        $faultResponse->add($fault);
        $faultResponse->add($double);

        $request = new XoopsXmlRpcRequest('sample.method');
        $request->add($array);
        $request->add($struct);

        $this->assertSame('<value><int>5</int></value>', $int->render());
        $this->assertSame('<value><double>1.5</double></value>', $double->render());
        $this->assertSame('<value><boolean>1</boolean></value>', $boolean->render());
        $this->assertSame('<value><string>&lt;test&gt;&amp;value</string></value>', $string->render());
        $this->assertSame('<value><dateTime.iso8601>20020101T00:00:00</dateTime.iso8601></value>', $datetime->render());
        $this->assertSame('<value><base64>YWJj</base64></value>', $base64->render());
        $this->assertStringContainsString('<array><data><value><int>5</int></value><value><string>&lt;test&gt;&amp;value</string></value>', $array->render());
        $this->assertStringContainsString('<member><name>a</name><value><double>1.5</double></value>', $struct->render());

        $this->assertSame('<?xml version="1.0"?><methodResponse><params><param><value><int>5</int></value><value><string>&lt;test&gt;&amp;value</string></value></param></params></methodResponse>', $response->render());
        $this->assertSame('<?xml version="1.0"?><methodResponse><fault><value><struct><member><name>faultCode</name><value>104</value></member><member><name>faultString</name><value>User authentication failed\n details</value></member></struct></value></fault></methodResponse>', $faultResponse->render());
        $this->assertSame('<?xml version="1.0"?><methodCall><methodName>sample.method</methodName><params><param><value><array><data><value><int>5</int></value><value><string>&lt;test&gt;&amp;value</string></value></data></array></value></param><param><value><struct><member><name>a</name><value><double>1.5</double></value></member><member><name>b</name><value><boolean>1</boolean></value></member></struct></value></param></params></methodCall>', $request->render());
    }

    public function testApiHelpers(): void
    {
        $params   = [];
        $response = new XoopsXmlRpcResponse();
        $module   = new class {
            public function getVar($name)
            {
                return $name === 'mid' ? 12 : null;
            }
        };
        $api      = new XoopsXmlRpcApi($params, $response, $module);

        $api->_setXoopsTagMap('title', 'blogTitle');
        $this->assertSame('blogTitle', $api->_getXoopsTagMap('title'));
        $this->assertSame('unknown', $api->_getXoopsTagMap('unknown'));

        $text = '<content>keep</content><remove>gone</remove>';
        $this->assertSame('gone', $api->_getTagCdata($text, 'remove'));
        $this->assertStringNotContainsString('remove', $text);

        $api->_setUser('notobject');
        $this->assertNull($api->user ?? null);

        $user = new class {
            public $admin = false;
            public function isAdmin($mid)
            {
                return $this->admin && $mid === 12;
            }
        };
        $api->_setUser($user);
        $this->assertFalse($api->_checkAdmin());
        $user->admin = true;
        $this->assertTrue($api->_checkAdmin());
    }

    public function testCheckUserFlow(): void
    {
        $params   = [];
        $response = new XoopsXmlRpcResponse();
        $module   = new class {
            public function getVar($name)
            {
                return 99;
            }
        };
        $api      = new XoopsXmlRpcApi($params, $response, $module);

        $member = new class {
            public $loginUserArgs;
            public $user;
            public function loginUser($u, $p)
            {
                $this->loginUserArgs = [$u, $p];
                return $this->user;
            }
        };
        $groupPerm = new class {
            public $allowed = false;
            public function checkRight($name, $mid, $groups)
            {
                return $this->allowed;
            }
        };
        $GLOBALS['xml_rpc_test_handlers']['member']   = $member;
        $GLOBALS['xml_rpc_test_handlers']['groupperm'] = $groupPerm;

        $this->assertFalse($api->_checkUser('bob', 'pw'));
        $member->user  = new class {
            public function getGroups()
            {
                return [1, 2];
            }
        };
        $this->assertFalse($api->_checkUser('bob', 'pw'));
        $this->assertSame(['bob', 'pw'], $member->loginUserArgs);

        $groupPerm->allowed = true;
        $this->assertTrue($api->_checkUser('bob', 'pw'));
        $this->assertNotNull($api->user);
    }

    public function testParserExtractsValues(): void
    {
        $xml = '<methodCall><methodName>demo.method</methodName><params>'
            . '<param><value><int>5</int></value></param>'
            . '<param><value><string>text</string></value></param>'
            . '<param><value><boolean>1</boolean></value></param>'
            . '<param><value><dateTime.iso8601>20020101T00:00:00</dateTime.iso8601></value></param>'
            . '<param><value><array><data><value><string>a</string></value><value><int>3</int></value></data></array></value></param>'
            . '<param><value><struct><member><name>key</name><value><string>value</string></value></member></struct></value></param>'
            . '</params></methodCall>';

        $parser = new XoopsXmlRpcParser($xml);
        $parser->parse();

        $this->assertSame('demo.method', $parser->getMethodName());
        $params = $parser->getParam();
        $this->assertSame(5, $params[0]);
        $this->assertSame('text', $params[1]);
        $this->assertSame(1, $params[2]);
        $this->assertSame(gmmktime(0, 0, 0, 1, 1, 2002), $params[3]);
        $this->assertSame(['a', 3], $params[4]);
        $this->assertSame(['key' => 'value'], $params[5]);
    }

    public function testRss2ParserCollections(): void
    {
        $parser = new XoopsXmlRss2Parser('<rss></rss>');
        $value  = 'example';
        $parser->setChannelData('title', $value);
        $parser->setChannelData('title', $value);
        $this->assertSame('exampleexample', $parser->getChannelData('title'));

        $parser->setImageData('url', $value);
        $this->assertSame('example', $parser->getImageData('url'));

        $item = ['id' => 1];
        $parser->setItems($item);
        $this->assertSame([['id' => 1]], $parser->getItems());

        $parser->setTempArr('cat', $value);
        $parser->setTempArr('cat', $value, ',');
        $this->assertSame(['cat' => 'example,example'], $parser->getTempArr());
        $parser->resetTempArr();
        $this->assertSame([], $parser->getTempArr());
    }
}
