<?php

declare(strict_types=1);

namespace app\service;

use app\exceptions\BusinessException;
use app\model\Tenant;
use app\repository\BaseRepository;
use app\support\TenantStatus;

/**
 * 超管租户管理 Service
 *
 * 职责：
 * - 平台超管 CRUD 租户（创建/列表/详情/改状态）
 * - 不走租户隔离 Scope（Tenant 表本身无 tenant_id）
 *
 * MVP 接口：
 * - GET    /api/sadmin/tenants         租户列表（分页 + 状态筛选）
 * - POST   /api/sadmin/tenants         创建租户
 * - PATCH  /api/sadmin/tenants/:id/status  切换租户状态
 */
class TenantAdminService
{
    public function __construct(
        private readonly BaseRepository $repo = new \app\repository\TenantRepository()
    ) {
    }

    /**
     * 租户列表（分页）
     *
     * @param array $where 筛选条件（status / code 模糊匹配）
     * @return array{list: mixed, total: int, page: int, page_size: int}
     */
    public function listPage(array $where, int $page, int $pageSize): array
    {
        return $this->repo->paginateWhere($where, $page, $pageSize, 'id DESC');
    }

    /**
     * 创建租户
     */
    public function create(array $data): Tenant
    {
        // code 全局唯一校验
        $existing = Tenant::where('code', $data['code'])->find();
        if ($existing) {
            throw new BusinessException('市场编码已存在：' . $data['code'], 20009);
        }

        // 默认状态为 PENDING（待审核）
        if (!isset($data['status'])) {
            $data['status'] = TenantStatus::PENDING->value;
        }

        /** @var Tenant */
        return $this->repo->create($data);
    }

    /**
     * 切换租户状态（超管主动启用/禁用/锁定等）
     */
    public function switchStatus(int $tenantId, int $newStatus): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $this->repo->findOrFail($tenantId);

        // 校验 status 必须是合法枚举值
        try {
            TenantStatus::from($newStatus);
        } catch (\ValueError) {
            throw new BusinessException('非法的租户状态：' . $newStatus, 20010);
        }

        $tenant->status = $newStatus;
        $tenant->save();

        return $tenant;
    }
}
