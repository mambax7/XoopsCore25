<?php

declare(strict_types=1);

namespace Xmf {
    if (!class_exists('Xmf\\Request')) {
        class Request
        {
            public static array $values = [];

            public static function reset(): void
            {
                self::$values = [];
            }

            public static function getString($name, $default = '', $type = 'GET')
            {
                return self::$values[$type][$name] ?? $default;
            }
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use Xmf\Request;

    require_once __DIR__ . '/init_new.php';

    if (!class_exists('XoopsTpl')) {
        class XoopsTpl
        {
            public static array $lastAssigned = [];
            public array $assigned = [];

            public function assign($key, $value): void
            {
                $this->assigned[$key] = $value;
            }

            public function fetch($template)
            {
                self::$lastAssigned = $this->assigned;

                return 'TPL:' . $template;
            }
        }
    }

    require_once XOOPS_ROOT_PATH . '/class/pagenav.php';

    class StubXoopsPath
    {
        private string $path;

        public function __construct(string $path)
        {
            $this->path = $path;
        }

        public function path($file): string
        {
            return $this->path;
        }
    }

    class XoopsPageNavTest extends TestCase
    {
        private string $stubInclude;

        protected function setUp(): void
        {
            parent::setUp();
            $this->stubInclude = sys_get_temp_dir() . '/xoops_pagenav_stub.php';
            file_put_contents($this->stubInclude, "<?php\n");
            $GLOBALS['xoops']   = new StubXoopsPath($this->stubInclude);
            $GLOBALS['xoTheme'] = (object) [];
            Request::reset();
            XoopsTpl::$lastAssigned = [];
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['xoops'], $GLOBALS['xoTheme']);
            @unlink($this->stubInclude);
            parent::tearDown();
        }

        public function testConstructorBuildsUrlAndExtra(): void
        {
            Request::$values['SERVER']['PHP_SELF'] = '/index.php';

            $nav = new \XoopsPageNav(100, 10, 30, 'offset', 'foo=bar');

            $this->assertSame(100, $nav->total);
            $this->assertSame(10, $nav->perpage);
            $this->assertSame(30, $nav->current);
            $this->assertSame('&amp;foo=bar', $nav->extra);
            $this->assertSame('/index.php?offset=', $nav->url);
        }

        public function testRenderNavAssignsNavigationData(): void
        {
            Request::$values['SERVER']['PHP_SELF'] = '/list.php';
            $nav = new \XoopsPageNav(50, 10, 10);

            $output = $nav->renderNav();

            $this->assertSame('TPL:db:system_pagenav.tpl', $output);
            $assigned = XoopsTpl::$lastAssigned;
            $this->assertSame('Nav', $assigned['pageNavType']);
            $navigation = $assigned['pageNavigation'];
            $this->assertSame('/list.php?start=0', $navigation[0]['url']);
            $this->assertSame('first', $navigation[0]['option']);
            $this->assertSame(2, $navigation[1]['value']);
            $this->assertSame('selected', $navigation[1]['option']);
            $this->assertSame('/list.php?start=20', $navigation[count($navigation) - 1]['url']);
            $this->assertSame('last', $navigation[count($navigation) - 1]['option']);
        }

        public function testRenderSelectIncludesButtonWhenRequested(): void
        {
            Request::$values['SERVER']['PHP_SELF'] = '/page.php';
            $nav = new \XoopsPageNav(40, 10, 0);

            $output = $nav->renderSelect(true);

            $this->assertSame('TPL:db:system_pagenav.tpl', $output);
            $assigned = XoopsTpl::$lastAssigned;
            $this->assertSame('Select', $assigned['pageNavType']);
            $this->assertTrue($assigned['pageNavigation']['button']);
            $this->assertStringContainsString('selected>', $assigned['pageNavigation']['select']);
            $this->assertStringContainsString('/page.php?start=0', $assigned['pageNavigation']['select']);
        }

        public function testRenderImageNavUsesEmptyMarkersAtEdges(): void
        {
            Request::$values['SERVER']['PHP_SELF'] = '/images.php';
            $nav = new \XoopsPageNav(20, 10, 0);

            $output = $nav->renderImageNav();

            $this->assertSame('TPL:db:system_pagenav.tpl', $output);
            $assigned = XoopsTpl::$lastAssigned;
            $this->assertSame('Image', $assigned['pageNavType']);
            $navigation = $assigned['pageNavigation'];
            $this->assertSame('firstempty', $navigation[0]['option']);
            $this->assertSame('selected', $navigation[1]['option']);
            $this->assertSame('last', $navigation[count($navigation) - 1]['option']);
        }

        public function testRenderMethodsReturnEmptyWhenSinglePage(): void
        {
            Request::$values['SERVER']['PHP_SELF'] = '/single.php';
            $nav = new \XoopsPageNav(5, 10, 0);

            $this->assertSame('', $nav->renderNav());
            $this->assertSame('', $nav->renderSelect());
            $this->assertSame('', $nav->renderImageNav());
        }
    }
}
