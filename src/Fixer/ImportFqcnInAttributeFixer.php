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

use const T_AS;
use const T_CLASS;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAMESPACE;
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
 * Import FQCNs used in attributes: #[\Vendor\Attr] -> #[Attr] + use Vendor\Attr;
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.1
 */
final class ImportFqcnInAttributeFixer extends AbstractFixer
{
    #[Override()]
    public function getName(): string
    {
        return 'Architecture/import_fqcn_in_attribute_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Replace fully-qualified class names in attributes with short names and add corresponding use statements.',
            codeSamples: [
                new CodeSample("<?php\n#[\\Vendor\\Pkg\\MyAttr]\nfinal class A {}\n"),
            ],
        );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(CT::T_ATTRIBUTE_CLOSE);
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
            if (!$tokens[$i]->isGivenKind(CT::T_ATTRIBUTE_CLOSE)) {
                continue;
            }

            $open = $tokens->findBlockStart(Tokens::BLOCK_TYPE_ATTRIBUTE, $i);

            if ($open === null) {
                continue;
            }

            // Scan between open and close; capture attribute names when parenthesis level is 0
            $paren = 0;
            $j = $open + 1;

            while ($j < $i) {
                $tok = $tokens[$j];

                if ($tok->equals('(')) {
                    $paren++;
                    $j++;

                    continue;
                }

                if ($tok->equals(')')) {
                    $paren--;
                    $j++;

                    continue;
                }

                if ($paren > 0) {
                    $j++;

                    continue;
                }

                // At top level of attribute group; parse names separated by comma
                if ($tok->isGivenKind([T_NS_SEPARATOR, T_STRING])) {
                    // Beginning of a possibly qualified name
                    [$parts, $nameStart, $nameEnd] = self::collectQualifiedName($tokens, $j);

                    if ($parts !== []) {
                        $isFqcn = $tokens[$nameStart]->isGivenKind(T_NS_SEPARATOR) || count($parts) > 1;

                        if ($isFqcn) {
                            $fqcn = implode('\\', $parts);
                            $short = $parts[count($parts) - 1];

                            if (!self::hasShortNameCollision($existingImports, $plannedImports, $short, $fqcn)) {
                                self::replaceRangeWithShortName($tokens, $nameStart, $nameEnd, $short);

                                if (!array_key_exists($short, $existingImports) || mb_strtolower($existingImports[$short]) !== mb_strtolower($fqcn)) {
                                    $plannedImports[$short] = $fqcn;
                                    $importsToAdd[$fqcn] = true;
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
     * @return array{0: list<string>, 1: int, 2: int} parts, startIndex, endIndex
     */
    private static function collectQualifiedName(Tokens $tokens, int $start): array
    {
        $idx = $start;
        $parts = [];
        $hasLeadingNs = false;

        if ($tokens[$idx]->isGivenKind(T_NS_SEPARATOR)) {
            $hasLeadingNs = true;
            $idx = $tokens->getNextMeaningfulToken($idx) ?? $idx;
        }

        $first = $idx;

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
            return [[], $start, $start];
        }

        $end = $idx;
        // Attribute name may be followed by parenthesis; but the name itself ends at $end
        $startIndex = $hasLeadingNs ? $start : $first;

        return [$parts, $startIndex, $end];
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
        // Reuse logic from ImportFqcnInNewFixer (duplicated here for isolation)
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
