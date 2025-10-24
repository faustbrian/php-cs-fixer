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

use const T_NEW;
use const T_STRING;
use const T_WHITESPACE;

use function count;
use function mb_strrpos;
use function mb_substr;
use function str_contains;

/**
 * Ensure method/function calls to `dispatch(` place a `new` expression
 * argument on the next line for readability:
 *
 *     $bus->dispatch(new Foo(...));
 * becomes
 *     $bus->dispatch(\n    new Foo(... )\n);
 *
 * This fixer only acts when the first argument is a `new` expression and
 * there are no non-whitespace tokens between the parenthesis and `new`.
 * Existing correct formatting (already multiline) is left unchanged.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class NewArgumentNewlineFixer extends AbstractFixer
{
    #[Override()]
    public function getName(): string
    {
        return 'Architecture/new_argument_newline_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Place `new` argument of dispatch(...) on a new indented line.',
            codeSamples: [
                new CodeSample(
                    "<?php\n\nfinal class Example {\n    public function run() {\n        {$id} = {$this->bus}->dispatch(new CreateCommand(\n            foo: 'bar',\n        ));\n    }\n}\n",
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
        for ($i = 0, $c = $tokens->count(); $i < $c; $i++) {
            if (!$tokens[$i]->equals('(')) {
                continue;
            }

            $prev = $tokens->getPrevMeaningfulToken($i);

            if ($prev === null || !$tokens[$prev]->isGivenKind(T_STRING)) {
                // Only consider calls where token before '(' is an identifier
                continue;
            }

            $firstMeaningful = $tokens->getNextMeaningfulToken($i);

            if ($firstMeaningful === null || !$tokens[$firstMeaningful]->isGivenKind(T_NEW)) {
                continue;
            }

            $onlyWhitespace = true;
            $alreadyMultiline = false;

            for ($j = $i + 1; $j < $firstMeaningful; $j++) {
                $t = $tokens[$j];

                if ($t->isWhitespace()) {
                    if (str_contains($t->getContent(), "\n")) {
                        $alreadyMultiline = true;

                        break;
                    }

                    continue;
                }

                // Comment or attribute or any token is a blocker
                $onlyWhitespace = false;

                break;
            }

            if ($onlyWhitespace && !$alreadyMultiline) {
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
        for ($open = 0, $c = $tokens->count(); $open < $c; $open++) {
            if (!$tokens[$open]->equals('(')) {
                continue;
            }

            $prev = $tokens->getPrevMeaningfulToken($open);

            if ($prev === null || !$tokens[$prev]->isGivenKind(T_STRING)) {
                continue;
            }

            $firstMeaningful = $tokens->getNextMeaningfulToken($open);

            if ($firstMeaningful === null || !$tokens[$firstMeaningful]->isGivenKind(T_NEW)) {
                continue;
            }

            // Check tokens between '(' and 'new'
            $onlyWhitespace = true;
            $hasNewline = false;
            $whitespaceIndices = [];

            for ($j = $open + 1; $j < $firstMeaningful; $j++) {
                $t = $tokens[$j];

                if ($t->isWhitespace()) {
                    $whitespaceIndices[] = $j;

                    if (str_contains($t->getContent(), "\n")) {
                        $hasNewline = true;

                        break; // already in desired shape for this call
                    }

                    continue;
                }

                // Found a non-whitespace token (e.g., comment), skip this occurrence
                $onlyWhitespace = false;

                break;
            }

            if (!$onlyWhitespace || $hasNewline) {
                continue;
            }

            // Determine base indentation from the line containing the '('
            $baseIndent = '';

            for ($b = $open - 1; $b >= 0; $b--) {
                $bt = $tokens[$b];

                if (!$bt->isWhitespace()) {
                    continue;
                }

                $content = $bt->getContent();
                $pos = mb_strrpos($content, "\n");

                if ($pos !== false) {
                    $baseIndent = mb_substr($content, $pos + 1);

                    break;
                }
            }

            $indent = $baseIndent.'    ';

            if ($whitespaceIndices === []) {
                // Insert a whitespace token before `new`
                $tokens->insertAt($firstMeaningful, [new Token([T_WHITESPACE, "\n".$indent])]);
            } else {
                // Replace the first whitespace with a newline + indent and clear the rest
                $tokens[$whitespaceIndices[0]] = new Token([T_WHITESPACE, "\n".$indent]);

                for ($k = 1, $n = count($whitespaceIndices); $k < $n; $k++) {
                    $tokens->clearAt($whitespaceIndices[$k]);
                }
            }

            // Ensure the matching closing parenthesis of dispatch(...) is on its own line
            $close = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $open);

            if ($close !== null) {
                $beforeClose = $close - 1;
                $hasNewlineBeforeClose = false;
                $firstWhitespaceBeforeClose = null;

                for ($j = $beforeClose; $j > $open; $j--) {
                    $t = $tokens[$j];

                    if ($t->isWhitespace()) {
                        $firstWhitespaceBeforeClose = $j;

                        if (str_contains($t->getContent(), "\n")) {
                            $hasNewlineBeforeClose = true;
                        }

                        break;
                    }

                    if (!$t->isComment() && !$t->isWhitespace()) {
                        break;
                    }
                }

                if (!$hasNewlineBeforeClose) {
                    $wsToken = new Token([T_WHITESPACE, "\n".$baseIndent]);

                    if ($firstWhitespaceBeforeClose !== null) {
                        $tokens[$firstWhitespaceBeforeClose] = $wsToken;
                    } else {
                        $tokens->insertAt($close, [$wsToken]);
                    }
                }
            }
        }
    }
}
