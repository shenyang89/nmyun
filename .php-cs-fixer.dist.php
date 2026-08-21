<?php

/**
 * 智慧农贸云（nmyun）PHP-CS-Fixer 配置
 * 使用：vendor/bin/php-cs-fixer fix（自动修复）或 --dry-run（仅检查）
 */

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/route')
    ->notPath('cache')
    ->notPath('log')
    ->exclude(['runtime', 'vendor', 'public', 'docs']);

return (new PhpCsFixer\Config())
    ->setRules([
        // PSR-12 基础
        '@PSR12'                               => true,
        // 数组语法：短数组语法 []
        'array_syntax'                         => ['syntax' => 'short'],
        // 严格比较 ===
        'strict_comparison'                    => true,
        // 严格参数类型声明
        'strict_param'                         => true,
        // 声明类型 -> 声明严格类型
        'declare_strict_types'                 => true,
        // 移除无用的括号
        'no_useless_else'                      => true,
        'no_useless_return'                    => true,
        // 类/方法/常量必须有可见性修饰符
        'visibility_required'                 => true,
        // 使用 import 而非 \ 全限定名
        'no_unused_imports'                    => true,
        'ordered_imports'                      => ['sort_algorithm' => 'alpha'],
        // 函数/方法参数：有默认值的放最后
        'no_trailing_whitespace'               => true,
        'no_trailing_whitespace_in_comment'    => true,
        // 单引号
        'single_quote'                         => true,
        // 类常量可见性（PSR-12 已包含，显式声明确保生效）
        // 移除 class_constant_visibility（已废弃名称）
        // 方法链调用每行一个
        'phpdoc_align'                         => true,
        // 行尾分号
        'semicolon_after_instruction'          => true,
        // 数组对齐
        'binary_operator_spaces'               => true,
        // 空行规范化
        'blank_line_after_opening_tag'         => true,
        'blank_line_between_import_groups'     => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
