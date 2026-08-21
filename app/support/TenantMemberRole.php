<?php

declare(strict_types=1);

namespace app\support;

/**
 * 租户成员角色枚举 - 4 种角色
 *
 * 与 tenant_member.role 字段一一对应：
 *   1=Owner 2=Admin 3=Operator 4=Auditor
 *
 * 权限矩阵：
 * | 角色     | canRead | canWrite | canDelete | canManageMember | 备注                          |
 * |----------|---------|-----------|-----------|------------------|-------------------------------|
 * | Owner    | ✓       | ✓         | ✓         | ✓（含转让）       | 唯一不可移除                  |
 * | Admin    | ✓       | ✓         | ✓         | ✓（不含 Owner）   | 不能移除/降级 Owner           |
 * | Operator| ✓       | ✓         | ✗         | ✗                | 仅业务运营，不能改成员         |
 * | Auditor  | ✓       | ✗         | ✗         | ✗                | 只读审计，所有写接口拦截       |
 */
enum TenantMemberRole: int
{
    /** 1 老板 / 市场所有者（每个租户有且仅有一个） */
    case OWNER = 1;

    /** 2 管理员（不能管理 Owner） */
    case ADMIN = 2;

    /** 3 运营（日常业务操作，不能管成员） */
    case OPERATOR = 3;

    /** 4 只读审计（所有写接口拦截，返回 20502） */
    case AUDITOR = 4;

    /**
     * 是否允许写操作
     * - Auditor 任何写操作都不允许
     */
    public function canWrite(): bool
    {
        return match ($this) {
            self::OWNER, self::ADMIN, self::OPERATOR => true,
            self::AUDITOR => false,
        };
    }

    /**
     * 是否允许删除操作（比写更高级别）
     * - Operator 不能删除（只能创建/修改）
     */
    public function canDelete(): bool
    {
        return match ($this) {
            self::OWNER, self::ADMIN => true,
            self::OPERATOR, self::AUDITOR => false,
        };
    }

    /**
     * 是否允许管理租户成员（邀请/移除/改角色）
     */
    public function canManageMember(): bool
    {
        return match ($this) {
            self::OWNER, self::ADMIN => true,
            self::OPERATOR, self::AUDITOR => false,
        };
    }

    /**
     * 是否允许管理 Owner 角色（转让、降级、移除）
     * - 只有 Owner 自己能做（防止 Admin 把 Owner 篡位）
     */
    public function canManageOwner(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * 中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => '老板',
            self::ADMIN => '管理员',
            self::OPERATOR => '运营',
            self::AUDITOR => '只读审计',
        };
    }
}
