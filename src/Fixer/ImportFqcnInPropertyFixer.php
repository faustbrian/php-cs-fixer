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
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_VARIABLE;
use const T_WHITESPACE;

use function array_key_exists;
use function array_keys;
use function count;
use function implode;
use function in_array;
use function mb_strtolower;

/**
 * Import FQCNs used in property (and parameter/promoted property) type declarations.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.1
 */
final class ImportFqcnInPropertyFixer extends AbstractFixer
{
    private const array BUILTIN_TYPES = [
        'int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'void', 'never', 'mixed', 'null', 'false', 'true', 'self', 'static', 'parent',
    ];

    #[Override()]
    public function getName(): string
    {
        return 'Architecture/import_fqcn_in_property_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Replace fully-qualified class names in property and parameter types with short names and add corresponding use statements.',
            codeSamples: [
                new CodeSample("<?php\nfinal class A { private \\Vendor\\Cls {$x}; }\n"),
            ],
        );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_VARIABLE);
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
            if (!$tokens[$i]->isGivenKind(T_VARIABLE)) {
                continue;
            }

            // Find the type segment immediately preceding the variable
            $typeEnd = $tokens->getPrevMeaningfulToken($i);

            if ($typeEnd === null) {
                continue;
            }

            // Walk backwards over a contiguous type expression: ?, |, &, T_NS_SEPARATOR, T_STRING
            $allowed = ['|', '&', '?'];
            $start = $typeEnd;
            $foundAnyTypeToken = false;

            while ($start !== null) {
                $t = $tokens[$start];

                if ($t->isGivenKind([T_NS_SEPARATOR, T_STRING])) {
                    $foundAnyTypeToken = true;
                    $start = $tokens->getPrevMeaningfulToken($start);

                    continue;
                }

                if ($t->equalsAny([['|'], ['&'], ['?']])) {
                    $foundAnyTypeToken = true;
                    $start = $tokens->getPrevMeaningfulToken($start);

                    continue;
                }

                break;
            }

            if (!$foundAnyTypeToken) {
                continue; // no type present
            }

            $typeStart = $start !== null ? $start + 1 : 0;
            // Now scan forward within [typeStart, typeEnd] and replace class name sequences
            $j = $typeStart;

            while ($j <= $typeEnd) {
                $tok = $tokens[$j];

                if ($tok->isGivenKind([T_NS_SEPARATOR, T_STRING])) {
                    [$nameStart, $nameEnd, $parts, $hasLeadingNs] = self::collectQualifiedNameRight($tokens, $j);

                    if ($parts !== []) {
                        $short = $parts[count($parts) - 1];
                        $lowerShort = mb_strtolower($short);

                        // Skip builtins
                        if (!in_array($lowerShort, self::BUILTIN_TYPES, true)) {
                            $isFqcn = $hasLeadingNs || count($parts) > 1;

                            if ($isFqcn) {
                                $fqcn = implode('\\', $parts);

                                if (!self::hasShortNameCollision($existingImports, $plannedImports, $short, $fqcn)) {
                                    self::replaceRangeWithShortName($tokens, $nameStart, $nameEnd, $short);

                                    if (!array_key_exists($short, $existingImports) || mb_strtolower($existingImports[$short]) !== mb_strtolower($fqcn)) {
                                        $plannedImports[$short] = $fqcn;
                                        $importsToAdd[$fqcn] = true;
                                    }
                                }
                            }
                        }

                        $j = $nameEnd + 1;

                        continue;
                    }
                }

                $j++;
            }
        }

        if ($importsToAdd === []) {
            return;
        }

        self::insertImports($tokens, array_keys($importsToAdd), $lastUseSemicolonIndex, $namespaceEndIndex);
    }

    /**
     * Collect name going right starting at $start (which is T_NS_SEPARATOR or T_STRING)
     *
     * @return array{0:int, 1:int, 2:list<string>, 3:bool} start, end, parts, hasLeadingNs
     */
    private static function collectQualifiedNameRight(Tokens $tokens, int $start): array
    {
        $idx = $start;
        $parts = [];
        $hasLeadingNs = false;
        $first = $idx;

        if ($tokens[$idx]->isGivenKind(T_NS_SEPARATOR)) {
            $hasLeadingNs = true;
            $idx = $tokens->getNextMeaningfulToken($idx) ?? $idx;
            $first = $start;
        }

        while ($idx !== null) {
            $t = $tokens[$idx];

            if ($t->isGivenKind(T_STRING)) {
                $parts[] = $t->getContent();
                $next = $tokens->getNextMeaningfulToken($idx);

                if ($next !== null && $tokens[$next]->isGivenKind(T_NS_SEPARATOR)) {
                    $idx = $tokens->getNextMeaningfulToken($next);

                    continue;
                }

                break;
            }

            break;
        }

        if ($parts === []) {
            return [$start, $start, [], $hasLeadingNs];
        }

        $end = $idx;
        $startIndex = $first;

        return [$startIndex, $end, $parts, $hasLeadingNs];
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
