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
use SplFileInfo;

use const T_ABSTRACT;
use const T_CLASS;
use const T_DOC_COMMENT;
use const T_FINAL;
use const T_READONLY;
use const T_STRING;
use const T_WHITESPACE;

use function str_contains;
use function str_replace;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class PsalmImmutableOnReadonlyClassFixer extends AbstractFixer
{
    #[Override()]
    public function getName(): string
    {
        return 'Architecture/psalm_immutable_on_readonly_class_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Adds @psalm-immutable to class docblocks for readonly classes when missing.',
            codeSamples: [
                new CodeSample(
                    '<?php
readonly class Example {}
',
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
        return $tokens->isAllTokenKindsFound([T_CLASS]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind([T_CLASS])) {
                continue;
            }

            // Skip anonymous classes
            $nextTokenIndex = $tokens->getNextMeaningfulToken($index);

            if ($nextTokenIndex === null) {
                continue;
            }

            $nextToken = $tokens[$nextTokenIndex];

            if (!$nextToken->isGivenKind(T_STRING)) {
                continue;
            }

            // Only proceed for readonly classes
            if (!self::hasClassModifier($tokens, $index, [T_READONLY])) {
                continue;
            }

            $classStartIndex = self::findClassStartIndex($tokens, $index);
            $docBlockIndex = self::findDocBlockIndex($tokens, $classStartIndex);

            if ($docBlockIndex !== null) {
                self::addPsalmImmutableToExistingDocBlock($tokens, $docBlockIndex);
            } else {
                self::createNewDocBlock($tokens, $classStartIndex);
            }
        }
    }

    /**
     * @param Tokens<Token> $tokens
     * @param array<int>    $modifiers
     */
    private static function hasClassModifier(Tokens $tokens, int $classIndex, array $modifiers): bool
    {
        $index = $classIndex;

        // Walk backwards through potential modifiers
        while ($prevIndex = $tokens->getPrevMeaningfulToken($index)) {
            $prevToken = $tokens[$prevIndex];

            if ($prevToken->isGivenKind($modifiers)) {
                return true;
            }

            if ($prevToken->isGivenKind([T_FINAL, T_READONLY, T_ABSTRACT])) {
                $index = $prevIndex;

                continue;
            }

            break;
        }

        return false;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function findClassStartIndex(Tokens $tokens, int $classIndex): int
    {
        $index = $classIndex;

        // Walk back through modifiers (final, readonly, abstract)
        while ($prevIndex = $tokens->getPrevMeaningfulToken($index)) {
            $prevToken = $tokens[$prevIndex];

            if (!$prevToken->isGivenKind([T_FINAL, T_READONLY, T_ABSTRACT])) {
                break;
            }

            $index = $prevIndex;
        }

        // Now check if there are attributes above the modifiers
        $checkIndex = $tokens->getPrevNonWhitespace($index);
        $firstAttributeIndex = null;

        while ($checkIndex !== null && $checkIndex >= 0) {
            $token = $tokens[$checkIndex];

            if ($token->isGivenKind(CT::T_ATTRIBUTE_CLOSE)) {
                $openIndex = $tokens->findBlockStart(Tokens::BLOCK_TYPE_ATTRIBUTE, $checkIndex);

                if ($openIndex !== null) {
                    $firstAttributeIndex = $openIndex;
                    $checkIndex = $tokens->getPrevNonWhitespace($openIndex);

                    continue;
                }
            }

            break;
        }

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
    private static function addPsalmImmutableToExistingDocBlock(Tokens $tokens, int $docBlockIndex): void
    {
        $content = $tokens[$docBlockIndex]->getContent();

        if (str_contains($content, '@psalm-immutable')) {
            return;
        }

        $newContent = str_replace(
            '*/',
            "* @psalm-immutable\n */",
            $content,
        );

        $tokens[$docBlockIndex] = new Token([T_DOC_COMMENT, $newContent]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function createNewDocBlock(Tokens $tokens, int $classIndex): void
    {
        $docBlock = "/**\n * @psalm-immutable\n */";

        $tokens->insertAt($classIndex, [
            new Token([T_DOC_COMMENT, $docBlock]),
            new Token([T_WHITESPACE, "\n"]),
        ]);
    }
}
