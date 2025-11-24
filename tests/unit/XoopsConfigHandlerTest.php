<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/kernel/config.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';

class XoopsConfigHandlerTest extends TestCase
{
    public function testConstructorInitializesItemAndOptionHandlers(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $this->assertInstanceOf(XoopsConfigItemHandler::class, $handler->_cHandler);
        $this->assertInstanceOf(XoopsConfigOptionHandler::class, $handler->_oHandler);
    }

    public function testCreateConfigUsesConfigItemHandler(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());
        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $handler->_cHandler->expects($this->once())
            ->method('create')
            ->willReturn('created');

        $this->assertSame('created', $handler->createConfig());
    }

    public function testGetConfigLoadsOptionsWhenRequested(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $config = new XoopsConfigItem();
        $config->setVar('conf_id', 99);

        $option = new XoopsConfigOption();

        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $handler->_cHandler->method('get')->with(99)->willReturn($config);

        $handler->_oHandler = $this->getMockBuilder(XoopsConfigOptionHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjects'])
            ->getMock();
        $handler->_oHandler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(Criteria::class), false)
            ->willReturn([$option]);

        $result = $handler->getConfig(99, true);

        $this->assertSame($config, $result);
        $this->assertSame([$option], $result->getConfOptions());
    }

    public function testInsertConfigInsertsOptionsAndClearsCache(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $config = new XoopsConfigItem();
        $config->setVar('conf_id', 7);
        $config->setVar('conf_modid', 12);
        $config->setVar('conf_catid', 3);

        $option = new XoopsConfigOption();
        $config->setConfOptions([$option]);

        $handler->_cachedConfigs[12][3] = ['cached' => 'value'];

        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert'])
            ->getMock();
        $handler->_cHandler->expects($this->once())
            ->method('insert')
            ->with($config)
            ->willReturn(true);

        $handler->_oHandler = $this->getMockBuilder(XoopsConfigOptionHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['insert'])
            ->getMock();
        $handler->_oHandler->expects($this->once())
            ->method('insert')
            ->with($this->isInstanceOf(XoopsConfigOption::class))
            ->willReturn(true);

        $this->assertTrue($handler->insertConfig($config));
        $this->assertSame(7, $option->getVar('conf_id'));
        $this->assertArrayNotHasKey(3, $handler->_cachedConfigs[12] ?? []);
    }

    public function testDeleteConfigRemovesOptionsAndClearsCache(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $config = new XoopsConfigItem();
        $config->setVar('conf_id', 15);
        $config->setVar('conf_modid', 2);
        $config->setVar('conf_catid', 1);

        $option = new XoopsConfigOption();
        $option->setVar('confop_id', 3);
        $config->setConfOptions([$option]);

        $handler->_cachedConfigs[2][1] = ['preset'];

        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $handler->_cHandler->expects($this->once())
            ->method('delete')
            ->with($config)
            ->willReturn(true);

        $handler->_oHandler = $this->getMockBuilder(XoopsConfigOptionHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $handler->_oHandler->expects($this->once())
            ->method('delete')
            ->with($option)
            ->willReturn(true);

        $this->assertTrue($handler->deleteConfig($config));
        $this->assertArrayNotHasKey(1, $handler->_cachedConfigs[2] ?? []);
    }

    public function testGetConfigsDelegatesToConfigHandler(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $criteria = new Criteria('conf_modid', 1);

        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjects'])
            ->getMock();
        $handler->_cHandler->expects($this->once())
            ->method('getObjects')
            ->with($criteria, true)
            ->willReturn(['objects']);

        $this->assertSame(['objects'], $handler->getConfigs($criteria, true, true));
    }

    public function testGetConfigCountDelegatesToConfigHandler(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());
        $criteria = new Criteria('conf_id', 1);

        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCount'])
            ->getMock();
        $handler->_cHandler->expects($this->once())
            ->method('getCount')
            ->with($criteria)
            ->willReturn(4);

        $this->assertSame(4, $handler->getConfigCount($criteria));
    }

    public function testGetConfigsByCatBuildsCachedList(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $config = new XoopsConfigItem();
        $config->setVar('conf_name', 'site_name');
        $config->setVar('conf_valuetype', 'text');
        $config->setVar('conf_value', 'XOOPS');

        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjects'])
            ->getMock();
        $handler->_cHandler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(CriteriaCompo::class), true)
            ->willReturn([1 => $config]);

        $first = $handler->getConfigsByCat(5, 8);
        $second = $handler->getConfigsByCat(5, 8);

        $this->assertSame(['site_name' => 'XOOPS'], $first);
        $this->assertSame($first, $second);
    }

    public function testCreateConfigOptionUsesOptionHandler(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());
        $handler->_oHandler = $this->getMockBuilder(XoopsConfigOptionHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $handler->_oHandler->expects($this->once())
            ->method('create')
            ->willReturn('option');

        $this->assertSame('option', $handler->createConfigOption());
    }

    public function testGetConfigOptionUsesOptionHandler(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());
        $handler->_oHandler = $this->getMockBuilder(XoopsConfigOptionHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $handler->_oHandler->expects($this->once())
            ->method('get')
            ->with(4)
            ->willReturn('opt');

        $this->assertSame('opt', $handler->getConfigOption(4));
    }

    public function testGetConfigOptionsDelegatesToOptionHandler(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());
        $criteria = new Criteria('conf_id', 2);

        $handler->_oHandler = $this->getMockBuilder(XoopsConfigOptionHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjects'])
            ->getMock();
        $handler->_oHandler->expects($this->once())
            ->method('getObjects')
            ->with($criteria, true)
            ->willReturn(['opts']);

        $this->assertSame(['opts'], $handler->getConfigOptions($criteria, true));
    }

    public function testGetConfigOptionsCountDelegatesToOptionHandler(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());
        $criteria = new Criteria('conf_id', 2);

        $handler->_oHandler = $this->getMockBuilder(XoopsConfigOptionHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCount'])
            ->getMock();
        $handler->_oHandler->expects($this->once())
            ->method('getCount')
            ->with($criteria)
            ->willReturn(9);

        $this->assertSame(9, $handler->getConfigOptionsCount($criteria));
    }

    public function testGetConfigListCachesResult(): void
    {
        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $config = new XoopsConfigItem();
        $config->setVar('conf_name', 'theme');
        $config->setVar('conf_valuetype', 'text');
        $config->setVar('conf_value', 'default');

        $handler->_cHandler = $this->getMockBuilder(XoopsConfigItemHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjects'])
            ->getMock();
        $handler->_cHandler->expects($this->once())
            ->method('getObjects')
            ->with($this->isInstanceOf(CriteriaCompo::class))
            ->willReturn([$config]);

        $first = $handler->getConfigList(22, 0);
        $second = $handler->getConfigList(22, 0);

        $this->assertSame(['theme' => 'default'], $first);
        $this->assertSame($first, $second);
    }

    public function testDeleteConfigOptionReturnsFalseAndLogsDeprecation(): void
    {
        $previousLogger = $GLOBALS['xoopsLogger'] ?? null;
        $messages       = [];
        $GLOBALS['xoopsLogger'] = new class {
            public array $messages = [];

            public function addDeprecated($message): void
            {
                $this->messages[] = $message;
            }
        };
        $GLOBALS['xoopsLogger']->messages =& $messages;

        $handler = new XoopsConfigHandler($this->createDatabaseMock());

        $this->assertFalse($handler->deleteConfigOption($this->createMock(Criteria::class)));
        $this->assertNotEmpty($messages);

        $GLOBALS['xoopsLogger'] = $previousLogger;
    }

    private function createDatabaseMock()
    {
        return $this->getMockBuilder(XoopsDatabase::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'prefix',
                'query',
                'isResultSet',
                'getRowsNum',
                'fetchArray',
                'exec',
                'getInsertId',
                'error',
                'fetchRow',
                'quote',
                'genId',
            ])
            ->getMock();
    }
}
