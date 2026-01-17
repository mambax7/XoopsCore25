<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopslists.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/tinymce7/TinyMCE.php';

if (!function_exists('xoops_getcss')) {
    function xoops_getcss($theme = '')
    {
        return XOOPS_THEME_URL . '/tinymce.css';
    }
}

class TinyMCETest extends TestCase
{
    private string $themePath;
    private string $pluginBase;
    private string $settingsFile;

    protected function setUp(): void
    {
        $this->themePath = sys_get_temp_dir() . '/tinymce_theme';
        $this->pluginBase = XOOPS_ROOT_PATH . '/tinymce_test/js/tinymce/plugins';
        $this->settingsFile = sys_get_temp_dir() . '/tinymce_settings.php';

        $this->prepareTheme();
        $this->preparePlugins();
        $this->prepareSettings();

        $GLOBALS['xoops'] = new class($this->settingsFile) {
            private $settingsFile;
            public function __construct($settingsFile)
            {
                $this->settingsFile = $settingsFile;
            }
            public function path($path)
            {
                return $this->settingsFile;
            }
        };
        $GLOBALS['xoopsConfig'] = ['theme_set' => 'default'];

        TinyMCE::$listOfElementsTinymce = [];
        TinyMCE::$lastOfElementsTinymce = '';
    }

    private function prepareTheme(): void
    {
        if (!defined('XOOPS_THEME_PATH')) {
            define('XOOPS_THEME_PATH', $this->themePath);
        }
        if (!defined('XOOPS_THEME_URL')) {
            define('XOOPS_THEME_URL', 'http://example.com/theme');
        }
        if (!defined('XOOPS_URL')) {
            define('XOOPS_URL', 'http://example.com');
        }

        if (!is_dir($this->themePath)) {
            mkdir($this->themePath, 0777, true);
        }
        file_put_contents($this->themePath . '/tinymce.css', "@import url(sub.css);\nbody{}\n");
        file_put_contents($this->themePath . '/sub.css', '/* nested */');
    }

    private function preparePlugins(): void
    {
        if (!is_dir($this->pluginBase)) {
            mkdir($this->pluginBase, 0777, true);
        }
        @mkdir($this->pluginBase . '/alpha', 0777, true);
        @mkdir($this->pluginBase . '/beta', 0777, true);
    }

    private function prepareSettings(): void
    {
        file_put_contents($this->settingsFile, '<?php return ['
            . "'language' => 'fr',"
            . "'theme' => 'simple',"
            . "'mode' => 'textareas',"
            . "'plugins' => 'alpha,delta',"
            . "'content_css' => 'existing',"
            . '];');
    }

    public function testConstructorTracksElementsAndRootPath(): void
    {
        $editor = new TinyMCE(['rootpath' => '/tinymce_test', 'elements' => 'editor1']);

        $this->assertSame('/tinymce_test/js/tinymce', $editor->rootpath);
        $this->assertSame('editor1', TinyMCE::$lastOfElementsTinymce);
        $this->assertSame(['editor1'], TinyMCE::$listOfElementsTinymce);
        $this->assertSame('editor1', $editor->config['elements']);
    }

    public function testInitMergesConfigAndLoadsPluginsAndCss(): void
    {
        $editor = new TinyMCE([
            'rootpath' => '/tinymce_test',
            'elements' => 'editor2',
            'language' => 'es',
            'theme' => 'dark',
            'mode' => 'specific_textareas',
            'plugins' => ['beta', 'gamma'],
        ]);

        $editor->init();

        $this->assertSame('es', $editor->setting['language']);
        $this->assertSame('dark', $editor->setting['theme']);
        $this->assertSame('specific_textareas', $editor->setting['mode']);
        $this->assertSame('alpha,beta,gamma', $editor->setting['plugins']);
        $this->assertSame([
            'http://example.com/theme/tinymce.css',
            'http://example.com/theme/sub.css',
        ], $editor->setting['content_css']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testRenderOutputsScriptAndRawFunctions(): void
    {
        $editor = new TinyMCE([
            'rootpath' => '/tinymce_test',
            'elements' => 'editor3',
        ]);

        $output = $editor->render([
            'selector' => '#editor3',
            'debug' => true,
            'setup' => 'function(editor) { console.log(editor.id); }',
        ]);

        $this->assertStringContainsString("tinymce.min.js", $output);
        $this->assertStringContainsString('TinyMCE Rendering', $output);
        $this->assertStringContainsString('#editor3', $output);
        $this->assertStringContainsString('function(editor) { console.log(editor.id); }', $output);
    }
}
