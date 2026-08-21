<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * SaaS 租户账单/发票表（带 tenant_id）
 * 三合一账单：SaaS 订阅费 + 交易佣金 + IoT 硬件服务费（见 §2.5）
 * 账单生成由 Think Command `invoice:generate YYYY-MM` 触发，写入明细行
 */
class CreateTenantInvoiceTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('tenant_invoice', ['id' => false, 'primary_key' => 'id', 'comment' => 'SaaS 租户账单/发票表']);
        $table->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('tenant_id', 'biginteger', ['comment' => '租户ID（SaaS 隔离）'])
            ->addColumn('plan_id', 'biginteger', ['comment' => '套餐ID（关联 tenant_plan）'])
            ->addColumn('billing_period', 'string', ['limit' => 7, 'comment' => '账期 YYYY-MM'])
            ->addColumn('invoice_no', 'string', ['limit' => 32, 'comment' => '账单号（租户内唯一）'])
            ->addColumn('saas_fee', 'integer', ['default' => 0, 'comment' => 'SaaS 订阅费（分）'])
            ->addColumn('commission_fee', 'integer', ['default' => 0, 'comment' => '交易佣金（分）'])
            ->addColumn('iot_service_fee', 'integer', ['default' => 0, 'comment' => 'IoT 硬件服务费（分）'])
            ->addColumn('total_amount', 'integer', ['default' => 0, 'comment' => '总金额（分）= saas + commission + iot'])
            ->addColumn('paid_amount', 'integer', ['default' => 0, 'comment' => '已付金额（分）'])
            ->addColumn('status', 'smallinteger', ['default' => 0, 'comment' => '状态：0未付 1已付 2部分付 3已退款 4已逾期'])
            ->addColumn('paid_at', 'timestamp', ['null' => true, 'comment' => '支付完成时间'])
            ->addColumn('due_at', 'timestamp', ['null' => true, 'comment' => '到期时间'])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['tenant_id', 'billing_period'], ['unique' => true])
            ->addIndex(['tenant_id', 'invoice_no'], ['unique' => true])
            ->addIndex(['tenant_id', 'status'])
            ->create();
    }
}
