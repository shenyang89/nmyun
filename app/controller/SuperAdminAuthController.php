<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\SuperAdminAuthService;
use app\validate\SuperAdminAuthValidate;

/**
 * 平台超管认证控制器
 *
 * 分层约束：
 * 1. 只做：接收请求 → 参数校验 → 调 Service → 返回 JSON
 * 2. 严禁直接 use SuperAdmin / SuperAdminRepository
 * 3. 严禁在此写 if-else 业务判断
 *
 * 路由：
 * - POST /api/sadmin/login   超管登录（白名单，无需鉴权）
 */
class SuperAdminAuthController extends BaseController
{
    public function __construct(
        \think\App $app,
        protected SuperAdminAuthService $authService
    ) {
        parent::__construct($app);
    }

    /**
     * POST /api/sadmin/login  超管登录
     *
     * Body: {"username": "string", "password": "string"}
     * Response: {"token": "jwt", "super_admin": {...}}
     */
    public function login()
    {
        $data = $this->request->post();

        // 1. 参数校验
        $this->validate($data, SuperAdminAuthValidate::class);

        // 2. 调 Service
        $result = $this->authService->login(
            (string) $data['username'],
            (string) $data['password']
        );

        // 3. 返回（隐藏 password 字段已在 SuperAdmin::$hidden 处理）
        return $this->success([
            'token' => $result['token'],
            'super_admin' => $result['super_admin']->toArray(),
        ], '登录成功');
    }
}
