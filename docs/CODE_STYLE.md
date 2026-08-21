# 代码规范（Code Style）

## 工具清单

| 工具 | 用途 | 命令 | 配置文件 |
|------|------|------|---------|
| PHP-CS-Fixer 3.x | 自动代码格式化（PSR-12 + 项目规则） | `vendor/bin/php-cs-fixer fix` | `.php-cs-fixer.dist.php` |
| PHPStan 2.x | 静态类型分析（Level 5 起步） | `vendor/bin/phpstan analyse` | `phpstan.neon` |

## 常用命令

```bash
# 检查代码规范（不修改文件，CI 用）
vendor/bin/php-cs-fixer fix --dry-run --allow-unsupported-php-version=true

# 自动修复代码格式
vendor/bin/php-cs-fixer fix --allow-unsupported-php-version=true

# 静态分析检查
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

## PSR-12 + 项目规则

基于 `@PSR12`，额外启用：

- `declare_strict_types` — 每个文件必须声明严格类型
- `strict_comparison` — 必须 `===` / `!==`
- `single_quote` — 字符串用单引号
- `no_unused_imports` — 禁止未使用的 use 语句
- `ordered_imports` — use 语句按字母排序
- `array_syntax` — 短数组语法 `[]`
- `no_useless_else` / `no_useless_return` — 移除无用的 else/return

## PHPStan 规则

- 当前 Level 5，后续逐步提升到 7
- 已加载 ThinkPHP helper.php 识别 `env()` / `config()` 等全局函数
- `env()` 函数的参数类型不匹配已全局忽略（框架级问题）

## CI 集成

Travis CI 在 `script` 阶段依次执行：
1. `php-cs-fixer fix --dry-run` — 格式不通过即 red
2. `phpstan analyse` — 类型错误即 red
3. `php think unit` — 单元测试

**PR 不通过以上 3 步无法合并。**
