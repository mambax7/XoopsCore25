<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

if (!class_exists('MyTextSanitizer')) {
    class MyTextSanitizer
    {
        public static function getInstance()
        {
            return new self();
        }

        public function htmlSpecialChars($text)
        {
            return htmlspecialchars((string) $text, ENT_QUOTES);
        }
    }
}

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return $GLOBALS['userutility_handlers'][$name] ?? null;
    }
}

if (!function_exists('xoops_getMailer')) {
    function xoops_getMailer()
    {
        return $GLOBALS['userutility_mailer'];
    }
}

if (!function_exists('xoops_loadLanguage')) {
    function xoops_loadLanguage($name): void
    {
        $GLOBALS['userutility_languages'][] = $name;
    }
}

if (!class_exists('\\Xmf\\IPAddress')) {
    class XmfIPAddressStub
    {
        public static $request = '127.0.0.1';
        private $value;

        public function __construct($value)
        {
            $this->value = $value;
        }

        public static function fromRequest()
        {
            return new self(self::$request);
        }

        public function asReadable()
        {
            return $this->value === 'invalid' ? false : $this->value;
        }

        public function asBinary()
        {
            return $this->value;
        }
    }
    class_alias(XmfIPAddressStub::class, 'Xmf\\IPAddress');
    class_alias(XmfIPAddressStub::class, 'Xmf\\IPAddressStub');
}

require_once XOOPS_ROOT_PATH . '/class/userutility.php';

if (!function_exists('checkEmail')) {
    function checkEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('xoops_trim')) {
    function xoops_trim($text)
    {
        return trim((string) $text);
    }
}

if (!class_exists('XoopsDatabaseFactory')) {
    class XoopsDatabaseFactory
    {
        public static $connection;

        public static function getDatabaseConnection()
        {
            return self::$connection;
        }
    }
}

if (!defined('XOOPS_CONF_USER')) {
    define('XOOPS_CONF_USER', 2);
}

foreach ([
             '_US_WELCOME_SUBJECT' => 'Welcome to %s',
             '_US_INVALIDMAIL'     => 'Invalid email',
             '_US_EMAILNOSPACES'   => 'Email has spaces',
             '_US_INVALIDNICKNAME' => 'Invalid nickname',
             '_US_NICKNAMETOOLONG' => 'Nickname too long (%s)',
             '_US_NICKNAMETOOSHORT'=> 'Nickname too short (%s)',
             '_US_NAMERESERVED'    => 'Name reserved',
             '_US_NICKNAMETAKEN'   => 'Nickname taken',
             '_US_EMAILTAKEN'      => 'Email taken',
             '_US_ENTERPWD'        => 'Enter password',
             '_US_PASSNOTSAME'     => 'Passwords not same',
             '_US_PWDTOOSHORT'     => 'Password too short (%s)',
             '_DB_QUERY_ERROR'     => 'Query error: %s',
         ] as $const => $value) {
    if (!defined($const)) {
        define($const, $value);
    }
}

if (!defined('XOOPS_URL')) {
    define('XOOPS_URL', 'https://xoops.example.com');
}

class DummyResult
{
    private $rows;
    private $index = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetchRow()
    {
        if (!isset($this->rows[$this->index])) {
            return false;
        }
        $row = $this->rows[$this->index];
        ++$this->index;

        return array_values($row);
    }

    public function fetchArray()
    {
        if (!isset($this->rows[$this->index])) {
            return false;
        }
        $row = $this->rows[$this->index];
        ++$this->index;

        return $row;
    }
}

class DummyDatabase
{
    public array $queries = [];
    private array $results;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function prefix($table)
    {
        return 'prefix_' . $table;
    }

    public function query($sql)
    {
        $this->queries[] = $sql;

        return array_shift($this->results);
    }

    public function isResultSet($result)
    {
        return $result instanceof DummyResult;
    }

    public function fetchRow($result)
    {
        return $result->fetchRow();
    }

