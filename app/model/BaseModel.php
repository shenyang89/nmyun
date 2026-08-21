<?php

declare (strict_types=1);

namespace app\model;

use app\exceptions\CrossTenantException;
use app\support\TenantContext;
use think\db\BaseQuery;
use think\facade\Log;
use think\Model;

/**
 * 模型基类
 *
 * - 约定：主键 id (BIGSERIAL / BIGINT)、create_time、update_time 自动写入
 *   （database.php 已全局开启 auto_timestamp；子类可用 $createTime/$updateTime 覆盖字段名）
 * - 软删除：业务需要时 use SoftDelete;
 *
 * §13.8.5 多租户行级隔离的「BaseModel 全局 Scope」双保险：
 *   1. 查询自动注入 `WHERE tenant_id = ctx.tenant_id`（通过 $globalScope = ['tenant']）
 *   2. INSERT 时自动写入 tenant_id；UPDATE/DELETE 时二次校验 model.tenant_id === ctx.tenant_id
 *   3. Tenant / SuperAdmin 等不带 tenant_id 的表，子类设置 `$enableTenantScope = false` 关闭
 */
abstract class BaseModel extends Model
{
    // 全局默认主键名
    protected $pk = 'id';

    // 默认每页数量（Repository 分页用）
    public const DEFAULT_PAGE_SIZE = 15;
    public const MAX_PAGE_SIZE = 100;

    /**
     * 是否启用 tenant_id 全局 Scope
     * - 默认 true：所有继承 BaseModel 的业务表自动加 tenant_id 条件
     * - Tenant / SuperAdmin 等不带 tenant_id 的表设 false
     */
    protected bool $enableTenantScope = true;

    /**
     * 全局查询范围：BaseModel 在 __construct 末尾根据 $enableTenantScope 决定是否注册 'tenant'
     * @var string[]
     */
    protected $globalScope = [];

    /**
     * 构造时根据 $enableTenantScope 注册 tenant 全局 Scope
     * （ThinkPHP Model::__construct 是 final，但 init() 是 protected static，会被首次实例化时调用一次；
     *  globalScope 是实例属性，子类每个实例都要正确初始化 → 在 __construct 末尾动态追加）
     */
    public function __construct(array|object $data = [])
    {
        parent::__construct($data);

        if ($this->enableTenantScope && !in_array('tenant', $this->globalScope, true)) {
            $this->globalScope[] = 'tenant';
        }
    }

    /**
     * tenant 全局 Scope：所有查询自动加 WHERE tenant_id = ctx.tenant_id
     *
     * 安全约定：
     * - ctx.tenant_id 必须已注入（中间件已完成）；未注入 → 视为系统级后台任务，放行不限制
     * - 超管跨租户访问时，中间件已将 X-Target-Tenant-Id 注入 ctx.tenant_id，本 Scope 同样生效
     */
    public function scopeTenant(BaseQuery $query): BaseQuery
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        // 未注入 tenant_id（如登录/健康检查/系统任务）→ 不强制限制
        // 业务层若调用业务模型查询，应通过 TenantContext::requireTenantId() 提前防御
        if ($tenantId === null) {
            return $query;
        }

