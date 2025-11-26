<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

if (!class_exists('XoopsDatabaseFactory')) {
    class XoopsDatabaseFactory
    {
        public static $connection;

        public static function getDatabaseConnection()
        {
            return static::$connection;
        }
    }
}

if (!class_exists('XoopsTpl')) {
    class XoopsTpl
    {
        public array $assigned = [];
        public array $appended = [];

        public function assign($key, $value): void
        {
            $this->assigned[$key] = $value;
        }

        public function append($key, $value): void
        {
            $this->appended[$key][] = $value;
        }
    }
}

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return new class {
            public function getUser($id)
            {
                return null;
            }
        };
    }
}

if (!function_exists('formatTimestamp')) {
    function formatTimestamp($time, $format = null)
    {
        return 'ts-' . $time;
    }
}

if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'http://example.com');
}

if (!defined('XOOPS_COMMENT_PENDING')) {
    define('XOOPS_COMMENT_PENDING', 1);
    define('XOOPS_COMMENT_ACTIVE', 2);
    define('XOOPS_COMMENT_HIDDEN', 3);
}

if (!defined('_CM_PENDING')) {
    define('_CM_PENDING', 'pending');
    define('_CM_ACTIVE', 'active');
    define('_CM_HIDDEN', 'hidden');
}

require_once XOOPS_ROOT_PATH . '/class/commentrenderer.php';

class StubComment extends XoopsObject
{
    public function __construct(array $values)
    {
        parent::__construct();
        foreach ($values as $key => $value) {
            $this->initVar($key, XOBJ_DTYPE_OTHER, $value, false);
        }
    }
}

class XoopsCommentRendererTest extends TestCase
{
    public function testRenderFlatViewSkipsInactiveWhenNotAdmin(): void
    {
        $tpl       = new XoopsTpl();
        $renderer  = new XoopsCommentRenderer($tpl, false, false);
        $anonymous = 'anon';
        $GLOBALS['xoopsConfig']['anonymous'] = $anonymous;

        $renderer->setComments($comments = [
            new StubComment([
                'com_id'      => 1,
                'com_pid'     => 0,
                'com_uid'     => 0,
                'com_user'    => '',
                'com_url'     => '',
                'com_title'   => 'First',
                'com_text'    => 'Hello',
                'com_status'  => XOOPS_COMMENT_ACTIVE,
                'com_created' => 10,
                'com_modified'=> 11,
                'com_icon'    => 'icon.gif',
            ]),
            new StubComment([
                'com_id'      => 2,
                'com_pid'     => 0,
                'com_uid'     => 0,
                'com_user'    => '',
                'com_url'     => '',
                'com_title'   => 'Second',
                'com_text'    => 'World',
                'com_status'  => XOOPS_COMMENT_PENDING,
                'com_created' => 20,
                'com_modified'=> 21,
                'com_icon'    => 'icon.gif',
            ]),
        ]);

        $renderer->renderFlatView(false);

        $this->assertCount(1, $tpl->appended['comments']);
        $this->assertSame('Hello', $tpl->appended['comments'][0]['text']);
        $this->assertSame($anonymous, $tpl->appended['comments'][0]['poster']['uname']);
    }

    public function testRenderFlatViewAdminShowsStatusAndAllComments(): void
    {
        $tpl      = new XoopsTpl();
        $renderer = new XoopsCommentRenderer($tpl, false, false);
        $GLOBALS['xoopsConfig']['anonymous'] = 'anon';

        $renderer->setComments($comments = [
            new StubComment([
                'com_id'      => 3,
                'com_pid'     => 0,
                'com_uid'     => 0,
                'com_user'    => '',
                'com_url'     => '',
                'com_title'   => 'Third',
                'com_text'    => 'Admin text',
                'com_status'  => XOOPS_COMMENT_HIDDEN,
                'com_created' => 30,
                'com_modified'=> 31,
                'com_icon'    => 'icon.gif',
            ]),
        ]);

        $renderer->renderFlatView(true);

        $this->assertCount(1, $tpl->appended['comments']);
        $this->assertStringContainsString(_CM_HIDDEN, $tpl->appended['comments'][0]['text']);
    }

    public function testGetTitleIconDefaultsWhenFileMissing(): void
    {
        $tpl      = new XoopsTpl();
        $renderer = new XoopsCommentRenderer($tpl, true, true);

        $GLOBALS['xoops'] = new class {
            public function path($path)
            {
                return sys_get_temp_dir() . '/' . $path;
            }
        };

        $icon = $renderer->_getTitleIcon('missing.gif');

        $this->assertStringContainsString('no_posticon.gif', $icon);
    }
}
