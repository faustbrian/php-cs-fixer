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

use const T_ABSTRACT;
use const T_AND_EQUAL;
use const T_CLASS;
use const T_COALESCE_EQUAL;
use const T_CONCAT_EQUAL;
use const T_DIV_EQUAL;
use const T_EXTENDS;
use const T_FINAL;
use const T_FUNCTION;
use const T_MINUS_EQUAL;
use const T_MOD_EQUAL;
use const T_MUL_EQUAL;
use const T_OBJECT_OPERATOR;
use const T_OR_EQUAL;
use const T_PLUS_EQUAL;
use const T_POW_EQUAL;
use const T_READONLY;
use const T_SL_EQUAL;
use const T_SR_EQUAL;
use const T_STRING;
use const T_UNSET;
use const T_VARIABLE;
use const T_WHITESPACE;
use const T_XOR_EQUAL;

use function array_merge;
use function count;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class FinalReadonlyClassFixer extends AbstractFixer
{
    private readonly array $configuration;

    public function __construct(array $configuration = [])
    {
        $this->configuration = array_merge([
            'skip_abstract' => true,
            'skip_if_mutation_detected' => true,
        ], $configuration);
    }

    #[Override()]
    public function getName(): string
    {
        return 'Architecture/final_readonly_class_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Makes classes final readonly if no property mutations are detected.',
            [
                new CodeSample(
                    '<?php
class Example
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
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
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind([T_CLASS])) {
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
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind([T_CLASS])) {
                continue;
            }

            // Skip anonymous classes
            $nextToken = $tokens[$tokens->getNextMeaningfulToken($index)];

            if (!$nextToken->isGivenKind(T_STRING)) {
                continue;
            }

            // Check class modifiers
            $hasFinal = self::hasClassModifier($tokens, $index, [T_FINAL]);
            $hasReadonly = self::hasClassModifier($tokens, $index, [T_READONLY]);
            $isAbstract = self::hasClassModifier($tokens, $index, [T_ABSTRACT]);
            $hasExtends = self::hasExtends($tokens, $index);

            // If class has readonly but extends another class, we need to remove readonly
            if ($hasReadonly && $hasExtends) {
                self::removeReadonlyModifier($tokens, $index);

                continue;
            }

            // Find class body boundaries
            $classBodyStart = self::findClassBodyStart($tokens, $index);
            $classBodyEnd = self::findClassBodyEnd($tokens, $classBodyStart);

            if ($classBodyStart === null || $classBodyEnd === null) {
                continue;
            }

            // Check for property mutations (always check when class has readonly)
            $hasMutations = self::hasPropertyMutations($tokens, $classBodyStart, $classBodyEnd);

            // If class has readonly but has mutations, we need to remove readonly (invalid PHP)
            // unless configuration says to ignore mutations
            if ($hasReadonly && $hasMutations && $this->configuration['skip_if_mutation_detected']) {
                self::removeReadonlyModifier($tokens, $index);

                continue;
            }

            // Skip if already has both final and readonly, no extends, and (no mutations OR ignoring mutations)
            $shouldConsiderMutations = $this->configuration['skip_if_mutation_detected'];
            $noBlockingMutations = !$hasMutations || !$shouldConsiderMutations;

            if ($hasFinal && $hasReadonly && !$hasExtends && $noBlockingMutations) {
                continue;
            }

            // Skip abstract classes if configured
            if ($this->configuration['skip_abstract'] && $isAbstract) {
                continue;
            }

            // Skip if class extends another class (readonly classes cannot extend)
            if ($hasExtends) {
                continue;
            }

            // Skip if mutations detected and configuration says to skip (don't add readonly to mutable classes)
            if ($hasMutations && $this->configuration['skip_if_mutation_detected']) {
                continue;
            }

            // Apply missing modifiers
            self::addMissingModifiers($tokens, $index, $hasFinal, $hasReadonly, $isAbstract);
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
    private static function findClassBodyStart(Tokens $tokens, int $classIndex): ?int
    {
        $index = $classIndex;

        while ($index < count($tokens)) {
            if ($tokens[$index]->equals('{')) {
                return $index;
            }

            $index++;
        }

        return null;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function findClassBodyEnd(Tokens $tokens, int $bodyStartIndex): ?int
    {
        return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $bodyStartIndex);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function hasPropertyMutations(Tokens $tokens, int $startIndex, int $endIndex): bool
    {
        for ($i = $startIndex; $i < $endIndex; $i++) {
            $token = $tokens[$i];

            // Skip constructor assignments (allowed in readonly classes)
            if (self::isInsideConstructor($tokens, $i, $startIndex, $endIndex)) {
                continue;
            }

            // Check for $this->property = assignments
            if (self::isPropertyAssignment($tokens, $i)) {
                return true;
            }

            // Check for unset($this->property)
            if (self::isPropertyUnset($tokens, $i)) {
                return true;
            }

            // Check for property mutations via array/object access
            if (self::isPropertyMutation($tokens, $i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function isInsideConstructor(Tokens $tokens, int $currentIndex, int $classStart, int $classEnd): bool
    {
        // Find function boundaries around current index
        $functionStart = null;
        $functionEnd = null;

        // Search backwards for function keyword
        for ($i = $currentIndex; $i >= $classStart; $i--) {
            if ($tokens[$i]->isGivenKind(T_FUNCTION)) {
                $functionStart = $i;

                break;
            }
        }

        if ($functionStart === null) {
            return false;
        }

        // Check if it's __construct
        $nameIndex = $tokens->getNextMeaningfulToken($functionStart);

        if ($nameIndex === null) {
            return false;
        }

        $nameToken = $tokens[$nameIndex];

        if (!$nameToken->isGivenKind(T_STRING) || $nameToken->getContent() !== '__construct') {
            return false;
        }

        // Find function body end
        $bodyStartIndex = null;

        for ($i = $functionStart; $i < $classEnd; $i++) {
            if ($tokens[$i]->equals('{')) {
                $bodyStartIndex = $i;

                break;
            }
        }

        if ($bodyStartIndex === null) {
            return false;
        }

        $functionEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $bodyStartIndex);

        return $currentIndex >= $bodyStartIndex && $currentIndex <= $functionEnd;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function isPropertyAssignment(Tokens $tokens, int $index): bool
    {
        $token = $tokens[$index];

        // Look for $this->property = pattern
        if (!$token->isGivenKind(T_VARIABLE) || $token->getContent() !== '$this') {
            return false;
        }

        $nextIndex = $tokens->getNextMeaningfulToken($index);

        if ($nextIndex === null || !$tokens[$nextIndex]->isGivenKind(T_OBJECT_OPERATOR)) {
            return false;
        }

        $propertyIndex = $tokens->getNextMeaningfulToken($nextIndex);

        if ($propertyIndex === null || !$tokens[$propertyIndex]->isGivenKind(T_STRING)) {
            return false;
        }

        $assignIndex = $tokens->getNextMeaningfulToken($propertyIndex);

        if ($assignIndex === null) {
            return false;
        }

        // Check for assignment operators
        return $tokens[$assignIndex]->equals('=') || $tokens[$assignIndex]->isGivenKind([T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_MOD_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_POW_EQUAL, T_COALESCE_EQUAL]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function isPropertyUnset(Tokens $tokens, int $index): bool
    {
        $token = $tokens[$index];

        if (!$token->isGivenKind(T_UNSET)) {
            return false;
        }

        // Look for unset($this->property) pattern
        $openParenIndex = $tokens->getNextMeaningfulToken($index);

        if ($openParenIndex === null || !$tokens[$openParenIndex]->equals('(')) {
            return false;
        }

        $thisIndex = $tokens->getNextMeaningfulToken($openParenIndex);

        if ($thisIndex === null || !$tokens[$thisIndex]->isGivenKind(T_VARIABLE) || $tokens[$thisIndex]->getContent() !== '$this') {
            return false;
        }

        $arrowIndex = $tokens->getNextMeaningfulToken($thisIndex);

        return $arrowIndex !== null && $tokens[$arrowIndex]->isGivenKind(T_OBJECT_OPERATOR);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function isPropertyMutation(Tokens $tokens, int $index): bool
    {
        $token = $tokens[$index];

        // Look for $this->property[...] = or $this->property->... = patterns
        if (!$token->isGivenKind(T_VARIABLE) || $token->getContent() !== '$this') {
            return false;
        }

        $nextIndex = $tokens->getNextMeaningfulToken($index);

        if ($nextIndex === null || !$tokens[$nextIndex]->isGivenKind(T_OBJECT_OPERATOR)) {
            return false;
        }

        $propertyIndex = $tokens->getNextMeaningfulToken($nextIndex);

        if ($propertyIndex === null || !$tokens[$propertyIndex]->isGivenKind(T_STRING)) {
            return false;
        }

        $afterPropertyIndex = $tokens->getNextMeaningfulToken($propertyIndex);

        if ($afterPropertyIndex === null) {
            return false;
        }

        // Check for array access mutations: $this->prop[...] =
        if ($tokens[$afterPropertyIndex]->equals('[')) {
            $closeBracketIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_INDEX_SQUARE_BRACE, $afterPropertyIndex);
            $assignIndex = $tokens->getNextMeaningfulToken($closeBracketIndex);

            return $assignIndex !== null && ($tokens[$assignIndex]->equals('=') || $tokens[$assignIndex]->isGivenKind([T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_MOD_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_POW_EQUAL, T_COALESCE_EQUAL]));
        }

        // Check for chained object mutations: $this->prop->method()
        if ($tokens[$afterPropertyIndex]->isGivenKind(T_OBJECT_OPERATOR)) {
            // This could be a method call that mutates state, but it's complex to detect
            // For now, we'll be conservative and assume it might mutate
            return true;
        }

        return false;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function addMissingModifiers(Tokens $tokens, int $classIndex, bool $hasFinal, bool $hasReadonly, bool $isAbstract): void
    {
        $tokensToInsert = [];

        // Determine what modifiers need to be added
        // Order should be: final readonly (abstract) class

        if (!$hasFinal) {
            $tokensToInsert[] = new Token([T_FINAL, 'final']);
            $tokensToInsert[] = new Token([T_WHITESPACE, ' ']);
        }

        if (!$hasReadonly) {
            $tokensToInsert[] = new Token([T_READONLY, 'readonly']);
            $tokensToInsert[] = new Token([T_WHITESPACE, ' ']);
        }

        if (empty($tokensToInsert)) {
            return;
        }

        // Find the correct position to insert
        if ($hasFinal && !$hasReadonly) {
            // Class has final but needs readonly - insert readonly after final
            $finalPosition = self::findFinalPosition($tokens, $classIndex);

            if ($finalPosition !== null) {
                $insertPosition = $finalPosition + 1;

                // Skip whitespace after final
                while ($insertPosition < count($tokens) && $tokens[$insertPosition]->isWhitespace()) {
                    $insertPosition++;
                }

                $tokens->insertAt($insertPosition, $tokensToInsert);

                return;
            }
        }

        // For all other cases, insert at the beginning of modifiers
        $insertPosition = self::findModifierInsertPosition($tokens, $classIndex, $hasFinal, $hasReadonly, $isAbstract);
        $tokens->insertAt($insertPosition, $tokensToInsert);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function findFinalPosition(Tokens $tokens, int $classIndex): ?int
    {
        $index = $classIndex;

        // Walk backwards to find final modifier
        while ($prevIndex = $tokens->getPrevMeaningfulToken($index)) {
            $prevToken = $tokens[$prevIndex];

            if ($prevToken->isGivenKind(T_FINAL)) {
                return $prevIndex;
            }

            if (!$prevToken->isGivenKind([T_FINAL, T_READONLY, T_ABSTRACT])) {
                break;
            }

            $index = $prevIndex;
        }

        return null;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function findModifierInsertPosition(Tokens $tokens, int $classIndex, bool $hasFinal, bool $hasReadonly, bool $isAbstract): int
    {
        // If the class already has any modifiers, find the first one
        $index = $classIndex;

        // Walk backwards to find the first modifier
        while ($prevIndex = $tokens->getPrevMeaningfulToken($index)) {
            $prevToken = $tokens[$prevIndex];

            if (!$prevToken->isGivenKind([T_FINAL, T_READONLY, T_ABSTRACT])) {
                break;
            }

            $index = $prevIndex;
        }

        return $index;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function removeReadonlyModifier(Tokens $tokens, int $classIndex): void
    {
        // Walk backwards from class keyword to find readonly modifier
        $index = $classIndex;

        while ($prevIndex = $tokens->getPrevMeaningfulToken($index)) {
            $prevToken = $tokens[$prevIndex];

            if ($prevToken->isGivenKind(T_READONLY)) {
                // Remove the readonly token
                $tokens->clearAt($prevIndex);

                // Also remove the whitespace after it
                $nextIndex = $prevIndex + 1;

                if ($tokens[$nextIndex]->isWhitespace()) {
                    $tokens->clearAt($nextIndex);
                }

                return;
            }

            if (!$prevToken->isGivenKind([T_FINAL, T_READONLY, T_ABSTRACT])) {
                break;
            }

            $index = $prevIndex;
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function hasExtends(Tokens $tokens, int $classIndex): bool
    {
        // Look for the extends keyword after the class name
        $index = $classIndex;
        $openBraceIndex = null;

        // Find the opening brace of the class
        for ($i = $index; $i < count($tokens); $i++) {
            if ($tokens[$i]->equals('{')) {
                $openBraceIndex = $i;

                break;
            }
        }

        if ($openBraceIndex === null) {
            return false;
        }

        // Check if there's an extends keyword between class and opening brace
        for ($i = $classIndex; $i < $openBraceIndex; $i++) {
            if ($tokens[$i]->isGivenKind(T_EXTENDS)) {
                return true;
            }
        }

        return false;
    }
}
