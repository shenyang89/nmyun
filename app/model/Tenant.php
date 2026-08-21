<?php

declare(strict_types=1);

namespace app\model;

/**
 * SaaS 租户（市场）模型
 *
 * 对应 nmyun_tenant 表（迁移脚本 20260821062046_create_tenant_table）
 * 一个租户 = 一个农贸市场，所有业务数据的隔离边界。
 */
class Tenant extends BaseModel
{
    protected $name = 'tenant';

    /** Tenant 表自身不带 tenant_id 字段，关闭全局 Scope */
    protected bool $enableTenantScope = false;

    /** 时间字段（迁移脚本使用 created_at/updated_at，非 BaseModel 默认 create_time/update_time） */
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    public function allowSearchFields(): array
    {
        return ['id', 'code', 'status', 'contact_name', 'contact_phone'];
    }

    public function allowCreateFields(): array
    {
        return ['name', 'code', 'status', 'contact_name', 'contact_phone', 'address', 'expired_at'];
    }

    public function allowUpdateFields(): array
    {
        return ['name', 'status', 'contact_name', 'contact_phone', 'address', 'expired_at'];
    }
}
