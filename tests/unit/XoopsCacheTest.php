<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

if (!function_exists('apc_cache_info')) {
    $GLOBALS['apc_cache'] = [];

    function apc_cache_info($type = '')
    {
        return [];
    }

    function apc_store($key, $value, $duration = null)
    {
        $GLOBALS['apc_cache'][$key] = $value;

        return true;
    }

    function apc_fetch($key)
    {
        return $GLOBALS['apc_cache'][$key] ?? false;
    }

    function apc_delete($key)
    {
        if (is_array($key)) {
            $failed = [];
            foreach ($key as $entry) {
                if (!isset($GLOBALS['apc_cache'][$entry])) {
                    $failed[] = $entry;
                    continue;
                }
                unset($GLOBALS['apc_cache'][$entry]);
            }

            return $failed ?: true;
        }
        unset($GLOBALS['apc_cache'][$key]);

        return true;
    }

    function apc_clear_cache($type = '')
    {
        $GLOBALS['apc_cache'] = [];

        return true;
    }
}

if (!class_exists('Memcache')) {
    if (!defined('MEMCACHE_COMPRESSED')) {
        define('MEMCACHE_COMPRESSED', 0);
    }

    class Memcache
    {
        public array $servers = [];
        public array $data = [];

        public function addServer($host, $port = 11211)
        {
            $this->servers[] = [$host, $port];

            return true;
        }

        public function set($key, $value, $compress, $duration)
        {
            $this->data[$key] = $value;

            return true;
        }

        public function get($key)
        {
            return $this->data[$key] ?? false;
        }

        public function delete($key)
        {
            unset($this->data[$key]);

            return true;
        }

        public function flush()
        {
            $this->data = [];

            return true;
        }

        public function getServerStatus($host, $port)
        {
            return 1;
        }

        public function connect($host, $port)
        {
            $this->servers[] = [$host, $port];

            return true;
        }
    }
}

if (!class_exists('XoopsLoad')) {
    class XoopsLoad
    {
        public static function load($class)
        {
            return true;
        }
    }
}

if (!class_exists('XoopsFile')) {
    class XoopsFile
    {
        public $folder;
        public $name;

        public function __construct($path)
        {
            $this->folder = new class {
                public $path;

                public function cd($path)
                {
                    $this->path = $path;
                    if (!is_dir($path)) {
                        mkdir($path, 0777, true);
                    }

                    return $path;
                }

                public function inPath($path, $reverse = true)
                {
                    return str_starts_with($path, $this->path);
                }
            };
            $this->folder->cd(dirname($path));
        }

        public static function getHandler($name, $path, $create)
        {
            return new self($path);
        }

        public function pwd()
        {
            return $this->folder->path;
        }

        public function write($contents)
        {
            $target = $this->folder->path . '/' . $this->name;

            return false !== file_put_contents($target, $contents);
        }

        public function read($bytes = false)
        {
            $target = $this->folder->path . '/' . $this->name;
            if (!file_exists($target)) {
                return false;
            }
            $contents = file_get_contents($target);
            if ($bytes === true) {
                return $contents;
            }
            if (is_int($bytes)) {
                return substr($contents, 0, $bytes);
            }

            return $contents;
        }

        public function close()
        {
        }

        public function delete()
        {
            $target = $this->folder->path . '/' . $this->name;
            if (file_exists($target)) {
                unlink($target);
            }

            return true;
        }

        public function lastChange()
        {
            $target = $this->folder->path . '/' . $this->name;

            return file_exists($target) ? filemtime($target) : false;
        }
    }
}

if (!class_exists('XoopsUtility')) {
    class XoopsUtility
    {
        public static function recursive($callback, $data)
        {
            return array_map($callback, $data);
        }
    }
}

if (!class_exists('XoopsDatabaseFactory')) {
    class XoopsDatabaseFactory
    {
        public static function getDatabaseConnection()
        {
            return 'db';
        }
    }
}

require_once XOOPS_ROOT_PATH . '/kernel/object.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';
require_once XOOPS_ROOT_PATH . '/class/criteria/compo.php';
require_once XOOPS_ROOT_PATH . '/class/cache/xoopscache.php';
require_once XOOPS_ROOT_PATH . '/class/cache/apc.php';
require_once XOOPS_ROOT_PATH . '/class/cache/file.php';
require_once XOOPS_ROOT_PATH . '/class/cache/memcache.php';
require_once XOOPS_ROOT_PATH . '/class/cache/model.php';

class XoopsCacheDummy extends XoopsCacheEngine
{
    public array $written = [];
    public array $readKeys = [];
    public array $deleted = [];
    public array $cleared = [];
    public bool $gcCalled = false;

    public function init($settings = [])
    {
        parent::init($settings);

        return true;
    }

    public function gc()
    {
        $this->gcCalled = true;
    }

    public function write($key, $value, $duration = null)
    {
        $this->written[] = [$key, $value, $duration, $this->settings];

        return true;
    }

    public function read($key)
    {
        $this->readKeys[] = $key;

        return 'value-' . $key;
    }

    public function delete($key)
    {
        $this->deleted[] = $key;

        return true;
    }

    public function clear($check)
    {
        $this->cleared[] = $check;

        return true;
    }
}

