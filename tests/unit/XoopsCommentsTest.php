<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopscomments.php';

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

if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'http://example.com');
}

if (!defined('_DB_QUERY_ERROR')) {
    define('_DB_QUERY_ERROR', 'Query error: %s');
}

$GLOBALS['xoopsLogger'] = new XoopsLogger();
$GLOBALS['xoopsConfig']['language'] = 'english';
$GLOBALS['xoopsConfig']['anonymous'] = 'anon';

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return null;
    }
}

class FakeCommentsDb
{
    public array $queries = [];
    public array $executions = [];
    public array $rows = [];
    public $resultSet = true;
    public $rowsNum = 1;
    public $genIdValue = 0;
    public $insertId = 0;

    public function query($sql)
    {
        $this->queries[] = $sql;
        return 'result';
    }

    public function isResultSet($result)
    {
        return $this->resultSet;
    }

    public function fetchArray($result)
    {
        return array_shift($this->rows);
    }

    public function exec($sql)
    {
        $this->executions[] = $sql;
        return true;
    }

    public function genId($seq)
    {
        return $this->genIdValue;
    }

    public function getInsertId()
    {
        return $this->insertId;
    }

    public function prefix($table)
    {
        return 'pref_' . $table;
    }

    public function error()
    {
        return 'db error';
    }
}

class XoopsCommentsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->db = new FakeCommentsDb();
        XoopsDatabaseFactory::$connection = $this->db;
    }

    public function testConstructorInitializesVariables(): void
    {
        $comment = new XoopsComments('pref_table');

        $this->assertSame('pref_table', $comment->ctable);
        $this->assertNull($comment->getVar('comment_id'));
        $this->assertSame(0, $comment->getVar('pid'));
        $this->assertSame(1, $comment->getVar('nohtml'));
    }

    public function testLoadAssignsFetchedValues(): void
    {
        $this->db->rows[] = [
            'comment_id' => 5,
            'subject'    => 'Loaded',
        ];
        $comment = new XoopsComments('pref_table');
        $comment->load(5);

        $this->assertSame('Loaded', $comment->getVar('subject'));
        $this->assertSame('SELECT * FROM pref_table WHERE comment_id=5', $this->db->queries[0]);
    }

    public function testStoreInsertsNewCommentWhenNoId(): void
    {
        $this->db->genIdValue = 0;
        $this->db->insertId   = 22;

        $comment = new XoopsComments('pref_table');
        $comment->setVar('pid', 0);
        $comment->setVar('item_id', 1);
        $comment->setVar('subject', 'Subject');
        $comment->setVar('comment', 'Body');
        $comment->setVar('user_id', 7);
        $comment->setVar('ip', '127.0.0.1');
        $comment->setVar('nohtml', 0);
        $comment->setVar('nosmiley', 1);
        $comment->setVar('noxcode', 0);
        $comment->setVar('icon', 'icon.gif');

        $result = $comment->store();

        $this->assertSame(22, $result);
        $this->assertStringContainsString('INSERT INTO pref_table', $this->db->executions[0]);
    }

    public function testStoreUpdatesExistingComment(): void
    {
        $comment = new XoopsComments('pref_table');
        $comment->setVar('comment_id', 9);
        $comment->setVar('comment', 'Updated');
        $comment->setVar('subject', 'Title');
        $comment->setVar('nohtml', 1);
        $comment->setVar('nosmiley', 0);
        $comment->setVar('noxcode', 1);
        $comment->setVar('icon', 'ico.gif');

        $result = $comment->store();

        $this->assertSame(9, $result);
        $this->assertStringContainsString('UPDATE pref_table', $this->db->executions[0]);
    }
}
