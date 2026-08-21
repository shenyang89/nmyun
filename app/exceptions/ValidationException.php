<?php

declare (strict_types = 1);

namespace app\exceptions;

/**
 * 参数验证失败异常（比系统 ValidateException 更可控）
 */
class ValidationException extends BusinessException
{
    public function __construct(
        string $message = '参数校验失败',
        int $code = 40001,
        mixed $extra = []
    ) {
        parent::__construct($message, $code, $extra);
    }
}
