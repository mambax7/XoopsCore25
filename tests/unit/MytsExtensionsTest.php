<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/module.textsanitizer.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xoopsload.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/iframe/iframe.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/image/image.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/li/li.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/mms/mms.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/mp3/mp3.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/rtsp/rtsp.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/soundcloud/soundcloud.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/syntaxhighlight/syntaxhighlight.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/ul/ul.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/wiki/wiki.php';
require_once XOOPS_TU_ROOT_PATH . '/class/textsanitizer/wmp/wmp.php';

class MytsExtensionsTest extends TestCase
{
    private MyTextSanitizer $myts;

    protected function setUp(): void
    {
        $this->myts                 = MyTextSanitizer::getInstance();
        $this->myts->patterns       = [];
        $this->myts->replacements   = [];
        $this->myts->callbackPatterns = [];
        $this->myts->callbacks        = [];
        $this->myts->config         = [];
        $GLOBALS['xoopsConfig']     = ['language' => 'english'];
        $GLOBALS['xoops']           = new class {
            public function path($path)
            {
                return XOOPS_ROOT_PATH . '/' . ltrim($path, '/');
            }
        };
    }

    public function testIframeLoadAddsPattern(): void
    {
        $extension = new MytsIframe($this->myts);
        $this->assertTrue($extension->load($this->myts));

        $this->assertCount(1, $this->myts->patterns);
        $this->assertCount(1, $this->myts->replacements);
        $this->assertStringContainsString('[iframe', $this->myts->patterns[0]);
        $this->assertStringContainsString('<iframe', $this->myts->replacements[0]);
    }

    public function testImageLoadWithImagesDisallowed(): void
    {
        $this->myts->config = ['allowimage' => false];
        $extension          = new MytsImage($this->myts);

        $this->assertTrue($extension->load($this->myts));
        $this->assertCount(6, $this->myts->patterns);
        $this->assertCount(6, $this->myts->replacements);
        $this->assertStringContainsString('image.php?id=\\2', $this->myts->replacements[5]);
    }

    public function testLiAndUlLoadCreateListMarkup(): void
    {
        $li  = new MytsLi($this->myts);
        $ul  = new MytsUl($this->myts);

        $this->assertTrue($li->load($this->myts));
        $this->assertTrue($ul->load($this->myts));

        $this->assertContains('<li>\\1</li>', $this->myts->replacements);
        $this->assertContains('<ul>\\1</ul>', $this->myts->replacements);
    }

    public function testMmsEncodeAndLoad(): void
    {
        $extension = new MytsMms($this->myts);
        [$button, $javascript] = $extension->encode('area');

        $this->assertStringContainsString('xoopsCodeMms', $button);
        $this->assertStringContainsString('xoopsCodeMms', $javascript);

        $this->assertTrue($extension->load($this->myts));
        $this->assertNotEmpty($this->myts->patterns);
        $this->assertStringContainsString('videowindow1', $this->myts->replacements[0]);
    }

    public function testMp3EncodingLoadingAndDecoding(): void
    {
        $extension = new MytsMp3($this->myts);
        [$button, $javascript] = $extension->encode('mp3area');
        $this->assertStringContainsString('xoopsCodeMp3', $button);
        $this->assertStringContainsString('xoopsCodeMp3', $javascript);

        $this->assertTrue($extension->load($this->myts));
        $this->assertSame('/\[mp3\](.*?)\[\/mp3\]/s', $this->myts->callbackPatterns[0]);
        $this->assertSame(MytsMp3::class . '::decode', $this->myts->callbacks[0]);

        $html = MytsMp3::decode(['', 'http://example.com/song.mp3']);
        $this->assertStringContainsString('audio', $html);
        $this->assertStringContainsString('example.com/song.mp3', $html);
    }

    public function testRtspEncodeAndLoad(): void
    {
        $extension = new MytsRtsp($this->myts);
        [$button, $javascript] = $extension->encode('rtsparea');
        $this->assertStringContainsString('xoopsCodeRtsp', $button);
        $this->assertStringContainsString('xoopsCodeRtsp', $javascript);

        $extension->load($this->myts);
        $this->assertNotEmpty($this->myts->patterns);
        $this->assertStringContainsString('rtsp', $this->myts->patterns[0]);
        $this->assertStringContainsString('clsid:CFCDAA03', $this->myts->replacements[0]);
    }

    public function testSoundcloudLoadAndCallback(): void
    {
        $extension = new MytsSoundcloud($this->myts);
        $this->assertEmpty($this->myts->callbackPatterns);
        $extension->load($this->myts);

        $this->assertSame('/\[soundcloud\](http[s]?:\/\/[^\"\'<>]*)(.*)\[\/soundcloud\]/sU', $this->myts->callbackPatterns[0]);
        $embed = MytsSoundcloud::myCallback([null, 'https://soundcloud.com/user/track', '']);
        $this->assertStringContainsString('player.soundcloud.com', $embed);

        $this->expectWarning();
        $this->assertSame('', MytsSoundcloud::myCallback([null, 'https://example.com', '']));
    }

    public function testSyntaxHighlightReturnsPreWhenDisabled(): void
    {
        $extension   = new MytsSyntaxhighlight($this->myts);
        $configFile  = $this->myts->path_config . '/config.syntaxhighlight.php';
        $original    = file_exists($configFile) ? file_get_contents($configFile) : null;
        file_put_contents($configFile, "<?php\nreturn ['highlight' => ''];\n");

        $output = $extension->load($this->myts, 'echo 1;', 'php');
        $this->assertSame('<pre>echo 1;</pre>', $output);

        if (null !== $original) {
            file_put_contents($configFile, $original);
        }
    }

    public function testUlLoad(): void
    {
        $extension = new MytsUl($this->myts);
        $this->assertTrue($extension->load($this->myts));
        $this->assertSame('<ul>\\1</ul>', end($this->myts->replacements));
    }

    public function testWikiCallbacks(): void
    {
        $extension = new MytsWiki($this->myts);
        $extension->load($this->myts);

        $this->assertSame('/\[\[([^\]]*)\]\]/sU', $this->myts->callbackPatterns[0]);
        $link = MytsWiki::decode('ExampleTerm', 0, 0);
        $this->assertStringContainsString('ExampleTerm', $link);
        $this->assertStringContainsString('mediawiki', $link);
    }

    public function testWmpEncodeAndLoad(): void
    {
        $extension = new MytsWmp($this->myts);
        [$button, $javascript] = $extension->encode('wmparea');
        $this->assertStringContainsString('xoopsCodeWmp', $button);
        $this->assertStringContainsString('xoopsCodeWmp', $javascript);

        $extension->load($this->myts);
        $this->assertNotEmpty($this->myts->patterns);
        $this->assertStringContainsString('WindowsMediaPlayer', $this->myts->replacements[0]);
    }
}