        return $query->where('tenant_id', $tenantId);
    }

    /**
     * INSERT 前钩子：自动注入 tenant_id；若显式 tenant_id 与 ctx 不一致 → 拒绝
     * 返回 false 阻止插入
     */
    public static function onBeforeInsert(Model $model): bool
    {
        // 不启用 Scope 的模型（如 Tenant 自己）直接放行
        if (!($model instanceof self) || !$model->enableTenantScope) {
            return true;
        }

        $ctx = TenantContext::getInstance();
        $ctxTenantId = $ctx->getTenantId();

        // 系统级路径（无 ctx）：不强制
        if ($ctxTenantId === null) {
            return true;
        }

        $modelTenantId = $model->getAttr('tenant_id');

        // 未指定 → 自动注入
        if ($modelTenantId === null || $modelTenantId === '') {
            $model->setAttr('tenant_id', $ctxTenantId);
            return true;
        }

        // 显式指定但与 ctx 不一致 → 越权，拒绝并记录 critical 日志
        if ((int) $modelTenantId !== $ctxTenantId) {
            self::logCriticalViolation('INSERT', $model, $ctxTenantId, (int) $modelTenantId);
            // 抛异常而不是返回 false，让上层明确感知越权
            throw new CrossTenantException(
                '禁止跨租户写入：模型 tenant_id 与上下文不一致',
                40301,
                [
                    'ctx_tenant_id' => $ctxTenantId,
                    'model_tenant_id' => (int) $modelTenantId,
                    'model' => get_class($model),
                ]
            );
        }

        return true;
    }

    /**
     * UPDATE 前钩子：二次校验 model.tenant_id === ctx.tenant_id
     */
    public static function onBeforeUpdate(Model $model): bool
    {
        if (!($model instanceof self) || !$model->enableTenantScope) {
            return true;
        }

        $ctx = TenantContext::getInstance();
        $ctxTenantId = $ctx->getTenantId();
        if ($ctxTenantId === null) {
            return true;
        }

        $modelTenantId = $model->getAttr('tenant_id');
        if ($modelTenantId === null || $modelTenantId === '') {
            // 已存在的 model 没有 tenant_id，说明表结构异常，拒绝并告警
            self::logCriticalViolation('UPDATE', $model, $ctxTenantId, null);
            throw new CrossTenantException(
                'UPDATE 拦截：模型缺失 tenant_id 字段',
                40301,
                ['model' => get_class($model), 'id' => $model->getKey()]
            );
        }

        if ((int) $modelTenantId !== $ctxTenantId) {
            self::logCriticalViolation('UPDATE', $model, $ctxTenantId, (int) $modelTenantId);
            throw new CrossTenantException(
                '禁止跨租户更新：模型 tenant_id 与上下文不一致',
                40301,
                [
                    'ctx_tenant_id' => $ctxTenantId,
                    'model_tenant_id' => (int) $modelTenantId,
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]
            );
        }

        return true;
    }

    /**
     * DELETE 前钩子：二次校验 model.tenant_id === ctx.tenant_id
     */
    public static function onBeforeDelete(Model $model): bool
    {
        if (!($model instanceof self) || !$model->enableTenantScope) {
            return true;
        }

        $ctx = TenantContext::getInstance();
        $ctxTenantId = $ctx->getTenantId();
        if ($ctxTenantId === null) {
            return true;
        }

        $modelTenantId = $model->getAttr('tenant_id');
        if ($modelTenantId === null || $modelTenantId === '') {
            self::logCriticalViolation('DELETE', $model, $ctxTenantId, null);
            throw new CrossTenantException(
                'DELETE 拦截：模型缺失 tenant_id 字段',
                40301,
                ['model' => get_class($model), 'id' => $model->getKey()]
            );
        }

        if ((int) $modelTenantId !== $ctxTenantId) {
            self::logCriticalViolation('DELETE', $model, $ctxTenantId, (int) $modelTenantId);
            throw new CrossTenantException(
                '禁止跨租户删除：模型 tenant_id 与上下文不一致',
                40301,
                [
                    'ctx_tenant_id' => $ctxTenantId,
                    'model_tenant_id' => (int) $modelTenantId,
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]
            );
        }

        return true;
    }

    /**
     * 记录 CRITICAL 级别越权日志（供 §1.9 巡检脚本/告警抓取）
     *
     * 注：原本设计要 trigger_error(E_USER_ERROR) 终止进程，
     * 但 PHP 8.4+ 已弃用 E_USER_ERROR（升级为 ErrorException 反而模糊了 CrossTenantException 的语义）。
     * 现采用 Log::critical + 上层 throw CrossTenantException 的组合，更明确且可被 Pest 测试断言。
     */
    private static function logCriticalViolation(
        string $op,
        Model $model,
        ?int $ctxTenantId,
        ?int $modelTenantId
    ): void {
        try {
            Log::critical('[CROSS_TENANT_VIOLATION]', [
                'op' => $op,
                'model' => get_class($model),
                'id' => $model->getKey(),
                'ctx_tenant_id' => $ctxTenantId,
                'model_tenant_id' => $modelTenantId,
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
            ]);
        } catch (\Throwable) {
            // 日志失败不影响业务异常抛出
        }
    }

    /**
     * 查询允许的字段（避免 input() 把所有字段都塞进 where 导致 SQL 注入）
     * 示例：return ['id', 'name', 'status'];
     * @return string[]
     */
    public function allowSearchFields(): array
    {
        return [];
    }

    /**
     * 批量创建时允许写入的字段
     * @return string[]
     */
    public function allowCreateFields(): array
    {
        return [];
    }

    /**
     * 批量更新时允许写入的字段
     * @return string[]
     */
    public function allowUpdateFields(): array
    {
        return [];
    }
}