    public function fetchArray($result)
    {
        return $result->fetchArray();
    }

    public function quote($value)
    {
        return "'" . $value . "'";
    }

    public function error()
    {
        return 'db error';
    }
}

class DummyUser
{
    private $vars;

    public function __construct(array $vars)
    {
        $this->vars = $vars;
    }

    public function getVar($name, $format = null)
    {
        return $this->vars[$name] ?? null;
    }
}

class DummyMailer
{
    public array $methods = [];
    public bool $sendResult = true;

    public function useMail(): void
    {
        $this->methods[] = 'mail';
    }

    public function usePM(): void
    {
        $this->methods[] = 'pm';
    }

    public function setTemplate($tpl): void
    {
        $this->methods[] = ['template', $tpl];
    }

    public function setSubject($subject): void
    {
        $this->methods[] = ['subject', $subject];
    }

    public function setToUsers($user): void
    {
        $this->methods[] = ['to', $user];
    }

    public function assign($key, $value): void
    {
        $this->methods[] = ['assign', $key, $value];
    }

    public function send()
    {
        return $this->sendResult;
    }
}

class XoopsUserUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['userutility_handlers']  = [];
        $GLOBALS['userutility_mailer']    = null;
        $GLOBALS['userutility_languages'] = [];
        $GLOBALS['xoopsConfigUser']       = [];
        $GLOBALS['xoopsConfig']           = ['sitename' => 'Test Site'];
        $GLOBALS['xoopsConfig']['anonymous'] = 'anonymous';
        $GLOBALS['xoopsUser']             = null;
    }

    public function testSendWelcomeReturnsEarlyWhenWelcomeTypeDisabled(): void
    {
        $configHandler = new class {
            public function getConfigsByCat($cat)
            {
                return ['welcome_type' => 0];
            }
        };
        $GLOBALS['userutility_handlers']['config'] = $configHandler;
        $mailer                                    = new DummyMailer();
        $GLOBALS['userutility_mailer']             = $mailer;

        $result = \XoopsUserUtility::sendWelcome(new DummyUser([]));

        $this->assertTrue($result);
        $this->assertSame([], $mailer->methods);
    }

    public function testSendWelcomeLoadsUserAndSendsMailAndPm(): void
    {
        $GLOBALS['xoopsConfigUser'] = [
            'welcome_type'   => 3,
            'reg_dispdsclmr' => 1,
            'reg_disclaimer' => 'Terms go here',
        ];
        $GLOBALS['xoopsConfig']['sitename'] = 'My Site';

        $memberHandler = new class {
            public function getUser($uid)
            {
                return new DummyUser(['uid' => $uid]);
            }
        };
        $GLOBALS['userutility_handlers']['member'] = $memberHandler;

        $mailer                        = new DummyMailer();
        $GLOBALS['userutility_mailer'] = $mailer;

        $result = \XoopsUserUtility::sendWelcome(12);

        $this->assertTrue($result);
        $this->assertEquals(
            [
                'mail',
                'pm',
                ['template', 'welcome.tpl'],
                ['subject', 'Welcome to My Site'],
                ['to', new DummyUser(['uid' => 12])],
                ['assign', 'TERMSOFUSE', 'Terms go here'],
            ],
            $mailer->methods
        );
        $this->assertSame(['user'], $GLOBALS['userutility_languages']);
    }

    public function testSendWelcomeReturnsFalseWhenUserMissing(): void
    {
        $GLOBALS['xoopsConfigUser'] = ['welcome_type' => 1];
        $GLOBALS['userutility_handlers']['member'] = new class {
            public function getUser($uid)
            {
                return null;
            }
        };

        $mailer                        = new DummyMailer();
        $GLOBALS['userutility_mailer'] = $mailer;

        $this->assertFalse(\XoopsUserUtility::sendWelcome(99));
        $this->assertSame([], $mailer->methods);
    }

    public function testValidateReturnsErrorsForInvalidEmailAndNickname(): void
    {
        $GLOBALS['xoopsConfigUser'] = [
            'bad_emails'       => [],
            'uname_test_level' => 0,
            'maxuname'         => 5,
            'minuname'         => 3,
            'bad_unames'       => [],
            'minpass'          => 3,
        ];
        XoopsDatabaseFactory::$connection = new DummyDatabase([
            new DummyResult([[0], [0]]),
            new DummyResult([[0], [0]]),
        ]);

        $errors = \XoopsUserUtility::validate('bad name', 'invalid email');

        $this->assertStringContainsString('Invalid email', $errors);
        $this->assertStringContainsString('Email has spaces', $errors);
        $this->assertStringContainsString('Invalid nickname', $errors);
    }

    public function testValidateChecksPasswordLength(): void
    {
        $GLOBALS['xoopsConfigUser'] = [
            'bad_emails'       => [],
            'uname_test_level' => 0,
            'maxuname'         => 10,
            'minuname'         => 3,
            'bad_unames'       => [],
            'minpass'          => 5,
        ];
        XoopsDatabaseFactory::$connection = new DummyDatabase([
            new DummyResult([[0]]),
            new DummyResult([[0]]),
        ]);

        $errors = \XoopsUserUtility::validate('good', 'good@example.com', '123', '123');

        $this->assertStringContainsString('Password too short (5)', $errors);
    }

    public function testValidateThrowsWhenQueryFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Query error');

        $GLOBALS['xoopsConfigUser'] = [
            'bad_emails'       => [],
            'uname_test_level' => 0,
            'maxuname'         => 10,
            'minuname'         => 3,
            'bad_unames'       => [],
            'minpass'          => 3,
        ];
        XoopsDatabaseFactory::$connection = new DummyDatabase([
            false,
        ]);

        \XoopsUserUtility::validate('good', 'good@example.com');
    }

    public function testGetIPPrefersProxyAddress(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
        Xmf\IPAddressStub::$request      = '192.168.0.1';

        $ip = \XoopsUserUtility::getIP();

        $this->assertSame(ip2long('10.0.0.1'), $ip);
    }

    public function testGetIPFallsBackWhenProxyInvalid(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalid';
        Xmf\IPAddressStub::$request      = '192.168.0.5';

        $ip = \XoopsUserUtility::getIP(true);

        $this->assertSame('192.168.0.5', $ip);
    }

    public function testGetUnameFromIdsLoadsAndFormatsUsers(): void
    {
        $rows = [
            ['uid' => 1, 'uname' => 'user1', 'name' => 'Real <One>'],
            ['uid' => 2, 'uname' => 'user2', 'name' => ''],
        ];
        XoopsDatabaseFactory::$connection = new DummyDatabase([
            new DummyResult($rows),
        ]);

        $users = \XoopsUserUtility::getUnameFromIds([1, 2], true, true);

        $this->assertSame(
            [
                1 => '<a href="https://xoops.example.com/userinfo.php?uid=1" title="Real &lt;One&gt;">Real &lt;One&gt;</a>',
                2 => '<a href="https://xoops.example.com/userinfo.php?uid=2" title="user2">user2</a>',
            ],
            $users
        );
    }

    public function testGetUnameFromIdUsesMemberHandlerOrAnonymous(): void
    {
        $GLOBALS['userutility_handlers']['member'] = new class {
            public function getUser($uid)
            {
                if ($uid === 7) {
                    return new DummyUser(['uid' => 7, 'uname' => 'name7', 'name' => 'Real']);
                }

                return null;
            }
        };

        $this->assertSame('Real', \XoopsUserUtility::getUnameFromId(7, true));
        $this->assertSame('<a href="https://xoops.example.com/userinfo.php?uid=7" title="name7">name7</a>', \XoopsUserUtility::getUnameFromId(7, false, true));
        $this->assertSame('anonymous', \XoopsUserUtility::getUnameFromId(0));
    }
}
