<?php

declare(strict_types=1);

namespace app\controller;

use app\attribute\AllowSuperAdminBypass;
use app\BaseController;
use app\service\TenantPlanService;
use app\validate\TenantPlanValidate;

/**
 * 超管套餐管理控制器（平台作用域 /api/sadmin/plans/*）
 *
 * MVP 接口：
 * - GET  /api/sadmin/plans  套餐列表
 * - POST /api/sadmin/plans  创建套餐
 */
#[AllowSuperAdminBypass]
class SuperAdminPlanController extends BaseController
{
    public function __construct(
        \think\App $app,
        protected TenantPlanService $service
    ) {
        parent::__construct($app);
    }

    /**
     * GET /api/sadmin/plans  套餐列表
     */
    public function list()
    {
        $where = [];
        $tier = input('tier');
        $status = input('status');

        if ($tier !== '' && $tier !== null) {
            $where[] = ['tier', '=', (int) $tier];
        }
        if ($status !== '' && $status !== null) {
            $where[] = ['status', '=', (int) $status];
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
     * POST /api/sadmin/plans  创建套餐
     */
    public function create()
    {
        $data = $this->request->post();
        $this->validate($data, TenantPlanValidate::class . '.create');

        $plan = $this->service->create($data);
        return $this->success($plan->toArray(), '套餐创建成功');
    }
}
