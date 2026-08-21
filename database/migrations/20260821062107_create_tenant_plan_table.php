<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * SaaS 套餐表（平台级，无 tenant_id）
 * 定义三种套餐：基础版 / 专业版 / 旗舰版
 * - 价格、配额、功能清单、费率模型全部走 JSONB features 字段配置（见 §2.9 三合一费率模型）
 * - 修改套餐配置无需改代码，账单生成时读取此表
 */
class CreateTenantPlanTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('tenant_plan', ['id' => false, 'primary_key' => 'id', 'comment' => 'SaaS 套餐表（平台级）']);
        $table->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('plan_code', 'string', ['limit' => 32, 'comment' => '套餐编码（全局唯一 basic/pro/premium）'])
            ->addColumn('plan_name', 'string', ['limit' => 64, 'comment' => '套餐名称 基础版/专业版/旗舰版'])
            ->addColumn('tier', 'smallinteger', ['default' => 1, 'comment' => '等级：1基础 2专业 3旗舰'])
            ->addColumn('price_monthly', 'integer', ['default' => 0, 'comment' => '月费（分）'])
            ->addColumn('price_yearly', 'integer', ['default' => 0, 'comment' => '年费（分）'])
            ->addColumn('max_devices', 'integer', ['default' => 0, 'comment' => 'IoT 设备数上限'])
            ->addColumn('max_merchants', 'integer', ['default' => 0, 'comment' => '商户数上限'])
            ->addColumn('max_storage_days', 'integer', ['default' => 7, 'comment' => '云存储天数上限'])
            ->addColumn('features', 'jsonb', ['null' => true, 'comment' => '功能清单 + 三合一费率模型 JSON：saas_fee / iot_overage_tiers / commission_tiers'])
            ->addColumn('status', 'smallinteger', ['default' => 1, 'comment' => '状态：0草稿 1启用 2停用'])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['plan_code'], ['unique' => true])
            ->addIndex(['tier', 'status'])
            ->create();
    }
}
