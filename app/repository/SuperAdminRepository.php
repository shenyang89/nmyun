<?php

declare(strict_types=1);

namespace app\repository;

use app\model\SuperAdmin;

/**
 * 平台超管 Repository
 *
 * 安全约束（§13.8.3）：
 * - SuperAdmin 表不带 tenant_id，独立于租户体系
 * - 仅提供登录态必需的查询，禁止任何跨租户业务方法
 *
 * @extends BaseRepository<SuperAdmin>
 */
class SuperAdminRepository extends BaseRepository
{
    /** @var class-string<SuperAdmin> */
    protected static string $modelClass = SuperAdmin::class;

    /**
     * 通过登录账号查找超管
     */
    public function findByUsername(string $username): ?SuperAdmin
    {
        /** @var SuperAdmin|null */
        return $this->findBy('username', $username);
    }

    /**
     * 通过主键查找超管
     */
    public function findById(int|string $id): ?SuperAdmin
    {
        /** @var SuperAdmin|null */
        return parent::findById($id);
    }

    /**
     * 更新最后登录时间
     */
    public function updateLastLoginAt(int $sadminId): void
    {
        $this->updateById($sadminId, [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
