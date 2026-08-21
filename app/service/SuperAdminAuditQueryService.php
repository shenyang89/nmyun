<?php

declare(strict_types=1);

namespace app\service;

use app\repository\SuperAdminAuditLogRepository;
use app\support\TenantContext;

/**
 * 超管审计日志查询 Service
 *
 * MVP 接口：
 * - GET /api/sadmin/audit-logs  超管审计日志列表（仅超管可读）
 *
 * 安全约束：
 * - 仅 ctx.is_super_admin=true 时允许调用
 */
class SuperAdminAuditQueryService
{
    public function __construct(
        private readonly SuperAdminAuditLogRepository $repo = new SuperAdminAuditLogRepository()
    ) {
    }

    /**
     * 查询审计日志列表
     *
     * @param array $where 筛选条件（admin_id / target_tenant_id / action）
     */
    public function listForSuperAdmin(array $where, int $page, int $pageSize): array
    {
        $ctx = TenantContext::getInstance();
        if (!$ctx->isSuperAdmin()) {
            throw new \app\exceptions\UnauthorizedException(
                '仅超管可查询审计日志',
                21003
            );
        }

        return $this->repo->paginateWhere($where, $page, $pageSize, 'id DESC');
    }
}
