<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/database/sqlutility.php';

class SqlUtilityTest extends TestCase
{
    public function testSplitMySqlFileSeparatesStatementsAndSkipsComments(): void
    {
        $sql = "SELECT 1;\n-- comment to ignore\nINSERT INTO t VALUES('a; b');\n# hash comment\nUPDATE t SET col='value';";

        $statements = [];
        $result = SqlUtility::splitMySqlFile($statements, $sql);

        $this->assertTrue($result);
        $this->assertSame([
            'SELECT 1',
            "INSERT INTO t VALUES('a; b')",
            "UPDATE t SET col='value'",
        ], $statements);
    }

    public function testSplitMySqlFileReturnsRemainderWhenStringUnterminated(): void
    {
        $sql = "INSERT INTO t VALUES('unfinished";
        $statements = [];

        $result = SqlUtility::splitMySqlFile($statements, $sql);

        $this->assertTrue($result);
        $this->assertSame([$sql], $statements);
    }

    public function testPrefixQueryReplacesTableNames(): void
    {
        $prefixed = SqlUtility::prefixQuery('INSERT INTO table1 (id) VALUES(1)', 'pre');

        $this->assertIsArray($prefixed);
        $this->assertSame('INSERT INTO pre_table1 (id) VALUES(1)', $prefixed[0]);
    }

    public function testPrefixQueryHandlesDropTable(): void
    {
        $prefixed = SqlUtility::prefixQuery('DROP TABLE table1', 'myprefix');

        $this->assertIsArray($prefixed);
        $this->assertSame('DROP TABLE myprefix_table1', $prefixed[0]);
    }

    public function testPrefixQueryReturnsFalseForUnsupportedStatements(): void
    {
        $this->assertFalse(SqlUtility::prefixQuery('SELECT * FROM table1', 'pre'));
    }
}
