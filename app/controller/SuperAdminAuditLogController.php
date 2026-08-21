<?php

declare(strict_types=1);

namespace app\controller;

use app\attribute\AllowSuperAdminBypass;
use app\BaseController;
use app\service\SuperAdminAuditQueryService;

/**
 * 超管审计日志查询控制器（平台作用域 /api/sadmin/audit-logs/*）
 *
 * MVP 接口：
 * - GET /api/sadmin/audit-logs  审计日志列表（按 admin_id / target_tenant_id / action 筛选）
 */
#[AllowSuperAdminBypass]
class SuperAdminAuditLogController extends BaseController
{
    public function __construct(
        \think\App $app,
        protected SuperAdminAuditQueryService $service
    ) {
        parent::__construct($app);
    }

    /**
     * GET /api/sadmin/audit-logs  审计日志列表
     */
    public function list()
    {
        $where = [];
        $adminId = input('admin_id');
        $targetTenantId = input('target_tenant_id');
        $action = trim((string) input('action', ''));

        if ($adminId !== '' && $adminId !== null) {
            $where[] = ['admin_id', '=', (int) $adminId];
        }
        if ($targetTenantId !== '' && $targetTenantId !== null) {
            $where[] = ['target_tenant_id', '=', (int) $targetTenantId];
        }
        if ($action !== '') {
            $where[] = ['action', '=', $action];
        }

        ['page' => $page, 'page_size' => $pageSize] = $this->resolvePagination();
        $result = $this->service->listForSuperAdmin($where, $page, $pageSize);

        return $this->paginated(
            $result['list']->toArray(),
            $result['total'],
            $result['page'],
            $result['page_size']
        );
    }
}
