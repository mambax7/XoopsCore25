<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/xoops_lib/vendor/smarty/smarty/libs/sysplugins/smarty_resource_custom.php';
require_once XOOPS_ROOT_PATH . '/class/smarty3_plugins/resource.db.php';
require_once XOOPS_ROOT_PATH . '/kernel/tplfile.php';

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return $GLOBALS['smarty_resource_db_handlers'][$name] ?? null;
    }
}

class SmartyResourceDbTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['smarty_resource_db_handlers'] = [];
        $GLOBALS['xoopsConfig'] = [
            'template_set' => 'default',
            'theme_set'    => 'default',
        ];
    }

    public function testFetchLoadsTemplateFromDatabase(): void
    {
        $GLOBALS['xoopsConfig']['template_set'] = 'custom';

        $tpl = $this->createMock(XoopsTplFile::class);
        $tpl->method('getVar')->willReturnMap([
            ['tpl_source', 'n', '<tpl>'],
            ['tpl_lastmodified', 'n', 123],
        ]);

        $handler = new class ($tpl) {
            public $calls = [];
            private $tpl;

            public function __construct($tpl)
            {
                $this->tpl = $tpl;
            }

            public function find($tplset, $tpl_module = null, $tpl_refid = null, $tpl_type = null, $tpl_name = null, $orderby = false)
            {
                $this->calls[] = [$tplset, $tpl_name];
                if ($tplset === 'custom') {
                    return [$this->tpl];
                }

                return [];
            }
        };

        $GLOBALS['smarty_resource_db_handlers']['tplfile'] = $handler;

        $resource = new Smarty_Resource_Db();
        $source = null;
        $mtime = null;

        $resource->fetch('db_template.tpl', $source, $mtime);

        $this->assertSame('<tpl>', $source);
        $this->assertSame(123, $mtime);
        $this->assertSame([['custom', 'db_template.tpl']], $handler->calls);
    }

    public function testFetchReadsTemplateFromFilesystem(): void
    {
        $GLOBALS['xoopsConfig']['template_set'] = 'custom';

        $handler = new class () {
            public $calls = 0;

            public function find($tplset, $tpl_module = null, $tpl_refid = null, $tpl_type = null, $tpl_name = null, $orderby = false)
            {
                $this->calls++;
                return [];
            }
        };
        $GLOBALS['smarty_resource_db_handlers']['tplfile'] = $handler;

        $file = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($file, 'file contents');

        $resource = new Smarty_Resource_Db();
        $source = null;
        $mtime = null;

        $resource->fetch($file, $source, $mtime);

        $this->assertSame('file contents', $source);
        $this->assertSame(filemtime($file), $mtime);
        $this->assertSame(2, $handler->calls);

        unlink($file);
    }

    public function testFetchHandlesMissingFilesystemTemplate(): void
    {
        $GLOBALS['xoopsConfig']['template_set'] = 'custom';

        $handler = new class () {
            public function find($tplset, $tpl_module = null, $tpl_refid = null, $tpl_type = null, $tpl_name = null, $orderby = false)
            {
                return [];
            }
        };
        $GLOBALS['smarty_resource_db_handlers']['tplfile'] = $handler;

        $resource = new Smarty_Resource_Db();
        $source = 'initial';
        $mtime = 1;

        $resource->fetch('/path/does/not/exist.tpl', $source, $mtime);

        $this->assertNull($source);
        $this->assertNull($mtime);
    }

    public function testDbTplInfoCachesLookups(): void
    {
        $GLOBALS['xoopsConfig']['template_set'] = 'custom';

        $handler = new class () {
            public $calls = 0;

            public function find($tplset, $tpl_module = null, $tpl_refid = null, $tpl_type = null, $tpl_name = null, $orderby = false)
            {
                $this->calls++;
                return [];
            }
        };
        $GLOBALS['smarty_resource_db_handlers']['tplfile'] = $handler;

        $resource = new Smarty_Resource_Db();
        $method = new ReflectionMethod(Smarty_Resource_Db::class, 'dbTplInfo');
        $method->setAccessible(true);

        $first = $method->invoke($resource, 'cache_test.tpl');
        $second = $method->invoke($resource, 'cache_test.tpl');

        $this->assertSame('cache_test.tpl', $first);
        $this->assertSame($first, $second);
        $this->assertSame(2, $handler->calls);
    }
}
