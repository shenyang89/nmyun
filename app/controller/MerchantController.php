<?php

declare (strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\MerchantService;

/**
 * 商户管理控制器
 * 分层约束（严格遵守）：
 * 1. 只做：接收请求 → 参数校验 → 调 Service → 返回 JSON
 * 2. 严禁直接 use Merchant / MerchantRepository
 * 3. 严禁在这里写 if-else 业务判断（写到 Service 里）
 */
class MerchantController extends BaseController
{
    public function __construct(
        \think\App $app,
        protected MerchantService $merchantService // 构造注入 Service（推荐写法）
    ) {
        parent::__construct($app);
    }

    /**
     * GET /api/merchant/list  商户列表（分页）
     */
    public function list()
    {
        $where = [];
        $status = input('status');
        $keyword = trim((string) input('keyword', ''));
        if ($status !== '' && $status !== null) {
            $where[] = ['status', '=', (int) $status];
        }
        if ($keyword !== '') {
            $where[] = ['name|stall_no|phone', 'like', '%' . $keyword . '%'];
        }

        ['page' => $page, 'page_size' => $pageSize] = $this->resolvePagination();

        $result = $this->merchantService->listPage($where, $page, $pageSize);

        return $this->paginated(
            $result['list']->toArray(),
            $result['total'],
            $result['page'],
            $result['page_size']
        );
    }

    /**
     * GET /api/merchant/:id  商户详情
     */
    public function detail(int $id)
    {
        $model = $this->merchantService->detailOrFail($id);
        return $this->success($model->toArray());
    }

    /**
     * POST /api/merchant  创建商户
     */
    public function create()
    {
        $data = $this->request->post();

        // 1. 参数校验
        $this->validate($data, \app\validate\MerchantValidate::class . '.create');

        // 2. 调用 Service
        $model = $this->merchantService->create($data);

        // 3. 返回
        return $this->success($model->toArray(), '商户创建成功', 0);
    }

    /**
     * PUT /api/merchant/:id  更新商户
     */
    public function update(int $id)
    {
        $data = $this->request->post();

        // 1. 参数校验（update 场景，merchant_no 无需传入）
        $this->validate($data, \app\validate\MerchantValidate::class . '.update');

        // 2. 调用 Service
        $model = $this->merchantService->update($id, $data);

        return $this->success($model->toArray(), '商户更新成功');
    }

    /**
     * PATCH /api/merchant/:id/status  修改商户状态
     */
    public function changeStatus(int $id)
    {
        $status = (int) $this->request->post('status');
        $model = $this->merchantService->changeStatus($id, $status);
        return $this->success($model->toArray(), '状态已更新');
    }

    /**
     * DELETE /api/merchant/:id  删除商户
     */
    public function delete(int $id)
    {
        $this->merchantService->delete($id);
        return $this->ok('商户已删除');
    }
}
