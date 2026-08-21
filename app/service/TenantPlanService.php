<?php

declare(strict_types=1);

namespace app\service;

use app\exceptions\BusinessException;
use app\model\TenantPlan;
use app\repository\TenantPlanRepository;

/**
 * 套餐管理 Service（平台级，无 tenant_id）
 *
 * MVP 接口：
 * - GET  /api/sadmin/plans        套餐列表
 * - POST /api/sadmin/plans        创建套餐
 */
class TenantPlanService
{
    public function __construct(
        private readonly TenantPlanRepository $repo = new TenantPlanRepository()
    ) {
    }

    public function listPage(array $where, int $page, int $pageSize): array
    {
        return $this->repo->paginateWhere($where, $page, $pageSize, 'tier ASC, id ASC');
    }

    public function create(array $data): TenantPlan
    {
        // plan_code 全局唯一校验
        $existing = $this->repo->findByCode($data['plan_code']);
        if ($existing) {
            throw new BusinessException('套餐编码已存在：' . $data['plan_code'], 22001);
        }

        if (!isset($data['status'])) {
            $data['status'] = TenantPlan::STATUS_DRAFT;
        }

        /** @var TenantPlan */
        return $this->repo->create($data);
    }
}
