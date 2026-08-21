<?php

declare (strict_types = 1);

namespace app\exceptions;

/**
 * 数据未找到异常（例如：根据 id 查不到记录）
 * 错误码统一 40401，HTTP 状态码建议 404
 */
class NotFoundException extends BusinessException
{
    public function __construct(
        string $message = '请求的资源不存在',
        int $code = 40401,
        mixed $extra = []
    ) {
        parent::__construct($message, $code, $extra);
    }
}
