<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xoopsapi.php';
require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpctag.php';

class XoopsApiTest extends TestCase
{
    private string $newsStoryFile;

    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $newsStoryDir = XOOPS_ROOT_PATH . '/modules/news/class';
        $this->newsStoryFile = $newsStoryDir . '/class.newsstory.php';
        if (!is_dir($newsStoryDir)) {
            mkdir($newsStoryDir, 0777, true);
        }
        if (!file_exists($this->newsStoryFile)) {
            file_put_contents($this->newsStoryFile, $this->buildNewsStoryStub());
        }
        require_once $this->newsStoryFile;
        if (method_exists(NewsStory::class, 'reset')) {
            NewsStory::reset();
        }
    }

    public function testNewPostAddsAuthFaultWhenUserInvalid(): void
    {
        $params = ['blog', 'baduser', 'badpass', []];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->checkUserResult = false;

        $api->newPost();

        $this->assertCount(1, $response->_tags);
        $fault = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
        $this->assertSame(104, $fault->_code);
    }

    public function testNewPostRequiresAdminToPublishImmediately(): void
    {
        $params = ['blog', 'user', 'pass', ['title' => 'Hello'], 1];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->postFields = ['title' => ['required' => true]];
        $api->isAdminResult = false;

        $api->newPost();

        $this->assertCount(1, $response->_tags);
        $fault = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
        $this->assertSame(111, $fault->_code);
    }

    public function testNewPostStoresStoryAndReturnsId(): void
    {
        $params = ['blog', 'user', 'pass', ['title' => 'Hello', 'hometext' => 'Body', 'moretext' => 'More'], 1];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->postFields = [
            'title' => ['required' => true],
            'hometext' => ['required' => false],
            'moretext' => ['required' => false],
        ];
        $api->tagValues = [];
        $api->user = new class {
            public function getVar($name)
            {
                return 42;
            }
        };
        $api->isadmin = true;
        NewsStory::$storeResult = 789;

        $api->newPost();

        $this->assertCount(1, $response->_tags);
        $result = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcString::class, $result);
        $this->assertSame('789', $result->_value);
    }

    public function testEditPostAddsMissingFieldFault(): void
    {
        $params = ['id', 'user', 'pass', ['xoops_text' => '<title></title>'], true];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->postFields = ['title' => ['required' => true]];
        $api->tagValues = ['title' => ''];

        $api->editPost();

        $this->assertCount(1, $response->_tags);
        $fault = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
        $this->assertSame(109, $fault->_code);
    }

    public function testEditPostRequiresAdmin(): void
    {
        $params = ['id', 'user', 'pass', ['title' => 'Updated', 'xoops_text' => '<title>Updated</title>'], true];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->postFields = ['title' => ['required' => true]];
        $api->tagValues = ['title' => 'Updated'];
        $api->isAdminResult = false;
        NewsStory::$storeResult = true;

        $api->editPost();

        $this->assertCount(1, $response->_tags);
        $fault = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcFault::class, $fault);
        $this->assertSame(111, $fault->_code);
    }

    public function testDeletePostRemovesStoryWhenAdmin(): void
    {
        $params = ['story', 'user', 'pass'];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->isAdminResult = true;
        NewsStory::$deleteResult = true;

        $api->deletePost();

        $this->assertCount(1, $response->_tags);
        $result = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcBoolean::class, $result);
        $this->assertSame(1, $result->_value);
    }

    public function testGetPostReturnsStructuredResponse(): void
    {
        $params = ['story', 'user', 'pass'];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->isAdminResult = true;
        $api->user = new stdClass();

        $story = new NewsStory('story');
        $story->setUid(55);
        $story->setPublished(1234567890);
        $story->setTitle('Headline');
        $story->setHometext('Intro');
        $story->setBodytext('Details');

        $api->getPost();

        $this->assertCount(1, $response->_tags);
        $struct = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcStruct::class, $struct);
        $names = array_column($struct->_tags, 'name');
        $this->assertContains('postid', $names);
        $this->assertContains('description', $names);
    }

    public function testGetRecentPostsBuildsArray(): void
    {
        $params = ['blog', 'user', 'pass', 0, 2];
        $response = new XoopsXmlRpcResponse();
        $api = new XoopsApiDouble($params, $response, $this->createDummyModule());
        $api->isAdminResult = true;
        $api->user = new stdClass();
        $first = new NewsStory(7);
        $first->setUid(1);
        $first->setPublished(10);
        $first->setTitle('First');
        $first->setHometext('Intro');
        $first->setBodytext('Body');
        $second = new NewsStory(8);
        $second->setUid(2);
        $second->setPublished(20);
        $second->setTitle('Second');
        $second->setHometext('Intro2');
        $second->setBodytext('Body2');
        NewsStory::$allPublished = [$first, $second];

        $api->getRecentPosts();

        $this->assertCount(1, $response->_tags);
        $arrayTag = $response->_tags[0];
        $this->assertInstanceOf(XoopsXmlRpcArray::class, $arrayTag);
        $this->assertCount(2, $arrayTag->_tags);
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

    private function buildNewsStoryStub(): string
    {
        return <<<'PHP'
<?php
class NewsStory
{
    public static $storeResult = 123;
    public static $deleteResult = true;
    public static $allPublished = [];

    public $id;
    public $uid = 0;
    public $published = 0;
    public $title = '';
    public $hometext = '';
    public $bodytext = '';
    public $type;
    public $approved = false;
    public $topicId = 0;
    public $hostname = '';
    public $nohtml = 0;
    public $nosmiley = 0;
    public $notifyPub = 0;
    public $topicalign = '';

    public function __construct($id = null)
    {
        $this->id = $id ?? 999;
    }

    public static function reset(): void
    {
        self::$storeResult = 123;
        self::$deleteResult = true;
        self::$allPublished = [];
    }

    public static function getAllPublished($limit = 0, $start = 0, $param = null)
    {
        return self::$allPublished;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }

    public function setApproved($flag): void
    {
        $this->approved = (bool) $flag;
    }

    public function setPublished($time): void
    {
        $this->published = $time;
    }

    public function setTopicId($id): void
    {
        $this->topicId = $id;
    }

    public function setTitle($title): void
    {
        $this->title = $title;
    }

    public function setBodytext($text): void
    {
        $this->bodytext = $text;
    }

    public function setHometext($text): void
    {
        $this->hometext = $text;
    }

    public function setUid($uid): void
    {
        $this->uid = $uid;
    }

    public function setHostname($host): void
    {
        $this->hostname = $host;
    }

    public function setNohtml($flag): void
    {
        $this->nohtml = $flag;
    }

    public function setNosmiley($flag): void
    {
        $this->nosmiley = $flag;
    }

    public function setNotifyPub($flag): void
    {
        $this->notifyPub = $flag;
    }

    public function setTopicalign($align): void
    {
        $this->topicalign = $align;
    }

    public function uid()
    {
        return $this->uid;
    }

    public function published()
    {
        return $this->published;
    }

    public function storyid()
    {
        return $this->id;
    }

    public function storyId()
    {
        return $this->id;
    }

    public function title($format = null)
    {
        return $this->title;
    }

    public function hometext($format = null)
    {
        return $this->hometext;
    }

    public function bodytext($format = null)
    {
        return $this->bodytext;
    }

    public function store()
    {
        return self::$storeResult;
    }

    public function delete()
    {
        return self::$deleteResult;
    }
}
PHP;
    }
}

class XoopsApiDouble extends XoopsApi
{
    public $postFields = [];
    public $tagValues = [];
    public $checkUserResult = true;
    public $isAdminResult = true;

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

    public function _checkAdmin()
    {
        return $this->isAdminResult;
    }
}
