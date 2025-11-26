<?php

namespace Xmf {
    if (!class_exists('Xmf\\Request')) {
        class Request
        {
            public static $files = [];

            public static function reset(): void
            {
                self::$files = [];
            }

            public static function hasVar($key, $type)
            {
                return $type === 'FILES' && array_key_exists($key, self::$files);
            }

            public static function getArray($key, $default = [], $type = 'GET')
            {
                if ($type === 'FILES' && array_key_exists($key, self::$files)) {
                    return self::$files[$key];
                }

                return $default;
            }
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/init_new.php';

    class XoopsPathStub
    {
        public function path($file)
        {
            return XOOPS_ROOT_PATH . '/' . ltrim($file, '/');
        }
    }

    require_once XOOPS_ROOT_PATH . '/class/uploader.php';

    class TestMediaUploader extends XoopsMediaUploader
    {
        public $copyCalled = false;

        public function _copyFile($chmod)
        {
            $matched = [];
            if (!preg_match('/\.([a-zA-Z0-9]+)$/', $this->mediaName, $matched)) {
                $this->setErrors(_ER_UP_INVALIDFILENAME);

                return false;
            }

            if (isset($this->targetFileName)) {
                $this->savedFileName = $this->targetFileName;
            } elseif (isset($this->prefix)) {
                $this->savedFileName = uniqid($this->prefix, false) . '.' . strtolower($matched[1]);
            } else {
                $this->savedFileName = strtolower($this->mediaName);
            }

            $this->savedFileName    = iconv('UTF-8', 'ASCII//TRANSLIT', $this->savedFileName);
            $this->savedFileName    = preg_replace('!\s+!', '_', $this->savedFileName);
            $this->savedFileName    = preg_replace('/[^a-zA-Z0-9\._-]/', '', $this->savedFileName);
            $this->savedDestination = $this->uploadDir . '/' . $this->savedFileName;
            $this->copyCalled       = true;

            if (!copy($this->mediaTmpName, $this->savedDestination)) {
                $this->setErrors(sprintf(_ER_UP_FAILEDSAVEFILE, $this->savedDestination));

                return false;
            }

            if (false === chmod($this->savedDestination, $chmod)) {
                $this->setErrors(_ER_UP_MODE_NOT_CHANGED);
            }

            return true;
        }
    }

    class XoopsMediaUploaderTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            \Xmf\Request::reset();
            $GLOBALS['xoops']                  = new XoopsPathStub();
            $GLOBALS['xoopsConfig']['language'] = 'english';
        }

        public function testConstructorSetsLimitsAndReferencesAllowedTypes(): void
        {
            $allowed   = ['application/octet-stream'];
            $uploader  = new XoopsMediaUploader(sys_get_temp_dir(), $allowed, 2048, 80, 120, true);
            $allowed[] = 'text/plain';

            $this->assertSame(2048, $uploader->maxFileSize);
            $this->assertSame(80, $uploader->maxWidth);
            $this->assertSame(120, $uploader->maxHeight);
            $this->assertTrue($uploader->randomFilename);
            $this->assertContains('text/plain', $uploader->allowedMimeTypes, 'Allowed types should be referenced');
        }

        public function testCountMediaReportsMissingFile(): void
        {
            $uploader = new XoopsMediaUploader(sys_get_temp_dir(), ['image/png']);

            $this->assertFalse($uploader->countMedia('missing'));
            $this->assertContains(_ER_UP_FILENOTFOUND, $uploader->getErrors(false));
        }

        public function testCountMediaReturnsFileCount(): void
        {
            \Xmf\Request::$files = [
                'upload' => [
                    'name' => ['one.png', 'two.png'],
                ],
            ];

            $uploader = new XoopsMediaUploader(sys_get_temp_dir(), ['image/png']);

            $this->assertSame(2, $uploader->countMedia('upload'));
        }

        public function testFetchMediaFailsWhenMimeMapMissing(): void
        {
            $uploader               = new XoopsMediaUploader(sys_get_temp_dir(), ['image/png']);
            $uploader->extensionToMime = [];

            $this->assertFalse($uploader->fetchMedia('upload'));
            $this->assertContains(_ER_UP_MIMETYPELOAD, $uploader->getErrors(false));
        }

        public function testFetchMediaRequiresIndexForMultipleUpload(): void
        {
            \Xmf\Request::$files = [
                'upload' => [
                    'name' => ['one.png', 'two.png'],
                ],
            ];

            $uploader = new XoopsMediaUploader(sys_get_temp_dir(), ['image/png']);

            $this->assertFalse($uploader->fetchMedia('upload'));
            $this->assertContains(_ER_UP_INDEXNOTSET, $uploader->getErrors(false));
        }

        public function testUploadValidatesAndCopiesFile(): void
        {
            $uploadDir = sys_get_temp_dir() . '/xoops_upload_' . uniqid('', true);
            mkdir($uploadDir);
            $tmpFile = tempnam(sys_get_temp_dir(), 'media');
            file_put_contents($tmpFile, 'payload');

            $uploader                = new TestMediaUploader($uploadDir, ['application/octet-stream'], 512);
            $uploader->mediaName     = 'avatar.php.png';
            $uploader->mediaType     = 'application/octet-stream';
            $uploader->mediaRealType = 'application/octet-stream';
            $uploader->mediaTmpName  = $tmpFile;
            $uploader->mediaSize     = 32;
            $uploader->prefix        = 'pref';
            $uploader->checkImageType = false;

            $this->assertTrue($uploader->upload());
            $this->assertTrue($uploader->copyCalled);
            $this->assertStringStartsWith('pref', $uploader->savedFileName);
            $this->assertStringEndsWith('.png', $uploader->savedFileName);
            $this->assertFileExists($uploader->savedDestination);

            unlink($uploader->savedDestination);
            rmdir($uploadDir);
        }
    }
}
