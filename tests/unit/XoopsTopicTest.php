<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopstopic.php';

if (!class_exists('XoopsDatabaseFactory')) {
    class XoopsDatabaseFactory
    {
        public static $connection;

        public static function getDatabaseConnection()
        {
            return static::$connection;
        }
    }
}

if (!class_exists('XoopsLogger')) {
    class XoopsLogger
    {
        public array $deprecated = [];

        public function addDeprecated($message): void
        {
            $this->deprecated[] = $message;
        }
    }
}

if (!class_exists('MyTextSanitizer')) {
    class MyTextSanitizer
    {
        public static function getInstance()
        {
            return new self();
        }

        public function htmlSpecialChars($text)
        {
            return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5);
        }
    }
}

if (!class_exists('XoopsTree')) {
    class XoopsTree
    {
        public static array $firstChild      = [];
        public static array $allChild        = [];
        public static array $childTree       = [];
        public static array $selBoxCalls     = [];
        public static string $nicePathResult = '';
        public static array $allChildIds     = [];

        public array $records = [];

        public function __construct(public $table, public $id, public $pid)
        {
            $this->records[] = [$table, $id, $pid];
        }

        public function getFirstChild($topicId, $order)
        {
            $this->records[] = ['first', $topicId, $order];

            return static::$firstChild;
        }

        public function getAllChild($topicId, $order)
        {
            $this->records[] = ['all', $topicId, $order];

            return static::$allChild;
        }

        public function getChildTreeArray($topicId, $order)
        {
            $this->records[] = ['tree', $topicId, $order];

            return static::$childTree;
        }

        public function makeMySelBox($title, $order, $selId, $none, $selname, $onchange)
        {
            static::$selBoxCalls[] = func_get_args();
        }

        public function getNicePathFromId($topicId, $title, $funcURL)
        {
            $this->records[] = ['nice', $topicId, $title, $funcURL];

            return static::$nicePathResult;
        }

        public function getAllChildId($topicId, $title)
        {
            $this->records[] = ['allid', $topicId, $title];

            return static::$allChildIds;
        }
    }
}

if (!class_exists('XoopsPerms')) {
    class XoopsPerms
    {
        public static function getPermitted($mid, $name, $group)
        {
            return [];
        }

        public function setModuleId($mid): void {}

        public function setName($name): void {}

        public function setItemId($id): void {}

        public function store(): void {}

        public function addGroup($group): void {}
    }
}

if (!class_exists('ErrorHandler')) {
    class ErrorHandler
    {
        public static array $errors = [];

        public static function show($code)
        {
            static::$errors[] = $code;

            return false;
        }
    }
}

if (!defined('_DB_QUERY_ERROR')) {
    define('_DB_QUERY_ERROR', 'Query error: %s');
}

class TopicTestDatabase
{
    public int $nextId          = 0;
    public int $insertId        = 0;
    public bool $execResult     = true;
    public $queryResult;
    public array $fetchArray    = [];
    public array $fetchArrayQueue = [];
    public array $fetchRow      = [];
    public array $queries       = [];

    public function genId($seq)
    {
        $this->queries[] = 'GENID:' . $seq;

        return $this->nextId;
    }

    public function exec($sql)
    {
        $this->queries[] = $sql;

        return $this->execResult;
    }

    public function getInsertId()
    {
        return $this->insertId;
    }

    public function query($sql)
    {
        $this->queries[] = $sql;

        return $this->queryResult;
    }

    public function isResultSet($result)
    {
        return $result === $this->queryResult && $result !== null;
    }

    public function fetchArray($result)
    {
        if ($this->fetchArrayQueue !== []) {
            return array_shift($this->fetchArrayQueue);
        }

        return $this->fetchArray;
    }

    public function escape($text)
    {
        return addslashes((string) $text);
    }

    public function error()
    {
        return 'error';
    }

    public function fetchRow($result)
    {
        return $this->fetchRow;
    }
}

