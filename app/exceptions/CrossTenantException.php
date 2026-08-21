<?php

declare(strict_types=1);

namespace app\exceptions;

/**
 * 跨租户访问异常（§13.8.5 安全红线）
 *
 * 触发场景：
 * - INSERT 时模型显式 tenant_id 与 ctx.tenant_id 不一致
 * - UPDATE/DELETE 时 model.tenant_id !== ctx.tenant_id
 * - BaseRepository::findById/findOrFail/updateById/deleteById 二次校验失败
 *
 * 统一错误码 40301，HTTP 状态码建议 403。
 */
class CrossTenantException extends BusinessException
{
    public function __construct(
        string $message = '跨租户访问被拒绝',
        int $code = 40301,
        mixed $extra = []
    ) {
        parent::__construct($message, $code, $extra);
    }
}
