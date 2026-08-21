<?php

declare(strict_types=1);

namespace app\model;

/**
 * SaaS 租户账单/发票模型（带 tenant_id）
 *
 * 对应 nmyun_tenant_invoice 表（迁移脚本 20260821062110）
 * 三合一账单：SaaS 订阅费 + 交易佣金 + IoT 硬件服务费
 *
 * 注意：tenant_invoice 表带 tenant_id，但平台超管跨租户查看账单时
 * 需要绕过租户隔离 → 由 Repository 显式 withoutScope 处理
 */
class TenantInvoice extends BaseModel
{
    protected $name = 'tenant_invoice';

    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /** 状态枚举 */
    public const STATUS_UNPAID = 0;
    public const STATUS_PAID = 1;
    public const STATUS_PARTIAL = 2;
    public const STATUS_REFUNDED = 3;
    public const STATUS_OVERDUE = 4;

    protected $type = [
        'tenant_id' => 'integer',
        'plan_id' => 'integer',
        'saas_fee' => 'integer',
        'commission_fee' => 'integer',
        'iot_service_fee' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'status' => 'integer',
    ];

    public function allowSearchFields(): array
    {
        return ['id', 'tenant_id', 'plan_id', 'billing_period', 'status', 'invoice_no'];
    }

    public function allowCreateFields(): array
    {
        return [
            'tenant_id', 'plan_id', 'billing_period', 'invoice_no',
            'saas_fee', 'commission_fee', 'iot_service_fee', 'total_amount',
            'paid_amount', 'status', 'paid_at', 'due_at',
        ];
    }

    public function allowUpdateFields(): array
    {
        return [
            'paid_amount', 'status', 'paid_at',
        ];
    }

    /**
     * 关联套餐
     */
    public function plan()
    {
        return $this->belongsTo(TenantPlan::class, 'plan_id', 'id');
    }
}
