<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/auth/auth.php';
require_once XOOPS_ROOT_PATH . '/class/auth/auth_ads.php';
require_once XOOPS_ROOT_PATH . '/class/auth/authfactory.php';
require_once XOOPS_ROOT_PATH . '/class/auth/auth_ldap.php';
require_once XOOPS_ROOT_PATH . '/class/auth/auth_provisionning.php';
require_once XOOPS_ROOT_PATH . '/class/auth/auth_xoops.php';
require_once XOOPS_ROOT_PATH . '/kernel/user.php';

if (!defined('XOOPS_CONF_AUTH')) {
    define('XOOPS_CONF_AUTH', 1);
}
if (!defined('XOOPS_CONF')) {
    define('XOOPS_CONF', 2);
}
if (!defined('_NONE')) {
    define('_NONE', 'none');
}
if (!defined('_AUTH_MSG_AUTH_METHOD')) {
    define('_AUTH_MSG_AUTH_METHOD', 'using %s');
}
if (!defined('_US_INCORRECTLOGIN')) {
    define('_US_INCORRECTLOGIN', 'incorrect');
}
if (!defined('_AUTH_LDAP_EXTENSION_NOT_LOAD')) {
    define('_AUTH_LDAP_EXTENSION_NOT_LOAD', 'ldap missing');
}
if (!defined('_AUTH_LDAP_USER_NOT_FOUND')) {
    define('_AUTH_LDAP_USER_NOT_FOUND', 'user not found %s %s %s');
}
if (!defined('_AUTH_LDAP_SERVER_NOT_FOUND')) {
    define('_AUTH_LDAP_SERVER_NOT_FOUND', 'server not found');
}
if (!defined('_AUTH_LDAP_XOOPS_USER_NOTFOUND')) {
    define('_AUTH_LDAP_XOOPS_USER_NOTFOUND', 'xoops user %s not found');
}
if (!defined('_XO_ER_CLASSNOTFOUND')) {
    define('_XO_ER_CLASSNOTFOUND', 'class not found');
}

if (!function_exists('xoops_getHandler')) {
    function xoops_getHandler($name)
    {
        return $GLOBALS['xoops_auth_handlers'][$name] ?? null;
    }
}

if (!function_exists('redirect_header')) {
    function redirect_header($url, $time = 0, $message = '')
    {
        $GLOBALS['redirects'][] = [$url, $time, $message];
    }
}

if (!class_exists('XoopsDatabaseFactory')) {
    class XoopsDatabaseFactory
    {
        public static function getDatabaseConnection()
        {
            return 'dao-connection';
        }
    }
}

if (!isset($GLOBALS['xoops'])) {
    $GLOBALS['xoops'] = new class {
        public function path($path)
        {
            return XOOPS_ROOT_PATH . '/' . ltrim($path, '/');
        }
    };
}

class XoopsAuthTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['xoopsConfig'] = ['debug_mode' => 1];
        $GLOBALS['xoops_auth_handlers'] = [];
        $GLOBALS['redirects'] = [];
        $GLOBALS['xoopsLogger'] = new class {
            public array $errors = [];

            public function triggerError($message, $code, $file, $line, $errorType)
            {
                $this->errors[] = [$message, $code, $file, $line, $errorType];
            }
        };
    }

    public function testBaseAuthStoresDaoAndErrors(): void
    {
        $auth = new XoopsAuth('dao');
        $auth->auth_method = 'ldap';
        $auth->setErrors(10, ' failed ');

        $this->assertSame(['10' => 'failed'], $auth->getErrors());
        $this->assertStringContainsString('failed', $auth->getHtmlErrors());
        $this->assertStringContainsString('ldap', $auth->getHtmlErrors());
    }

    public function testHtmlErrorsWhenDebugDisabled(): void
    {
        $GLOBALS['xoopsConfig']['debug_mode'] = 0;
        $auth = new XoopsAuth(null);
        $auth->setErrors(0, 'ignored');

        $this->assertSame('incorrect', $auth->getHtmlErrors());
    }

    /**
     * @runInSeparateProcess
     */
    public function testAuthFactoryCreatesXoopsAuthInstance(): void
    {
        $GLOBALS['xoops_auth_handlers']['config'] = new class {
            public function getConfigsByCat($cat)
            {
                return [
                    'auth_method'       => 'xoops',
                    'ldap_users_bypass' => [],
                ];
            }
        };

        $auth = XoopsAuthFactory::getAuthConnection('user');

        $this->assertInstanceOf(XoopsAuthXoops::class, $auth);
        $this->assertSame('dao-connection', $auth->_dao);
        $this->assertSame($auth, XoopsAuthFactory::getAuthConnection('other'));
    }

    public function testAuthXoopsAuthenticateSetsErrorsOnFailure(): void
    {
        $member = $this->createMock(stdClass::class);
        $member->method('loginUser')->willReturn(false);
        $GLOBALS['xoops_auth_handlers']['member'] = $member;

        $auth = new XoopsAuthXoops(null);
        $result = $auth->authenticate('u', 'p');

        $this->assertFalse($result);
        $this->assertSame([1 => 'incorrect'], $auth->getErrors());
    }

    public function testAuthXoopsAuthenticateReturnsUser(): void
    {
        $user = new stdClass();
        $member = $this->createMock(stdClass::class);
        $member->expects($this->once())->method('loginUser')->with('user', 'pass')->willReturn($user);
        $GLOBALS['xoops_auth_handlers']['member'] = $member;

        $auth = new XoopsAuthXoops(null);
        $this->assertSame($user, $auth->authenticate('user', 'pass'));
    }

    public function testAuthLdapCp1252ConversionUsesMap(): void
    {
        if (extension_loaded('ldap')) {
            $this->markTestSkipped('cp1252 map is independent of LDAP extension state.');
        }
        $GLOBALS['xoops_auth_handlers']['config'] = new class {
            public function getConfigsByCat($cat)
            {
                return [
                    'ldap_use_TLS'          => false,
                    'ldap_loginname_asdn'   => true,
                    'ldap_loginldap_attr'   => 'uid',
                    'ldap_filter_person'    => '',
                    'ldap_server'           => 'ldap',
                    'ldap_port'             => '389',
                    'ldap_version'          => '3',
                    'ldap_domain_name'      => 'domain',
                    'ldap_provisionning'    => false,
                    'ldap_provisionning_upd'=> false,
                    'ldap_field_mapping'    => '',
                    'ldap_provisionning_group' => [],
                ];
            }
        };

        $auth = new XoopsAuthLdap(null);
        $converted = $auth->cp1252_to_utf8("\xc2\x80 symbol");

        $this->assertStringContainsString("\xe2\x82\xac", $converted);
    }

    public function testAuthLdapAuthenticateWithoutExtensionAddsError(): void
    {
        if (extension_loaded('ldap')) {
            $this->markTestSkipped('LDAP extension present');
        }
        $GLOBALS['xoops_auth_handlers']['config'] = new class {
            public function getConfigsByCat($cat)
            {
                return [
                    'ldap_use_TLS'          => false,
                    'ldap_loginname_asdn'   => true,
                    'ldap_loginldap_attr'   => 'uid',
                    'ldap_filter_person'    => '',
                    'ldap_server'           => 'ldap',
                    'ldap_port'             => '389',
                    'ldap_version'          => '3',
                    'ldap_domain_name'      => 'domain',
                    'ldap_provisionning'    => false,
                    'ldap_provisionning_upd'=> false,
                    'ldap_field_mapping'    => '',
                    'ldap_provisionning_group' => [],
                ];
            }
        };

        $auth = new XoopsAuthAds(null);
        $this->assertFalse($auth->authenticate('user', 'pwd'));
        $this->assertSame([0 => 'ldap missing'], $auth->getErrors());
    }

    public function testAuthAdsGetUpn(): void
    {
        $GLOBALS['xoops_auth_handlers']['config'] = new class {
            public function getConfigsByCat($cat)
            {
                return [
                    'ldap_use_TLS'          => false,
                    'ldap_loginname_asdn'   => true,
                    'ldap_loginldap_attr'   => 'uid',
                    'ldap_filter_person'    => '',
                    'ldap_server'           => 'ldap',
                    'ldap_port'             => '389',
                    'ldap_version'          => '3',
                    'ldap_domain_name'      => 'example.com',
                    'ldap_provisionning'    => false,
                    'ldap_provisionning_upd'=> false,
                    'ldap_field_mapping'    => '',
                    'ldap_provisionning_group' => [],
                ];
            }
        };

        $auth = new XoopsAuthAds(null);
        $this->assertSame('alice@example.com', $auth->getUPN('alice'));
    }

    public function testProvisionningSyncAddsUser(): void
    {
        $memberHandler = new class {
            public array $insertedUsers = [];
            public array $groups = [];

            public function createUser()
            {
                return new XoopsUser();
            }

            public function insertUser($user)
            {
                $this->insertedUsers[] = $user;

                return true;
            }

            public function addUserToGroup($groupId, $uid)
            {
                $this->groups[] = [$groupId, $uid];
            }

            public function getUsers()
            {
                return [];
            }
        };

        $GLOBALS['xoops_auth_handlers']['member'] = $memberHandler;
        $GLOBALS['xoops_auth_handlers']['config'] = new class {
            public function getConfigsByCat($cat)
            {
                if ($cat === XOOPS_CONF_AUTH) {
                    return [
                        'ldap_provisionning'       => true,
                        'ldap_provisionning_upd'   => true,
                        'ldap_field_mapping'       => 'email=mail',
                        'ldap_provisionning_group' => [99],
                    ];
                }

                return [
                    'default_TZ' => '0',
                    'theme_set'  => 'default',
                    'com_mode'   => 'flat',
                    'com_order'  => 0,
                ];
            }
        };

        $auth = new XoopsAuth();
        $provision = new XoopsAuthProvisionning($auth);
        $user = $provision->sync(['mail' => ['a@example.com']], 'alice', 'pw');

        $this->assertInstanceOf(XoopsUser::class, $user);
        $this->assertNotEmpty($memberHandler->insertedUsers);
        $this->assertSame([[99, $user->getVar('uid')]], $memberHandler->groups);
        $this->assertSame('a@example.com', $user->getVar('email'));
    }

    public function testProvisionningSyncUpdatesExistingUser(): void
    {
        $existingUser = new XoopsUser();
        $existingUser->setVar('uid', 11);
        $memberHandler = new class ($existingUser) {
            private XoopsUser $user;
            public function __construct($user)
            {
                $this->user = $user;
            }

            public function getUsers()
            {
                return [$this->user];
            }

            public function insertUser($user)
            {
                return true;
            }
        };

        $GLOBALS['xoops_auth_handlers']['member'] = $memberHandler;
        $GLOBALS['xoops_auth_handlers']['config'] = new class {
            public function getConfigsByCat($cat)
            {
                if ($cat === XOOPS_CONF_AUTH) {
                    return [
                        'ldap_provisionning'       => true,
                        'ldap_provisionning_upd'   => true,
                        'ldap_field_mapping'       => 'name=cn',
                        'ldap_provisionning_group' => [],
                    ];
                }

                return [
                    'default_TZ' => '0',
                    'theme_set'  => 'default',
                    'com_mode'   => 'flat',
                    'com_order'  => 0,
                ];
            }
        };

        $auth = new XoopsAuth();
        $provision = new XoopsAuthProvisionning($auth);
        $user = $provision->sync(['cn' => ['Alice']], 'alice', 'pw');

        $this->assertSame('Alice', $user->getVar('name'));
        $this->assertSame(11, $user->getVar('uid'));
    }
}
