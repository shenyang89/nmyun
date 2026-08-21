<?php

declare(strict_types=1);

namespace app\repository;

use app\model\TenantInvoice;
use app\support\TenantContext;
use think\Collection;
use think\db\BaseQuery;

/**
 * 租户账单 Repository
 *
 * 设计要点：
 * - 账单表带 tenant_id，正常路径下走全局 Scope 自动隔离
 * - 平台超管跨租户查询账单列表时，需要绕过 Scope → 提供 listForPlatformAdmin() 方法
 *   仅在 ctx.is_super_admin=true 时允许调用，否则抛 CrossTenantException
 *
 * @extends BaseRepository<TenantInvoice>
 */
class TenantInvoiceRepository extends BaseRepository
{
    /** @var class-string<TenantInvoice> */
    protected static string $modelClass = TenantInvoice::class;

    /**
     * 平台超管跨租户查询账单列表（绕过租户隔离）
     *
     * 安全约束：
     * - 必须在 ctx.is_super_admin=true 时才能调用
     * - 不允许筛选具体租户外的数据
     *
     * @param array $where 筛选条件（如 ['tenant_id' => 1, 'status' => 0]）
     */
    public function listForPlatformAdmin(array $where, int $page, int $pageSize, string $order = 'id DESC'): array
    {
        $ctx = TenantContext::getInstance();
        if (!$ctx->isSuperAdmin()) {
            throw new \app\exceptions\CrossTenantException(
                '非超管身份禁止调用平台级账单查询',
                40301
            );
        }

        // 绕过租户 Scope：使用 Db::name 直接构造查询
        $query = \think\facade\Db::name('tenant_invoice');
        foreach ($where as $k => $v) {
            if (is_int($k) && is_array($v)) {
                $query->where(...$v);
            } else {
                $query->where($k, $v);
            }
        }

        $total = (int) (clone $query)->count();
        $list = (clone $query)->order($order)->page($page, $pageSize)->select();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }
}
