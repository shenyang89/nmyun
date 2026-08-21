<?php

declare(strict_types=1);

namespace app\controller;

use app\attribute\AllowSuperAdminBypass;
use app\BaseController;
use app\service\TenantAdminService;
use app\validate\TenantAdminValidate;

/**
 * 超管租户管理控制器（平台作用域 /api/sadmin/tenants/*）
 *
 * MVP 接口：
 * - GET   /api/sadmin/tenants             租户列表
 * - POST  /api/sadmin/tenants             创建租户
 * - PATCH /api/sadmin/tenants/:id/status 切换租户状态
 *
 * 鉴权：
 * - 类级 #[AllowSuperAdminBypass]：超管 JWT 调用全部放行
 * - 路由级白名单：SuperAdminBypassMiddleware 的 isPlatformRoute 跳过白名单 + 审计
 */
#[AllowSuperAdminBypass]
class SuperAdminTenantController extends BaseController
{
    public function __construct(
        \think\App $app,
        protected TenantAdminService $service
    ) {
        parent::__construct($app);
    }

    /**
     * GET /api/sadmin/tenants  租户列表（分页 + 状态筛选）
     */
    public function list()
    {
        $where = [];
        $status = input('status');
        $code = trim((string) input('code', ''));
        $name = trim((string) input('keyword', ''));

        if ($status !== '' && $status !== null) {
            $where[] = ['status', '=', (int) $status];
        }
        if ($code !== '') {
            $where[] = ['code', 'like', '%' . $code . '%'];
        }
        if ($name !== '') {
            $where[] = ['name', 'like', '%' . $name . '%'];
        }

        ['page' => $page, 'page_size' => $pageSize] = $this->resolvePagination();
        $result = $this->service->listPage($where, $page, $pageSize);

        return $this->paginated(
            $result['list']->toArray(),
            $result['total'],
            $result['page'],
            $result['page_size']
        );
    }

    /**
     * POST /api/sadmin/tenants  创建租户
     */
    public function create()
    {
        $data = $this->request->post();
        $this->validate($data, TenantAdminValidate::class . '.create');

        $tenant = $this->service->create($data);
        return $this->success($tenant->toArray(), '市场创建成功');
    }

    /**
     * PATCH /api/sadmin/tenants/:id/status  切换租户状态
     */
    public function switchStatus(int $id)
    {
        $data = $this->request->post();
        $this->validate($data, TenantAdminValidate::class . '.switchStatus');

        $tenant = $this->service->switchStatus($id, (int) $data['status']);
        return $this->success($tenant->toArray(), '状态已更新');
    }
}
