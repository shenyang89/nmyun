<?php

declare (strict_types=1);

namespace app\repository;

use app\exceptions\CrossTenantException;
use app\exceptions\NotFoundException;
use app\model\BaseModel;
use app\support\TenantContext;
use think\Collection;
use think\db\BaseQuery;
use think\facade\Db;
use think\Model;

/**
 * Repository 基类：封装所有数据库交互
 *
 * 规则：
 * - Service 层**只能**调用 Repository 读写数据，不能直接 use Model::xxx()
 * - Repository 只做 CRUD + 基础查询构造，不写业务逻辑
 * - Repository 不 throw 业务异常，但会抛 NotFoundException（当 findOrFail 未找到时）
 *
 * §13.8.5 多租户隔离「Repository 二次校验」双保险：
 * - findById/findOrFail/updateById/deleteById 取到 model 后再次断言 model.tenant_id === ctx.tenant_id
 * - 防止开发者绕过 BaseModel 全局 Scope（withoutScope / 裸 Db::name）造成的越权
 * - 不一致立即抛 CrossTenantException(40301)
 *
 * @template TModel of BaseModel
 */
abstract class BaseRepository
{
    /**
     * 当前 Repository 对应的模型类名（子类必须赋值）
     * @var class-string<TModel>
     */
    protected static string $modelClass;

    /**
     * 新建查询构造器
     */
    protected function query(): BaseQuery
    {
        return $this->newQuery();
    }

    protected function newQuery(): BaseQuery
    {
        $class = static::$modelClass;
        return $class::query();
    }

    /**
     * 获取模型实例
     * @return TModel
     */
    protected function newModelInstance(): BaseModel
    {
        $class = static::$modelClass;
        return new $class();
    }

    /* ================= 基础查询 ================= */

    public function findById(int|string $id): ?BaseModel
    {
        /** @var BaseModel|null */
        $model = $this->query()->find($id);

        // 二次校验：scope 失效时通过 Repository 兜底（§13.8.5 双保险）
        if ($model !== null) {
            $this->assertTenantMatch($model);
        }

        return $model;
    }

    /**
     * 根据主键查找，找不到抛 NotFoundException
     *
     * @param  int|string        $id
     * @param  string            $message 可选自定义错误信息
     * @return TModel
     * @throws NotFoundException | CrossTenantException
     */
    public function findOrFail(int|string $id, string $message = ''): BaseModel
    {
        $model = $this->findById($id);
        if ($model === null) {
            throw new NotFoundException($message ?: '记录不存在', 40401, ['id' => $id]);
        }
        return $model;
    }

    /**
     * 单条件查找
     */
    public function findBy(string $field, mixed $value): ?BaseModel
    {
        /** @var BaseModel|null */
        $model = $this->query()->where($field, $value)->find();
        if ($model !== null) {
            $this->assertTenantMatch($model);
        }
        return $model;
    }

    /**
     * 根据条件取所有
     * @param array<string,mixed> $where
     */
    public function getWhere(array $where, string $order = 'id DESC'): Collection
    {
        return $this->applyWhere($this->query(), $where)
            ->order($order)
            ->select();
    }

