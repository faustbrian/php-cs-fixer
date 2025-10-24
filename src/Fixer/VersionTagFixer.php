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
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use RuntimeException;
use SplFileInfo;
use Throwable;

use const T_ABSTRACT;
use const T_CLASS;
use const T_DOC_COMMENT;
use const T_ENUM;
use const T_FINAL;
use const T_INTERFACE;
use const T_READONLY;
use const T_STRING;
use const T_TRAIT;
use const T_WHITESPACE;

use function escapeshellarg;
use function mb_trim;
use function once;
use function preg_match;
use function preg_replace;
use function shell_exec;
use function sprintf;
use function str_contains;
use function str_replace;
use function throw_unless;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class VersionTagFixer extends AbstractFixer
{
    private readonly ?string $authorName;

    public function __construct(?string $authorName = null)
    {
        $this->authorName = $authorName ?? self::getDefaultAuthorName();
    }

    #[Override()]
    public function getName(): string
    {
        return 'Architecture/version_tag_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Increment @version tag in class docblocks when git diff exists.',
            [
                new CodeSample(
                    '<?php
/**
 * @version 1.0.0
 */
class Example
{
}',
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
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind([T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE])) {
                $nextToken = $tokens[$tokens->getNextMeaningfulToken($index)];

                if (!$nextToken->isGivenKind(T_STRING)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $author = $this->authorName;

        if ($author === null) {
            return;
        }

        // Check if file has git changes (only for incrementing existing versions)
        $hasChanges = self::hasGitChanges($file->getRealPath());

        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind([T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE])) {
                continue;
            }

            // Skip anonymous classes
            $nextToken = $tokens[$tokens->getNextMeaningfulToken($index)];

            if (!$nextToken->isGivenKind(T_STRING)) {
                continue;
            }

            // Rest of the fix logic...
            $classStartIndex = self::findClassStartIndex($tokens, $index);
            $docBlockIndex = self::findDocBlockIndex($tokens, $classStartIndex);

            if ($docBlockIndex !== null) {
                self::updateVersionInExistingDocBlock($tokens, $docBlockIndex, $author, $hasChanges);
            } else {
                self::createNewDocBlockWithVersion($tokens, $classStartIndex, $author);
            }
        }
    }

    private static function getDefaultAuthorName(): ?string
    {
        try {
            $gitName = once(static fn (): string => mb_trim(shell_exec('git config user.name') ?? ''));
            $gitEmail = once(static fn (): string => mb_trim(shell_exec('git config user.email') ?? ''));

            if (!$gitName || !$gitEmail) {
                throw new RuntimeException('Unable to determine author name.');
            }

            return sprintf('%s <%s>', $gitName, $gitEmail);
        } catch (Throwable) {
            return null;
        }
    }

    private static function hasGitChanges(string $filePath): bool
    {
        // Check if file has uncommitted changes
        $gitStatus = shell_exec(sprintf('git diff --name-only %s 2>/dev/null', escapeshellarg($filePath)));

        if (!empty(mb_trim($gitStatus ?? ''))) {
            return true;
        }

        // Check if file has staged changes
        $gitStaged = shell_exec(sprintf('git diff --cached --name-only %s 2>/dev/null', escapeshellarg($filePath)));

        return !empty(mb_trim($gitStaged ?? ''));
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function findClassStartIndex(Tokens $tokens, int $classIndex): int
    {
        $index = $classIndex;

        // First, walk back through modifiers (final, readonly, abstract)
        while ($prevIndex = $tokens->getPrevMeaningfulToken($index)) {
            $prevToken = $tokens[$prevIndex];

            if (!$prevToken->isGivenKind([T_FINAL, T_READONLY, T_ABSTRACT])) {
                break;
            }

            $index = $prevIndex;
        }

        // Now check if there are attributes above the modifiers
        // Start from the position we found (after walking back through modifiers)
        $checkIndex = $tokens->getPrevNonWhitespace($index);
        $firstAttributeIndex = null;

        // Look backwards from the first modifier (or class if no modifiers)
        while ($checkIndex !== null && $checkIndex >= 0) {
            $token = $tokens[$checkIndex];

            // Check if this is the end of an attribute using CT::T_ATTRIBUTE_CLOSE
            if ($token->isGivenKind(CT::T_ATTRIBUTE_CLOSE)) {
                // Find the start of this attribute group
                $openIndex = $tokens->findBlockStart(Tokens::BLOCK_TYPE_ATTRIBUTE, $checkIndex);

                if ($openIndex !== null) {
                    // The opening token is T_ATTRIBUTE which includes #[
                    // So the attribute starts at openIndex
                    $firstAttributeIndex = $openIndex;
                    // Continue checking for more attributes above this one
                    $checkIndex = $tokens->getPrevNonWhitespace($openIndex);

                    continue;
                }
            }

            // Stop if we hit something that's not an attribute
            break;
        }

        // If we found attributes, return the position of the first (topmost) attribute
        // Otherwise return the position after walking back through modifiers
        return $firstAttributeIndex ?? $index;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function findDocBlockIndex(Tokens $tokens, int $startIndex): ?int
    {
        $beforeStart = $tokens->getPrevNonWhitespace($startIndex);

        if ($beforeStart === null) {
            return null;
        }

        if ($tokens[$beforeStart]->isGivenKind(T_DOC_COMMENT)) {
            return $beforeStart;
        }

        return null;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function updateVersionInExistingDocBlock(Tokens $tokens, int $docBlockIndex, string $author, bool $hasChanges): void
    {
        $content = $tokens[$docBlockIndex]->getContent();

        // Parse existing version - now also matches versions with or without author info
        if (preg_match('/@version\s+(\d+)\.(\d+)\.(\d+)(?:\s+\([^)]+\))?/', $content, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            $patch = (int) $matches[3];

            // Only increment if there are git changes
            if ($hasChanges) {
                $patch++;
            }

            $newVersion = sprintf('@version %d.%d.%d', $major, $minor, $patch);

            // Replace old version with new version (including any author info that might be there)
            $newContent = preg_replace(
                '/@version\s+\d+\.\d+\.\d+(?:\s+\([^)]+\))?/',
                $newVersion,
                $content,
            );
        } else {
            // No version tag exists, add it
            $newVersion = '@version 1.0.0';

            // Try to add it after @author if it exists
            if (str_contains($content, '@author')) {
                $newContent = preg_replace(
                    '/(@author[^\n]*\n)/',
                    "$1 * {$newVersion}\n",
                    $content,
                );
            } else {
                // Otherwise add it before the closing */
                $newContent = str_replace(
                    '*/',
                    "* {$newVersion}\n */",
                    $content,
                );
            }
        }

        $tokens[$docBlockIndex] = new Token([T_DOC_COMMENT, $newContent]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function createNewDocBlockWithVersion(Tokens $tokens, int $classIndex, string $author): void
    {
        $docBlock = "/**\n * @version 1.0.0\n */";
        $tokens->insertAt($classIndex, [
            new Token([T_DOC_COMMENT, $docBlock]),
            new Token([T_WHITESPACE, "\n"]),
        ]);
    }
}
