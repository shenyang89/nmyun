<?php

declare(strict_types=1);

namespace app\model;

/**
 * 平台超管审计日志模型
 *
 * 对应 nmyun_super_admin_audit_log 表（迁移脚本 20260821062111）
 *
 * 合规约束（§13.8.3）：
 * - 永久留存，不可删除（不提供 delete / update 方法）
 * - 仅由 BaseController 后置钩子在超管写操作时自动写入
 * - 平台级表，无 tenant_id 字段，关闭全局 Scope
 */
class SuperAdminAuditLog extends BaseModel
{
    protected $name = 'super_admin_audit_log';

    /** 审计日志表无 tenant_id 字段，关闭全局 Scope */
    protected bool $enableTenantScope = false;

    /** 只有 created_at，无 updated_at（只增不改不删） */
    protected $createTime = 'created_at';
    protected $updateTime = false;

    /**
     * 字段类型转换
     * - request_params: jsonb 字段，ThinkORM 用 'json' 类型自动 encode/decode
     */
    protected $type = [
        'request_params' => 'json',
        'response_status' => 'integer',
        'admin_id' => 'integer',
        'target_tenant_id' => 'integer',
    ];

    /** 操作类型枚举 */
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_CONTROL = 'control';
    public const ACTION_LOGIN = 'login';
    public const ACTION_TENANT_STATUS_SWITCH = 'tenant_status_switch';

    public function allowCreateFields(): array
    {
        return [
            'admin_id', 'target_tenant_id', 'action', 'api_path', 'method',
            'request_params', 'response_status', 'ip', 'user_agent',
        ];
    }

    public function allowSearchFields(): array
    {
        return ['id', 'admin_id', 'target_tenant_id', 'action'];
    }

    /**
     * 防御性覆盖：审计日志禁止更新
     */
    public function allowUpdateFields(): array
    {
        return [];
    }
}
