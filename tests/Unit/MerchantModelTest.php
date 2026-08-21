<?php

/**
 * 单元测试：Merchant 模型字段白名单
 */

describe('Merchant Model', function () {
    it('allows search fields returns correct keys', function () {
        $merchant = new \app\model\Merchant();

        expect($merchant->allowSearchFields())
            ->toBeArray()
            ->toContain('merchant_no', 'name', 'phone');
    });

    it('allows create fields includes merchant_no and name', function () {
        $merchant = new \app\model\Merchant();

        expect($merchant->allowCreateFields())
            ->toBeArray()
            ->toContain('merchant_no', 'name');
    });
});
