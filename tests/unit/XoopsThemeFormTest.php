<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/form.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/themeform.php';

if (!class_exists('DummyThemeRenderer')) {
    class DummyThemeRenderer
    {
        public array $calls = [];

        public function addThemeFormBreak($form, $extra, $class): void
        {
            $this->calls[] = ['addThemeFormBreak', $form, $extra, $class];
        }

        public function renderThemeForm($form)
        {
            $this->calls[] = ['renderThemeForm', $form];

            return '<rendered:' . $form->getName() . '>';
        }
    }
}

if (!class_exists('XoopsFormRenderer')) {
    class XoopsFormRenderer
    {
        private static $instance;
        private DummyThemeRenderer $renderer;

        private function __construct()
        {
            $this->renderer = new DummyThemeRenderer();
        }

        public static function getInstance(): self
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function get(): DummyThemeRenderer
        {
            return $this->renderer;
        }
    }
}

/**
 * @covers XoopsThemeForm
 */
class XoopsThemeFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $ref = new ReflectionClass(XoopsFormRenderer::class);
        $instanceProperty = $ref->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null);
    }

    public function testInsertBreakDelegatesToRenderer(): void
    {
        $form = new XoopsThemeForm('title', 'theme', '/action.php');
        $renderer = XoopsFormRenderer::getInstance()->get();

        $form->insertBreak('extra', 'cls');

        $this->assertSame([
            ['addThemeFormBreak', $form, 'extra', 'cls'],
        ], $renderer->calls);
    }

    public function testRenderUsesRendererOutput(): void
    {
        $form = new XoopsThemeForm('title', 'theme', '/action.php');
        $renderer = XoopsFormRenderer::getInstance()->get();

        $output = $form->render();

        $this->assertSame('<rendered:theme>', $output);
        $this->assertSame([
            ['renderThemeForm', $form],
        ], $renderer->calls);
    }
}
