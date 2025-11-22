<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\PhpCsFixer\Fixer;

use Override;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\FileSpecificCodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use RuntimeException;
use SplFileInfo;

use const T_NAMESPACE;
use const T_STRING;
use const T_WHITESPACE;

use function array_key_exists;
use function dirname;
use function file_exists;
use function file_get_contents;
use function getcwd;
use function json_decode;
use function mb_rtrim;
use function mb_strlen;
use function mb_substr;
use function str_replace;
use function str_starts_with;
use function throw_if;
use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class NamespaceFixer extends AbstractFixer
{
    private readonly array $psr4Config;

    public function __construct()
    {
        $this->psr4Config = self::loadPsr4Config();
    }

    #[Override()]
    public function getName(): string
    {
        return 'Architecture/namespace_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Updates namespace based on PSR-4 configuration from composer.json',
            [
                new FileSpecificCodeSample(
                    '<?php
namespace Wrong\Namespace;',
                    new SplFileInfo(__FILE__),
                    // 'Updates namespace to match PSR-4 autoload configuration.',
                ),
            ],
        );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_NAMESPACE);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $expectedNamespace = $this->determineNamespace($file);

        if ($expectedNamespace === null) {
            return;
        }

        $namespacePosition = $tokens->getNextTokenOfKind(0, [[T_NAMESPACE]]);

        if ($namespacePosition === null) {
            return;
        }

        // Find the namespace name tokens
        $namespaceEndPosition = $tokens->getNextTokenOfKind($namespacePosition, [';']);

        if ($namespaceEndPosition === null) {
            return;
        }

        // Replace existing namespace with new one
        $tokens->clearRange($namespacePosition, $namespaceEndPosition);
        $tokens->insertAt($namespacePosition, [
            new Token([T_NAMESPACE, 'namespace']),
            new Token([T_WHITESPACE, ' ']),
            new Token([T_STRING, $expectedNamespace]),
            new Token(';'),
        ]);
    }

    private static function loadPsr4Config(): array
    {
        $composerPath = self::findComposerJson();

        if ($composerPath === null || $composerPath === '' || $composerPath === '0') {
            throw new RuntimeException('composer.json not found in any parent directory');
        }

        $composerJson = file_get_contents($composerPath);

        if ($composerJson === false) {
            throw new RuntimeException('Unable to read composer.json');
        }

        $composer = json_decode($composerJson, true);

        if (!array_key_exists('psr-4', $composer['autoload'])) {
            throw new RuntimeException('No PSR-4 autoload configuration found in composer.json');
        }

        // Convert relative paths to absolute paths based on composer.json location
        $baseDir = dirname($composerPath);
        $psr4Config = [];

        foreach ($composer['autoload']['psr-4'] as $namespace => $path) {
            $psr4Config[$namespace] = self::normalizePath($baseDir.'/'.$path);
        }

        return $psr4Config;
    }

    private static function findComposerJson(): ?string
    {
        $dir = getcwd();

        while ($dir !== '/' && $dir !== '') {
            $composerPath = $dir.'/composer.json';

            if (file_exists($composerPath)) {
                return $composerPath;
            }

            $dir = dirname($dir);
        }

        return null;
    }

    private static function normalizePath(string $path): string
    {
        return mb_rtrim(str_replace('\\', '/', $path), '/');
    }

    private function determineNamespace(SplFileInfo $file): ?string
    {
        self::normalizePath($file->getRealPath());
        $dirPath = self::normalizePath(dirname($file->getRealPath()));

        foreach ($this->psr4Config as $namespace => $directory) {
            if (str_starts_with($dirPath, (string) $directory)) {
                $subPath = mb_substr($dirPath, mb_strlen((string) $directory) + 1);
                $subNamespace = str_replace('/', '\\', $subPath);

                // Remove trailing slash from namespace if it exists
                return mb_rtrim($namespace, '\\').($subNamespace !== '' && $subNamespace !== '0' ? '\\'.$subNamespace : '');
            }
        }

        return null;
    }
}
