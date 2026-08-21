<?php

declare (strict_types=1);

namespace app\repository;

use app\model\Merchant;

/**
 * 商户 Repository：封装所有 Merchant 表的查询写入
 * 业务层（Service）请调用这里，不要直接 use Merchant::xxx()
 *
 * @extends BaseRepository<Merchant>
 */
class MerchantRepository extends BaseRepository
{
    /** @var class-string<Merchant> */
    protected static string $modelClass = Merchant::class;

    /**
     * 通过商户编号查找（市场内唯一）
     */
    public function findByMerchantNo(string $merchantNo): ?Merchant
    {
        /** @var Merchant|null */
        return $this->findBy('merchant_no', $merchantNo);
    }
}
