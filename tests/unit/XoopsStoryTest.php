<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsstory.php';

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
        public function addDeprecated($message): void {}
    }
}

if (!class_exists('MyTextSanitizer')) {
    class MyTextSanitizer
    {
        public array $displayArgs = [];
        public array $previewArgs = [];

        public static function getInstance()
        {
            return new self();
        }

        public function censorString($text)
        {
            return (string) $text;
        }

        public function htmlSpecialChars($text)
        {
            return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5);
        }

        public function displayTarea($text, $html, $smiley, $xcodes)
        {
            $this->displayArgs[] = func_get_args();

            return "display:" . $text;
        }

        public function previewTarea($text, $html, $smiley, $xcodes)
        {
            $this->previewArgs[] = func_get_args();

            return "preview:" . $text;
        }
    }
}

if (!class_exists('XoopsUser')) {
    class XoopsUser
    {
        public static function getUnameFromId($uid)
        {
            return 'user-' . $uid;
        }
    }
}

if (!class_exists('XoopsTopic')) {
    class XoopsTopic
    {
        public string $table;
        public int $topicid;

        public function __construct($table, $topicid)
        {
            $this->table   = $table;
            $this->topicid = (int) $topicid;
        }
    }
}

if (!defined('_DB_QUERY_ERROR')) {
    define('_DB_QUERY_ERROR', 'Query error: %s');
}

class StoryTestDatabase
{
    public int $nextId      = 0;
    public int $insertId    = 0;
    public bool $execResult = true;
    public $queryResult;
    public array $fetchArray = [];
    public array $queries    = [];

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
}

class XoopsStoryTest extends TestCase
{
    private StoryTestDatabase $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db                    = new StoryTestDatabase();
        XoopsDatabaseFactory::$connection = $this->db;
        $GLOBALS['xoopsLogger']      = new XoopsLogger();
        $GLOBALS['xoopsDB']          = $this->db;
    }

    public function testMakeStoryPopulatesFields(): void
    {
        $story = new XoopsStory();
        $story->table = 'stories';
        $story->makeStory(['storyid' => 42, 'title' => 'hello', 'topicid' => 9]);

        $this->assertSame(42, $story->storyid);
        $this->assertSame('hello', $story->title);
        $this->assertSame(9, $story->topicid);
    }

    public function testStoreInsertsNewStory(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->table       = 'stories';
        $story->topicstable = 'topics';
        $story->uid         = 1;
        $story->title       = 'Title';
        $story->hometext    = 'Home';
        $story->bodytext    = 'Body';
        $story->hostname    = 'host';
        $story->topicid     = 3;
        $story->ihome       = 0;
        $story->notifypub   = 1;
        $story->type        = 'news';
        $story->topicdisplay = 1;
        $story->topicalign   = 'L';
        $story->comments     = 0;
        $story->approved     = true;
        $story->published    = 123;
        $this->db->nextId    = 7;

        $newId = $story->store();

        $this->assertSame(7, $newId);
        $this->assertSame(7, $story->storyid);
        $this->assertStringContainsString("INSERT INTO stories", $this->db->queries[1]);
        $this->assertStringContainsString("'Title'", $this->db->queries[1]);
    }

    public function testStoreUpdatesExistingStory(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->table       = 'stories';
        $story->storyid     = 5;
        $story->title       = 'Updated';
        $story->published   = 10;
        $story->expired     = 0;
        $story->nohtml      = 1;
        $story->nosmiley    = 0;
        $story->hometext    = 'Home';
        $story->bodytext    = 'Body';
        $story->topicid     = 3;
        $story->ihome       = 0;
        $story->topicdisplay = 1;
        $story->topicalign   = 'C';
        $story->comments     = 2;
        $story->approved     = false;

        $updatedId = $story->store();

        $this->assertSame(5, $updatedId);
        $this->assertSame(5, $story->storyid);
        $this->assertStringContainsString('UPDATE stories SET title', $this->db->queries[0]);
        $this->assertStringContainsString('WHERE storyid = 5', $this->db->queries[0]);
    }

    public function testStoreReturnsFalseOnExecFailure(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->table   = 'stories';
        $story->uid     = 1;
        $this->db->execResult = false;

        $this->assertFalse($story->store());
    }

    public function testGetStoryThrowsOnInvalidResult(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->table = 'stories';
        $this->db->queryResult = null;

        $this->expectException(\RuntimeException::class);
        $story->getStory(2);
    }

    public function testGetStoryPopulatesFromDatabase(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->table = 'stories';
        $this->db->queryResult = new stdClass();
        $this->db->fetchArray  = ['storyid' => 9, 'title' => 'loaded'];

        $story->getStory(9);

        $this->assertSame(['SELECT * FROM stories WHERE storyid=9'], $this->db->queries);
        $this->assertSame(9, $story->storyid);
        $this->assertSame('loaded', $story->title);
    }

    public function testDeleteAndCounters(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->table   = 'stories';
        $story->storyid = 4;

        $this->assertTrue($story->delete());
        $this->assertTrue($story->updateCounter());
        $this->assertTrue($story->updateComments(5));
        $this->assertCount(3, $this->db->queries);
        $this->assertStringContainsString('DELETE FROM stories WHERE storyid = 4', $this->db->queries[0]);
        $this->assertStringContainsString('UPDATE stories SET counter = counter+1 WHERE storyid = 4', $this->db->queries[1]);
        $this->assertStringContainsString('UPDATE stories SET comments = 5 WHERE storyid = 4', $this->db->queries[2]);
    }

    public function testFormattingHelpers(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->title    = '<b>title</b>';
        $story->hometext = 'home';
        $story->bodytext = 'body';
        $story->nohtml   = 1;
        $story->nosmiley = 1;

        $this->assertSame('&lt;b&gt;title&lt;/b&gt;', $story->title());
        $this->assertSame('display:home', $story->hometext());
        $this->assertSame('display:body', $story->bodytext());

        $story->nosmiley = 0;
        $this->assertSame('preview:home', $story->hometext('Preview'));
    }

    public function testTopicAndUserHelpers(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->topicstable = 'topics';
        $story->topicid     = 11;
        $story->uid         = 8;

        $topic = $story->topic();
        $this->assertInstanceOf(XoopsTopic::class, $topic);
        $this->assertSame('topics', $topic->table);
        $this->assertSame(11, $topic->topicid);
        $this->assertSame('user-8', $story->uname());
    }

    public function testTopicalignReturnsTextOrRaw(): void
    {
        $story = new XoopsStory();
        $story->Story();
        $story->topicalign = 'R';

        $this->assertSame('right', $story->topicalign());
        $this->assertSame('R', $story->topicalign(false));
    }
}
