<?php

declare(strict_types=1);

namespace app\repository;

use app\model\SuperAdminAuditLog;

/**
 * 超管审计日志 Repository
 *
 * 合规约束（§13.8.3）：
 * - 仅提供 create + 查询，禁止任何 update / delete 方法
 * - 防止业务代码误删审计日志
 *
 * @extends BaseRepository<SuperAdminAuditLog>
 */
class SuperAdminAuditLogRepository extends BaseRepository
{
    /** @var class-string<SuperAdminAuditLog> */
    protected static string $modelClass = SuperAdminAuditLog::class;

    /**
     * 写入一条审计日志（不可变）
     *
     * @param array{
     *     admin_id: int,
     *     target_tenant_id: int,
     *     action: string,
     *     api_path: string,
     *     method: string,
     *     request_params?: array,
     *     response_status: int,
     *     ip: string,
     *     user_agent?: string|null
     * } $data
     */
    public function record(array $data): SuperAdminAuditLog
    {
        return $this->create($data);
    }

    /**
     * 防御性禁用：审计日志禁止更新
     * @throws \RuntimeException
     */
    public function updateById(int|string $id, array $data): \app\model\BaseModel
    {
        throw new \RuntimeException('审计日志不可更新（§13.8.3 合规约束）', 40302);
    }

    /**
     * 防御性禁用：审计日志禁止删除
     * @throws \RuntimeException
     */
    public function deleteById(int|string $id): bool
    {
        throw new \RuntimeException('审计日志不可删除（§13.8.3 合规约束）', 40302);
    }
}
