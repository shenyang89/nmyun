<?php

declare (strict_types=1);

namespace app\model;

use think\Model;

/**
 * 模型基类
 * - 约定：主键 id (BIGSERIAL / BIGINT)、create_time、update_time 自动写入（database.php 已全局开启 auto_timestamp）
 * - 软删除：业务需要时 use SoftDelete;
 */
abstract class BaseModel extends Model
{
    // 全局默认主键名
    protected $pk = 'id';

    // 自动时间戳（默认已全局开启 true；这里留注释方便按模型单独关闭）
    // protected $autoWriteTimestamp = true;
    // protected $createTime = 'create_time';
    // protected $updateTime = 'update_time';

    // 默认每页数量（Repository 分页用）
    public const DEFAULT_PAGE_SIZE = 15;
    public const MAX_PAGE_SIZE = 100;

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
