<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\PhpCsFixer\Fixer;

use Illuminate\Support\Str;
use Override;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

use const T_STRING_VARNAME;
use const T_VARIABLE;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class VariableCaseFixer extends AbstractFixer implements ConfigurableFixerInterface
{
    use ConfigurableFixerTrait;

    #[Override()]
    public function getName(): string
    {
        return 'Architecture/variable_case';
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([T_VARIABLE, T_STRING_VARNAME]);
    }

    #[Override()]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(summary: 'Enforce specific casing for variable names, following configuration.', codeSamples: []);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override()]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        if ($this->configuration === null) {
            return;
        }

        $caseMethod = $this->configuration['case'];

        foreach ($tokens as $index => $token) {
            if (($token->getId() !== T_VARIABLE) && ($token->getId() !== T_STRING_VARNAME)) {
                continue;
            }

            // @phpstan-ignore-next-line
            $tokens[$index] = new Token([$token->getId(), Str::$caseMethod($token->getContent())]);
        }
    }

    #[Override()]
    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            new FixerOptionBuilder(name: 'case', description: 'Apply various types of string casing to variables.')
                ->setAllowedValues(['camel', 'kebab', 'snake', 'studly', 'title'])
                ->setDefault('camel')
                ->getOption(),
        ]);
    }
}
