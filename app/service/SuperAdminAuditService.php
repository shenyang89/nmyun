<?php

declare(strict_types=1);

namespace app\service;

use app\model\SuperAdminAuditLog;
use app\repository\SuperAdminAuditLogRepository;
use app\support\TenantContext;

/**
 * 超管审计日志 Service
 *
 * 职责：
 * - 由 BaseController 在超管写操作完成后调用
 * - 自动从 TenantContext + Request 提取审计字段
 * - 异常不阻塞主业务（合规要求：审计失败不能让业务回滚）
 *
 * 查询接口：仅超管可读（Phase 1.7 接口对接）
 */
class SuperAdminAuditService
{
    public function __construct(
        private readonly SuperAdminAuditLogRepository $repo = new SuperAdminAuditLogRepository()
    ) {
    }

    /**
     * 记录一条审计日志（由 BaseController 调用）
     *
     * 设计要点：
     * - 失败不能抛异常：审计是合规要求，但若审计库挂了不能让业务回滚
     * - 敏感字段脱敏：password / token / secret 等 key 自动遮蔽
     *
     * @param array{
     *     action: string,
     *     api_path: string,
     *     method: string,
     *     request_params: array,
     *     response_status: int
     * } $payload
     */
    public function record(array $payload): void
    {
        try {
            $ctx = TenantContext::getInstance();
            if (!$ctx->isSuperAdmin()) {
                return; // 仅记录超管操作
            }

            $this->repo->record([
                'admin_id' => $ctx->getSuperAdminId() ?? 0,
                'target_tenant_id' => $ctx->getTenantId() ?? 0,
                'action' => $payload['action'],
                'api_path' => $payload['api_path'],
                'method' => $payload['method'],
                'request_params' => $this->sanitize($payload['request_params']),
                'response_status' => $payload['response_status'],
                'ip' => $payload['ip'] ?? '',
                'user_agent' => $payload['user_agent'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // 审计失败不能阻塞主业务，记录到日志
            try {
                \think\facade\Log::critical('审计日志写入失败', [
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ]);
            } catch (\Throwable) {
                // 连 Log 都挂了，只能吞掉
            }
        }
    }

    /**
     * 查询审计日志列表（仅超管可调）
     *
     * @return array{list: mixed, total: int, page: int, page_size: int}
     */
    public function listAuditLogs(array $where, int $page, int $pageSize): array
    {
        return $this->repo->paginateWhere($where, $page, $pageSize, 'id DESC');
    }

    /**
     * 敏感字段脱敏
     * password / token / secret / key 的 value 替换为 ***
     */
    private function sanitize(array $params): array
    {
        $sensitive = ['password', 'token', 'secret', 'key', 'authorization', 'apikey'];
        foreach ($params as $k => $v) {
            $lower = strtolower((string) $k);
            foreach ($sensitive as $s) {
                if (str_contains($lower, $s)) {
                    $params[$k] = '***';
                    break;
                }
            }
        }
        return $params;
    }

    /**
     * 根据 HTTP 方法推断审计 action
     */
    public static function actionFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'POST' => SuperAdminAuditLog::ACTION_CREATE,
            'PUT', 'PATCH' => SuperAdminAuditLog::ACTION_UPDATE,
            'DELETE' => SuperAdminAuditLog::ACTION_DELETE,
            default => 'other',
        };
    }
}
