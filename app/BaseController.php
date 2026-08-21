<?php

declare (strict_types=1);

namespace app;

use app\attribute\AllowSuperAdminBypass;
use app\exceptions\UnauthorizedException;
use app\service\SuperAdminAuditService;
use app\support\TenantContext;
use app\traits\ApiResponseTrait;
use app\traits\PaginatesTrait;
use think\App;
use think\exception\ValidateException;
use think\Request;
use think\Validate;

/**
 * 控制器基础类 —— 智慧农贸云增强版
 *
 * 分层约束：
 * - 只做三件事：① 接收请求 + 参数校验  ② 调用 Service  ③ 调用 Trait 转 JSON 返回
 * - 禁止直接 use Model / Repository
 * - 禁止在此类里写业务逻辑（if-else 循环等）
 *
 * Phase 1.6 超管审计 + 白名单：
 * - initialize() 后置钩子：超管写操作调用前校验 #[AllowSuperAdminBypass] 注解
 * - 后置装饰器 dispatchAudit()：超管写操作完成后自动写审计日志
 */
abstract class BaseController
{
    use ApiResponseTrait;
    use PaginatesTrait;

    /**
     * Request 实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
        $this->initialize();
    }

    protected function initialize()
    {
    }

    /**
     * 验证数据（失败自动抛 ValidateException，被 ExceptionHandle 捕获转 JSON）
     */
    protected function validate(
        array $data,
        string|array $validate,
        array $message = [],
        bool $batch = false
    ): bool {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                [$validate, $scene] = explode('.', $validate);
            }
            $class = str_contains($validate, '\\')
                ? $validate
                : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * ThinkPHP Controller 入口拦截（在 action 执行前）
     *
     * 在 BaseController 上加入超管白名单校验：
     * - 若 ctx.is_super_admin=true 且当前 action 方法未标注 #[AllowSuperAdminBypass]
     *   且方法不是 login/health 等白名单方法 → 抛 40302
     *
     * 实现方式：通过 \think\Request->controller() / action() 反射定位子类方法
     */
    public function __thinkInitializer(): void
    {
        // ThinkPHP 在路由 dispatch 时不会自动调用此方法
        // 真正的拦截点在中间件层；BaseController 仅提供工具方法 isSuperAdminBypassAllowed()
    }

    /**
     * 校验当前 action 是否允许超管绕过
     *
     * 由路由中间件 / Service 调用：
     *   if (!$controller->assertSuperAdminBypassAllowed($request)) {
     *       throw new UnauthorizedException('超管无权限调用该接口', 40302);
     *   }
     *
     * @return bool true=放行（标注了 #[AllowSuperAdminBypass]）；false=拒绝
     */
    public function assertSuperAdminBypassAllowed(Request $request): bool
    {
        $ctx = TenantContext::getInstance();
        if (!$ctx->isSuperAdmin()) {
            return true; // 非超管：不参与白名单校验
        }

        $action = (string) $request->action();
        if ($action === '' || $action === 'null') {
            return true; // 未匹配 action（如健康检查）
        }

        try {
            $method = new \ReflectionMethod($this, $action);
        } catch (\ReflectionException) {
            return true; // 方法不存在 → 让路由层去 404
        }

        // 方法上标注了 #[AllowSuperAdminBypass] → 放行
        $attrs = $method->getAttributes(AllowSuperAdminBypass::class);
        if (count($attrs) > 0) {
            return true;
        }

        // 类上标注了 #[AllowSuperAdminBypass] → 整个类放行
        $classAttrs = (new \ReflectionClass($this))->getAttributes(AllowSuperAdminBypass::class);
        if (count($classAttrs) > 0) {
            return true;
        }

        // 未标注 → 超管调用此方法必须按租户内成员判定
        // 当前实现：超管 JWT 未带 member_id → 拒绝（40302）
        return false;
    }

    /**
     * 在响应返回后调用：超管写操作自动写审计日志
     *
     * 用法：子类不需要主动调用；由 Controller 后置中间件 / 装饰器在响应返回时触发
     *
     * @param array $params 关键请求参数（由子类提供，应已脱敏）
     * @param int   $responseStatus HTTP 响应状态码
     */
    public function dispatchAudit(array $params, int $responseStatus = 200): void
    {
        $ctx = TenantContext::getInstance();
        if (!$ctx->isSuperAdmin()) {
            return;
        }

        $request = $this->request;
        $method = strtoupper((string) $request->method());

        // 仅写操作审计（GET 不审计）
        $writeMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array($method, $writeMethods, true)) {
            return;
        }

        $service = new SuperAdminAuditService();
        $service->record([
            'action' => SuperAdminAuditService::actionFromMethod($method),
            'api_path' => (string) $request->pathinfo(),
            'method' => $method,
            'request_params' => $params,
            'response_status' => $responseStatus,
            'ip' => (string) $request->ip(),
            'user_agent' => (string) $request->header('User-Agent'),
        ]);
    }
}
