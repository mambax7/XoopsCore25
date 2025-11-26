<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_TU_ROOT_PATH . '/class/xml/rpc/xmlrpcparser.php';

class RpcHandlersTest extends TestCase
{
    private function getParserStub(): object
    {
        return new class {
            public $methodName;
            public $tempValue;
            public $tempName = [];
            public $tempMember = [];
            public $tempStruct = [];
            public $tempArray = [];
            public $params = [];
            public $currentTag = '';
            public $parentTag = '';
            public $workingLevels = [];
            public $currentLevel = 0;

            public function getCurrentLevel()
            {
                return $this->currentLevel;
            }

            public function getWorkingLevel()
            {
                if (empty($this->workingLevels)) {
                    return $this->currentLevel;
                }

                return $this->workingLevels[count($this->workingLevels) - 1];
            }

            public function setWorkingLevel()
            {
                $this->workingLevels[] = $this->getCurrentLevel();
            }

            public function releaseWorkingLevel()
            {
                array_pop($this->workingLevels);
            }

            public function setMethodName($name)
            {
                $this->methodName = $name;
            }

            public function setTempName($name)
            {
                $this->tempName[$this->getWorkingLevel()] = $name;
            }

            public function getTempName()
            {
                return $this->tempName[$this->getWorkingLevel()] ?? null;
            }

            public function setTempValue($value)
            {
                if (is_array($value) && is_array($this->tempValue ?? null)) {
                    $this->tempValue = array_merge($this->tempValue, $value);
                } elseif (is_string($value) && isset($this->tempValue) && is_string($this->tempValue)) {
                    $this->tempValue .= $value;
                } else {
                    $this->tempValue = $value;
                }
            }

            public function getTempValue()
            {
                return $this->tempValue;
            }

            public function resetTempValue()
            {
                unset($this->tempValue);
            }

            public function setTempMember($name, $value)
            {
                $this->tempMember[$this->getWorkingLevel()][$name] = $value;
            }

            public function getTempMember()
            {
                return $this->tempMember[$this->getWorkingLevel()] ?? [];
            }

            public function resetTempMember()
            {
                $this->tempMember[$this->getCurrentLevel()] = [];
            }

            public function setTempStruct($member)
            {
                $key = key($member);
                $this->tempStruct[$this->getWorkingLevel()][$key] = $member[$key];
            }

            public function getTempStruct()
            {
                return $this->tempStruct[$this->getWorkingLevel()] ?? [];
            }

            public function resetTempStruct()
            {
                $this->tempStruct[$this->getCurrentLevel()] = [];
            }

            public function setTempArray($value)
            {
                $this->tempArray[$this->getWorkingLevel()][] = $value;
            }

            public function getTempArray()
            {
                return $this->tempArray[$this->getWorkingLevel()] ?? [];
            }

            public function resetTempArray()
            {
                $this->tempArray[$this->getCurrentLevel()] = [];
            }

            public function setParam($value)
            {
                $this->params[] = $value;
            }

            public function getParam()
            {
                return $this->params;
            }

            public function getParentTag()
            {
                return $this->parentTag;
            }

            public function getCurrentTag()
            {
                return $this->currentTag;
            }
        };
    }

    public function testBasicValueHandlers(): void
    {
        $parser = $this->getParserStub();

        $methodHandler = new RpcMethodNameHandler();
        $this->assertSame('methodName', $methodHandler->getName());
        $name = 'system.listMethods';
        $methodHandler->handleCharacterData($parser, $name);
        $this->assertSame($name, $parser->methodName);

        $intHandler = new RpcIntHandler();
        $this->assertSame(['int', 'i4'], $intHandler->getName());
        $intHandler->handleCharacterData($parser, '42');
        $this->assertSame(42, $parser->getTempValue());

        $doubleHandler = new RpcDoubleHandler();
        $this->assertSame('double', $doubleHandler->getName());
        $value = '3.14';
        $doubleHandler->handleCharacterData($parser, $value);
        $this->assertSame(3.14, $parser->getTempValue());

        $booleanHandler = new RpcBooleanHandler();
        $this->assertSame('boolean', $booleanHandler->getName());
        $boolValue = '0';
        $booleanHandler->handleCharacterData($parser, $boolValue);
        $this->assertFalse($parser->getTempValue());

        $stringHandler = new RpcStringHandler();
        $this->assertSame('string', $stringHandler->getName());
        $stringHandler->handleCharacterData($parser, 'hello');
        $this->assertSame('hello', $parser->getTempValue());

        $dateHandler = new RpcDateTimeHandler();
        $this->assertSame('dateTime.iso8601', $dateHandler->getName());
        $dateHandler->handleCharacterData($parser, '20240102T03:04:05');
        $this->assertSame(gmmktime(3, 4, 5, 1, 2, 2024), $parser->getTempValue());

        $base64Handler = new RpcBase64Handler();
        $this->assertSame('base64', $base64Handler->getName());
        $base64Handler->handleCharacterData($parser, base64_encode('payload'));
        $this->assertSame('payload', $parser->getTempValue());
    }

