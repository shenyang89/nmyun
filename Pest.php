<?php

/**
 * Pest 配置 / Bootstrap
 * 使用：vendor/bin/pest tests/
 */

// 加载 Composer 自动加载
require __DIR__ . '/vendor/autoload.php';

// 加载 ThinkPHP 辅助函数（json / env / config 等）
require __DIR__ . '/vendor/topthink/framework/src/helper.php';

// 启动 ThinkPHP App（让 Env / Config / Db 等 facade 可用）
// Feature 测试需要 Model 查询 DB，必须先初始化应用容器
$app = new \think\App();
$app->debug = true;
$app->rootPath = __DIR__ . DIRECTORY_SEPARATOR;
$app->initialize();

// 显式确保 Container 单例指向 App 实例
\think\Container::setInstance($app);




