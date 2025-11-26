<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/downloader.php';

class XoopsDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function testConstructorLeavesPropertiesNull(): void
    {
        $downloader = new XoopsDownloader();

        $this->assertNull($downloader->mimetype);
        $this->assertNull($downloader->ext);
        $this->assertNull($downloader->archiver);
    }

    public function testHeaderOutputsIeSpecificCachingHeaders(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';
        $downloader               = new XoopsDownloader();
        $downloader->mimetype     = 'text/plain';

        $downloader->_header('example.txt');

        $headers = headers_list();
        $this->assertContains('Content-Type: text/plain', $headers);
        $this->assertContains('Content-Disposition: attachment; filename="example.txt"', $headers);
        $this->assertContains('Expires: 0', $headers);
        $this->assertContains('Cache-Control: must-revalidate, post-check=0, pre-check=0', $headers);
        $this->assertContains('Pragma: public', $headers);
    }

    public function testHeaderOutputsGenericCachingHeadersForNonIe(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Firefox/120.0';
        $downloader               = new XoopsDownloader();
        $downloader->mimetype     = 'application/octet-stream';

        $downloader->_header('binary.bin');

        $headers = headers_list();
        $this->assertContains('Content-Type: application/octet-stream', $headers);
        $this->assertContains('Content-Disposition: attachment; filename="binary.bin"', $headers);
        $this->assertContains('Expires: 0', $headers);
        $this->assertContains('Pragma: no-cache', $headers);
    }
}