    /**
     * 基础分页
     *
     * @param  array<string,mixed>                                            $where    精确匹配条件 [ 'status' => 1, 'xxx' => 'yyy' ]
     * @param  int                                                            $page
     * @param  int                                                            $pageSize
     * @param  string                                                         $order
     * @return array{list: Collection, total: int, page: int, page_size: int}
     */
    public function paginateWhere(
        array $where = [],
        int $page = 1,
        int $pageSize = 15,
        string $order = 'id DESC'
    ): array {
        $query = $this->applyWhere($this->query(), $where);

        $total = (int) $query->count();
        $list = (clone $query)
            ->order($order)
            ->page($page, $pageSize)
            ->select();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /* ================= 写入 ================= */

    /**
     * 新建单条记录
     *
     * @param  array<string,mixed> $data
     * @return TModel
     * @throws CrossTenantException 当 data.tenant_id 与 ctx 不一致时（onBeforeInsert + Repository 双保险）
     */
    public function create(array $data): BaseModel
    {
        $class = static::$modelClass;
        $model = new $class();
        $model->save($data);
        return $model;
    }

    /**
     * 根据主键更新
     *
     * @param  int|string          $id
     * @param  array<string,mixed> $data
     * @return TModel
     * @throws NotFoundException | CrossTenantException
     */
    public function updateById(int|string $id, array $data): BaseModel
    {
        // findOrFail 内部已经做了二次校验
        $model = $this->findOrFail($id);
        $model->save($data);
        return $model;
    }

    /**
     * 根据主键删除（物理删除；需要软删除的话模型里 use SoftDelete）
     *
     * @param  int|string        $id
     * @throws NotFoundException | CrossTenantException
     */
    public function deleteById(int|string $id): bool
    {
        // findOrFail 内部已经做了二次校验
        $model = $this->findOrFail($id);
        return $model->delete();
    }

    /* ================= 事务 ================= */

    /**
     * 事务闭包（快捷方法，避免 use Db 到处写）
     *
     * @template R
     * @param  callable():R $fn
     * @return R
     */
    public function transaction(callable $fn): mixed
    {
        return Db::transaction($fn);
    }

    /* ================= 内部辅助 ================= */

    /**
     * 将条件数组应用到查询（支持 ['field' => value] 精确匹配 + ['field', '>', value] 高级条件的混合写法）
     */
    protected function applyWhere(BaseQuery $query, array $where): BaseQuery
    {
        foreach ($where as $k => $v) {
            if (is_int($k) && is_array($v)) {
                // 高级写法：['status', '>', 1]
                $query->where(...$v);
            } else {
                // 精确匹配：['status' => 1]
                $query->where($k, $v);
            }
        }
        return $query;
    }

    /**
     * 二次校验：取到的 model.tenant_id 必须与 ctx.tenant_id 一致
     *
     * 设计意图：
     * - BaseModel::scopeTenant 已经在 SQL where 注入 tenant_id，正常路径下不会越权
     * - 但是当开发者使用 `withoutScope(['tenant'])` 或裸 `Db::name('merchant')->find()` 时，
     *   Scope 失效，本方法作为最后一道兜底
     * - 仅对启用了 tenant_scope 的模型生效（避免对 Tenant/SuperAdmin 等表误判）
     *
     * @throws CrossTenantException 当 model.tenant_id !== ctx.tenant_id 时
     */
    private function assertTenantMatch(BaseModel $model): void
    {
        // 当前模型未启用 tenant scope（如 Tenant/SuperAdmin）→ 跳过
        if (!property_exists($model, 'enableTenantScope') || !$model->enableTenantScope) {
            return;
        }

        $ctx = TenantContext::getInstance();
        $ctxTenantId = $ctx->getTenantId();

        // 系统级路径（无 ctx）→ 不强制（如 Think Command 巡检脚本）
        if ($ctxTenantId === null) {
            return;
        }

        $modelTenantId = $model->getAttr('tenant_id');
        if ($modelTenantId === null || $modelTenantId === '') {
            // 业务表必须有 tenant_id，缺失视为表结构异常
            throw new CrossTenantException(
                '记录缺失 tenant_id 字段，无法通过租户隔离校验',
                40301,
                ['model' => get_class($model), 'id' => $model->getKey(), 'ctx_tenant_id' => $ctxTenantId]
            );
        }

        if ((int) $modelTenantId !== $ctxTenantId) {
            throw new CrossTenantException(
                '禁止跨租户访问：记录 tenant_id 与上下文不一致',
                40301,
                [
                    'ctx_tenant_id' => $ctxTenantId,
                    'model_tenant_id' => (int) $modelTenantId,
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                ]
            );
        }
    }
}
