<?php

/**
 * 单元测试：ApiResponseTrait 统一响应格式
 */

describe('ApiResponseTrait', function () {
    beforeEach(function () {
        // 创建匿名类使用 Trait（方法为 protected，需反射调用）
        $this->traitInstance = new class {
            use \app\traits\ApiResponseTrait;
        };
    });

    it('success returns correct structure', function () {
        $method = new ReflectionMethod($this->traitInstance, 'success');

        $response = $method->invoke($this->traitInstance, ['id' => 1], 'created');
        $data = json_decode($response->getContent(), true);

        expect($data)
            ->toBeArray()
            ->toHaveKey('code')
            ->toHaveKey('message')
            ->toHaveKey('data')
            ->toHaveKey('timestamp')
            ->and($data['code'])->toBe(0)
            ->and($data['message'])->toBe('created')
            ->and($data['data'])->toBe(['id' => 1]);
    });

    it('error returns correct code and message', function () {
        $method = new ReflectionMethod($this->traitInstance, 'error');

        // error(string $message, int $code, mixed $data)
        $response = $method->invoke($this->traitInstance, '商户编号已存在', 10001);
        $data = json_decode($response->getContent(), true);

        expect($data)
            ->toBeArray()
            ->and($data['code'])->toBe(10001)
            ->and($data['message'])->toBe('商户编号已存在');
    });
});
