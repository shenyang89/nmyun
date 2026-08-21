<?php

// +----------------------------------------------------------------------
// | 缓存设置
// | 智慧农贸云：默认使用 Redis（生产/开发通用），File 作为开发备用
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    // ⚠️ 新手开发阶段推荐用 file（不需要运行 Redis 服务）
    //    当 Redis 服务可用后，在 .env 中设置 CACHE_DRIVER=redis 即可切换
    'default' => env('CACHE_DRIVER', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => env('CACHE_PREFIX', 'nmyun_'),
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],

        // Redis 缓存（推荐）
        'redis' => [
            // 驱动方式
            'type'       => 'Redis',
            // 服务器地址
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            // 端口
            'port'       => env('REDIS_PORT', 6379),
            // 密码（无密码留空）
            'password'   => env('REDIS_PASSWORD', ''),
            // 数据库索引（0-15，建议按业务分：0通用 1会话 2队列）
            'select'     => env('REDIS_DB', 0),
            // 缓存前缀（统一用 nmyun_ 避免多项目冲突）
            'prefix'     => env('CACHE_PREFIX', 'nmyun_'),
            // 缓存有效期 0表示永久缓存
            'expire'     => env('CACHE_EXPIRE', 3600),
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制：默认用 PHP 序列化，如需与其他系统交互可改为 json
            'serialize'  => [],
            // 连接超时（秒）
            'timeout'    => 2,
            // 是否持久化连接
            'persistent' => false,
        ],

        // 更多的缓存连接
    ],
];
