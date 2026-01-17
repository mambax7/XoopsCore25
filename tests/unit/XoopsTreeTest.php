<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopstree.php';

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
        public $deprecated = [];

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
            return htmlspecialchars($text, ENT_QUOTES);
        }
    }
}

if (!defined('_DB_QUERY_ERROR')) {
    define('_DB_QUERY_ERROR', 'Query error: %s');
}

class FakeTreeDb
{
    public array $data = [];
    public array $queries = [];
    public bool $resultSet = true;

    public function query($sql)
    {
        $this->queries[] = $sql;
        $rows            = $this->filterRows($sql);

        return (object)['rows' => $rows];
    }

    public function isResultSet($result)
    {
        return $this->resultSet && is_object($result) && isset($result->rows);
    }

    public function getRowsNum($result)
    {
        return count($result->rows);
    }

    public function fetchArray($result)
    {
        return array_shift($result->rows);
    }

    public function fetchRow($result)
    {
        $row = array_shift($result->rows);
        if ($row === null) {
            return false;
        }

        return array_values($row);
    }

    public function error()
    {
        return 'db error';
    }

    private function filterRows(string $sql): array
    {
        $cols = '*';
        if (preg_match('/SELECT\s+(.+)\s+FROM/i', $sql, $matches)) {
            $cols = trim($matches[1]);
        }

        $conditionField = null;
        $conditionValue = null;
        if (preg_match('/WHERE\s+(\w+)\s*=\s*(\d+)/i', $sql, $matches)) {
            $conditionField = $matches[1];
            $conditionValue = (int)$matches[2];
        }

        $rows = array_filter($this->data, static function ($row) use ($conditionField, $conditionValue) {
            if ($conditionField === null) {
                return true;
            }

            return (int)$row[$conditionField] === $conditionValue;
        });

        if (stripos($sql, 'order by') !== false && preg_match('/ORDER BY\s+(\w+)/i', $sql, $orderMatch)) {
            $field = $orderMatch[1];
            usort($rows, static function ($a, $b) use ($field) {
                return strcmp((string)$a[$field], (string)$b[$field]);
            });
        }

        $selected = [];
        foreach ($rows as $row) {
            if ($cols === '*') {
                $selected[] = $row;
                continue;
            }
            $subset = [];
            foreach (array_map('trim', explode(',', $cols)) as $col) {
                $subset[$col] = $row[$col];
            }
            $selected[] = $subset;
        }

        return array_values($selected);
    }
}

class XoopsTreeTest extends TestCase
{
    private FakeTreeDb $db;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['xoopsLogger'] = new XoopsLogger();
        $this->db               = new FakeTreeDb();
        $this->db->data         = [
            ['catid' => 1, 'pid' => 0, 'title' => 'Root & Root'],
            ['catid' => 2, 'pid' => 1, 'title' => 'Child'],
            ['catid' => 3, 'pid' => 1, 'title' => 'Child Two'],
            ['catid' => 4, 'pid' => 2, 'title' => 'Grandchild'],
        ];
        XoopsDatabaseFactory::$connection = $this->db;
    }

    private function createTree(): XoopsTree
    {
        return new XoopsTree('table', 'catid', 'pid');
    }

    public function testConstructorAssignsValuesAndLogsDeprecation(): void
    {
        $tree = $this->createTree();

        $this->assertSame('table', $tree->table);
        $this->assertSame('catid', $tree->id);
        $this->assertSame('pid', $tree->pid);
        $this->assertNotEmpty($GLOBALS['xoopsLogger']->deprecated);
    }

    public function testChildRetrievalHelpers(): void
    {
        $tree        = $this->createTree();
        $firstChild  = $tree->getFirstChild(1, 'title');
        $firstIds    = $tree->getFirstChildId(1);
        $allChildIds = $tree->getAllChildId(1, 'title');

        $this->assertSame(['catid' => 2, 'pid' => 1, 'title' => 'Child'], $firstChild[0]);
        $this->assertSame([2, 3], $firstIds);
        $this->assertSame([2, 4, 3], $allChildIds);
    }

    public function testParentRetrievalAndPaths(): void
    {
        $tree       = $this->createTree();
        $parents    = $tree->getAllParentId(4, 'title');
        $path       = $tree->getPathFromId(4, 'title');
        $nicePath   = $tree->getNicePathFromId(4, 'title', 'http://example.com/view.php');
        $idPath     = $tree->getIdPathFromId(4);

        $this->assertSame([2, 1], $parents);
        $this->assertSame('/Root &amp; Root/Child/Grandchild', $path);
        $this->assertSame("<a href='http://example.com/view.php&amp;catid=4'>Grandchild</a>&nbsp;:&nbsp;<a href='http://example.com/view.php&amp;catid=2'>Child</a>&nbsp;:&nbsp;<a href='http://example.com/view.php&amp;catid=1'>Root &amp; Root</a>", $nicePath);
        $this->assertSame('/1/2/4', $idPath);
    }

    public function testAllChildAndTreeArray(): void
    {
        $tree   = $this->createTree();
        $all    = $tree->getAllChild(1, 'title');
        $nested = $tree->getChildTreeArray(1, 'title');

        $this->assertCount(3, $all);
        $this->assertSame('Grandchild', $all[2]['title']);
        $prefixes = array_column($nested, 'prefix', 'title');
        $this->assertSame('.', $prefixes['Child']);
        $this->assertSame('.', $prefixes['Child Two']);
        $this->assertSame('..', $prefixes['Grandchild']);
    }

    public function testMakeMySelBoxOutputsOptions(): void
    {
        $tree = $this->createTree();

        ob_start();
        $tree->makeMySelBox('title', 'title', 3, 1, 'pick', "alert('x')");
        $output = ob_get_clean();

        $this->assertStringContainsString("<select name='pick' onchange='alert('x')'>", $output);
        $this->assertStringContainsString("<option value='0'>----</option>", $output);
        $this->assertStringContainsString("<option value='1'>Root &amp; Root</option>", $output);
        $this->assertStringContainsString("<option value='3' selected>Child Two</option>", $output);
    }

    public function testQueryFailureThrowsException(): void
    {
        $tree             = $this->createTree();
        $this->db->resultSet = false;

        $this->expectException(\RuntimeException::class);
        $tree->getFirstChild(1);
    }
}
