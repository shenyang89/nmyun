<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * 批发商户 / 档口表（merchant_id）
 * 五大核心实体中的「批发商户/档口」
 * 带 tenant_id 字段用于多租户行级隔离，作为 BaseModel 全局 Scope 的示范业务表。
 */
class CreateMerchantTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('merchant', ['id' => false, 'primary_key' => 'id', 'comment' => '批发商户/档口表']);
        $table->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('tenant_id', 'biginteger', ['comment' => '所属租户ID（SaaS 行级隔离）'])
            ->addColumn('merchant_no', 'string', ['limit' => 32, 'comment' => '商户编号（市场内唯一）'])
            ->addColumn('name', 'string', ['limit' => 64, 'comment' => '档口/商户名称'])
            ->addColumn('owner_name', 'string', ['limit' => 32, 'null' => true, 'comment' => '老板姓名'])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'comment' => '联系电话'])
            ->addColumn('stall_no', 'string', ['limit' => 16, 'null' => true, 'comment' => '档口号 A-105'])
            ->addColumn('status', 'smallinteger', ['default' => 1, 'comment' => '状态：0禁用 1正常'])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['tenant_id', 'merchant_no'], ['unique' => true])
            ->addIndex(['tenant_id', 'status'])
            ->create();
    }
}
