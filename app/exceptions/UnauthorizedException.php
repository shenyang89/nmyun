<?php

declare (strict_types = 1);

namespace app\exceptions;

/**
 * 权限/认证异常
 * 错误码 40101=未登录 / 40301=无权限
 */
class UnauthorizedException extends BusinessException
{
    public const NOT_LOGIN   = 40101;
    const FORBIDDEN = 40301;

    public function __construct(
        string $message = '请先登录',
        int $code = self::NOT_LOGIN,
        mixed $extra = []
    ) {
        parent::__construct($message, $code, $extra);
    }
}