class XoopsTopicTest extends TestCase
{
    private TopicTestDatabase $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db                    = new TopicTestDatabase();
        $this->db->queryResult       = new stdClass();
        XoopsDatabaseFactory::$connection = $this->db;
        $GLOBALS['xoopsDB']          = $this->db;
        $GLOBALS['xoopsLogger']      = new XoopsLogger();
        XoopsTree::$firstChild       = [];
        XoopsTree::$allChild         = [];
        XoopsTree::$childTree        = [];
        XoopsTree::$selBoxCalls      = [];
        XoopsTree::$nicePathResult   = '';
        XoopsTree::$allChildIds      = [];
    }

    public function testConstructorUsesArrayToPopulate(): void
    {
        $topic = new XoopsTopic('topics', ['topic_id' => 5, 'topic_title' => 'Title']);

        $this->assertSame(5, $topic->topic_id);
        $this->assertSame('Title', $topic->topic_title);
        $this->assertSame('topics', $topic->table);
    }

    public function testConstructorLoadsFromDatabaseWhenIdProvided(): void
    {
        $this->db->fetchArray = ['topic_id' => 7, 'topic_title' => 'Loaded'];
        $topic                = new XoopsTopic('topics', 7);

        $this->assertSame(['SELECT * FROM topics WHERE topic_id=7'], $this->db->queries);
        $this->assertSame(7, $topic->topic_id);
        $this->assertSame('Loaded', $topic->topic_title);
    }

    public function testSettersAndAccessors(): void
    {
        $topic = new XoopsTopic('topics', 0);
        $topic->setTopicTitle('<b>Title</b>');
        $topic->setTopicImgurl('img.png');
        $topic->setTopicPid(3);
        $topic->topic_id = 2;

        $this->assertSame(2, $topic->topic_id());
        $this->assertSame(3, $topic->topic_pid());
        $this->assertSame('&lt;b&gt;Title&lt;/b&gt;', $topic->topic_title());
        $this->assertSame('img.png', $topic->topic_imgurl());
        $this->assertNull($topic->prefix());
    }

    public function testStoreInsertsNewTopic(): void
    {
        $topic              = new XoopsTopic('topics', 0);
        $topic->topic_title = 'New';
        $topic->topic_imgurl = 'url';
        $topic->topic_pid   = 1;
        $this->db->nextId   = 10;

        $this->assertTrue($topic->store());
        $this->assertSame(10, $topic->topic_id);
        $this->assertStringContainsString('INSERT INTO topics', $this->db->queries[1]);
    }

    public function testStoreUpdatesExistingTopic(): void
    {
        $topic              = new XoopsTopic('topics', 0);
        $topic->topic_id    = 4;
        $topic->topic_title = 'Update';
        $topic->topic_imgurl = 'img';
        $topic->topic_pid   = 2;

        $this->assertTrue($topic->store());
        $this->assertStringContainsString('UPDATE topics SET topic_pid = 2, topic_imgurl =', $this->db->queries[0]);
    }

    public function testDeleteIssuesQuery(): void
    {
        $topic           = new XoopsTopic('topics', 0);
        $topic->topic_id = 9;

        $topic->delete();
        $this->assertStringContainsString('DELETE FROM topics WHERE topic_id = 9', $this->db->queries[0]);
    }

    public function testTopicExistsCountsMatches(): void
    {
        $topic             = new XoopsTopic('topics', 0);
        $this->db->fetchRow = [2];

        $this->assertTrue($topic->topicExists(1, 'Title'));
        $this->db->fetchRow = [0];
        $this->assertFalse($topic->topicExists(1, 'Missing'));
    }

    public function testGetTopicsListReturnsSanitizedTitles(): void
    {
        $topic                    = new XoopsTopic('topics', 0);
        $this->db->fetchArrayQueue = [
            ['topic_id' => 1, 'topic_pid' => 0, 'topic_title' => 'First'],
            ['topic_id' => 2, 'topic_pid' => 1, 'topic_title' => '<Second>'],
            false,
        ];

        $list = $topic->getTopicsList();

        $this->assertSame(['title' => 'First', 'pid' => 0], $list[1]);
        $this->assertSame(['title' => '&lt;Second&gt;', 'pid' => 1], $list[2]);
    }

    public function testChildTopicHelpersReturnObjects(): void
    {
        XoopsTree::$firstChild = [
            ['topic_id' => 5, 'topic_title' => 'Child'],
        ];
        XoopsTree::$allChild = [
            ['topic_id' => 6, 'topic_title' => 'All'],
        ];
        XoopsTree::$childTree = [
            ['topic_id' => 7, 'topic_title' => 'Tree'],
        ];

        $topic           = new XoopsTopic('topics', ['topic_id' => 1]);
        $firstChildren   = $topic->getFirstChildTopics();
        $allChildren     = $topic->getAllChildTopics();
        $treeChildren    = $topic->getChildTopicsTreeArray();

        $this->assertSame(5, $firstChildren[0]->topic_id);
        $this->assertSame('Child', $firstChildren[0]->topic_title);
        $this->assertSame(6, $allChildren[0]->topic_id);
        $this->assertSame(7, $treeChildren[0]->topic_id);
    }

    public function testSelectionAndPathHelpers(): void
    {
        XoopsTree::$nicePathResult = 'nice-path';
        XoopsTree::$allChildIds    = [1, 2, 3];

        $topic           = new XoopsTopic('topics', ['topic_id' => 12]);
        $topic->makeTopicSelBox(0, -1, 'sel', 'onchange');

        $this->assertSame([
            ['topic_title', 'topic_title', 12, 0, 'sel', 'onchange'],
        ], XoopsTree::$selBoxCalls);
        $this->assertSame('nice-path', $topic->getNiceTopicPathFromId('url'));
        $this->assertSame([1, 2, 3], $topic->getAllChildTopicsId());
    }
}
