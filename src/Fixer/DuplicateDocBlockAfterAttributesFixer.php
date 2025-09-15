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
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

use const T_CLASS;
use const T_DOC_COMMENT;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_TRAIT;

use function array_reverse;
use function array_unique;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class DuplicateDocBlockAfterAttributesFixer extends AbstractFixer
{
    #[Override()]
    public function getName(): string
    {
        return 'Architecture/duplicate_docblock_after_attributes_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Remove duplicate PHPDoc blocks that appear after PHP attributes.',
            codeSamples: [
                new CodeSample(
                    '<?php
/**
 * @property string $name
 */
#[SomeAttribute]
/**
 * @property string $name
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
        // Always return true to allow the fixer to run and debug
        return $tokens->isAllTokenKindsFound([T_CLASS, T_DOC_COMMENT]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        // Find all class-like declarations and fix them
        for ($index = 0; $index < $tokens->count(); $index++) {
            if (!$tokens[$index]->isGivenKind([T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE])) {
                continue;
            }

            self::fixDuplicateDocBlocksForClass($tokens, $index);
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function fixDuplicateDocBlocksForClass(Tokens $tokens, int $classIndex): void
    {
        // Simple pattern matching: find all docblocks that come immediately after closing ]
        $tokensToRemove = [];

        // Scan backwards from class to find the pattern
        for ($i = $classIndex - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            // Stop scanning when we reach another class or namespace
            if ($token->isGivenKind([T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE, T_NAMESPACE])) {
                break;
            }

            // Look for docblocks that come after ] (attribute closing)
            if (!$token->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }

            // Check if there's a ] before this docblock (indicating it's after an attribute)
            $foundClosingBracket = false;

            for ($j = $i - 1; $j >= 0 && !$foundClosingBracket; $j--) {
                if ($tokens[$j]->getContent() === ']') {
                    $foundClosingBracket = true;

                    break;
                }

                // If we hit another docblock or significant token, stop looking
                if ($tokens[$j]->isGivenKind([T_DOC_COMMENT, T_CLASS, T_NAMESPACE])
                    || (!$tokens[$j]->isWhitespace() && !$tokens[$j]->isComment() && $tokens[$j]->getContent() !== "\n")
                ) {
                    break;
                }
            }

            if (!$foundClosingBracket) {
                continue;
            }

            // This docblock comes after an attribute closing bracket
            $tokensToRemove[] = $i;

            // Also mark surrounding whitespace for removal
            $nextIndex = $i + 1;

            while ($nextIndex < $tokens->count() && $tokens[$nextIndex]->isWhitespace()) {
                $tokensToRemove[] = $nextIndex;
                $nextIndex++;
            }
        }

        // Remove tokens in reverse order to maintain indices
        foreach (array_reverse(array_unique($tokensToRemove)) as $tokenIndex) {
            if (!$tokens->offsetExists($tokenIndex)) {
                continue;
            }

            $tokens->clearAt($tokenIndex);
        }
    }
}
