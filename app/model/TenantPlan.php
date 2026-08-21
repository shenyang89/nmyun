<?php

declare(strict_types=1);

namespace app\model;

/**
 * SaaS 套餐模型（平台级，无 tenant_id）
 *
 * 对应 nmyun_tenant_plan 表（迁移脚本 20260821062107）
 * 三种套餐：basic / pro / premium，价格、配额、费率模型走 features JSONB
 */
class TenantPlan extends BaseModel
{
    protected $name = 'tenant_plan';

    /** 平台级表无 tenant_id 字段，关闭全局 Scope */
    protected bool $enableTenantScope = false;

    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /** 状态枚举 */
    public const STATUS_DRAFT = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_DISABLED = 2;

    /** 套餐等级 */
    public const TIER_BASIC = 1;
    public const TIER_PRO = 2;
    public const TIER_PREMIUM = 3;

    /**
     * 字段类型转换
     * - features: jsonb 字段，ThinkORM 用 'json' 类型自动 encode/decode
     */
    protected $type = [
        'features' => 'json',
        'tier' => 'integer',
        'price_monthly' => 'integer',
        'price_yearly' => 'integer',
        'max_devices' => 'integer',
        'max_merchants' => 'integer',
        'max_storage_days' => 'integer',
        'status' => 'integer',
    ];

    public function allowSearchFields(): array
    {
        return ['id', 'plan_code', 'tier', 'status'];
    }

    public function allowCreateFields(): array
    {
        return [
            'plan_code', 'plan_name', 'tier', 'price_monthly', 'price_yearly',
            'max_devices', 'max_merchants', 'max_storage_days', 'features', 'status',
        ];
    }

    public function allowUpdateFields(): array
    {
        return [
            'plan_name', 'tier', 'price_monthly', 'price_yearly',
            'max_devices', 'max_merchants', 'max_storage_days', 'features', 'status',
        ];
    }
}
