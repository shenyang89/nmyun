<?php

declare(strict_types=1);

namespace app\repository;

use app\model\TenantPlan;

/**
 * 套餐 Repository（平台级，无 tenant_id）
 *
 * @extends BaseRepository<TenantPlan>
 */
class TenantPlanRepository extends BaseRepository
{
    /** @var class-string<TenantPlan> */
    protected static string $modelClass = TenantPlan::class;

    /**
     * 通过 plan_code 查找套餐
     */
    public function findByCode(string $code): ?TenantPlan
    {
        /** @var TenantPlan|null */
        return $this->findBy('plan_code', $code);
    }
}
