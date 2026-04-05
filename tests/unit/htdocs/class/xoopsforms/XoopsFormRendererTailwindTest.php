<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package     tests
 * @subpackage  xoopsforms
 * @since       2.5.12
 * @author      XOOPS Project
 */

declare(strict_types=1);

namespace xoopsforms;

use PHPUnit\Framework\TestCase;
use XoopsFormButton;
use XoopsFormButtonTray;
use XoopsFormElementTray;
use XoopsFormLabel;
use XoopsFormRendererTailwind;
use XoopsFormTextDateSelect;

xoops_load('XoopsFormElement');
xoops_load('XoopsFormButton');
xoops_load('XoopsFormButtonTray');
xoops_load('XoopsFormElementTray');
xoops_load('XoopsFormHidden');
xoops_load('XoopsFormHiddenToken');
xoops_load('XoopsFormLabel');
xoops_load('XoopsFormRenderer');
xoops_load('XoopsFormRendererInterface');
xoops_load('XoopsFormRendererTailwind');
xoops_load('XoopsFormText');
xoops_load('XoopsFormTextDateSelect');

/**
 * Unit tests for XoopsFormRendererTailwind.
 *
 * Focus areas:
 *   - renderer can be instantiated
 *   - HTML attributes are escaped (XSS defense)
 *   - renderFormLabel produces a properly closed label element
 *   - renderFormElementTray picks the correct container class for orientation
 *
 * @category  XoopsForm
 * @package   tests
 * @author    XOOPS Project
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
class XoopsFormRendererTailwindTest extends TestCase
{
    private XoopsFormRendererTailwind $renderer;

    protected function setUp(): void
    {
        $this->renderer = new XoopsFormRendererTailwind();
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(XoopsFormRendererTailwind::class, $this->renderer);
    }

    public function testRenderFormButtonEscapesValue(): void
    {
        $element = new XoopsFormButton('Caption', 'btn', '<script>alert(1)</script>');
        $html    = $this->renderer->renderFormButton($element);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('class="btn btn-neutral"', $html);
    }

    public function testRenderFormButtonEscapesName(): void
    {
        $element = new XoopsFormButton('Caption', '"><img src=x>', 'Click');
        $html    = $this->renderer->renderFormButton($element);

        $this->assertStringNotContainsString('<img src=x>', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function testRenderFormLabelHasClosingTag(): void
    {
        $element = new XoopsFormLabel('Caption', 'Label text', 'labelname');
        $html    = $this->renderer->renderFormLabel($element);

        $this->assertStringContainsString('<label', $html);
        $this->assertStringContainsString('</label>', $html);
        $this->assertStringContainsString('Label text', $html);
    }

    public function testRenderFormLabelEscapesValue(): void
    {
        $element = new XoopsFormLabel('Caption', '<b>bold</b>', 'name');
        $html    = $this->renderer->renderFormLabel($element);

        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function testRenderFormElementTrayVerticalUsesSpaceY(): void
    {
        $tray = new XoopsFormElementTray('Tray');
        $tray->setOrientation(XoopsFormElementTray::ORIENTATION_VERTICAL);
        $tray->addElement(new XoopsFormButton('A', 'a', 'A'));
        $tray->addElement(new XoopsFormButton('B', 'b', 'B'));

        $html = $this->renderer->renderFormElementTray($tray);

        $this->assertStringContainsString('space-y-2', $html);
        $this->assertStringNotContainsString('inline-flex', $html);
    }

    public function testRenderFormElementTrayHorizontalUsesFlexWrap(): void
    {
        $tray = new XoopsFormElementTray('Tray');
        $tray->setOrientation(XoopsFormElementTray::ORIENTATION_HORIZONTAL);
        $tray->addElement(new XoopsFormButton('A', 'a', 'A'));
        $tray->addElement(new XoopsFormButton('B', 'b', 'B'));

        $html = $this->renderer->renderFormElementTray($tray);

        $this->assertStringContainsString('flex flex-wrap', $html);
        $this->assertStringContainsString('inline-flex', $html);
    }

    public function testRenderFormCheckBoxEscapesOptionLabel(): void
    {
        xoops_load('XoopsFormCheckBox');
        $element = new \XoopsFormCheckBox('Caption', 'name', '1');
        $element->addOption('1', '<script>alert(1)</script>');
        $html = $this->renderer->renderFormCheckBox($element);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderThemeFormDoesNotThrowOnFormExtras(): void
    {
        xoops_load('XoopsThemeForm');
        $form = new \XoopsThemeForm('Test Form', 'testform', 'action.php', 'post');
        $form->addElement(new XoopsFormButton('Submit', 'submit', 'Go'));

        // Must not throw TypeError — renderExtra previously only accepted XoopsFormElement
        $html = $this->renderer->renderThemeForm($form);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('action="action.php"', $html);
        $this->assertStringContainsString('</form>', $html);
    }

    public function testRenderEditorButtonEscapesTitleAttribute(): void
    {
        // Anonymous subclass exposes the protected helper without coupling the
        // test to the full DHTML editor pipeline (MyTextSanitizer, xoopsSecurity, …)
        $renderer = new class extends XoopsFormRendererTailwind {
            public function exposedRenderEditorButton(string $title): string
            {
                return $this->renderEditorButton('btn btn-sm', 'noop()', $title, 'fa-solid fa-x');
            }
        };

        $html = $renderer->exposedRenderEditorButton('"><script>alert(1)</script>');

        // Raw payload must not appear anywhere in the output
        $this->assertStringNotContainsString('"><script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        // Escaped form must appear inside the title='...' attribute
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString("title='", $html);
    }

    public function testRenderFormButtonTrayEscapesDeleteAndResetLabels(): void
    {
        // Override esc() so we can prove _DELETE / _RESET flow through the escape
        // path without having to redefine the language constants (which is
        // impossible once they are defined by the XOOPS bootstrap).
        $renderer = new class extends XoopsFormRendererTailwind {
            protected function esc($value): string
            {
                return '[ESC]' . parent::esc($value) . '[/ESC]';
            }
        };

        $tray = new XoopsFormButtonTray('Submit', 'Go', 'submit', '', true);
        $html = $renderer->renderFormButtonTray($tray);

        $expectedDelete = '[ESC]' . htmlspecialchars((string) _DELETE, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '[/ESC]';
        $expectedReset  = '[ESC]' . htmlspecialchars((string) _RESET, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '[/ESC]';
        $expectedCancel = '[ESC]' . htmlspecialchars((string) _CANCEL, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '[/ESC]';

        $this->assertStringContainsString($expectedDelete, $html, '_DELETE must flow through esc()');
        $this->assertStringContainsString($expectedReset, $html, '_RESET must flow through esc()');
        // _CANCEL was already escaped before this fix — guard the baseline.
        $this->assertStringContainsString($expectedCancel, $html, '_CANCEL must flow through esc()');
    }

    public function testEsJsEncodesScriptBreakoutAndQuotes(): void
    {
        // Anonymous subclass exposes the protected helper so we can assert the
        // JSON-encoded form directly, without threading a malicious payload
        // through a full render pipeline.
        $renderer = new class extends XoopsFormRendererTailwind {
            public function exposedEsJs(string $value): string
            {
                return $this->esJs($value);
            }
        };

        // </script> must be hex-escaped so it cannot terminate an inline block
        $this->assertSame('"\u003C\/script\u003Ealert(1)"', $renderer->exposedEsJs('</script>alert(1)'));

        // Double quotes must be hex-escaped so they cannot close the JS string literal
        $this->assertSame('"he said \u0022hi\u0022"', $renderer->exposedEsJs('he said "hi"'));

        // Single quotes must be hex-escaped (JSON_HEX_APOS) for defense in depth
        $this->assertSame('"it\u0027s fine"', $renderer->exposedEsJs("it's fine"));

        // Ampersand must be hex-escaped (JSON_HEX_AMP)
        $this->assertSame('"a \u0026 b"', $renderer->exposedEsJs('a & b'));

        // Unicode passes through unescaped (JSON_UNESCAPED_UNICODE) so
        // translations remain human-readable
        $this->assertSame('"Übergröße"', $renderer->exposedEsJs('Übergröße'));
    }

    public function testBuildCalendarLocaleJsEncodesLocaleStrings(): void
    {
        // Anonymous subclass exposes the protected helper directly, so the test
        // does not depend on renderFormTextDateSelect() static state (the
        // $included / $fallbackEmitted flags that gate the fallback branch).
        $renderer = new class extends XoopsFormRendererTailwind {
            public function exposedBuildCalendarLocaleJs(string $jstime): string
            {
                return $this->buildCalendarLocaleJs($jstime);
            }
        };

        $js = $renderer->exposedBuildCalendarLocaleJs('01/15/2026');

        // Locale strings must appear JSON-encoded (surrounded by quotes),
        // not bare PHP-string-interpolated values.
        $this->assertStringContainsString('"Sunday"', $js);
        $this->assertStringContainsString('"January"', $js);
        $this->assertStringContainsString('"December"', $js);

        // The seed date is encoded via esJs(), not concatenated raw
        $this->assertStringContainsString('"01\/15\/2026"', $js);

        // showCalendar() definition must still be present — the button onclick
        // handler references it, so removing it would leave a dead control
        $this->assertStringContainsString('function showCalendar(', $js);
    }

    public function testResolveCalendarLanguageFileRejectsTraversal(): void
    {
        // Expose the protected resolver so we can assert the hardening logic
        // without triggering include_once on the language file (which would
        // re-define the _CAL_* constants the test bootstrap already provides).
        $renderer = new class extends XoopsFormRendererTailwind {
            public function exposedResolveCalendarLanguageFile(): string
            {
                return $this->resolveCalendarLanguageFile();
            }
        };

        $previousLang = $GLOBALS['xoopsConfig']['language'] ?? null;
        // Rejected inputs return the naive (non-canonicalized) english
        // fallback path; the happy path below canonicalizes via realpath.
        $englishFallback = XOOPS_ROOT_PATH . '/language/english/calendar.php';
        $englishCanonical = realpath($englishFallback);
        $this->assertNotFalse($englishCanonical, 'english calendar.php must exist in the repo');

        try {
            // Layer 1 — allowlist rejections all return the naive fallback
            // path without ever touching the filesystem for the malicious
            // input.
            $GLOBALS['xoopsConfig']['language'] = '../../../etc';
            $this->assertSame($englishFallback, $renderer->exposedResolveCalendarLanguageFile(), 'traversal must be rejected');

            $GLOBALS['xoopsConfig']['language'] = '/etc/passwd';
            $this->assertSame($englishFallback, $renderer->exposedResolveCalendarLanguageFile(), 'absolute path must be rejected');

            $GLOBALS['xoopsConfig']['language'] = "english\x00/../../../etc";
            $this->assertSame($englishFallback, $renderer->exposedResolveCalendarLanguageFile(), 'null byte injection must be rejected');

            $GLOBALS['xoopsConfig']['language'] = 'en us';
            $this->assertSame($englishFallback, $renderer->exposedResolveCalendarLanguageFile(), 'space must be rejected');

            $GLOBALS['xoopsConfig']['language'] = '';
            $this->assertSame($englishFallback, $renderer->exposedResolveCalendarLanguageFile(), 'empty string must be rejected');

            // Valid plain language name that does not ship a calendar.php —
            // regex passes but is_file() fails, so we hit the same fallback.
            $GLOBALS['xoopsConfig']['language'] = 'no_such_language';
            $this->assertSame($englishFallback, $renderer->exposedResolveCalendarLanguageFile(), 'missing per-language calendar.php must fall back');

            // Missing config entirely — default to english, which ships with
            // core. Canonicalized via realpath (happy path).
            unset($GLOBALS['xoopsConfig']['language']);
            $this->assertSame($englishCanonical, $renderer->exposedResolveCalendarLanguageFile(), 'missing config must resolve to english');

            // Explicit english — same canonicalized result.
            $GLOBALS['xoopsConfig']['language'] = 'english';
            $this->assertSame($englishCanonical, $renderer->exposedResolveCalendarLanguageFile(), 'explicit english must resolve');
        } finally {
            if ($previousLang === null) {
                unset($GLOBALS['xoopsConfig']['language']);
            } else {
                $GLOBALS['xoopsConfig']['language'] = $previousLang;
            }
        }
    }
}
