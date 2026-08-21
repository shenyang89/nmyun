<?php

declare (strict_types=1);

namespace app\exceptions;

use RuntimeException;

/**
 * 业务异常基类
 * 用法：throw new BusinessException('商户不存在', 10001);
 *       throw new BusinessException('参数错误', 400);
 */
class BusinessException extends RuntimeException
{
    /** @var mixed 错误附加数据 */
    protected mixed $extra;

    public function __construct(
        string $message = '业务处理失败',
        int $code = 1,
        mixed $extra = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->extra = $extra;
    }

    public function getExtra(): mixed
    {
        return $this->extra;
    }
}
