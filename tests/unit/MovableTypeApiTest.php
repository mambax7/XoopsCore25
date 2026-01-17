<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/movabletypeapi.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpctag.php';

class MovableTypeApiTest extends TestCase
{
    public function testGetCategoryListAddsAuthFaultWhenUserInvalid(): void
    {
        $params = ['blog', 'user', 'badpass'];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new MovableTypeApiDouble($params, $response, $module);
        $api->checkUserResult = false;

        $api->getCategoryList();

        $this->assertCount(1, $response->_tags);
        $fault = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
        $this->assertSame(104, $fault->_code);
    }

    public function testGetCategoryListReturnsCategories(): void
    {
        $params = ['blog', 'user', 'pass'];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new MovableTypeApiDouble($params, $response, $module);
        $api->categories = [
            1 => ['title' => 'News'],
            2 => ['title' => 'Tech'],
        ];
        $api->user = new stdClass();
        $api->isadmin = true;

        $api->getCategoryList();

        $this->assertCount(1, $response->_tags);
        $arrayTag = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcArray::class, $arrayTag);
        $this->assertCount(2, $arrayTag->_tags);
        $firstStruct = $arrayTag->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcStruct::class, $firstStruct);
        $this->assertSame('categoryId', $firstStruct->_tags[0]['name']);
        $this->assertSame('1', $firstStruct->_tags[0]['value']->_value);
        $this->assertSame('categoryName', $firstStruct->_tags[1]['name']);
        $this->assertSame('News', $firstStruct->_tags[1]['value']->_value);
    }

    public function testUnsupportedMethodsReturnFault(): void
    {
        $params = ['blog', 'user', 'pass'];
        $response = new XoopsXmlRpcResponse();
        $module = $this->createDummyModule();
        $api = new MovableTypeApiDouble($params, $response, $module);

        $api->getPostCategories();
        $api->setPostCategories();
        $api->supportedMethods();

        $this->assertCount(3, $response->_tags);
        foreach ($response->_tags as $fault) {
            $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
            $this->assertSame(107, $fault->_code);
        }
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

class MovableTypeApiDouble extends MovableTypeApi
{
    public $checkUserResult = true;
    public $categories = [];
    public $user;
    public $isadmin = false;
    public $xoopsApi;

    public function _checkUser($username, $password)
    {
        return $this->checkUserResult;
    }

    public function _getXoopsApi(&$params)
    {
        $this->xoopsApi = new MovableTypeApiXoopsStub($params, $this->response, $this->module, $this->categories);

        return $this->xoopsApi;
    }
}

class MovableTypeApiXoopsStub extends XoopsXmlRpcApi
{
    public $categories;

    public function __construct(&$params, &$response, &$module, array $categories)
    {
        parent::__construct($params, $response, $module);
        $this->categories = $categories;
    }

    public function _setUser(&$user, $isadmin = false)
    {
        $this->user = $user;
        $this->isadmin = $isadmin;
    }

    public function &getCategories($asstruct = false)
    {
        return $this->categories;
    }
}
