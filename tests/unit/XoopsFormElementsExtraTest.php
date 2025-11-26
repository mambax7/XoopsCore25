<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

if (!defined('_SHORTDATESTRING')) {
    define('_SHORTDATESTRING', 'Y-m-d');
}

if (!function_exists('xoops_load')) {
    function xoops_load($name)
    {
        return true;
    }
}

if (!class_exists('XoopsLogger')) {
    class XoopsLogger
    {
        public array $deprecated = [];

        public function addDeprecated($message)
        {
            $this->deprecated[] = $message;
        }
    }
}

$GLOBALS['xoopsLogger'] = $GLOBALS['xoopsLogger'] ?? new XoopsLogger();

if (!class_exists('DummyRenderer')) {
    class DummyRenderer
    {
        public array $calls = [];

        public function __call($name, $arguments)
        {
            $this->calls[] = [$name, $arguments];

            return '<' . $name . '>';
        }
    }
}

if (!class_exists('XoopsFormRenderer')) {
    class XoopsFormRenderer
    {
        private static $instance;
        private $renderer;

        public function __construct()
        {
            $this->renderer = new DummyRenderer();
        }

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function get()
        {
            return $this->renderer;
        }
    }
}

if (!class_exists('XoopsCaptcha')) {
    class XoopsCaptcha
    {
        public array $configs = [];
        public bool $active = true;
        public static $instance;

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function setConfigs(array $configs)
        {
            $this->configs = $configs + $this->configs;
        }

        public function setConfig($name, $val)
        {
            $this->configs[$name] = $val;

            return $val;
        }

        public function isActive()
        {
            return $this->active;
        }

        public function getCaption()
        {
            return 'captcha-caption';
        }

        public function render()
        {
            return '[captcha]';
        }

        public function renderValidationJS()
        {
            return 'validate-captcha';
        }
    }
}

if (!class_exists('XoopsEditorHandler')) {
    class XoopsEditorHandler
    {
        public static $instance;

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function get($name, $options = [], $nohtml = false, $OnFailure = '')
        {
            return new class($name, $options) {
                public $name;
                public $options;
                public bool $_required = false;
                public string $caption = '';

                public function __construct($name, $options)
                {
                    $this->name    = $name;
                    $this->options = $options;
                }

                public function renderValidationJS()
                {
                    return 'editor-validation';
                }

                public function setName($name)
                {
                    $this->name = $name;
                }

                public function setCaption($caption)
                {
                    $this->caption = $caption;
                }
            };
        }
    }
}

if (!class_exists('XoopsLists')) {
    class XoopsLists
    {
        public static function getCountryList()
        {
            return ['US' => 'United States', 'CA' => 'Canada'];
        }

        public static function getTimeZoneList()
        {
            return ['UTC' => 'UTC'];
        }

        public static function getThemesList()
        {
            return ['default' => 'Default'];
        }
    }
}

if (!class_exists('XoopsCache')) {
    class XoopsCache
    {
        public static function read($key)
        {
            return [1 => 'CachedUser'];
        }

        public static function write($key, $value, $ttl)
        {
            return true;
        }
    }
}

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        if ($name === 'member') {
            return new class {
                public function getUserList($criteria)
                {
                    return [2 => 'SelectedUser'];
                }

                public function getUserCount()
                {
                    return 1;
                }
            };
        }

        return null;
    }
}

if (!class_exists('Criteria')) {
    class Criteria
    {
        public function __construct($column = '', $value = null, $operator = '=')
        {
        }

        public function setSort($sort)
        {
        }

        public function setOrder($order)
        {
        }
    }
}

if (!class_exists('CriteriaCompo')) {
    class CriteriaCompo extends Criteria
    {
        public function setLimit($limit)
        {
        }
    }
}

if (!class_exists('XoopsTheme')) {
    class XoopsTheme
    {
        public array $assigned = [];

        public function assign($key, $value)
        {
            $this->assigned[$key] = $value;
        }
    }
}

if (!class_exists('XoopsSecurity')) {
    class XoopsSecurity
    {
        public function createToken($timeout = 0, $name = 'XOOPS_TOKEN')
        {
            return 'token';
        }
    }
}

$GLOBALS['xoopsSecurity'] = $GLOBALS['xoopsSecurity'] ?? new XoopsSecurity();
$GLOBALS['xoopsConfig']   = $GLOBALS['xoopsConfig'] ?? ['anonymous' => 'Anon'];

require_once XOOPS_ROOT_PATH . '/class/xoopsform/formbutton.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formbuttontray.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formcaptcha.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formcheckbox.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formcolorpicker.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formdatetime.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formdhtmltextarea.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formeditor.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelementtray.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formfile.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formhidden.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formhiddentoken.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formlabel.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formpassword.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formradio.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formradioyn.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselect.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselectcheckgroup.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselectcountry.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselecteditor.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselectgroup.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselectlang.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselectmatchoption.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselecttheme.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselecttimezone.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formselectuser.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtext.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtextarea.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtextdateselect.php';

class XoopsFormElementsExtraTest extends TestCase
{
    public function testButtonsRenderThroughRenderer(): void
    {
        $button = new XoopsFormButton('Caption', 'btn', 'click', 'submit');
        $tray   = new XoopsFormButtonTray('actions', 'save');

        $this->assertSame('submit', $button->getType());
        $this->assertSame('save', $tray->getValue());
        $this->assertSame('submit', $tray->getType());
        $this->assertSame('<renderFormButton>', $button->render());
        $this->assertSame('<renderFormButtonTray>', $tray->render());
    }

