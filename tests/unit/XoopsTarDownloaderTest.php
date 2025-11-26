<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/tardownloader.php';

class TarStub
{
    public array $files = [];
    public int $numFiles = 0;
    public ?string $lastOutputName = null;
    public ?bool $lastGzip = null;

    public function addFile($path, $binary = false): void
    {
        $this->files[] = ['name' => $path, 'time' => 0, 'binary' => $binary];
        $this->numFiles = count($this->files);
    }

    public function toTarOutput($name, $gzip)
    {
        $this->lastOutputName = $name;
        $this->lastGzip      = $gzip;

        return 'tar-output:' . $name . ':' . ($gzip ? 'gzip' : 'plain');
    }
}

class XoopsTarDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('XOOPS_CACHE_PATH')) {
            define('XOOPS_CACHE_PATH', sys_get_temp_dir() . '/xoops_cache');
        }
        if (!is_dir(XOOPS_CACHE_PATH)) {
            mkdir(XOOPS_CACHE_PATH, 0777, true);
        }

        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function testConstructorSetsDefaults(): void
    {
        $downloader = new XoopsTarDownloader();

        $this->assertInstanceOf(tar::class, $downloader->archiver);
        $this->assertSame('.tar.gz', $downloader->ext);
        $this->assertSame('application/x-gzip', $downloader->mimetype);
    }

    public function testAddFileRenamesWhenProvided(): void
    {
        $downloader            = new XoopsTarDownloader();
        $downloader->archiver  = new TarStub();

        $downloader->addFile('/tmp/original.txt', 'renamed.txt');

        $this->assertSame(1, $downloader->archiver->numFiles);
        $this->assertSame('renamed.txt', $downloader->archiver->files[0]['name']);
    }

    public function testAddBinaryFileKeepsBinaryFlag(): void
    {
        $downloader           = new XoopsTarDownloader();
        $downloader->archiver = new TarStub();

        $downloader->addBinaryFile('/tmp/binary.bin', 'binary.bin');

        $this->assertTrue($downloader->archiver->files[0]['binary']);
        $this->assertSame('binary.bin', $downloader->archiver->files[0]['name']);
    }

    public function testAddFileDataWritesAndReplacesNameAndTime(): void
    {
        $downloader           = new XoopsTarDownloader();
        $downloader->archiver = new TarStub();

        $downloader->addFileData('content', 'archive.txt', 1234);

        $this->assertSame('archive.txt', $downloader->archiver->files[0]['name']);
        $this->assertSame(1234, $downloader->archiver->files[0]['time']);
    }

    public function testAddBinaryFileDataMarksBinaryAndTime(): void
    {
        $downloader           = new XoopsTarDownloader();
        $downloader->archiver = new TarStub();

        $downloader->addBinaryFileData("\x00\x01", 'binary.data', 5678);

        $this->assertTrue($downloader->archiver->files[0]['binary']);
        $this->assertSame('binary.data', $downloader->archiver->files[0]['name']);
        $this->assertSame(5678, $downloader->archiver->files[0]['time']);
    }

    public function testDownloadOutputsArchiveAndHeaders(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'UnitTestBrowser/1.0';
        $downloader                 = new XoopsTarDownloader();
        $downloader->archiver       = new TarStub();

        ob_start();
        $downloader->download('package', false);
        $output = ob_get_clean();

        $this->assertSame('tar-output:package.tar.gz:plain', $output);
        $this->assertSame('package.tar.gz', $downloader->archiver->lastOutputName);
        $this->assertFalse($downloader->archiver->lastGzip);

        $headers = headers_list();
        $this->assertContains('Content-Type: application/x-gzip', $headers);
        $this->assertContains('Content-Disposition: attachment; filename="package.tar.gz"', $headers);
        $this->assertContains('Pragma: no-cache', $headers);
    }
}
