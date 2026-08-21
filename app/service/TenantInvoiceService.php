<?php

declare(strict_types=1);

namespace app\service;

use app\repository\TenantInvoiceRepository;
use app\support\TenantContext;

/**
 * 租户账单 Service
 *
 * MVP 接口：
 * - GET /api/sadmin/invoices  跨租户账单列表（支持按 tenant_id / status 筛选）
 *
 * 安全约束：
 * - listForPlatformAdmin 仅在 ctx.is_super_admin=true 时可用
 */
class TenantInvoiceService
{
    public function __construct(
        private readonly TenantInvoiceRepository $repo = new TenantInvoiceRepository()
    ) {
    }

    /**
     * 平台超管跨租户查询账单列表
     *
     * @param array $where 筛选条件（tenant_id / status / billing_period）
     */
    public function listForPlatformAdmin(array $where, int $page, int $pageSize): array
    {
        // Repository 内部会校验 ctx.is_super_admin，未授权抛 CrossTenantException
        return $this->repo->listForPlatformAdmin($where, $page, $pageSize);
    }
}
