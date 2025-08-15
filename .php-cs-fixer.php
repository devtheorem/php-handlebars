<?php

$finder = PhpCsFixer\Finder::create()
    ->path([
        'src/',
        'tests/',
    ])
    ->ignoreVCSIgnored(true)
    ->append([__FILE__])
    ->in(__DIR__);

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        '@PER-CS' => true,
        'align_multiline_comment' => true,
        'binary_operator_spaces' => true,
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'class_reference_name_casing' => true,
        'clean_namespace' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'declare_parentheses' => true,
        'integer_literal_case' => true,
        'lambda_not_used_import' => true,
        'linebreak_after_opening_tag' => true,
        'method_chaining_indentation' => true,
        'multiline_comment_opening_closing' => true,
        'native_function_casing' => true,
        'no_alternative_syntax' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_spaces_around_offset' => true,
        'no_superfluous_phpdoc_tags' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unused_imports' => true,
        'no_useless_concat_operator' => true,
        'no_useless_return' => true,
        'no_whitespace_before_comma_in_array' => true,
        'object_operator_without_whitespace' => true,
        'ordered_class_elements' => ['order' => ['use_trait', 'case', 'constant', 'property', 'method']],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_indent' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_order' => true,
        'phpdoc_param_order' => true,
        'phpdoc_single_line_var_spacing' => true,
        'return_assignment' => true,
        'semicolon_after_instruction' => true,
        'space_after_semicolon' => true,
        'standardize_not_equals' => true,
        'trim_array_spaces' => true,
        'type_declaration_spaces' => true,
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
    ])
    ->setFinder($finder);
