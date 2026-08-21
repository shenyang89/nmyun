<?php

declare (strict_types=1);

namespace app\service;

use app\repository\BaseRepository;

/**
 * Service 基类：编排业务逻辑
 *
 * 分层约束：
 * - Controller → Service（唯一入口）
 * - Service 调用一个或多个 Repository
 * - Service 之间可以互相调用（同级）
 * - Service 严禁直接调用 Model（所有数据访问走 Repository）
 * - Service 不直接返回 Response，只返回数组/模型/集合，由 Controller 转响应
 *
 * @template TRepo of BaseRepository
 */
abstract class BaseService
{
    /**
     * 主 Repository（通常一个 Service 对应一个主资源；其他资源通过属性注入）
     * @var class-string<TRepo>
     */
    protected static string $repositoryClass;

    protected ?BaseRepository $repository = null;

    /**
     * 获取主 Repository（单例）
     * @return TRepo
     */
    protected function repo(): BaseRepository
    {
        if ($this->repository === null) {
            $class = static::$repositoryClass;
            $this->repository = new $class();
        }
        return $this->repository;
    }
}
