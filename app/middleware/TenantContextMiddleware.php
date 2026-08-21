<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\JwtService;
use app\support\TenantContext;
use app\support\TenantMemberRole;
use app\support\TenantStatus;
use Closure;
use think\Request;
use think\Response;
use think\response\Json;

/**
 * 租户上下文中间件（§13.8.2 三段式职责）
 *
 * 1. 上下文注入：
 *    (a) JWT Bearer Token 解析（生产路径）→ is_super_admin / member / tenant_id
 *    (b) 开发模式头注入（TENANT_DEV_MODE=true，仅测试用）→ 兜底兼容旧测试
 *    (c) 超管 X-Target-Tenant-Id 头：超管 JWT + 该头 → 注入目标 tenant_id
 * 2. 状态完整校验：7 种状态（PENDING/ACTIVE/DISABLED/LOCKED/UNPAID/REJECTED/PENDING_DELETE）
 *    每种状态明确映射到错误码 + HTTP 状态 + 读写权限，**无 default 兜底分支**
 * 3. 读写拦截：写方法（POST/PUT/PATCH/DELETE）调用 TenantContext::canWrite()，否则 canRead()
 *
 * Phase 1.5 超管约束（§13.8.3）：
 * - 超管 JWT payload 仅含 is_super_admin=true + sadmin_id，绝不夹带 tenant_id 或 role
 * - 超管访问「租户作用域」路由必须携带 X-Target-Tenant-Id，否则 422
 * - 超管访问「平台作用域」路由（如 /api/sadmin/login、Phase 1.7 /api/sadmin/tenants/*）
 *   不需要 X-Target-Tenant-Id，但需要超管 JWT
 */
class TenantContextMiddleware
{
    /** 写操作 HTTP 方法（仅这些走 canWrite 拦截） */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * 平台作用域路由白名单（正则匹配 path）：
     * 这些路由仅超管可访问，且不需要 X-Target-Tenant-Id。
     * - /api/sadmin/login   登录本身无需鉴权（无 JWT 也放行）
     * - /api/sadmin/tenants* 超管平台租户管理（Phase 1.7 占位）
     * - /api/sadmin/plans*  套餐配置（Phase 1.7 占位）
     * - /api/sadmin/invoices* 账单概览（Phase 1.7 占位）
     */
    private const PLATFORM_WHITELIST = [
        '#^/api/sadmin/login$#',
        '#^/api/sadmin/tenants#',
        '#^/api/sadmin/plans#',
        '#^/api/sadmin/invoices#',
    ];

    public function __construct(
        private readonly \app\model\Tenant $tenantModel = new \app\model\Tenant()
    ) {
    }

    /**
     * @param Request  $request
     * @param Closure  $next
     * @return Response|Json
     */
    public function handle(Request $request, Closure $next)
    {
        // 每个请求重置上下文，防止跨请求污染
        TenantContext::reset();
        $ctx = TenantContext::getInstance();

        // ============ (a) 上下文注入 ============
        try {
            $this->injectContext($request, $ctx);
        } catch (\RuntimeException $e) {
            // JWT 验证失败统一 401（错误码由 JwtService / payload 校验给出）
            return $this->deny(401, (int) $e->getCode(), $e->getMessage());
        }

        // 超管访问租户作用域路由但未带 X-Target-Tenant-Id → 422
        if ($ctx->isSuperAdmin()
            && $ctx->getTenantId() === null
            && !$this->isPlatformRoute($request)
        ) {
            return $this->deny(422, 40302, '超管访问租户作用域接口必须指定 X-Target-Tenant-Id');
        }

        // 未注入 tenant_id 且非超管平台路由：白名单接口（如登录、健康检查）直接放行
        $tenantId = $ctx->getTenantId();
        if ($tenantId === null) {
            return $next($request);
        }

        // ============ (b) 7 种状态完整校验 ============
        // 超管跨租户访问也需要校验目标租户状态（防止操作已删除租户）
        $status = $this->resolveTenantStatus($tenantId);
        if ($status === null) {
            return $this->deny(404, 20001, '租户不存在');
        }
        $ctx->setTenantStatus($status);

        // 非超管：登录阶段拦截（DISABLED/LOCKED/REJECTED/PENDING_DELETE）
        if (!$ctx->isSuperAdmin() && !$status->canLogin()) {
            $err = $status->loginError();
            return $this->deny(401, $err['code'], $err['message']);
        }

        // ============ (c) 读写拦截 ============
        $isWrite = in_array(strtoupper($request->method()), self::WRITE_METHODS, true);
        $result = $isWrite ? $ctx->canWrite() : $ctx->canRead();
        if (!$result['allowed']) {
            // 写操作被拦截用 403；登录状态层拦截已在上面用 401
            $httpStatus = $isWrite ? 403 : 401;
            return $this->deny($httpStatus, $result['code'], $result['message']);
        }

        return $next($request);
    }

