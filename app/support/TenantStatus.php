<?php

declare(strict_types=1);

namespace app\support;

/**
 * 租户（市场）状态枚举 - 7 种状态
 *
 * 每种状态严格映射到「读/写权限 + 错误码 + 提示语」，
 * 中间件、Repository、Service 任何层都通过此枚举统一判定，
 * 严禁在业务代码里写裸 if ($status === 1) 之类的硬编码。
 *
 * §13.8.2 三段式中间件对 7 种状态的处理：
 * - 启用：全部放行
 * - 欠费：读放行，写请求拦截 20003
 * - 待审核：登录允许，写请求拦截 20005
 * - 禁用/审核不通过/预删除：登录阶段拦截
 * - 锁定：登录阶段拦截（与禁用同类，更侧重业务异常待排查）
 *
 * @see https://www.php.net/manual/zh/language.enumerations.php
 */
enum TenantStatus: int
{
    /** 0 待审核（市场刚提交资料，平台尚未审批） */
    case PENDING = 0;

    /** 1 启用（正常运营） */
    case ACTIVE = 1;

    /** 2 禁用（平台主动封禁，需平台管理员解锁） */
    case DISABLED = 2;

    /** 3 锁定（异常态，常见于安全风控触发，等待管理员排查） */
    case LOCKED = 3;

    /** 4 欠费（账单逾期，仅允许只读，续费后自动恢复） */
    case UNPAID = 4;

    /** 5 审核不通过（资料不符合要求，需重新提交） */
    case REJECTED = 5;

    /** 6 预删除（进入 30 天回收站，到期物理清理） */
    case PENDING_DELETE = 6;

    /**
     * 是否允许读操作（GET / 查询类）
     * - PENDING/ACTIVE/UNPAID：允许读
     * - DISABLED/LOCKED/REJECTED/PENDING_DELETE：登录拦截，根本进不来
     */
    public function canRead(): bool
    {
        return match ($this) {
            self::PENDING, self::ACTIVE, self::UNPAID => true,
            self::DISABLED, self::LOCKED, self::REJECTED, self::PENDING_DELETE => false,
        };
    }

    /**
     * 是否允许写操作（POST/PUT/DELETE）
     * - ACTIVE：允许写
     * - PENDING/UNPAID：登录放行但写请求拦截
     * - DISABLED/LOCKED/REJECTED/PENDING_DELETE：登录拦截
     */
    public function canWrite(): bool
    {
        return match ($this) {
            self::ACTIVE => true,
            self::PENDING, self::UNPAID,
            self::DISABLED, self::LOCKED, self::REJECTED, self::PENDING_DELETE => false,
        };
    }

    /**
     * 是否允许登录（决定中间件是否放行进入业务层）
     * - DISABLED/LOCKED/REJECTED/PENDING_DELETE：登录拦截
     * - PENDING：允许登录（但写请求拦截）
     */
    public function canLogin(): bool
    {
        return match ($this) {
            self::PENDING, self::ACTIVE, self::UNPAID => true,
            self::DISABLED, self::LOCKED, self::REJECTED, self::PENDING_DELETE => false,
        };
    }

    /**
     * 写操作被拦截时返回的错误码（§4 错误码规范 20xxx 区间）
     * 仅在 canWrite()=false 状态下调用；ACTIVE 兜底返回 0（不该走到这里）
     */
    public function writeErrorCode(): int
    {
        return match ($this) {
            self::UNPAID => 20003,           // 租户欠费已锁定（只读）
            self::PENDING => 20005,         // 市场正在审核中，暂不可编辑
            self::DISABLED => 20002,        // 市场已被禁用
            self::LOCKED => 20006,          // 市场已被锁定
            self::REJECTED => 20007,        // 市场审核未通过
            self::PENDING_DELETE => 20008,  // 市场已进入预删除状态
            self::ACTIVE => 0,              // canWrite=true 不该走到
        };
    }

    /**
     * 写操作被拦截时的用户可读提示
     */
    public function writeErrorMessage(): string
    {
        return match ($this) {
            self::UNPAID => '您的市场已欠费，请续费后再操作',
            self::PENDING => '市场正在审核中，暂不可编辑',
            self::DISABLED => '市场已被禁用，请联系平台管理员',
            self::LOCKED => '市场已被锁定，请联系平台管理员',
            self::REJECTED => '市场审核未通过，请补充资料后重新提交',
            self::PENDING_DELETE => '市场已进入预删除状态，不可操作',
            self::ACTIVE => '',
        };
    }

    /**
     * 登录被拦截时的错误码 + 提示
     * @return array{code: int, message: string}
     */
    public function loginError(): array
    {
        return match ($this) {
            self::DISABLED => ['code' => 20002, 'message' => '市场已被禁用，请联系平台管理员'],
            self::LOCKED => ['code' => 20006, 'message' => '市场已被锁定，请联系平台管理员'],
            self::REJECTED => ['code' => 20007, 'message' => '市场审核未通过，请补充资料后重新提交'],
            self::PENDING_DELETE => ['code' => 20008, 'message' => '市场已进入预删除状态，不可登录'],
            // 这些状态允许登录，理论上不会调用此方法
            self::PENDING, self::ACTIVE, self::UNPAID
                => ['code' => 0, 'message' => ''],
        };
    }

    /**
     * 中文标签（给前端列表筛选用）
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待审核',
            self::ACTIVE => '启用',
            self::DISABLED => '禁用',
            self::LOCKED => '锁定',
            self::UNPAID => '欠费',
            self::REJECTED => '审核不通过',
            self::PENDING_DELETE => '预删除',
        };
    }
}
