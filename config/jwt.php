<?php

declare(strict_types=1);

/**
 * JWT 配置（平台超管 / 租户成员登录共用）
 *
 * 安全约定（§13.8.3）：
 * - 超管 JWT payload 只写 is_super_admin=true + sadmin_id，绝不夹带 tenant_id 或 tenant_role
 * - 租户成员 JWT payload 只写 member_id + tenant_id + role，绝不夹带 is_super_admin
 * - secret 通过 .env 注入，禁止硬编码到代码
 */

return [
    // JWT 签名密钥（生产环境必须通过 .env 注入 32+ 字节随机串）
    'secret' => env('JWT_SECRET', 'change-me-in-production-nmyun-default-secret'),

    // 算法（HS256 足够，平台内部签发；外部分发建议 RS256）
    'alg' => 'HS256',

    // Token 有效期（秒）：超管 8 小时；租户成员登录可单独覆盖
    'ttl' => env('JWT_TTL', 8 * 3600),

    // 签发方（iss claim）
    'issuer' => env('JWT_ISSUER', 'nmyun-saas'),

    // 受众（aud claim，可留空）
    'audience' => env('JWT_AUDIENCE', ''),

    // 时钟容差（秒）：防止客户端/服务器时钟漂移导致 token 立即过期
    'leeway' => 30,
];
