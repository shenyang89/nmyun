<?php

declare (strict_types=1);

namespace app\repository;

use app\exceptions\NotFoundException;
use app\model\BaseModel;
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
        return $this->query()->find($id);
    }

    /**
     * 根据主键查找，找不到抛 NotFoundException
     *
     * @param  int|string        $id
     * @param  string            $message 可选自定义错误信息
     * @return TModel
     * @throws NotFoundException
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
        return $this->query()->where($field, $value)->find();
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
     * @throws NotFoundException
     */
    public function updateById(int|string $id, array $data): BaseModel
    {
        $model = $this->findOrFail($id);
        $model->save($data);
        return $model;
    }

    /**
     * 根据主键删除（物理删除；需要软删除的话模型里 use SoftDelete）
     *
     * @param  int|string        $id
     * @throws NotFoundException
     */
    public function deleteById(int|string $id): bool
    {
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
}
