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
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

use const T_ABSTRACT;
use const T_CLASS;
use const T_FINAL;
use const T_READONLY;
use const T_STRING;

use function str_ends_with;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class AbstractNameFixer extends AbstractFixer
{
    private const string SUFFIX = 'Abstract';

    #[Override()]
    public function getName(): string
    {
        return 'Architecture/abstract_name_fixer';
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            summary: 'Exception classes should have suffix "Abstract".',
            codeSamples: [],
        );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAllTokenKindsFound([T_ABSTRACT, T_CLASS]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(T_ABSTRACT)) {
                continue;
            }

            // Find the T_CLASS token after T_ABSTRACT (may have modifiers in between)
            $searchIndex = $index;
            $classTokenIndex = null;

            while (($searchIndex = $tokens->getNextMeaningfulToken($searchIndex)) !== null) {
                if ($tokens[$searchIndex]->isGivenKind(T_CLASS)) {
                    $classTokenIndex = $searchIndex;

                    break;
                }

                // Skip modifiers like readonly, final, etc.
                if (!$tokens[$searchIndex]->isGivenKind([T_READONLY, T_FINAL])) {
                    break; // If we hit something else, stop looking
                }
            }

            if ($classTokenIndex === null) {
                continue;
            }

            // Find the class name after T_CLASS
            $classNameIndex = $tokens->getNextMeaningfulToken($classTokenIndex);
            $classNameToken = $tokens[$classNameIndex]->getContent();

            if (str_ends_with($classNameToken, self::SUFFIX)) {
                continue;
            }

            $tokens[$classNameIndex] = new Token([T_STRING, $classNameToken.self::SUFFIX]);
        }
    }
}
