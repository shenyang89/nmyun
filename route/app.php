<?php

declare(strict_types=1);
// +----------------------------------------------------------------------
// | 智慧农贸云 路由定义
// +----------------------------------------------------------------------
use think\facade\Route;

// -------- 默认示例路由 --------
Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});
Route::get('hello/:name', 'index/hello');

// -------- API 路由（统一前缀 /api） --------
Route::group('api', function () {
    // ---- 商户管理（链路A/B/C 通用）----
    Route::get('merchant/list', 'MerchantController/list');
    Route::get('merchant/:id', 'MerchantController/detail');
    Route::post('merchant', 'MerchantController/create');
    Route::put('merchant/:id', 'MerchantController/update');
    Route::patch('merchant/:id/status', 'MerchantController/changeStatus');
    Route::delete('merchant/:id', 'MerchantController/delete');

    // ---- 平台超管认证（§13.8.3 超管体系）----
    // POST /api/sadmin/login  超管登录 → 签发 JWT（白名单，无需鉴权）
    Route::post('sadmin/login', 'SuperAdminAuthController/login');

    // 后续业务模块在这里追加……
    // Route::get('supplier/list',        'SupplierController/list');      // 产地供应商（链路A）
    // Route::get('buyer/list',           'BuyerController/list');         // B端采购方（链路B）
    // Route::get('order/b/list',         'OrderController/bList');        // B端订单（链路B）
    // Route::get('order/c/list',         'OrderController/cList');        // C端订单（链路C）
    // Route::get('group-leader/list',    'GroupLeaderController/list');   // 社区团长（链路D）
    // Route::get('finance/loan/list',    'FinanceController/loanList');   // 供应链金融（链路E）
})
    // 统一挂载租户上下文中间件：解析 JWT/开发头、校验租户状态、读写拦截
    ->middleware(\app\middleware\TenantContextMiddleware::class)
    ->allowCrossDomain();
