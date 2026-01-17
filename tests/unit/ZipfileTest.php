<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/class.zipfile.php';

class ZipfileTest extends TestCase
{
    public function testUnix2DosTimeClampsTo1980(): void
    {
        $zipfile = new Zipfile();

        $result = $zipfile->unix2DosTime(mktime(0, 0, 0, 12, 31, 1970));

        $this->assertSame(2162688, $result);
    }

    public function testUnix2DosTimeCalculatesExpectedBits(): void
    {
        $zipfile = new Zipfile();
        $timestamp = mktime(0, 0, 0, 5, 4, 2024);

        $expected = ((2024 - 1980) << 25) | (5 << 21) | (4 << 16);
        $this->assertSame($expected, $zipfile->unix2DosTime($timestamp));
    }

    public function testAddFileStoresForwardSlashNamesAndOffsets(): void
    {
        $zipfile = new Zipfile();
        $zipfile->addFile('abc', 'dir\\file.txt', 0);

        $this->assertCount(1, $zipfile->datasec);
        $this->assertCount(1, $zipfile->ctrl_dir);
        $this->assertSame(strlen($zipfile->datasec[0]), $zipfile->old_offset);
        $this->assertStringContainsString('dir/file.txt', $zipfile->datasec[0]);
        $this->assertStringContainsString('dir/file.txt', $zipfile->ctrl_dir[0]);
    }

    public function testFileOutputIncludesDirectoryTrailer(): void
    {
        $zipfile = new Zipfile();
        $zipfile->addFile('first', 'first.txt', 0);
        $zipfile->addFile('second', 'second.txt', 0);

        $data = implode('', $zipfile->datasec);
        $dir  = implode('', $zipfile->ctrl_dir);

        $output = $zipfile->file();
        $tail   = substr($output, -22);

        $expectedTail = "\x50\x4b\x05\x06\x00\x00\x00\x00"
            . pack('v', 2)
            . pack('v', 2)
            . pack('V', strlen($dir))
            . pack('V', strlen($data))
            . "\x00\x00";

        $this->assertSame($expectedTail, $tail);
        $this->assertSame(strlen($data), $zipfile->old_offset);
    }
}
