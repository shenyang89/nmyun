<?php

declare(strict_types=1);

namespace app\controller;

use app\attribute\AllowSuperAdminBypass;
use app\BaseController;
use app\service\TenantInvoiceService;

/**
 * 超管账单管理控制器（平台作用域 /api/sadmin/invoices/*）
 *
 * MVP 接口：
 * - GET /api/sadmin/invoices  跨租户账单列表（按 tenant_id / status / billing_period 筛选）
 */
#[AllowSuperAdminBypass]
class SuperAdminInvoiceController extends BaseController
{
    public function __construct(
        \think\App $app,
        protected TenantInvoiceService $service
    ) {
        parent::__construct($app);
    }

    /**
     * GET /api/sadmin/invoices  账单列表
     */
    public function list()
    {
        $where = [];
        $tenantId = input('tenant_id');
        $status = input('status');
        $billingPeriod = trim((string) input('billing_period', ''));

        if ($tenantId !== '' && $tenantId !== null) {
            $where[] = ['tenant_id', '=', (int) $tenantId];
        }
        if ($status !== '' && $status !== null) {
            $where[] = ['status', '=', (int) $status];
        }
        if ($billingPeriod !== '') {
            $where[] = ['billing_period', '=', $billingPeriod];
        }

        ['page' => $page, 'page_size' => $pageSize] = $this->resolvePagination();
        $result = $this->service->listForPlatformAdmin($where, $page, $pageSize);

        return $this->paginated(
            $result['list']->toArray(),
            $result['total'],
            $result['page'],
            $result['page_size']
        );
    }
}
