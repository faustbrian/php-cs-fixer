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
use const T_FINAL;
use const T_FUNCTION;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_READONLY;
use const T_WHITESPACE;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class RedundantReadonlyPropertyFixer extends AbstractFixer
{
    #[Override()]
    public function getName(): string
    {
        return 'Architecture/redundant_readonly_property_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Removes redundant readonly modifier from constructor promoted properties when the class is already readonly.',
            codeSamples: [
                new CodeSample(
                    '<?php
final readonly class Example
{
    public function __construct(
        public readonly string $foo,
        private readonly int $bar,
    ) {
    }
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
        // Must have both class and readonly keywords
        return $tokens->isTokenKindFound(T_CLASS) && $tokens->isTokenKindFound(T_READONLY);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        // First pass: find all readonly classes
        $readonlyClasses = [];

        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(T_CLASS)) {
                continue;
            }

            // Check if this class is marked as readonly
            $checkIndex = $index;

            while ($prevIndex = $tokens->getPrevMeaningfulToken($checkIndex)) {
                $prevToken = $tokens[$prevIndex];

                if ($prevToken->isGivenKind(T_READONLY)) {
                    $readonlyClasses[] = $index;

                    break;
                }

                // Stop if we hit something that's not a class modifier
                if (!$prevToken->isGivenKind([T_FINAL, T_ABSTRACT, T_PUBLIC, T_PROTECTED, T_PRIVATE])) {
                    break;
                }

                $checkIndex = $prevIndex;
            }
        }

        // Second pass: for each readonly class, find and fix constructor promoted properties
        foreach ($readonlyClasses as $classIndex) {
            self::fixConstructorPromotedProperties($tokens, $classIndex);
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function fixConstructorPromotedProperties(Tokens $tokens, int $classIndex): void
    {
        // Find the class opening brace
        $classOpenBrace = $tokens->getNextTokenOfKind($classIndex, ['{']);

        if ($classOpenBrace === null) {
            return;
        }

        // Find the class closing brace
        $classCloseBrace = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $classOpenBrace);

        // Look for constructor within the class
        for ($i = $classOpenBrace + 1; $i < $classCloseBrace; $i++) {
            if (!$tokens[$i]->isGivenKind(T_FUNCTION)) {
                continue;
            }

            $nameIndex = $tokens->getNextMeaningfulToken($i);

            if ($nameIndex === null) {
                continue;
            }

            // Check if this is the constructor
            if ($tokens[$nameIndex]->getContent() !== '__construct') {
                continue;
            }

            // Find the parameter list
            $paramsOpenParen = $tokens->getNextTokenOfKind($nameIndex, ['(']);

            if ($paramsOpenParen === null) {
                continue;
            }

            $paramsCloseParen = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $paramsOpenParen);

            // Process parameters to remove redundant readonly
            for ($j = $paramsOpenParen + 1; $j < $paramsCloseParen; $j++) {
                $paramToken = $tokens[$j];

                // Look for promoted property visibility modifiers
                if (!$paramToken->isGivenKind([
                    T_PUBLIC, T_PROTECTED, T_PRIVATE,
                    CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC,
                    CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED,
                    CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE,
                ])) {
                    continue;
                }

                // Check if the next meaningful token is readonly
                $nextIndex = $tokens->getNextMeaningfulToken($j);

                if ($nextIndex === null || $nextIndex >= $paramsCloseParen || !$tokens[$nextIndex]->isGivenKind(T_READONLY)) {
                    continue;
                }

                // Clear the readonly token
                $tokens->clearAt($nextIndex);

                // Handle whitespace cleanup
                if ($nextIndex - 1 <= $j || !$tokens[$nextIndex - 1]->isWhitespace()) {
                    continue;
                }

                if ($nextIndex + 1 >= $paramsCloseParen || !$tokens[$nextIndex + 1]->isWhitespace()) {
                    continue;
                }

                // Keep only one space between visibility and type
                $tokens[$nextIndex - 1] = new Token([T_WHITESPACE, ' ']);
                $tokens->clearAt($nextIndex + 1);
            }

            // Only one constructor per class
            break;
        }
    }
}
