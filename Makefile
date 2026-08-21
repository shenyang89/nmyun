# 智慧农贸云（nmyun）Makefile
# 使用：make help 查看所有命令

.PHONY: help up down logs shell test cs cs-check stan migrate seed clear

# PHP 相关
PHP   = php
THINK = php think
PEST  = vendor/bin/pest
CS    = vendor/bin/php-cs-fixer
STAN  = vendor/bin/phpstan

# Docker
COMPOSE = docker compose

help: ## 显示所有可用命令
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

# ===================== Docker 环境 =====================

up: ## 启动全部 Docker 基础设施（pg+redis+emqx+zlm）
	$(COMPOSE) up -d

down: ## 停止 Docker 基础设施
	$(COMPOSE) down

logs: ## 查看 Docker 日志（tail -f）
	$(COMPOSE) logs -f --tail=100

shell: ## 进入 PHP 容器 shell（如有）
	$(COMPOSE) exec php-fpm sh

# ===================== 开发服务器 =====================

run: ## 启动 ThinkPHP 开发服务器（localhost:8000）
	$(THINK) run --host=0.0.0.0 --port=8000

# ===================== 代码质量 =====================

cs: ## 自动修复代码格式（php-cs-fixer fix）
	$(CS) fix --allow-unsupported-php-version=true

cs-check: ## 检查代码格式（dry-run，不修改，CI 用）
	$(CS) fix --dry-run --allow-unsupported-php-version=true

stan: ## 静态类型分析（PHPStan Level 5）
	$(STAN) analyse --no-progress --memory-limit=512M

check: cs-check stan ## 运行全部代码质量检查（cs-check + stan）

# ===================== 测试 =====================

test: ## 运行全部单元测试（Pest）
	$(PEST)

test-unit: ## 只运行 Unit 测试
	$(PEST) --testsuite=Unit

test-coverage: ## 运行测试并生成覆盖率报告
	$(PEST) --coverage-html coverage

# ===================== 数据库迁移 =====================

migrate: ## 执行数据库迁移
	$(THINK) migrate:run

migrate-rollback: ## 回滚最后一次迁移
	$(THINK) migrate:rollback

migrate-status: ## 查看迁移状态
	$(THINK) migrate:status

migrate-create: ## 创建新迁移（用法：make migrate-create name=CreateTenantTable）
	$(THINK) migrate:create $(name)

# ===================== 清理 =====================

clear: ## 清理 ThinkPHP 运行时缓存
	rm -rf runtime/cache/*
	rm -rf runtime/log/*
	rm -rf runtime/temp/*
	@echo "Runtime cache cleared."
