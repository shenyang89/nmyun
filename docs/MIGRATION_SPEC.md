# 数据库迁移规范（Migration Spec）

## 命令速查

```bash
# 创建迁移文件（文件名自动加时间戳前缀）
php think migrate:create CreateTenantTable

# 执行所有待迁移
php think migrate:run

# 回滚最后一次迁移
php think migrate:rollback

# 查看迁移状态
php think migrate:status

# 回滚所有迁移（危险！）
php think migrate:reset
```

## 文件命名规范

```
database/migrations/YYYYMMDDHHMMSS_description.php
```

- 时间戳自动生成，**禁止手工修改**
- `description` 使用 **PascalCase**，动词开头：`CreateXxxTable` / `AddFieldToXxx` / `DropXxxTable`
- 示例：`20260821060000_CreateTenantTable.php`

## 编写规范

### 强制规则

1. **每个迁移文件只做一件事**：一张表的创建/一个字段的添加/一个索引的变更
2. **所有业务表必须包含 `tenant_id` 字段**（SaaS 多租户隔离红线，详见 PROJECT_MEMORY.md §13.8.1）
3. **表名加 `nmyun_` 前缀**（已在 .env `DB_PREFIX=nmyun_` 配置，迁移中用 `$table->name('xxx')` 不含前缀）
4. **字段必须写注释**（`->comment('xxx')`），枚举字段注释列出可选值
5. **必须有 `up()` 和 `down()` 两个方法**，down 必须能完整回滚
6. **主键用 `bigInteger('id')->primaryKey()`**（PostgreSQL SERIAL/BIGSERIAL 等价）
7. **时间戳字段**：`created_at` + `updated_at`（ThinkORM auto_timestamp 自动写入），用 `timestamp()` 类型
8. **索引命名**：ThinkORM 自动生成，无需手工命名

### 示例

```php
<?php

use think\migration\Migrator;

class CreateTenantTable extends Migrator
{
    public function up()
    {
        $table = $this->table('tenant', ['comment' => 'SaaS 租户表（市场）']);
        $table->addColumn('big_integer', 'id', ['identity' => true])
              ->addColumn('string', 'name', ['limit' => 100, 'comment' => '市场名称'])
              ->addColumn('string', 'code', ['limit' => 32, 'comment' => '市场编码（唯一）'])
              ->addColumn('integer', 'status', ['default' => 0, 'comment' => '状态：0待审核 1启用 2禁用 3锁定 4欠费 5审核不通过 6预删除'])
              ->addColumn('timestamp', 'expired_at', ['null' => true, 'comment' => '套餐到期时间'])
              ->addColumn('timestamp', 'created_at', ['null' => true])
              ->addColumn('timestamp', 'updated_at', ['null' => true])
              ->addIndex(['code'], ['unique' => true])
              ->create();
    }

    public function down()
    {
        $this->table('tenant')->drop()->save();
    }
}
```

### 多租户字段规范

所有业务表（非平台级共享表如 `tenant_plan` / `device_product`）必须包含：

```php
->addColumn('big_integer', 'tenant_id', ['comment' => '租户ID（SaaS 隔离）'])
->addIndex(['tenant_id'])  // 必须加索引
->addIndex(['tenant_id', 'merchant_no'])  // 联合唯一索引
```

## 禁止事项

- 禁止在生产环境直接跑 `migrate:reset`
- 禁止修改已执行的迁移文件（已迁移的文件不可变更，新建迁移修正）
- 禁止在迁移中写业务逻辑（只做 DDL）
- 禁止跳过 `down()` 方法
