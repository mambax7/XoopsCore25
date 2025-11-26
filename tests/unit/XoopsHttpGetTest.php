<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../htdocs/class/xoopshttpget.php';

class XoopsHttpGetTest extends TestCase
{
    public function testFetchUsesCurlWhenEnabled(): void
    {
        $stub = new class('http://example.com') extends XoopsHttpGet {
            public $fetchCurlCalled = false;
            public $fetchFopenCalled = false;

            protected function fetchCurl()
            {
                $this->fetchCurlCalled = true;
                $this->error = 'curl';
                return 'curl-response';
            }

            protected function fetchFopen()
            {
                $this->fetchFopenCalled = true;
                return 'fopen-response';
            }
        };

        $this->setUseCurl($stub, true);

        $this->assertSame('curl-response', $stub->fetch());
        $this->assertTrue($stub->fetchCurlCalled);
        $this->assertFalse($stub->fetchFopenCalled);
        $this->assertSame('curl', $stub->getError());
    }

    public function testFetchUsesFopenWhenDisabled(): void
    {
        $stub = new class('http://example.com') extends XoopsHttpGet {
            public $fetchCurlCalled = false;
            public $fetchFopenCalled = false;

            protected function fetchCurl()
            {
                $this->fetchCurlCalled = true;
                return 'curl-response';
            }

            protected function fetchFopen()
            {
                $this->fetchFopenCalled = true;
                $this->error = 'fopen';
                return 'fopen-response';
            }
        };

        $this->setUseCurl($stub, false);

        $this->assertSame('fopen-response', $stub->fetch());
        $this->assertFalse($stub->fetchCurlCalled);
        $this->assertTrue($stub->fetchFopenCalled);
        $this->assertSame('fopen', $stub->getError());
    }

    public function testFetchFopenReadsLocalFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'xoops_http_get');
        file_put_contents($file, 'local content');

        $getter = new XoopsHttpGet('file://' . $file);
        $this->setUseCurl($getter, false);

        $this->assertSame('local content', $getter->fetch());
        $this->assertNull($getter->getError());

        @unlink($file);
    }

    public function testFetchFopenHandlesMissingFile(): void
    {
        $getter = new XoopsHttpGet('file:///nonexistent/path/does_not_exist.txt');
        $this->setUseCurl($getter, false);

        $this->assertFalse($getter->fetch());
        $this->assertSame('file_get_contents() failed.', $getter->getError());
    }

    private function setUseCurl(XoopsHttpGet $getter, bool $value): void
    {
        $property = new \ReflectionProperty(XoopsHttpGet::class, 'useCurl');
        $property->setAccessible(true);
        $property->setValue($getter, $value);
    }
}
