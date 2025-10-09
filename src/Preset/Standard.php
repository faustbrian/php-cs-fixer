<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\PhpCsFixer\Preset;

use Override;
use PhpCsFixerCustomFixers\Fixer\DeclareAfterOpeningTagFixer;
use PhpCsFixerCustomFixers\Fixer\EmptyFunctionBodyFixer;
use PhpCsFixerCustomFixers\Fixer\IssetToArrayKeyExistsFixer;
use PhpCsFixerCustomFixers\Fixer\MultilineCommentOpeningClosingAloneFixer;
use PhpCsFixerCustomFixers\Fixer\MultilinePromotedPropertiesFixer;
use PhpCsFixerCustomFixers\Fixer\NoDuplicatedArrayKeyFixer;
use PhpCsFixerCustomFixers\Fixer\NoDuplicatedImportsFixer;
use PhpCsFixerCustomFixers\Fixer\NoImportFromGlobalNamespaceFixer;
use PhpCsFixerCustomFixers\Fixer\NoNullableBooleanTypeFixer;
use PhpCsFixerCustomFixers\Fixer\NoPhpStormGeneratedCommentFixer;
use PhpCsFixerCustomFixers\Fixer\NoReferenceInFunctionDefinitionFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessParenthesisFixer;
use PhpCsFixerCustomFixers\Fixer\NoUselessStrlenFixer;
use PhpCsFixerCustomFixers\Fixer\PromotedConstructorPropertyFixer;
use PhpCsFixerCustomFixers\Fixer\ReadonlyPromotedPropertiesFixer;
use PhpCsFixerCustomFixers\Fixer\StringableInterfaceFixer;
use PhpCsFixerCustomFixers\Fixer\TypedClassConstantFixer;

use function mb_trim;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @version 1.0.2
 */
final class Standard implements PresetInterface
{
    #[Override()]
    public function name(): string
    {
        return 'Standard (PHP 8.1)';
    }