    public function testCaptchaUsesHandler(): void
    {
        $captcha = new XoopsFormCaptcha('', 'mycaptcha');
        $this->assertSame('mycaptcha', $captcha->captchaHandler->configs['name']);
        $this->assertSame('[captcha]', $captcha->render());
        $this->assertSame('validate-captcha', $captcha->renderValidationJS());

        $captcha->captchaHandler->active = false;
        $hiddenCaptcha                   = new XoopsFormCaptcha('hidden', 'hiddenName');
        $this->assertTrue($hiddenCaptcha->isHidden());
    }

    public function testElementTrayAndHiddenFields(): void
    {
        $tray = new XoopsFormElementTray('Tray', '/', 'tray');
        $hid  = new XoopsFormHidden('hid', '123');
        $token = new XoopsFormHiddenToken('token_name');

        $tray->addElement($hid, true);
        $required = $tray->getRequired();

        $this->assertTrue($tray->isContainer());
        $this->assertTrue($tray->isRequired());
        $this->assertSame($hid, $required[0]);
        $this->assertSame('123', $hid->getValue());
        $this->assertNotEmpty($token->getValue());
    }

    public function testChoiceElementsManageOptions(): void
    {
        $check = new XoopsFormCheckBox('Check', 'choices', ['a']);
        $check->addOption('a', 'Alpha');
        $check->addOptionArray(['b' => 'Beta']);
        $this->assertSame(['a' => 'Alpha', 'b' => 'Beta'], $check->getOptions());

        $radio = new XoopsFormRadio('Radio', 'radio', 'x');
        $radio->addOption('x', 'Ex');
        $this->assertSame('radio', $radio->getName());
        $this->assertSame(['x' => 'Ex'], $radio->getOptions());

        $yesNo = new XoopsFormRadioYN('YN', 'yn', 1);
        $this->assertArrayHasKey(1, $yesNo->getOptions());
    }

    public function testSelectVariantsCollectOptions(): void
    {
        $select = new XoopsFormSelect('Select', 'sel', 'one', 1, false);
        $select->addOptionArray(['one' => 'One', 'two' => 'Two']);
        $this->assertSame(['one' => 'One', 'two' => 'Two'], $select->getOptions());

        $match = new XoopsFormSelectMatchOption('Match', 'match', 'start');
        $this->assertNotEmpty($match->getOptions());

        $lang = new XoopsFormSelectLang('Lang', 'lang', 'english');
        $this->assertNotEmpty($lang->getOptions());

        $country = new XoopsFormSelectCountry('Country', 'country', 'CA');
        $this->assertArrayHasKey('CA', $country->getOptions());

        $tz = new XoopsFormSelectTimezone('TZ', 'tz', 'UTC');
        $this->assertArrayHasKey('UTC', $tz->getOptions());

        $theme = new XoopsFormSelectTheme('Theme', 'theme', 'default', 1, false);
        $this->assertArrayHasKey('default', $theme->getOptions());

        $group = new XoopsFormSelectGroup('Group', 'group', false, 1, true);
        $this->assertTrue($group->isMultiple());

        $checkGroup = new XoopsFormSelectCheckGroup('CheckGroup', 'chk', false);
        $this->assertTrue($checkGroup->isMultiple());

        $editor = new XoopsFormSelectEditor(new XoopsTheme(), 'ed', 'plain');
        $this->assertNotEmpty($editor->getOptions());

        $user = new XoopsFormSelectUser('User', 'user', true, 2, 1, false);
        $elements = $user->getElements();
        $this->assertNotEmpty($elements);
    }

    public function testTextualElements(): void
    {
        $text = new XoopsFormText('Text', 'txt', 10, 255, 'value');
        $this->assertSame('value', $text->getValue());

        $area = new XoopsFormTextArea('Area', 'area', 'body', 3, 40);
        $this->assertSame('body', $area->getValue());

        $dateSelect = new XoopsFormTextDateSelect('Date', 'date', 15, 0);
        $this->assertSame(15, $dateSelect->getSize());

        $dhtml = new XoopsFormDhtmlTextArea('DHTML', 'dhtml', 'content', 5, 50, 'hidden', ['editor' => 'plain']);
        $this->assertNotNull($dhtml->htmlEditor);

        $editor = new XoopsFormEditor('Editor', 'textarea', ['name' => 'textarea'], false);
        $this->assertNotNull($editor->editor);

        $pass = new XoopsFormPassword('Password', 'pass', 10, 255, 'secret');
        $this->assertSame('secret', $pass->getValue());

        $color = new XoopsFormColorPicker('Color', 'color', '#fff');
        $this->assertSame('#fff', $color->getValue());

        $dt = new XoopsFormDateTime('DateTime', 'dt', 15, 0, XoopsFormDateTime::SHOW_BOTH);
        $this->assertTrue($dt->isContainer());
        $this->assertNotEmpty($dt->getElements());

        $file = new XoopsFormFile('File', 'file', 2048);
        $this->assertSame(2048, $file->getMaxFileSize());

        $label = new XoopsFormLabel('Label', 'value');
        $this->assertSame('value', $label->getValue());
    }
}
