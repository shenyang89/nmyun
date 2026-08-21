<?php

declare(strict_types=1);

return [
    // 允许跨域的路径（空数组表示所有路径）
    'paths' => ['*'],

    // 允许的 Origin（开发阶段用 *，生产阶段建议改为具体域名）
    // ⚠️ 注意：如果 supports_credentials = true，这里不能写 *，必须写具体域名
    'allowed_origins' => ['*'],

    // 用正则匹配 Origin（当 allowed_origins 不够用时）
    'allowed_origins_patterns' => [],

    // 允许的 HTTP 方法
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],

    // 允许的请求头
    'allowed_headers' => ['*'],

    // 暴露给前端的响应头
    'exposed_headers' => [
        'Authorization',
        'X-Trace-Id',
        'Content-Disposition',
    ],

    // 预检请求 OPTIONS 的缓存时间（秒），减少重复预检
    'max_age' => 86400,

    // 是否允许携带 Cookie / Authorization（前端需配合 withCredentials: true）
    // ⚠️ 注意：开启后 allowed_origins 不能为 *，必须配置具体域名
    'supports_credentials' => false,
];