class XoopsCacheTest extends TestCase
{
    protected function setUp(): void
    {
        $instance = XoopsCache::getInstance();
        $ref = new ReflectionObject($instance);
        foreach (['engine' => [], 'configs' => [], 'name' => null] as $property => $value) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($instance, $value);
        }
        $GLOBALS['apc_cache'] = [];
    }

    public function testConfigSetsEngineAndSettings(): void
    {
        $cache = XoopsCache::getInstance();

        $config = $cache->config('demo', ['engine' => 'dummy', 'duration' => 10, 'probability' => 1]);

        $this->assertSame('dummy', $config['engine']);
        $this->assertSame('demo', (new ReflectionObject($cache))->getProperty('name')->getValue($cache));
        $this->assertInstanceOf(XoopsCacheDummy::class, (new ReflectionObject($cache))->getProperty('engine')->getValue($cache)['dummy']);
    }

    public function testWriteReadAndDeleteUseEngineWithPrefixedKeys(): void
    {
        $cache = XoopsCache::getInstance();
        $cache->config('demo', ['engine' => 'dummy', 'duration' => 5, 'probability' => 1]);

        $this->assertTrue(XoopsCache::write('abc/def', 'payload', 2));
        $readValue = XoopsCache::read('abc/def');
        $this->assertStringContainsString('value-', (string) $readValue);
        $this->assertTrue(XoopsCache::delete('abc/def'));

        $engine = (new ReflectionObject($cache))->getProperty('engine')->getValue($cache)['dummy'];
        $expectedKey = substr(md5(XOOPS_URL), 0, 8) . '_abc_def';
        $this->assertSame($expectedKey, $engine->written[0][0]);
        $this->assertSame([$expectedKey], $engine->readKeys);
        $this->assertSame([$expectedKey], $engine->deleted);
    }

    public function testIsInitializedAndSettingsAccess(): void
    {
        $cache = XoopsCache::getInstance();
        $cache->config('demo', ['engine' => 'dummy', 'duration' => 5]);

        $this->assertTrue($cache->isInitialized('dummy'));
        $settings = $cache->settings('dummy');
        $this->assertSame(5, $settings['duration']);
    }

    public function testKeySanitization(): void
    {
        $cache = XoopsCache::getInstance();
        $this->assertSame('a_b_c', $cache->key('a/b.c'));
        $this->assertFalse($cache->key(''));
    }

    public function testCacheEngineInitializationDefaults(): void
    {
        $engine = new XoopsCacheEngine();
        $engine->init(['duration' => 50]);

        $this->assertSame(50, $engine->settings['duration']);
        $this->assertSame(100, $engine->settings['probability']);
    }

    public function testApcEngineProvidesBasicOperations(): void
    {
        $engine = new XoopsCacheApc();
        $this->assertTrue($engine->init());

        $this->assertTrue($engine->write('apc-key', 'value', 10));
        $this->assertSame('value', $engine->read('apc-key'));
        $this->assertTrue($engine->delete('apc-key'));
        $this->assertTrue($engine->clear());
    }

    public function testMemcacheEngineUsesConfiguredServers(): void
    {
        $engine = new XoopsCacheMemcache();
        $this->assertTrue($engine->init(['servers' => ['localhost:22122']]));

        $this->assertTrue($engine->write('mem-key', 'value', 3));
        $this->assertSame('value', $engine->read('mem-key'));
        $this->assertTrue($engine->delete('mem-key'));
        $this->assertTrue($engine->clear());
    }

    public function testCacheModelObjectInitialization(): void
    {
        $object = new XoopsCacheModelObject();
        $this->assertNull($object->getVar('key'));
        $this->assertNull($object->getVar('data'));
        $this->assertNull($object->getVar('expires'));
    }

    public function testCacheModelHandlerConfiguration(): void
    {
        $database = new class {
            public function prefix($name)
            {
                return 'pref_' . $name;
            }
        };

        $handler = new XoopsCacheModelHandler($database);

        $this->assertSame('pref_cache_model', $handler->table);
        $this->assertSame(XoopsCacheModelHandler::KEYNAME, $handler->keyName);
        $this->assertSame(XoopsCacheModelHandler::CLASSNAME, $handler->className);
    }

    public function testCacheModelReadWriteAndCleanup(): void
    {
        $model = new class {
            public string $keyname = 'key';
            public $inserted;
            public array $deleted = [];
            public $allCriteria;

            public function create()
            {
                return new XoopsCacheModelObject();
            }

            public function insert($object)
            {
                $this->inserted = $object;

                return true;
            }

            public function delete($key)
            {
                $this->deleted[] = $key;

                return true;
            }

            public function deleteAll($criteria = null)
            {
                $this->allCriteria = $criteria;

                return 'cleared';
            }

            public function getAll($criteria)
            {
                $this->allCriteria = $criteria;

                return [serialize(['hello' => 'world'])];
            }
        };

        $engine = new XoopsCacheModel();
        $engine->fields = ['data', 'expires'];
        $engine->model = $model;

        $this->assertTrue($engine->write('cache-key', ['hello' => 'world'], 5));
        $this->assertSame('cleared', $engine->clear());
        $this->assertSame(['hello' => 'world'], $engine->read('cache-key'));
        $this->assertTrue($engine->delete('cache-key'));
        $this->assertInstanceOf(Criteria::class, $model->allCriteria);

        $expires = $model->inserted->getVar('expires');
        $this->assertGreaterThanOrEqual(time(), $expires - 5);
    }
}
