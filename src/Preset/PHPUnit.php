<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\PhpCsFixer\Preset;

use Override;
use PhpCsFixerCustomFixers\Fixer\PhpUnitAssertArgumentsOrderFixer;
use PhpCsFixerCustomFixers\Fixer\PhpUnitDedicatedAssertFixer;
use PhpCsFixerCustomFixers\Fixer\PhpUnitNoUselessReturnFixer;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.0
 */
final class PHPUnit implements PresetInterface
{
    #[Override()]
    public function name(): string
    {
        return 'PHPUnit';
    }

    #[Override()]
    public function rules(): array
    {
        return [
            'php_unit_data_provider_method_order' => [
                'placement' => 'after',
            ],
            'php_unit_data_provider_name' => true,
            'php_unit_data_provider_return_type' => true,
            'php_unit_data_provider_static' => true,
            'php_unit_dedicate_assert' => [
                'target' => 'newest',
            ],
            'php_unit_dedicate_assert_internal_type' => [
                'target' => 'newest',
            ],
            'php_unit_expectation' => [
                'target' => 'newest',
            ],
            'php_unit_fqcn_annotation' => true,
            'php_unit_internal_class' => [
                'types' => [
                    'abstract',
                    'final',
                    'normal',
                ],
            ],
            'php_unit_method_casing' => [
                'case' => 'snake_case',
            ],
            'php_unit_mock' => [
                'target' => 'newest',
            ],
            'php_unit_mock_short_will_return' => true,
            'php_unit_namespaced' => [
                'target' => 'newest',
            ],
            'php_unit_no_expectation_annotation' => [
                'target' => 'newest',
                'use_class_const' => true,
            ],
            'php_unit_set_up_tear_down_visibility' => true,
            'php_unit_size_class' => false,
            'php_unit_strict' => false,
            'php_unit_test_annotation' => [
                'style' => 'prefix',
            ],
            'php_unit_test_case_static_method_calls' => [
                'call_type' => 'self',
                'methods' => [],
            ],
            // This messes with using the `PHPUnit\Framework\Attributes\CoversNothing` attribute
            'php_unit_test_class_requires_covers' => false,
            PhpUnitAssertArgumentsOrderFixer::name() => true,
            PhpUnitDedicatedAssertFixer::name() => true,
            PhpUnitNoUselessReturnFixer::name() => true,
        ];
    }

    #[Override()]
    public function targetPhpVersion(): int
    {
        return 80_200;
    }
}