    #[Override()]
    public function rules(): array
    {
        $header = <<<'EOF'
Copyright (C) Brian Faust

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

        return [
            ...new Ordered()->rules(),
            ...new PHPDoc()->rules(),
            ...new PHPUnit()->rules(),
            'array_indentation' => true,
            'array_push' => true,
            'array_syntax' => ['syntax' => 'short'],
            'assign_null_coalescing_to_coalesce_equal' => true,
            'attribute_empty_parentheses' => [
                'use_parentheses' => true,
            ],
            'backtick_to_shell_exec' => true,
            'binary_operator_spaces' => [
                'default' => 'single_space',
                'operators' => [],
            ],
            'blank_line_after_namespace' => true,
            'blank_line_after_opening_tag' => true,
            'blank_line_before_statement' => [
                'statements' => [
                    'break',
                    'case',
                    'continue',
                    'declare',
                    'default',
                    'do',
                    'exit',
                    'for',
                    'foreach',
                    'goto',
                    'if',
                    'include',
                    'include_once',
                    'phpdoc',
                    'require',
                    'require_once',
                    'return',
                    'switch',
                    'throw',
                    'try',
                    'while',
                    'yield',
                    'yield_from',
                ],
            ],
            'blank_line_between_import_groups' => true,
            'blank_lines_before_namespace' => true,
            'control_structure_braces' => true,
            'control_structure_continuation_position' => [
                'position' => 'same_line',
            ],
            'braces_position' => [
                'allow_single_line_anonymous_functions' => false,
                'allow_single_line_empty_anonymous_classes' => false,
                'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
                'anonymous_functions_opening_brace' => 'same_line',
                'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
                'control_structures_opening_brace' => 'same_line',
                'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            ],
            'cast_spaces' => true,
            'class_attributes_separation' => [
                'elements' => [
                    'const' => 'one',
                    'method' => 'one',
                    'property' => 'one',
                    'trait_import' => 'none',
                ],
            ],
            'class_definition' => [
                'inline_constructor_arguments' => true,
                'multi_line_extends_each_single_line' => false,
                'single_item_single_line' => false,
                'single_line' => false,
                'space_before_parenthesis' => false,
            ],
            'class_reference_name_casing' => true,
            'clean_namespace' => true,
            'combine_consecutive_issets' => true,
            'combine_consecutive_unsets' => true,
            'combine_nested_dirname' => true,
            'comment_to_phpdoc' => [
                'ignored_tags' => [],
            ],
            'compact_nullable_type_declaration' => true,
            'concat_space' => ['spacing' => 'none'],
            'constant_case' => ['case' => 'lower'],
            'date_time_create_from_format_call' => true,
            'date_time_immutable' => false, // Otherwise fixer always changes \DateTime to CarbonInterface even for conversion and casting code
            'declare_equal_normalize' => true,
            'declare_parentheses' => true,
            'declare_strict_types' => true,
            'dir_constant' => true,
            'echo_tag_syntax' => [
                'format' => 'long',
                'long_function' => 'echo',
                'shorten_simple_statements_only' => true,
            ],
            'elseif' => true,
            'empty_loop_body' => ['style' => 'braces'],
            'empty_loop_condition' => ['style' => 'while'],
            'encoding' => true,
            'ereg_to_preg' => true,
            'error_suppression' => [
                'mute_deprecation_error' => true,
                'noise_remaining_usages' => true,
                'noise_remaining_usages_exclude' => [],
            ],
            'escape_implicit_backslashes' => [
                'double_quoted' => true,
                'heredoc_syntax' => true,
                'single_quoted' => false,
            ],
            'explicit_indirect_variable' => true,
            'explicit_string_variable' => true,
            'final_class' => true,
            'final_internal_class' => [
                'exclude' => [
                    '@Entity',
                    '@final',
                    '@Mapping\\Entity',
                    '@ORM\\Entity',
                    '@ORM\\Mapping\\Entity',
                ],
                'include' => [
                    '@internal',
                ],
                'consider_absent_docblock_as_internal_class' => false,
            ],
            'final_public_method_for_abstract_class' => false,
            'fopen_flag_order' => true,
            'fopen_flags' => ['b_mode' => true],
            'full_opening_tag' => true,
            'fully_qualified_strict_types' => [
                'leading_backslash_in_global_namespace' => false,
            ],
            'function_declaration' => true,
            'function_to_constant' => [
                'functions' => [
                    'get_called_class',
                    'get_class',
                    'php_sapi_name',
                    'phpversion',
                    'pi',
                ],
            ],
            'general_phpdoc_annotation_remove' => false,
            'general_phpdoc_tag_rename' => true,
            'get_class_to_class_keyword' => true,
            'global_namespace_import' => [
                'import_classes' => true,
                'import_constants' => true,
                'import_functions' => true,
            ],
            'group_import' => false,
            'header_comment' => [
                'comment_type' => 'PHPDoc',
                'header' => mb_trim($header),
                'location' => 'after_declare_strict',
                'separate' => 'both',
            ],
            'heredoc_indentation' => false,
            'heredoc_to_nowdoc' => true,
            'implode_call' => true,
            'include' => true,
            'increment_style' => ['style' => 'pre'],
            'indentation_type' => true,
            'integer_literal_case' => true,
            'is_null' => true,
            'lambda_not_used_import' => true,
            'line_ending' => true,
            'linebreak_after_opening_tag' => true,
            'list_syntax' => true,
            'logical_operators' => true,
            'lowercase_cast' => true,
            'lowercase_keywords' => true,
            'lowercase_static_reference' => true,
            'magic_constant_casing' => true,
            'magic_method_casing' => true,
            'mb_str_functions' => true,
            'method_argument_space' => [
                'after_heredoc' => false,
                'keep_multiple_spaces_after_comma' => false,
                'on_multiline' => 'ensure_fully_multiline',
            ],
            'method_chaining_indentation' => true,
            'modernize_strpos' => true,
            'modernize_types_casting' => true,
            'multiline_comment_opening_closing' => true,
            'multiline_promoted_properties' => true,
            'multiline_whitespace_before_semicolons' => [
                'strategy' => 'no_multi_line',
            ],
            'native_constant_invocation' => [
                'exclude' => [
                    'false',
                    'null',
                    'true',
                ],
                'fix_built_in' => true,
                'include' => [],
                'scope' => 'all',
                'strict' => false,
            ],
            'native_function_casing' => true,
            'native_function_invocation' => [
                'exclude' => [],
                'include' => [
                    '@all',
                ],
                'scope' => 'namespaced',
                'strict' => true,
            ],
            'native_type_declaration_casing' => true,
            'new_expression_parentheses' => true,
            'new_with_parentheses' => [
                'anonymous_class' => true,
                'named_class' => true,
            ],
            'no_alias_functions' => true,
            'no_alias_language_construct_call' => true,
            'no_alternative_syntax' => true,
            'no_binary_string' => true,
            'no_blank_lines_after_class_opening' => true,
            'no_blank_lines_after_phpdoc' => true,
            'no_break_comment' => [
                'comment_text' => 'no break',
            ],
            'no_closing_tag' => true,
            'no_empty_comment' => false,
            'no_empty_phpdoc' => true,
            'no_empty_statement' => true,
            'no_extra_blank_lines' => [
                'tokens' => [
                    'attribute',
                    'break',
                    'case',
                    'comma',
                    'continue',
                    'curly_brace_block',
                    'default',
                    'extra',
                    'parenthesis_brace_block',
                    'return',
                    'square_brace_block',
                    'switch',
                    'throw',
                    'use',
                    'use_trait',
                ],
            ],
            'no_homoglyph_names' => true,
            'no_leading_import_slash' => true,
            'no_leading_namespace_whitespace' => true,
            'no_mixed_echo_print' => [
                'use' => 'echo',
            ],
            'no_multiline_whitespace_around_double_arrow' => true,
            'no_multiple_statements_per_line' => true,
            'no_null_property_initialization' => true,
            'no_php4_constructor' => false,
            'no_short_bool_cast' => true,
            'no_singleline_whitespace_before_semicolons' => true,
            'no_spaces_after_function_name' => true,
            'no_space_around_double_colon' => true,
            'no_spaces_around_offset' => [
                'positions' => [
                    'inside',
                    'outside',
                ],
            ],
            'no_superfluous_phpdoc_tags' => [
                'allow_mixed' => true,
                'allow_unused_params' => true,
                'remove_inheritdoc' => false,
            ],
            'no_trailing_comma_in_singleline' => true,
            'no_trailing_whitespace' => true,
            'no_trailing_whitespace_in_comment' => true,
            'no_trailing_whitespace_in_string' => true,
            'no_unneeded_braces' => [
                'namespaces' => true,
            ],
            'no_unneeded_control_parentheses' => [
                'statements' => [
                    'break',
                    'clone',
                    'continue',
                    'echo_print',
                    'negative_instanceof',
                    'others',
                    'return',
                    'switch_case',
                    'yield',
                    'yield_from',
                ],
            ],
            'no_unneeded_final_method' => true,
            'no_unneeded_import_alias' => true,
            'no_unreachable_default_argument_value' => true,
            'no_unset_cast' => true,
            'no_unset_on_property' => true,
            // Disabled due to PCRE2 pattern issues in PhpCsFixer\Fixer\Import\NoUnusedImportsFixer on PHP 8.4/PCRE2 10.44
            'no_unused_imports' => false,
            'no_useless_concat_operator' => true,
            'no_useless_return' => true,
            'no_whitespace_before_comma_in_array' => true,
            'no_whitespace_in_blank_line' => true,
            'non_printable_character' => [
                'use_escape_sequences_in_strings' => false,
            ],
            'normalize_index_brace' => true,
            'not_operator_with_space' => false,
            'not_operator_with_successor_space' => false,
            'nullable_type_declaration' => true,
            'nullable_type_declaration_for_default_null_value' => [
                'use_nullable_type_declaration' => true,
            ],
            'object_operator_without_whitespace' => true,
            'octal_notation' => true,
            'operator_linebreak' => [
                'only_booleans' => true,
                'position' => 'beginning',
            ],
            'psr_autoloading' => true,
            'random_api_migration' => [
                'replacements' => [
                    'getrandmax' => 'mt_getrandmax',
                    'rand' => 'mt_rand',
                    'srand' => 'mt_srand',
                ],
            ],
            'regular_callable_call' => true,
            'return_assignment' => true,
            'return_to_yield_from' => true,
            'return_type_declaration' => ['space_before' => 'none'],
            'self_accessor' => true,
            'self_static_accessor' => true,
            'semicolon_after_instruction' => true,
            'set_type_to_cast' => true,
            'short_scalar_cast' => true,
            'simple_to_complex_string_variable' => true,
            'simplified_if_return' => false,
            'simplified_null_return' => false,
            'single_blank_line_at_eof' => true,
            'single_class_element_per_statement' => [
                'elements' => [
                    'const',
                    'property',
                ],
            ],
            'single_import_per_statement' => true,
            'single_line_after_imports' => true,
            'single_line_comment_spacing' => true,
            'single_line_comment_style' => [
                'comment_types' => ['hash'],
            ],
            'single_line_empty_body' => true,
            'single_line_throw' => false,
            'single_quote' => true,
            'single_space_around_construct' => true,
            'single_trait_insert_per_statement' => true,
            'space_after_semicolon' => [
                'remove_in_empty_for_expressions' => false,
            ],
            'spaces_inside_parentheses' => [
                'space' => 'none',
            ],
            'standardize_increment' => true,
            'standardize_not_equals' => true,
            'statement_indentation' => true,
            'static_lambda' => false,
            'static_private_method' => true,
            'strict_comparison' => true,
            'strict_param' => true,
            'string_length_to_empty' => true,
            'string_line_ending' => true,
            'switch_case_semicolon_to_colon' => true,
            'switch_case_space' => true,
            'switch_continue_to_break' => true,
            'ternary_operator_spaces' => true,
            'ternary_to_elvis_operator' => true,
            'ternary_to_null_coalescing' => true,
            'trailing_comma_in_multiline' => [
                'after_heredoc' => false,
                'elements' => [
                    'arguments',
                    'arrays',
                    'match',
                    'parameters',
                ],
            ],
            'trim_array_spaces' => true,
            'type_declaration_spaces' => [
                'elements' => [
                    'constant',
                    'function',
                    'property',
                ],
            ],
            'types_spaces' => true,
            'unary_operator_spaces' => true,
            'visibility_required' => true,
            'void_return' => true,
            'whitespace_after_comma_in_array' => true,
            'numeric_literal_separator' => true,
            // 'Architecture/abstract_name_fixer' => true,
            // 'Architecture/exception_name_fixer' => true,
            // 'Architecture/interface_name_fixer' => true,
            // 'Architecture/trait_name_fixer' => true,
            'Architecture/author_tag_fixer' => true,
            'Architecture/import_fqcn_in_new_fixer' => true,
            'Architecture/import_fqcn_in_attribute_fixer' => true,
            'Architecture/import_fqcn_in_static_call_fixer' => true,
            'Architecture/import_fqcn_in_property_fixer' => true,
            'Architecture/new_argument_newline_fixer' => true,
            // 'Architecture/version_tag_fixer' => true,
            'Architecture/namespace_fixer' => true,
            'Architecture/duplicate_docblock_after_attributes_fixer' => true,
            'Architecture/redundant_readonly_property_fixer' => true,
            'Architecture/psalm_immutable_on_readonly_class_fixer' => true,
            // 'Architecture/final_readonly_class_fixer' => true,
            DeclareAfterOpeningTagFixer::name() => true,
            EmptyFunctionBodyFixer::name() => true,
            IssetToArrayKeyExistsFixer::name() => true,
            MultilineCommentOpeningClosingAloneFixer::name() => true,
            MultilinePromotedPropertiesFixer::name() => true,
            NoDuplicatedArrayKeyFixer::name() => true,
            NoDuplicatedImportsFixer::name() => true,
            NoImportFromGlobalNamespaceFixer::name() => true,
            NoNullableBooleanTypeFixer::name() => true,
            NoPhpStormGeneratedCommentFixer::name() => true,
            NoReferenceInFunctionDefinitionFixer::name() => true,
            NoUselessParenthesisFixer::name() => true,
            NoUselessStrlenFixer::name() => true,
            PromotedConstructorPropertyFixer::name() => true,
            ReadonlyPromotedPropertiesFixer::name() => true,
            StringableInterfaceFixer::name() => true,
            TypedClassConstantFixer::name() => true,
            'ErickSkrauch/line_break_after_statements' => true,
            'ErickSkrauch/multiline_if_statement_braces' => true,
            NoImportFromGlobalNamespaceFixer::name() => false,
        ];
    }

    #[Override()]
    public function targetPhpVersion(): int
    {
        return 80_400;
    }
}