    public function testNameAndValueHandlerRoutesByParentTag(): void
    {
        $parser            = $this->getParserStub();
        $parser->parentTag = 'member';
        $parser->setWorkingLevel();

        $nameHandler = new RpcNameHandler();
        $this->assertSame('name', $nameHandler->getName());
        $data = 'memberName';
        $nameHandler->handleCharacterData($parser, $data);
        $this->assertSame($data, $parser->getTempName());

        $valueHandler = new RpcValueHandler();
        $this->assertSame('value', $valueHandler->getName());
        $memberData = 'memberValue';
        $valueHandler->handleCharacterData($parser, $memberData);
        $this->assertSame($memberData, $parser->getTempValue());

        $parser->parentTag = 'data';
        $valueHandler->handleCharacterData($parser, 'arrayValue');
        $this->assertSame('memberValuearrayValue', $parser->getTempValue());
    }

    public function testValueHandlerEndElementBranches(): void
    {
        $parser                 = $this->getParserStub();
        $parser->currentTag     = 'member';
        $parser->parentTag      = 'member';
        $parser->currentLevel   = 1;
        $parser->setWorkingLevel();
        $parser->setTempName('username');
        $parser->setTempValue('bob');

        $valueHandler = new RpcValueHandler();
        $valueHandler->handleEndElement($parser);
        $this->assertSame(['username' => 'bob'], $parser->getTempMember());
        $this->assertNull($parser->getTempValue());

        $parser->currentTag = 'array';
        $parser->setTempValue(['first']);
        $valueHandler->handleEndElement($parser);
        $this->assertSame([['first']], $parser->tempArray);

        $parser->currentTag = 'value';
        $parser->setTempValue('final');
        $valueHandler->handleEndElement($parser);
        $this->assertSame(['final'], $parser->getParam());
    }

    public function testMemberHandlerPreparesWorkingLevels(): void
    {
        $parser               = $this->getParserStub();
        $parser->currentLevel = 2;

        $handler = new RpcMemberHandler();
        $this->assertSame('member', $handler->getName());
        $handler->handleBeginElement($parser, $attributes = []);

        $this->assertSame(2, $parser->getWorkingLevel());
        $this->assertSame([], $parser->getTempMember());

        $parser->setTempMember('id', 99);
        $handler->handleEndElement($parser);
        $this->assertSame(['id' => 99], $parser->getTempStruct());
        $this->assertEmpty($parser->workingLevels);
    }

    public function testArrayAndStructHandlersManageNestedValues(): void
    {
        $parser               = $this->getParserStub();
        $parser->currentLevel = 3;

        $arrayHandler = new RpcArrayHandler();
        $this->assertSame('array', $arrayHandler->getName());
        $arrayHandler->handleBeginElement($parser, $attributes = []);
        $parser->setTempArray('value1');
        $arrayHandler->handleEndElement($parser);
        $this->assertSame(['value1'], $parser->getTempValue());
        $this->assertSame([], $parser->workingLevels);

        $structHandler = new RpcStructHandler();
        $this->assertSame('struct', $structHandler->getName());
        $structHandler->handleBeginElement($parser, $attributes = []);
        $parser->setTempStruct(['name' => 'xoops']);
        $structHandler->handleEndElement($parser);
        $this->assertSame(['name' => 'xoops'], $parser->getTempValue());
        $this->assertSame([], $parser->workingLevels);
    }
}
