<?php

declare(strict_types=1);

namespace app\repository;

use app\model\Tenant;

/**
 * 租户 Repository（平台级表，无 tenant_id）
 *
 * @extends BaseRepository<Tenant>
 */
class TenantRepository extends BaseRepository
{
    /** @var class-string<Tenant> */
    protected static string $modelClass = Tenant::class;

    /**
     * 通过 code 查找租户
     */
    public function findByCode(string $code): ?Tenant
    {
        /** @var Tenant|null */
        return $this->findBy('code', $code);
    }
}
