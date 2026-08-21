<?php

declare(strict_types=1);

namespace app\model;

/**
 * 平台超管账号模型
 *
 * 对应 nmyun_super_admin 表（迁移脚本 20260821062300_create_super_admin_table）
 *
 * 安全约束（§13.8.3）：
 * - 独立于 tenant_member 体系，不带 tenant_id 字段
 * - JWT payload 只写 is_super_admin=true + sadmin_id，绝不夹带 tenant_id 或 tenant_role
 */
class SuperAdmin extends BaseModel
{
    protected $name = 'super_admin';

    /** SuperAdmin 表自身不带 tenant_id 字段，关闭全局 Scope */
    protected bool $enableTenantScope = false;

    /** 时间字段（迁移脚本使用 created_at/updated_at） */
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /** 密码字段在序列化时自动隐藏 */
    protected $hidden = ['password'];

    /** 状态枚举 */
    public const STATUS_DISABLED = 0;
    public const STATUS_ACTIVE = 1;

    public function allowSearchFields(): array
    {
        return ['id', 'username', 'status'];
    }

    public function allowCreateFields(): array
    {
        return ['username', 'password', 'name', 'status'];
    }

    public function allowUpdateFields(): array
    {
        return ['username', 'password', 'name', 'status', 'last_login_at'];
    }
}
