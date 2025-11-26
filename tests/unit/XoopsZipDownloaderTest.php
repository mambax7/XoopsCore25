<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/zipdownloader.php';

class ZipfileStub
{
    public array $files = [];

    public function addFile($data, $filename, $time): void
    {
        $this->files[] = ['data' => $data, 'name' => $filename, 'time' => $time];
    }

    public function file()
    {
        return 'zip:' . count($this->files);
    }
}

class XoopsZipDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function testConstructorSetsDefaults(): void
    {
        $downloader = new XoopsZipDownloader();

        $this->assertInstanceOf(Zipfile::class, $downloader->archiver);
        $this->assertSame('.zip', $downloader->ext);
        $this->assertSame('application/x-zip', $downloader->mimetype);
    }

    public function testAddFileReadsDataAndUsesFilepath(): void
    {
        $downloader           = new XoopsZipDownloader();
        $downloader->archiver = new ZipfileStub();

        $tempFile = tempnam(sys_get_temp_dir(), 'zipfile');
        file_put_contents($tempFile, 'file-data');

        $downloader->addFile($tempFile);

        $this->assertSame('file-data', $downloader->archiver->files[0]['data']);
        $this->assertSame($tempFile, $downloader->archiver->files[0]['name']);
        $this->assertSame(filemtime($tempFile), $downloader->archiver->files[0]['time']);

        unlink($tempFile);
    }

    public function testAddFileUsesNewFilenameAndTimestamp(): void
    {
        $downloader           = new XoopsZipDownloader();
        $downloader->archiver = new ZipfileStub();

        $original = tempnam(sys_get_temp_dir(), 'zipfile');
        file_put_contents($original, 'original');

        $renameTarget = tempnam(sys_get_temp_dir(), 'ziprename');
        touch($renameTarget, time() - 10);

        $downloader->addFile($original, $renameTarget);

        $this->assertSame('original', $downloader->archiver->files[0]['data']);
        $this->assertSame($renameTarget, $downloader->archiver->files[0]['name']);
        $this->assertSame(filemtime($renameTarget), $downloader->archiver->files[0]['time']);

        unlink($original);
        unlink($renameTarget);
    }

    public function testAddBinaryFileReadsBinaryData(): void
    {
        $downloader           = new XoopsZipDownloader();
        $downloader->archiver = new ZipfileStub();

        $binaryFile = tempnam(sys_get_temp_dir(), 'zipbinary');
        file_put_contents($binaryFile, "\x00\x01\x02");

        $downloader->addBinaryFile($binaryFile);

        $this->assertSame("\x00\x01\x02", $downloader->archiver->files[0]['data']);
        $this->assertSame($binaryFile, $downloader->archiver->files[0]['name']);

        unlink($binaryFile);
    }

    public function testAddFileDataPassesValues(): void
    {
        $downloader           = new XoopsZipDownloader();
        $downloader->archiver = new ZipfileStub();

        $downloader->addFileData('data', 'filename.txt', 123);

        $this->assertSame('data', $downloader->archiver->files[0]['data']);
        $this->assertSame('filename.txt', $downloader->archiver->files[0]['name']);
        $this->assertSame(123, $downloader->archiver->files[0]['time']);
    }

    public function testAddBinaryFileDataDelegates(): void
    {
        $downloader           = new XoopsZipDownloader();
        $downloader->archiver = new ZipfileStub();

        $downloader->addBinaryFileData('binary-data', 'binary.bin', 456);

        $this->assertSame('binary-data', $downloader->archiver->files[0]['data']);
        $this->assertSame('binary.bin', $downloader->archiver->files[0]['name']);
        $this->assertSame(456, $downloader->archiver->files[0]['time']);
    }

    public function testDownloadOutputsArchiveAndHeaders(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'UnitTestBrowser/1.0';
        $downloader                 = new XoopsZipDownloader();
        $downloader->archiver       = new ZipfileStub();
        $downloader->archiver->addFile('example', 'example.txt', time());

        ob_start();
        $downloader->download('archive', false);
        $output = ob_get_clean();

        $this->assertSame('zip:1', $output);
        $headers = headers_list();
        $this->assertContains('Content-Type: application/x-zip', $headers);
        $this->assertContains('Content-Disposition: attachment; filename="archive.zip"', $headers);
        $this->assertContains('Pragma: no-cache', $headers);
    }
}
