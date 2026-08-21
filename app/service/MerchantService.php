<?php

declare (strict_types=1);

namespace app\service;

use app\exceptions\BusinessException;
use app\model\Merchant;
use app\repository\MerchantRepository;

/**
 * 商户业务服务：编排商户相关的业务逻辑
 * - 控制器只调用这里的方法
 * - 这里禁止直接写 SQL/Model 查询，全部通过 $this->repo() 或其他 Repository 完成
 *
 * @extends BaseService<MerchantRepository>
 */
class MerchantService extends BaseService
{
    /** @var class-string<MerchantRepository> */
    protected static string $repositoryClass = MerchantRepository::class;

    /**
     * 商户详情
     */
    public function detail(int $id): ?Merchant
    {
        /** @var Merchant|null */
        return $this->repo()->findById($id);
    }

    /**
     * 商户详情（不存在则抛业务异常）
     */
    public function detailOrFail(int $id): Merchant
    {
        return $this->repo()->findOrFail($id, '商户不存在');
    }

    /**
     * 商户列表（基础分页）
     *
     * @param array<string,mixed> $where 精确条件，例如 ['status'=>1]、[['id','>',10]]
     */
    public function listPage(array $where, int $page, int $pageSize, string $order = 'id DESC'): array
    {
        return $this->repo()->paginateWhere($where, $page, $pageSize, $order);
    }

    /**
     * 创建商户
     * 业务规则：
     * 1. merchant_no 必须唯一
     * 2. 商户名称不能为空
     *
     * @param  array{merchant_no:string, name:string, owner_name?:string, phone?:string, stall_no?:string, status?:int} $data
     * @throws BusinessException                                                                                        商户编号已存在
     */
    public function create(array $data): Merchant
    {
        // 业务规则 1：编号唯一
        if (!empty($data['merchant_no'])) {
            $exist = $this->repo()->findByMerchantNo($data['merchant_no']);
            if ($exist) {
                throw new BusinessException(
                    '商户编号已存在：' . $data['merchant_no'],
                    10001,
                    ['merchant_no' => $data['merchant_no']]
                );
            }
        }

        return $this->repo()->create([
            'merchant_no' => $data['merchant_no'],
            'name' => $data['name'],
            'owner_name' => $data['owner_name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'stall_no' => $data['stall_no'] ?? '',
            'status' => $data['status'] ?? 1,
        ]);
    }

    /**
     * 更新商户
     */
    public function update(int $id, array $data): Merchant
    {
        return $this->repo()->updateById($id, [
            'name' => $data['name'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'stall_no' => $data['stall_no'] ?? null,
            'status' => isset($data['status']) ? (int) $data['status'] : null,
        ]);
    }

    /**
     * 启用/禁用商户
     */
    public function changeStatus(int $id, int $status): Merchant
    {
        if (!in_array($status, [0, 1], true)) {
            throw new BusinessException('状态值无效（0=禁用 1=正常）', 10002);
        }
        return $this->repo()->updateById($id, ['status' => $status]);
    }

    /**
     * 删除商户
     */
    public function delete(int $id): bool
    {
        return $this->repo()->deleteById($id);
    }
}
