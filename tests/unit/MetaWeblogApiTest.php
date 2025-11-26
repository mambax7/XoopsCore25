<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/metaweblogapi.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpctag.php';

class MetaWeblogApiTest extends TestCase
{
    public function testConstructorSetsTagMap(): void
    {
        $params = [];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();

        $api = new MetaWeblogApi($params, $response, $module);

        $this->assertSame('postid', $api->_getXoopsTagMap('storyid'));
        $this->assertSame('dateCreated', $api->_getXoopsTagMap('published'));
        $this->assertSame('userid', $api->_getXoopsTagMap('uid'));
    }

    public function testNewPostAddsAuthFaultWhenUserInvalid(): void
    {
        $params = ['blog', 'user', 'badpass', []];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new MetaWeblogApiDouble($params, $response, $module);
        $api->checkUserResult = false;

        $api->newPost();

        $this->assertCount(1, $response->_tags);
        $fault = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
        $this->assertSame(104, $fault->_code);
    }

    public function testNewPostReportsMissingRequiredFields(): void
    {
        $params = ['blog', 'user', 'pass', ['description' => '<title></title>']];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new MetaWeblogApiDouble($params, $response, $module);
        $api->postFields = ['title' => ['required' => true]];
        $api->tagValues['title'] = '';

        $api->newPost();

        $this->assertCount(1, $response->_tags);
        $fault = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
        $this->assertSame(109, $fault->_code);
        $this->assertStringContainsString('<title>', $fault->_extra);
    }

    public function testNewPostForwardsToXoopsApiWithMappedParams(): void
    {
        $params = [
            'blog123',
            'writer',
            'secret',
            ['description' => '<title>Hello</title><hometext>Body</hometext>'],
            true,
        ];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new MetaWeblogApiDouble($params, $response, $module);
        $api->postFields = [
            'title' => ['required' => true],
            'hometext' => ['required' => false],
        ];
        $api->tagValues = [
            'title' => 'Hello',
            'hometext' => 'Body',
        ];
        $api->xoopsApi = new MetaWeblogApiXoopsApiStub($params, $response, $module);
        $api->user = new stdClass();
        $api->isadmin = true;

        $api->newPost();

        $expectedParams = [
            'blog123',
            'writer',
            'secret',
            [
                'title' => 'Hello',
                'hometext' => 'Body',
                'xoops_text' => '<title>Hello</title><hometext>Body</hometext>',
            ],
            true,
        ];
        $this->assertSame($expectedParams, $api->capturedParams);
        $this->assertSame([$api->user, true], $api->xoopsApi->userSet);
        $this->assertTrue($api->xoopsApi->newPostCalled);
    }

    private function createDummyModule(): object
    {
        return new class {
            public function getVar($name)
            {
                return $name;
            }
        };
    }
}

class MetaWeblogApiDouble extends MetaWeblogApi
{
    public $postFields = [];
    public $tagValues = [];
    public $xoopsApi;
    public $capturedParams;
    public $checkUserResult = true;

    public function _checkUser($username, $password)
    {
        return $this->checkUserResult;
    }

    public function &_getPostFields($post_id = null, $blog_id = null)
    {
        return $this->postFields;
    }

    public function _getTagCdata(&$text, $tag, $remove = true)
    {
        return $this->tagValues[$tag] ?? '';
    }

    public function _getXoopsApi(&$params)
    {
        $this->capturedParams = $params;

        return $this->xoopsApi;
    }
}

class MetaWeblogApiXoopsApiStub extends XoopsXmlRpcApi
{
    public $userSet;
    public $newPostCalled = false;

    public function __construct(&$params, &$response, &$module)
    {
        parent::__construct($params, $response, $module);
    }

    public function _setUser(&$user, $isadmin = false)
    {
        $this->userSet = [$user, $isadmin];
    }

    public function newPost(): void
    {
        $this->newPostCalled = true;
    }
}
