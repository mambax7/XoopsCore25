<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/bloggerapi.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpctag.php';

class BloggerApiTest extends TestCase
{
    public function testConstructorSetsTagMap(): void
    {
        $params = [];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();

        $api = new BloggerApi($params, $response, $module);

        $this->assertSame('postid', $api->_getXoopsTagMap('storyid'));
        $this->assertSame('dateCreated', $api->_getXoopsTagMap('published'));
        $this->assertSame('userid', $api->_getXoopsTagMap('uid'));
    }

    public function testNewPostAddsAuthFaultWhenUserInvalid(): void
    {
        $params = [null, 'blog', 'user', 'badpass'];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new BloggerApiDouble($params, $response, $module);
        $api->checkUserResult = false;

        $api->newPost();

        $this->assertCount(1, $response->_tags);
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $response->_tags[0]);
        $this->assertSame(104, $response->_tags[0]->_code);
    }

    public function testNewPostReportsMissingRequiredFields(): void
    {
        $params = [null, 'blog', 'user', 'pass', '<title></title>'];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new BloggerApiDouble($params, $response, $module);
        $api->postFields = ['title' => ['required' => true]];

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
            'app',
            'blog123',
            'writer',
            'secret',
            '<title>Hello</title><hometext>Body</hometext>',
            true,
        ];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new BloggerApiDouble($params, $response, $module);
        $api->postFields = [
            'title' => ['required' => true],
            'hometext' => ['required' => false],
        ];
        $api->xoopsApi = new BloggerApiXoopsApiStub($params, $response, $module);
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

class BloggerApiDouble extends BloggerApi
{
    public $postFields = [];
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

    public function _getXoopsApi(&$params)
    {
        $this->capturedParams = $params;

        return $this->xoopsApi;
    }
}

class BloggerApiXoopsApiStub extends XoopsXmlRpcApi
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