    /**
     * 上下文注入优先级：
     *  1. JWT Bearer Token（Authorization: Bearer xxx）
     *     - is_super_admin=true → 注入超管身份
     *     - is_super_admin=false → 注入租户成员身份（member_id/tenant_id/role）
     *  2. 开发模式头（TENANT_DEV_MODE=true）：仅当无 JWT 时生效，方便测试
     *  3. X-Target-Tenant-Id：超管身份注入后，再读该头切到目标租户
     *
     * @throws \RuntimeException JWT 验证失败时（中间件层捕获转 401）
     */
    private function injectContext(Request $request, TenantContext $ctx): void
    {
        $token = $this->extractBearerToken($request);

        // 优先级 1：JWT 解析（生产路径）
        if ($token !== null) {
            $payload = JwtService::verify($token);

            if (!empty($payload['is_super_admin']) && !empty($payload['sadmin_id'])) {
                // 超管 JWT：严格校验 payload 不夹带 tenant_id / role（§13.8.3）
                $this->assertCleanSuperAdminPayload($payload);
                $ctx->setSuperAdmin(true, (int) $payload['sadmin_id']);
            } elseif (isset($payload['member_id'], $payload['tenant_id'], $payload['role'])) {
                // 租户成员 JWT（Phase 1.8 完整实现，此处解析逻辑就绪）
                $ctx->setMember(
                    (int) $payload['member_id'],
                    TenantMemberRole::from((int) $payload['role'])
                );
                $ctx->setTenantId((int) $payload['tenant_id']);
            } else {
                throw new \RuntimeException('Token payload 非法：缺少必要字段', 40104);
            }

            // 超管读 X-Target-Tenant-Id（仅在超管身份下生效）
            $targetTenantId = $request->header('X-Target-Tenant-Id');
            if ($ctx->isSuperAdmin() && $targetTenantId !== null && $targetTenantId !== '') {
                $ctx->setTenantId((int) $targetTenantId);
            }
            return;
        }

        // 优先级 2：开发模式头注入（仅测试用，生产环境必须 TENANT_DEV_MODE=false）
        $this->injectDevHeaders($request, $ctx);
    }

    /**
     * 提取 Bearer Token
     */
    private function extractBearerToken(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if ($auth === '' || !str_starts_with($auth, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($auth, 7));
        return $token === '' ? null : $token;
    }

    /**
     * 超管 JWT payload 必须只含 is_super_admin + sadmin_id
     * 任何夹带 tenant_id / member_id / role 的 payload 视为伪造 → 拒绝
     */
    private function assertCleanSuperAdminPayload(array $payload): void
    {
        $forbidden = ['tenant_id', 'member_id', 'role', 'tenant_role'];
        foreach ($forbidden as $key) {
            if (array_key_exists($key, $payload)) {
                throw new \RuntimeException(
                    '超管 Token payload 非法：禁止包含 ' . $key . '（§13.8.3）',
                    40104
                );
            }
        }
    }

    /**
     * 开发模式头注入（仅 TENANT_DEV_MODE=true 时生效）
     */
    private function injectDevHeaders(Request $request, TenantContext $ctx): void
    {
        // 兼容 putenv('TENANT_DEV_MODE=false') 等字符串布尔值场景
        $devModeRaw = getenv('TENANT_DEV_MODE') ?: env('TENANT_DEV_MODE', false);
        $devMode = filter_var($devModeRaw, FILTER_VALIDATE_BOOLEAN);
        if (!$devMode) {
            return;
        }

        $tenantId = $request->header('X-Tenant-Id');
        $memberId = $request->header('X-Tenant-Member-Id');
        $memberRole = $request->header('X-Tenant-Member-Role');
        $isSuperAdmin = $request->header('X-Super-Admin') === 'true';
        $superAdminId = $request->header('X-Super-Admin-Id');
        $targetTenantId = $request->header('X-Target-Tenant-Id');

        if ($isSuperAdmin && $superAdminId !== null) {
            $ctx->setSuperAdmin(true, (int) $superAdminId);
        }

        if ($ctx->isSuperAdmin() && $targetTenantId !== null) {
            $ctx->setTenantId((int) $targetTenantId);
        } elseif ($tenantId !== null) {
            $ctx->setTenantId((int) $tenantId);
        }

        if ($memberId !== null && $memberRole !== null) {
            $ctx->setMember((int) $memberId, TenantMemberRole::from((int) $memberRole));
        }
    }

    /**
     * 当前路由是否为「平台作用域」路由（白名单）
     */
    private function isPlatformRoute(Request $request): bool
    {
        $path = '/' . ltrim($request->pathinfo(), '/');
        foreach (self::PLATFORM_WHITELIST as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 解析租户状态：查询 nmyun_tenant 表
     * 返回 null 表示租户不存在
     */
    private function resolveTenantStatus(int $tenantId): ?TenantStatus
    {
        $tenant = $this->tenantModel->where('id', $tenantId)->find();
        if (!$tenant) {
            return null;
        }
        return TenantStatus::from((int) $tenant->status);
    }

    /**
     * 拒绝响应（与 ApiResponseTrait 格式一致）
     */
    private function deny(int $httpStatus, int $code, string $message): Json
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => [],
            'timestamp' => time(),
        ], $httpStatus);
    }
}
