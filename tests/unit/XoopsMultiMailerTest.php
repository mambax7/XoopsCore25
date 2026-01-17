<?php

namespace {
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/init_new.php';
}

namespace PHPMailer\PHPMailer {
    if (!class_exists(__NAMESPACE__ . '\\PHPMailer')) {
        class PHPMailer
        {
            public $exceptions;
            public $CharSet;
            public $Sender;
            public $From;
            public $Mailer;
            public $SMTPAuth;
            public $Host;
            public $Username;
            public $Password;
            public $Sendmail;
            public $language;
            public $setLanguageArgs;
            public $ErrorInfo;

            public function __construct($exceptions = false)
            {
                $this->exceptions = $exceptions;
            }

            public function setLanguage($langcode, $path = '')
            {
                $this->setLanguageArgs = [$langcode, $path];
                $this->language        = [$langcode, $path];

                return true;
            }

            public function setError($message)
            {
                $this->ErrorInfo = $message;
            }
        }
    }

    if (!class_exists(__NAMESPACE__ . '\\Exception')) {
        class Exception extends \Exception
        {
        }
    }
}

namespace Xmf\Mail {
    class SendmailRunner
    {
        public static $deliveries = [];
        public static $throwMessage = null;

        public function deliver($path, $message, $from)
        {
            if (null !== self::$throwMessage) {
                throw new \RuntimeException(self::$throwMessage);
            }

            self::$deliveries[] = compact('path', 'message', 'from');
        }
    }
}

namespace {
    use Xmf\Mail\SendmailRunner;

    class ConfigHandlerStub
    {
        private $config;

        public function __construct(array $config)
        {
            $this->config = $config;
        }

        public function getConfigsByCat($category)
        {
            return $this->config;
        }
    }

    if (!function_exists('xoops_getHandler')) {
        function xoops_getHandler($name)
        {
            return $GLOBALS['xoopsConfigHandler'];
        }
    }

    if (!defined('XOOPS_CONF_MAILER')) {
        define('XOOPS_CONF_MAILER', 5);
    }

    if (!defined('_CHARSET')) {
        define('_CHARSET', 'UTF-8');
    }

    /**
     * @runTestsInSeparateProcesses
     */
    class XoopsMultiMailerTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            SendmailRunner::$deliveries   = [];
            SendmailRunner::$throwMessage = null;

            $GLOBALS['xoopsConfig'] = [
                'adminmail' => 'admin@example.com',
                'language'  => 'unknown',
            ];

            $GLOBALS['xoopsConfigHandler'] = new ConfigHandlerStub([
                'from'         => 'from@example.com',
                'mailmethod'   => 'smtpauth',
                'smtphost'     => ['smtp.example.com'],
                'smtpuser'     => 'smtp-user',
                'smtppass'     => 'smtp-pass',
                'sendmailpath' => '/custom/sendmail',
            ]);

            if (!class_exists('XoopsMultiMailer', false)) {
                require_once XOOPS_ROOT_PATH . '/class/mail/xoopsmultimailer.php';
            }
        }

        protected function tearDown(): void
        {
            parent::tearDown();
            unset($GLOBALS['xoopsConfig'], $GLOBALS['xoopsConfigHandler']);
        }

        public function testConstructorAppliesSmtpConfiguration(): void
        {
            $mailer = new \XoopsMultiMailer();

            $this->assertSame('from@example.com', $mailer->From);
            $this->assertSame('from@example.com', $mailer->Sender);
            $this->assertSame('smtp', $mailer->Mailer);
            $this->assertTrue($mailer->SMTPAuth);
            $this->assertSame('smtp.example.com', $mailer->Host);
            $this->assertSame('smtp-user', $mailer->Username);
            $this->assertSame('smtp-pass', $mailer->Password);
            $this->assertSame('utf-8', $mailer->CharSet);
            $this->assertSame(['en', XOOPS_ROOT_PATH . '/class/mail/phpmailer/language/'], $mailer->setLanguageArgs);
        }

        public function testConstructorFallsBackToAdminMailAndSendmail(): void
        {
            $GLOBALS['xoopsConfig'] = [
                'adminmail' => 'admin@example.com',
                'language'  => 'unknown',
            ];

            $GLOBALS['xoopsConfigHandler'] = new ConfigHandlerStub([
                'from'         => '',
                'mailmethod'   => 'sendmail',
                'smtphost'     => ['smtp.other'],
                'smtpuser'     => 'user2',
                'smtppass'     => 'pass2',
                'sendmailpath' => '/usr/bin/sendmail',
            ]);

            $mailer = new \XoopsMultiMailer();

            $this->assertSame('admin@example.com', $mailer->From);
            $this->assertSame('admin@example.com', $mailer->Sender);
            $this->assertSame('sendmail', $mailer->Mailer);
            $this->assertFalse($mailer->SMTPAuth);
            $this->assertSame('/usr/bin/sendmail', $mailer->Sendmail);
            $this->assertSame('smtp.other', $mailer->Host);
        }

        public function testSendmailSendDeliversMessage(): void
        {
            $mailer             = new class extends \XoopsMultiMailer {
                public function callSendmail($header, $body)
                {
                    return $this->sendmailSend($header, $body);
                }
            };
            $mailer->Sendmail   = '/bin/sendmail';
            $mailer->Sender     = 'sender@example.com';
            $mailer->From       = 'from@example.com';

            $result = $mailer->callSendmail("Subject: Hi\r\n", 'Body content');

            $this->assertTrue($result);
            $this->assertSame([
                [
                    'path'    => '/bin/sendmail',
                    'message' => "Subject: Hi\n\nBody content",
                    'from'    => 'sender@example.com',
                ],
            ], SendmailRunner::$deliveries);
        }

        public function testSendmailSendCapturesRuntimeError(): void
        {
            SendmailRunner::$throwMessage = 'send failure';

            $mailer           = new class extends \XoopsMultiMailer {
                public function callSendmail($header, $body)
                {
                    return $this->sendmailSend($header, $body);
                }
            };
            $mailer->exceptions = false;
            $mailer->Sendmail   = '/bin/sendmail';

            $this->assertFalse($mailer->callSendmail('H', 'B'));
            $this->assertSame('send failure', $mailer->ErrorInfo);
        }

        public function testSendmailSendThrowsWhenExceptionsEnabled(): void
        {
            SendmailRunner::$throwMessage = 'send explode';

            $mailer = new class extends \XoopsMultiMailer {
                public function callSendmail($header, $body)
                {
                    return $this->sendmailSend($header, $body);
                }
            };

            $this->expectException(\PHPMailer\PHPMailer\Exception::class);
            $this->expectExceptionMessage('send explode');
            $mailer->callSendmail('H', 'B');
        }
    }
}
