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

use const T_AS;
use const T_CLASS;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

use function array_key_exists;
use function array_keys;
use function count;
use function implode;
use function mb_strtolower;

/**
 * Ensures fully-qualified class names used in `new` expressions are imported via `use`
 * and the short class name is used at the call site.
 *
 * Example: `new \Vendor\Package\Foo()` becomes `new Foo()` with `use Vendor\Package\Foo;` added.
 *
 * This fixer focuses on `new` expressions and avoids changes when doing so would
 * create a name collision with an already imported different class of the same short name.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class ImportFqcnInNewFixer extends AbstractFixer
{
    #[Override()]
    public function getName(): string
    {
        return 'Architecture/import_fqcn_in_new_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Replace fully-qualified class names in new-expressions with short names and add corresponding use statements.',
            codeSamples: [
                new CodeSample(
                    "<?php\n\nnamespace App;\n\nfinal class Example {\n    public function __construct() {\n        {$x} = new \\Vendor\\Package\\Foo();\n    }\n}\n",
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
        return $tokens->isTokenKindFound(T_NEW);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        // Collect existing imports and determine insertion point for new imports
        [$existingImports, $lastUseSemicolonIndex, $namespaceEndIndex] = self::collectImportsAndPositions($tokens);

        // Keep track of planned imports in this pass to avoid short-name collisions
        $plannedImports = []; // shortName => FQCN
        $importsToAdd = [];   // FQCN strings without leading backslash

        for ($index = 0; $index < $tokens->count(); $index++) {
            if (!$tokens[$index]->isGivenKind(T_NEW)) {
                continue;
            }

            $nameStart = $tokens->getNextMeaningfulToken($index);

            if ($nameStart === null) {
                continue;
            }

            // Skip anonymous classes
            if ($tokens[$nameStart]->isGivenKind(T_CLASS)) {
                continue;
            }

            // Gather the class name sequence after `new`
            $seqStart = $nameStart;
            $seqEnd = $nameStart;
            $hasNsSep = false;

            // Optional leading namespace separator
            if ($tokens[$seqEnd]->isGivenKind(T_NS_SEPARATOR)) {
                $hasNsSep = true;
                $seqEnd = $tokens->getNextMeaningfulToken($seqEnd) ?? $seqEnd;
            }

            // Now consume T_STRING (namespace parts) and T_NS_SEPARATOR
            $parts = [];

            while ($seqEnd !== null) {
                $token = $tokens[$seqEnd];

                if ($token->isGivenKind(T_STRING)) {
                    $parts[] = $token->getContent();
                    $next = $tokens->getNextMeaningfulToken($seqEnd);

                    if ($next !== null && $tokens[$next]->isGivenKind(T_NS_SEPARATOR)) {
                        // Keep going across backslashes
                        $seqEnd = $tokens->getNextMeaningfulToken($next);

                        continue;
                    }

                    // Likely end of FQCN sequence
                    break;
                }

                break;
            }

            if ($parts === []) {
                // Not a recognizable class name after new
                continue;
            }

            // Determine if it is fully-qualified (starts with \ or has at least one \ separator)
            $isFqcn = $hasNsSep || count($parts) > 1;

            if (!$isFqcn) {
                // Already a short name; nothing to do
                continue;
            }

            $fqcn = implode('\\', $parts);
            $short = $parts[count($parts) - 1];

            // Collision check against existing and planned imports
            if (array_key_exists($short, $existingImports) && mb_strtolower($existingImports[$short]) !== mb_strtolower($fqcn)) {
                // Different class already imported with same short name: skip to avoid ambiguity
                continue;
            }

            if (array_key_exists($short, $plannedImports) && mb_strtolower($plannedImports[$short]) !== mb_strtolower($fqcn)) {
                // Another planned import in this pass would conflict
                continue;
            }

            // Replace the token sequence representing the FQCN with the short name
            // Determine the real start index for replacement (include possible leading \\)
            $replaceStart = $seqStart;

            if ($tokens[$replaceStart]->isGivenKind(T_NS_SEPARATOR)) {
                $replaceStart = $seqStart; // include leading backslash
            }

            // Clear existing name tokens (from replaceStart to seqEnd)
            for ($i = $replaceStart; $i <= $seqEnd; $i++) {
                if (!$tokens->offsetExists($i)) {
                    continue;
                }

                $tokens->clearAt($i);
            }

            // Insert short name at replaceStart
            $tokens->insertAt($replaceStart, [new Token([T_STRING, $short])]);

            // Plan import if not already present
            if (array_key_exists($short, $existingImports) && mb_strtolower($existingImports[$short]) === mb_strtolower($fqcn)) {
                continue;
            }

            $plannedImports[$short] = $fqcn;
            $importsToAdd[$fqcn] = true;
        }

        if ($importsToAdd === []) {
            return;
        }

        // Decide where to insert imports: after last existing use; otherwise after namespace;
        $insertionIndex = $lastUseSemicolonIndex ?? $namespaceEndIndex;

        if ($insertionIndex === null) {
            // No namespace and no imports found; put at the very beginning after opening tag or declare(strict_types)
            $insertionIndex = 0;
        }

        // Build and insert import tokens for each new FQCN
        foreach (array_keys($importsToAdd) as $fqcnToAdd) {
            $importTokens = [
                new Token([T_USE, 'use']),
                new Token([T_WHITESPACE, ' ']),
                new Token([T_STRING, $fqcnToAdd]),
                new Token(';'),
                new Token([T_WHITESPACE, "\n"]),
            ];

            $tokens->insertAt($insertionIndex + 1, $importTokens);
            // Move insertionIndex forward to keep adding imports in order
            $insertionIndex += count($importTokens);
        }
    }

    /**
     * Collect existing top-level imports and determine positions to insert new ones.
     *
     * @param  Tokens<Token>                                             $tokens
     * @return array{0: array<string, string>, 1: null|int, 2: null|int} [imports by short name => FQCN, lastUseSemicolonIndex, namespaceEndIndex]
     */
    private static function collectImportsAndPositions(Tokens $tokens): array
    {
        $importsByShort = [];
        $namespaceEndIndex = null;
        $lastUseSemicolonIndex = null;

        // Find namespace end (semicolon form) or the closing brace of block namespace
        $nsIndex = $tokens->getNextTokenOfKind(0, [[T_NAMESPACE]]);

        if ($nsIndex !== null) {
            $end = $tokens->getNextTokenOfKind($nsIndex, [';', '{']);

            if ($end !== null) {
                $namespaceEndIndex = $end;
            }
        }

        // Find first class-like token to limit the area where use statements appear typically
        $firstClassLike = $tokens->getNextTokenOfKind(0, [[T_CLASS], [T_INTERFACE], [T_TRAIT], [T_ENUM]]);
        $scanUntil = $firstClassLike ?? $tokens->count();

        for ($i = 0; $i < $scanUntil; $i++) {
            if (!$tokens[$i]->isGivenKind(T_USE)) {
                continue;
            }

            // Heuristic: consider T_USE as import if it appears before a class-like declaration
            // and is not followed by '(' (closure use) within a few tokens
            $nextMeaningful = $tokens->getNextMeaningfulToken($i);

            if ($nextMeaningful !== null && $tokens[$nextMeaningful]->getContent() === '(') {
                // closure use
                continue;
            }

            // Parse until semicolon and extract short names (including aliases and group uses)
            $j = $i + 1;
            $currentShort = null;
            $maybePrefix = [];

            while ($j < $tokens->count() && !$tokens[$j]->equals(';')) {
                $tok = $tokens[$j];

                if ($tok->isWhitespace() || $tok->isComment()) {
                    $j++;

                    continue;
                }

                if ($tok->isGivenKind(T_AS)) {
                    // Next string is alias
                    $aliasIndex = $tokens->getNextMeaningfulToken($j);

                    if ($aliasIndex !== null && $tokens[$aliasIndex]->isGivenKind(T_STRING)) {
                        $currentShort = $tokens[$aliasIndex]->getContent();
                        // Register alias (exact FQCN unknown here, but short-name collision prevention suffices)
                        $importsByShort[$currentShort] ??= '__alias__';
                        $j = $aliasIndex + 1;

                        continue;
                    }
                }

                if ($tok->isGivenKind(T_STRING)) {
                    $maybePrefix[] = $tok->getContent();
                }

                if ($tok->equals(',')) {
                    // End of one import in group or simple list
                    if ($maybePrefix !== []) {
                        $short = $maybePrefix[count($maybePrefix) - 1];
                        $importsByShort[$short] ??= implode('\\', $maybePrefix);
                    }

                    $maybePrefix = [];
                }

                $j++;
            }

            // Semicolon found at $j
            $lastUseSemicolonIndex = $j;

            // Flush last element before semicolon
            if ($maybePrefix !== []) {
                $short = $maybePrefix[count($maybePrefix) - 1];
                $importsByShort[$short] ??= implode('\\', $maybePrefix);
            }

            // Continue scanning after semicolon
            $i = $j;
        }

        return [$importsByShort, $lastUseSemicolonIndex, $namespaceEndIndex];
    }
}
