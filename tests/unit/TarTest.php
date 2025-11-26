<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/class.tar.php';

class TarTest extends TestCase
{
    public function testComputeUnsignedChecksum(): void
    {
        $tar = new Tar();
        $block = str_repeat(' ', 512);

        $this->assertSame(16384, $tar->__computeUnsignedChecksum($block));
    }

    public function testParseNullPaddedString(): void
    {
        $tar = new Tar();

        $this->assertSame('abc', $tar->__parseNullPaddedString("abc\0def"));
    }

    public function testAddFileContainsAndRemove(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tar_file_');
        file_put_contents($file, "hello world");

        $tar = new Tar();
        $this->assertTrue($tar->addFile($file));
        $this->assertTrue($tar->containsFile($file));
        $this->assertSame(1, $tar->numFiles);

        $this->assertTrue($tar->removeFile($file));
        $this->assertFalse($tar->containsFile($file));
        $this->assertSame(0, $tar->numFiles);

        unlink($file);
    }

    public function testAddDirectoryContainsAndRemove(): void
    {
        $dir = sys_get_temp_dir() . '/tar_dir_' . uniqid();
        mkdir($dir);

        $tar = new Tar();
        $this->assertTrue($tar->addDirectory($dir));
        $this->assertTrue($tar->containsDirectory($dir));
        $this->assertSame(1, $tar->numDirectories);

        $this->assertTrue($tar->removeDirectory($dir));
        $this->assertFalse($tar->containsDirectory($dir));
        $this->assertSame(0, $tar->numDirectories);

        rmdir($dir);
    }

    public function testGenerateAndParseTarOutput(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tar_parse_');
        file_put_contents($file, "sample content");

        $tar = new Tar();
        $tar->addFile($file);
        $output = $tar->toTarOutput('sample.tar', false);

        $this->assertNotFalse($output);

        $reader = new Tar();
        $reader->tar_file = $output;
        $this->assertTrue($reader->__parseTar());
        $this->assertSame(1, $reader->numFiles);
        $parsed = $reader->getFile($file);

        $this->assertIsArray($parsed);
        $this->assertSame('sample content', $parsed['file']);

        unlink($file);
    }

    public function testAppendTarWithGzip(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tar_gz_');
        file_put_contents($file, "gzipped content");

        $tar = new Tar();
        $tar->addFile($file);

        $archive = tempnam(sys_get_temp_dir(), 'tar_archive_');
        $this->assertTrue($tar->toTar($archive, true));

        $reader = new Tar();
        $this->assertTrue($reader->appendTar($archive));
        $this->assertTrue($reader->isGzipped);
        $this->assertTrue($reader->containsFile($file));

        unlink($file);
        unlink($archive);
    }

    public function testSaveTarWritesFileWhenFilenameProvided(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tar_save_');
        file_put_contents($file, 'save content');

        $archive = tempnam(sys_get_temp_dir(), 'tar_saved_');
        $tar = new Tar();
        $tar->filename = $archive;
        $tar->addFile($file);

        $this->assertTrue($tar->saveTar());
        $this->assertFileExists($archive);

        unlink($file);
        unlink($archive);
    }

    public function testSaveTarFailsWithoutFilename(): void
    {
        $tar = new Tar();

        $this->assertFalse($tar->saveTar());
    }
}
