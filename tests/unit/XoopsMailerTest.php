<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

/**
 * Stub for resolving XoopsMultiMailer include path.
 */
class XoopsPathStub
{
    /** @var string */
    public $stubPath;

    public function path($file)
    {
        if ($file === 'class/mail/xoopsmultimailer.php') {
            return $this->stubPath;
        }

        return $file;
    }
}

class XoopsMailerTest extends TestCase
{
    /** @var string */
    private $mailerStub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailerStub = sys_get_temp_dir() . '/xoopsmailer_stub.php';
        file_put_contents(
            $this->mailerStub,
            <<<'PHP'
<?php
namespace PHPMailer\PHPMailer {
    class Exception extends \Exception {}
}

class XoopsMultiMailer
{
    public $isHtmlFlag;
    public $addresses = [];
    public $headers = [];
    public $Subject;
    public $Body;
    public $CharSet;
    public $Encoding;
    public $FromName;
    public $Sender;
    public $From;
    public $sendReturn = true;
    public $ErrorInfo = 'mailer error';
    public $sendException;

    public function isHTML($value)
    {
        $this->isHtmlFlag = $value;
    }

    public function clearAllRecipients()
    {
        $this->addresses = [];
    }

    public function addAddress($email)
    {
        $this->addresses[] = $email;
    }

    public function clearCustomHeaders()
    {
        $this->headers = [];
    }

    public function addCustomHeader($header)
    {
        $this->headers[] = $header;
    }

    public function send()
    {
        if ($this->sendException) {
            throw new \PHPMailer\PHPMailer\Exception($this->sendException);
        }

        return $this->sendReturn;
    }
}
PHP
        );

        $pathStub            = new XoopsPathStub();
        $pathStub->stubPath  = $this->mailerStub;
        $GLOBALS['xoops']    = $pathStub;
        $GLOBALS['xoopsConfig'] = [
            'adminmail' => 'admin@example.com',
            'sitename'  => 'Test Site',
            'language'  => 'english',
        ];

        if (!defined('XOOPS_URL')) {
            define('XOOPS_URL', 'http://example.com');
        }

        foreach ([
                     '_MAIL_MSGBODY'   => 'message body missing',
                     '_MAIL_FAILOPTPL' => 'missing template',
                     '_MAIL_SENDMAILNG'=> 'send failure to %s',
                     '_MAIL_MAILGOOD'  => 'good send to %s',
                     '_ERRORS'         => 'Errors',
                 ] as $constant => $value) {
            if (!defined($constant)) {
                define($constant, $value);
            }
        }

