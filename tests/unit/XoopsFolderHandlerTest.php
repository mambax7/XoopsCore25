<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/file/folder.php';

class XoopsFolderHandlerTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDir = sys_get_temp_dir() . '/xoopsfolder_' . uniqid();
        mkdir($this->baseDir);
    }

    protected function tearDown(): void
    {
        $this->removeIfExists($this->baseDir);
        parent::tearDown();
    }

    private function removeIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }
        if (is_dir($path)) {
            $items = scandir($path);
            if ($items) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $this->removeIfExists($path . DIRECTORY_SEPARATOR . $item);
                }
            }
            rmdir($path);
        }
    }

    public function testConstructorCreatesDirectoryAndSetsWorkingPath(): void
    {
        $target = $this->baseDir . '/created';
        $this->assertDirectoryDoesNotExist($target);

        $handler = new XoopsFolderHandler($target, true, '0755');

        $this->assertDirectoryExists($target);
        $this->assertSame(realpath($target), $handler->pwd());
    }

    public function testReadAndFindReturnsSortedEntries(): void
    {
        $dir = $this->baseDir . '/sub';
        mkdir($dir);
        file_put_contents($this->baseDir . '/a.txt', '');
        file_put_contents($this->baseDir . '/b.md', '');

        $handler = new XoopsFolderHandler($this->baseDir, false);
        [$dirs, $files] = $handler->read(true);

        $this->assertSame(['sub'], $dirs);
        $this->assertSame(['a.txt', 'b.md'], $files);
        $this->assertSame(['a.txt'], $handler->find('.*\.txt'));
    }

    public function testFindRecursiveReturnsFullPathsAndRestoresWorkingDir(): void
    {
        $nested = $this->baseDir . '/n1/n2';
        mkdir($nested, 0777, true);
        $targetFile = $nested . '/c.txt';
        file_put_contents($targetFile, 'content');

        $handler = new XoopsFolderHandler($this->baseDir, false);
        $original = $handler->pwd();

        $results = $handler->findRecursive('.*\.txt', true);

        $this->assertSame([$targetFile], $results);
        $this->assertSame($original, $handler->pwd());
    }

    public function testDeleteRemovesDirectoriesAndRecordsMessages(): void
    {
        $path = $this->baseDir . '/remove_me';
        mkdir($path);
        file_put_contents($path . '/file.txt', 'bye');

        $handler = new XoopsFolderHandler($this->baseDir, false);
        $this->assertTrue($handler->delete($path));

        $this->assertDirectoryDoesNotExist($path);
        $this->assertNotEmpty($handler->messages());
        $this->assertEmpty($handler->errors());
    }
}
