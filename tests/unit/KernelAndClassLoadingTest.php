<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/init_new.php';

/**
 * Ensure all kernel and class PHP files declare loadable classes, interfaces, or traits.
 */
class KernelAndClassLoadingTest extends TestCase
{
    /**
     * @dataProvider classFileProvider
     */
    public function testClassFilesLoadAndDeclareSymbols(string $file, array $declarations): void
    {
        require_once $file;

        $allSymbols = array_merge($declarations['classes'], $declarations['interfaces'], $declarations['traits']);
        $this->assertNotEmpty(
            $allSymbols,
            sprintf('File %s should declare at least one class, interface, or trait', $file)
        );
    }

    /**
     * @dataProvider declaredSymbolProvider
     */
    public function testDeclaredSymbolIsLoadable(string $file, string $symbolType, string $symbolName): void
    {
        require_once $file;

        if ($symbolType === 'class') {
            $this->assertTrue(
                class_exists($symbolName, false),
                sprintf('Expected class %s to be defined after including %s', $symbolName, $file)
            );
        } elseif ($symbolType === 'interface') {
            $this->assertTrue(
                interface_exists($symbolName, false),
                sprintf('Expected interface %s to be defined after including %s', $symbolName, $file)
            );
        } else {
            $this->assertTrue(
                trait_exists($symbolName, false),
                sprintf('Expected trait %s to be defined after including %s', $symbolName, $file)
            );
        }
    }

    public static function classFileProvider(): array
    {
        $files = self::listPhpFiles([
            XOOPS_ROOT_PATH . '/kernel',
            XOOPS_ROOT_PATH . '/class',
        ]);

        $cases = [];
        foreach ($files as $file) {
            $declarations = self::collectDeclarations($file);
            if (!empty($declarations['classes']) || !empty($declarations['interfaces']) || !empty($declarations['traits'])) {
                $cases[] = [$file, $declarations];
            }
        }

        return $cases;
    }

    public static function declaredSymbolProvider(): array
    {
        $files = self::listPhpFiles([
            XOOPS_ROOT_PATH . '/kernel',
            XOOPS_ROOT_PATH . '/class',
        ]);

        $cases = [];
        foreach ($files as $file) {
            $declarations = self::collectDeclarations($file);
            foreach ($declarations['classes'] as $className) {
                $cases[] = [$file, 'class', $className];
            }
            foreach ($declarations['interfaces'] as $interfaceName) {
                $cases[] = [$file, 'interface', $interfaceName];
            }
            foreach ($declarations['traits'] as $traitName) {
                $cases[] = [$file, 'trait', $traitName];
            }
        }

        return $cases;
    }

    private static function listPhpFiles(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
                    $files[] = $fileInfo->getRealPath();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{classes: array<int, string>, interfaces: array<int, string>, traits: array<int, string>}
     */
    private static function collectDeclarations(string $file): array
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return ['classes' => [], 'interfaces' => [], 'traits' => []];
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $classes = [];
        $interfaces = [];
        $traits = [];

        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = self::parseNamespace($tokens, $index);
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            [$id, $text] = $token;
            if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT], true) === false) {
                continue;
            }

            // Skip anonymous classes.
            $previous = self::previousSignificantToken($tokens, $index);
            if ($previous !== null && is_array($previous) && $previous[0] === T_NEW) {
                continue;
            }

            $nameToken = self::nextSignificantToken($tokens, $index);
            if (!is_array($nameToken)) {
                continue;
            }

            [$nameId, $name] = $nameToken;
            if ($nameId !== T_STRING) {
                continue;
            }

            $fqn = $namespace !== '' ? $namespace . '\\' . $name : $name;

            if ($id === T_CLASS) {
                $classes[] = $fqn;
            } elseif ($id === T_INTERFACE) {
                $interfaces[] = $fqn;
            } elseif ($id === T_TRAIT) {
                $traits[] = $fqn;
            }
        }

        return ['classes' => $classes, 'interfaces' => $interfaces, 'traits' => $traits];
    }

    private static function parseNamespace(array $tokens, int $index): string
    {
        $namespace = '';
        $tokenCount = count($tokens);
        for ($i = $index + 1; $i < $tokenCount; ++$i) {
            $token = $tokens[$i];
            if ($token === '{' || $token === ';') {
                break;
            }

            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
            }
        }

        return trim($namespace, '\\');
    }

    private static function previousSignificantToken(array $tokens, int $index)
    {
        for ($i = $index - 1; $i >= 0; --$i) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            if (in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }

    private static function nextSignificantToken(array $tokens, int $index)
    {
        $count = count($tokens);
        for ($i = $index + 1; $i < $count; ++$i) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            if (in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }
}
