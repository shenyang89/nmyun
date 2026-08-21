<?php

declare(strict_types=1);

namespace app\model;

/**
 * 租户成员模型
 *
 * 对应 nmyun_tenant_member 表（含邀请 token 字段）
 *
 * 安全约束：
 * - 全局 Scope 自动按 tenant_id 隔离
 * - password 字段在 toArray() 时隐藏
 * - invite_token 字段不直接对外暴露
 */
class TenantMember extends BaseModel
{
    protected $name = 'tenant_member';

    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    /** 状态枚举 */
    public const STATUS_PENDING = 0;  // 待激活（邀请中）
    public const STATUS_ACTIVE = 1;   // 启用
    public const STATUS_DISABLED = 2; // 禁用

    protected $type = [
        'tenant_id' => 'integer',
        'role' => 'integer',
        'status' => 'integer',
    ];

    /** password / invite_token 不对外暴露 */
    protected $hidden = ['password', 'invite_token', 'invite_expires_at'];

    public function allowSearchFields(): array
    {
        return ['id', 'tenant_id', 'username', 'role', 'status'];
    }

    public function allowCreateFields(): array
    {
        return [
            'tenant_id', 'username', 'password', 'role', 'phone', 'email',
            'status', 'invite_token', 'invite_expires_at',
        ];
    }

    public function allowUpdateFields(): array
    {
        return [
            'password', 'role', 'phone', 'email', 'status',
            'last_login_at', 'invite_token', 'invite_expires_at',
        ];
    }
}
