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
use const T_DOUBLE_COLON;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STATIC;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

use function array_key_exists;
use function array_keys;
use function array_reverse;
use function count;
use function implode;
use function in_array;
use function mb_strtolower;

/**
 * Import FQCNs used in static calls: \Vendor\Pkg\Cls::method() -> Cls::method() + use Vendor\Pkg\Cls;
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.1
 */
final class ImportFqcnInStaticCallFixer extends AbstractFixer
{
    #[Override()]
    public function getName(): string
    {
        return 'Architecture/import_fqcn_in_static_call_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Replace fully-qualified class names in static calls with short names and add corresponding use statements.',
            codeSamples: [
                new CodeSample("<?php\n\\Vendor\\Pkg\\Cls::call();\n"),
            ],
        );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_DOUBLE_COLON);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        [$existingImports, $lastUseSemicolonIndex, $namespaceEndIndex] = self::collectImportsAndPositions($tokens);
        $plannedImports = [];
        $importsToAdd = [];

        for ($i = 0; $i < $tokens->count(); $i++) {
            if (!$tokens[$i]->isGivenKind(T_DOUBLE_COLON)) {
                continue;
            }

            $left = $tokens->getPrevMeaningfulToken($i);

            if ($left === null) {
                continue;
            }

            // Skip self/static/parent
            if ($tokens[$left]->isGivenKind(T_STATIC)) {
                continue;
            }

            if ($tokens[$left]->isGivenKind(T_STRING)) {
                $val = mb_strtolower($tokens[$left]->getContent());

                if (in_array($val, ['self', 'parent', 'static'], true)) {
                    continue;
                }
            }

            // Collect qualified name to the left of ::
            [$start, $end, $parts, $hasLeadingNs] = self::collectQualifiedNameLeft($tokens, $left);

            if ($parts === []) {
                continue;
            }

            $isFqcn = $hasLeadingNs || count($parts) > 1;

            if (!$isFqcn) {
                continue;
            }

            $fqcn = implode('\\', $parts);
            $short = $parts[count($parts) - 1];

            if (self::hasShortNameCollision($existingImports, $plannedImports, $short, $fqcn)) {
                continue;
            }

            self::replaceRangeWithShortName($tokens, $start, $end, $short);

            if (array_key_exists($short, $existingImports) && mb_strtolower($existingImports[$short]) === mb_strtolower($fqcn)) {
                continue;
            }

            $plannedImports[$short] = $fqcn;
            $importsToAdd[$fqcn] = true;
        }

        if ($importsToAdd === []) {
            return;
        }

        self::insertImports($tokens, array_keys($importsToAdd), $lastUseSemicolonIndex, $namespaceEndIndex);
    }

    /**
     * @return array{0:int, 1:int, 2:list<string>, 3:bool} startIndex, endIndex, parts, hasLeadingNs
     */
    private static function collectQualifiedNameLeft(Tokens $tokens, int $endIndex): array
    {
        $idx = $endIndex;
        $hasLeadingNs = false;
        $current = [];
        $firstStringIndex = null;

        // Expect to start at a T_STRING (e.g., ClassName in ClassName::const)
        if (!$tokens[$idx]->isGivenKind(T_STRING)) {
            return [$endIndex, $endIndex, [], $hasLeadingNs];
        }

        while ($idx >= 0) {
            $t = $tokens[$idx];

            if ($t->isGivenKind(T_STRING)) {
                $current[] = $t->getContent();
                $firstStringIndex = $idx;
                $prev = $tokens->getPrevMeaningfulToken($idx);

                if ($prev !== null && $tokens[$prev]->isGivenKind(T_NS_SEPARATOR)) {
                    // Move over backslash to previous part
                    $idx = $tokens->getPrevMeaningfulToken($prev) ?? $prev;

                    continue;
                }

                break; // no more namespace parts
            }

            break;
        }

        if ($current === [] || $firstStringIndex === null) {
            return [$endIndex, $endIndex, [], $hasLeadingNs];
        }

        // Include leading backslash if present
        $startIndex = $firstStringIndex;
        $prev = $tokens->getPrevMeaningfulToken($firstStringIndex);

        if ($prev !== null && $tokens[$prev]->isGivenKind(T_NS_SEPARATOR)) {
            $hasLeadingNs = true;
            $startIndex = $prev;
        }

        $parts = array_reverse($current);

        return [$startIndex, $endIndex, $parts, $hasLeadingNs];
    }

    private static function replaceRangeWithShortName(Tokens $tokens, int $start, int $end, string $short): void
    {
        for ($k = $start; $k <= $end; $k++) {
            if (!$tokens->offsetExists($k)) {
                continue;
            }

            $tokens->clearAt($k);
        }

        $tokens->insertAt($start, [new Token([T_STRING, $short])]);
    }

    private static function hasShortNameCollision(array $existingImports, array $plannedImports, string $short, string $fqcn): bool
    {
        if (array_key_exists($short, $existingImports) && mb_strtolower((string) $existingImports[$short]) !== mb_strtolower($fqcn)) {
            return true;
        }

        return array_key_exists($short, $plannedImports) && mb_strtolower((string) $plannedImports[$short]) !== mb_strtolower($fqcn);
    }

    /**
     * @param list<string> $fqcns
     */
    private static function insertImports(Tokens $tokens, array $fqcns, ?int $lastUseSemicolonIndex, ?int $namespaceEndIndex): void
    {
        $insertionIndex = $lastUseSemicolonIndex ?? $namespaceEndIndex ?? 0;

        foreach ($fqcns as $fqcnToAdd) {
            $importTokens = [
                new Token([T_USE, 'use']),
                new Token([T_WHITESPACE, ' ']),
                new Token([T_STRING, $fqcnToAdd]),
                new Token(';'),
                new Token([T_WHITESPACE, "\n"]),
            ];
            $tokens->insertAt($insertionIndex + 1, $importTokens);
            $insertionIndex += count($importTokens);
        }
    }

    /**
     * @return array{0: array<string, string>, 1: null|int, 2: null|int}
     */
    private static function collectImportsAndPositions(Tokens $tokens): array
    {
        $importsByShort = [];
        $namespaceEndIndex = null;
        $lastUseSemicolonIndex = null;

        $nsIndex = $tokens->getNextTokenOfKind(0, [[T_NAMESPACE]]);

        if ($nsIndex !== null) {
            $end = $tokens->getNextTokenOfKind($nsIndex, [';', '{']);

            if ($end !== null) {
                $namespaceEndIndex = $end;
            }
        }

        $firstClassLike = $tokens->getNextTokenOfKind(0, [[T_CLASS], [T_INTERFACE], [T_TRAIT], [T_ENUM]]);
        $scanUntil = $firstClassLike ?? $tokens->count();

        for ($i = 0; $i < $scanUntil; $i++) {
            if (!$tokens[$i]->isGivenKind(T_USE)) {
                continue;
            }

            $nextMeaningful = $tokens->getNextMeaningfulToken($i);

            if ($nextMeaningful !== null && $tokens[$nextMeaningful]->getContent() === '(') {
                continue; // closure use
            }

            $j = $i + 1;
            $maybePrefix = [];

            while ($j < $tokens->count() && !$tokens[$j]->equals(';')) {
                $tok = $tokens[$j];

                if ($tok->isWhitespace() || $tok->isComment()) {
                    $j++;

                    continue;
                }

                if ($tok->isGivenKind(T_AS)) {
                    $aliasIndex = $tokens->getNextMeaningfulToken($j);

                    if ($aliasIndex !== null && $tokens[$aliasIndex]->isGivenKind(T_STRING)) {
                        $importsByShort[$tokens[$aliasIndex]->getContent()] ??= '__alias__';
                        $j = $aliasIndex + 1;

                        continue;
                    }
                }

                if ($tok->isGivenKind(T_STRING)) {
                    $maybePrefix[] = $tok->getContent();
                }

                if ($tok->equals(',')) {
                    if ($maybePrefix !== []) {
                        $short = $maybePrefix[count($maybePrefix) - 1];
                        $importsByShort[$short] ??= implode('\\', $maybePrefix);
                    }

                    $maybePrefix = [];
                }

                $j++;
            }

            $lastUseSemicolonIndex = $j;

            if ($maybePrefix !== []) {
                $short = $maybePrefix[count($maybePrefix) - 1];
                $importsByShort[$short] ??= implode('\\', $maybePrefix);
            }

            $i = $j;
        }

        return [$importsByShort, $lastUseSemicolonIndex, $namespaceEndIndex];
    }
}