        require_once XOOPS_ROOT_PATH . '/class/xoopsmailer.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->mailerStub);
        parent::tearDown();
    }

    public function testConstructorResetsDefaults(): void
    {
        $mailer = new XoopsMailer();

        $this->assertSame('', $mailer->fromEmail);
        $this->assertSame('', $mailer->fromName);
        $this->assertNull($mailer->fromUser);
        $this->assertSame([], $mailer->toEmails);
        $this->assertSame([], $mailer->headers);
        $this->assertSame('', $mailer->subject);
        $this->assertSame('', $mailer->body);
        $this->assertFalse($mailer->isMail);
        $this->assertFalse($mailer->isPM);
        $this->assertSame("\n", $mailer->LE);
        $this->assertSame('iso-8859-1', $mailer->charSet);
        $this->assertSame('8bit', $mailer->encoding);
    }

    public function testSetHtmlDelegatesToMultimailer(): void
    {
        $mailer                = new XoopsMailer();
        $mailer->setHTML(false);

        $this->assertFalse($mailer->multimailer->isHtmlFlag);
    }

    public function testSetTemplateDirUsesModuleDirnameWhenNull(): void
    {
        $module = $this->getMockBuilder(stdClass::class)
            ->setMethods(['getVar'])
            ->getMock();
        $module->expects($this->once())
            ->method('getVar')
            ->with('dirname', 'n')
            ->willReturn('moddir');
        $GLOBALS['xoopsModule'] = $module;

        $mailer = new XoopsMailer();
        $mailer->setTemplateDir();

        $this->assertSame('moddir', $mailer->templatedir);

        $mailer->setTemplateDir('path\\to\\tpl');
        $this->assertSame('path/to/tpl', $mailer->templatedir);
    }

    public function testGetTemplatePathFallsBackToEnglish(): void
    {
        $tempDir = sys_get_temp_dir() . '/xoopsmailer_tpl/';
        $tplPath = $tempDir . 'english/mail_template/sample.tpl';
        if (!is_dir(dirname($tplPath))) {
            mkdir(dirname($tplPath), 0777, true);
        }
        file_put_contents($tplPath, 'template body');

        $mailer = new XoopsMailer();
        $mailer->setTemplateDir($tempDir);
        $mailer->setTemplate('sample.tpl');
        $GLOBALS['xoopsConfig']['language'] = 'french';

        $this->assertSame($tplPath, $mailer->getTemplatePath());
    }

    public function testSendReturnsFalseWhenMissingBodyAndTemplate(): void
    {
        $mailer = new XoopsMailer();

        $this->assertFalse($mailer->send(true));
        $this->assertSame(['message body missing'], $mailer->errors);
    }

    public function testSendLoadsTemplateAndReplacesTags(): void
    {
        $tempDir = sys_get_temp_dir() . '/xoopsmailer_tpl2/';
        $tplPath = $tempDir . 'english/mail_template/sample.tpl';
        if (!is_dir(dirname($tplPath))) {
            mkdir(dirname($tplPath), 0777, true);
        }
        file_put_contents($tplPath, 'Hello {NAME}');

        $mailer = new class extends XoopsMailer {
            public $sent = [];
            public $sendReturn = true;

            public function sendMail($email, $subject, $body, $headers)
            {
                $this->sent[] = compact('email', 'subject', 'body', 'headers');

                return $this->sendReturn;
            }
        };
        $mailer->setTemplateDir($tempDir);
        $mailer->setTemplate('sample.tpl');
        $mailer->setSubject('Welcome {NAME}');
        $mailer->assign('name', 'Tester');
        $mailer->setToEmails('user@example.com');

        $this->assertTrue($mailer->send(true));
        $this->assertSame(
            [
                [
                    'email'   => 'user@example.com',
                    'subject' => 'Welcome Tester',
                    'body'    => "Hello Tester\n",
                    'headers' => '',
                ],
            ],
            $mailer->sent
        );
        $this->assertSame(['good send to user@example.com'], $mailer->success);
    }

    public function testSendMailAddsErrorOnFailure(): void
    {
        $mailer              = new XoopsMailer();
        $mailer->fromName    = 'Sender Name';
        $mailer->fromEmail   = 'sender@example.com';
        $mailer->headers     = ['X-Test: 1'];
        $mailer->multimailer->sendReturn = false;
        $mailer->multimailer->ErrorInfo  = 'fail reason';

        $this->assertFalse($mailer->sendMail('dest@example.com', 'Subject', 'Body', 'Headers'));
        $this->assertSame(['fail reason'], $mailer->errors);
    }

    public function testSendMailCatchesMailerException(): void
    {
        $mailer                     = new XoopsMailer();
        $mailer->multimailer->sendException = 'boom';

        $this->assertFalse($mailer->sendMail('dest@example.com', 'Subject', 'Body', 'Headers'));
        $this->assertSame(['boom'], $mailer->errors);
    }

    public function testAssignCollectsTags(): void
    {
        $mailer = new XoopsMailer();
        $mailer->assign('token', 'value');
        $mailer->assign(['item' => 'v2']);

        $this->assertSame(['TOKEN' => 'value', 'ITEM' => 'v2'], $mailer->assignedTags);
    }

    public function testGetErrorsReturnsHtml(): void
    {
        $mailer = new XoopsMailer();
        $mailer->errors = ['one', 'two'];

        $this->assertSame('<h4>Errors</h4>one<br>two<br>', $mailer->getErrors());
        $this->assertSame(['one', 'two'], $mailer->getErrors(false));
    }
}

