<?php

declare(strict_types=1);

namespace app\middleware;

use app\attribute\AllowSuperAdminBypass;
use app\BaseController;
use app\service\SuperAdminAuditService;
use app\support\TenantContext;
use Closure;
use think\Request;
use think\Response;
use think\response\Json;

/**
 * 超管白名单 + 审计中间件（§13.8.3 Phase 1.6）
 *
 * 在路由 dispatch 之后、controller action 执行前后触发：
 *  - 前置：超管调用未标注 #[AllowSuperAdminBypass] 的 action → 40302 拒绝
 *  - 后置：超管写操作完成后自动写审计日志
 *
 * 实现要点：
 *  - 通过 request->controller() / action() 反射子类方法判断注解（无需 controller 实例）
 *  - 仅在 super_admin 已注入 ctx 时生效
 *  - 平台路由白名单（/api/sadmin/*）跳过白名单校验
 */
class SuperAdminBypassMiddleware
{
    /** 平台作用域路由（跳过白名单 + 跳过审计） */
    private const PLATFORM_ROUTES = [
        '#^/api/sadmin/login$#',
        '#^/api/sadmin/tenants#',
        '#^/api/sadmin/plans#',
        '#^/api/sadmin/invoices#',
        '#^/api/sadmin/audit-logs#',
    ];

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response|Json
     */
    public function handle(Request $request, Closure $next)
    {
        $ctx = TenantContext::getInstance();

        // 非超管：直接放行
        if (!$ctx->isSuperAdmin()) {
            return $next($request);
        }

        // 平台作用域路由：跳过白名单 + 跳过审计
        if ($this->isPlatformRoute($request)) {
            return $next($request);
        }

        // 前置拦截 1：白名单校验
        if (!$this->isSuperAdminBypassAllowed($request)) {
            // 被白名单拦截也写一条审计日志（记录"拒绝操作"便于追责）
            $this->dispatchAudit($request, 403);
            return $this->deny(403, 40302, '超管无权限调用该接口：未标注 #[AllowSuperAdminBypass]，请以租户成员身份调用或联系平台管理员');
        }

        // 主流程
        $response = $next($request);

        // 后置拦截 2：审计日志（仅写操作）
        $this->dispatchAudit($request, $response->getCode());

        return $response;
    }

    /**
     * 反射判断当前 controller@action 是否标注 #[AllowSuperAdminBypass]
     */
    private function isSuperAdminBypassAllowed(Request $request): bool
    {
        $controller = $request->controller();
        $action = $request->action();

        if ($controller === '' || $action === '') {
            return true; // 未匹配到 controller/action，不拦截
        }

        // 解析 controller 类名（与 ThinkPHP 规则一致）
        $suffix = $this->app->config->get('route.controller_suffix') ? 'Controller' : '';
        $layer = $this->app->config->get('route.controller_layer') ?: 'controller';
        $class = $this->app->parseClass($layer, $controller . $suffix);

        if (!class_exists($class)) {
            return true; // 类不存在，让路由层 404
        }

        try {
            $reflectionClass = new \ReflectionClass($class);

            // 类级注解 → 整个类放行
            if (count($reflectionClass->getAttributes(AllowSuperAdminBypass::class)) > 0) {
                return true;
            }

            // 方法级注解 → 单方法放行
            if ($reflectionClass->hasMethod($action)) {
                $method = $reflectionClass->getMethod($action);
                if (count($method->getAttributes(AllowSuperAdminBypass::class)) > 0) {
                    return true;
                }
            }
        } catch (\ReflectionException) {
            return true; // 反射失败，不拦截
        }

        return false;
    }

    /**
     * 当前路由是否为平台作用域
     */
    private function isPlatformRoute(Request $request): bool
    {
        $path = '/' . ltrim($request->pathinfo(), '/');
        foreach (self::PLATFORM_ROUTES as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 调用审计 Service 写入日志
     */
    private function dispatchAudit(Request $request, int $responseStatus): void
    {
        $method = strtoupper($request->method());
        $writeMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (!in_array($method, $writeMethods, true)) {
            return; // 仅写操作审计
        }

        try {
            $service = new SuperAdminAuditService();
            $service->record([
                'action' => SuperAdminAuditService::actionFromMethod($method),
                'api_path' => (string) $request->pathinfo(),
                'method' => $method,
                'request_params' => $this->collectParams($request),
                'response_status' => $responseStatus,
                'ip' => (string) $request->ip(),
                'user_agent' => (string) $request->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {
            try {
                \think\facade\Log::critical('审计中间件异常', ['error' => $e->getMessage()]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * 收集请求参数（敏感字段在 Service 层脱敏）
     */
    private function collectParams(Request $request): array
    {
        $method = strtoupper($request->method());
        $params = $method === 'GET' ? $request->get() : $request->post();
        if (!is_array($params)) {
            return [];
        }
        // 截断过长字段
        foreach ($params as $k => $v) {
            if (is_string($v) && strlen($v) > 1000) {
                $params[$k] = substr($v, 0, 1000) . '...[truncated]';
            }
        }
        return $params;
    }

    /**
     * 拒绝响应
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

    public function __construct(
        private readonly \think\App $app
    ) {
    }
}
