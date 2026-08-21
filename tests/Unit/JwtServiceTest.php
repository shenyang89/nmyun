<?php

/**
 * Unit 测试：JwtService 签发与验证
 *
 * 覆盖场景：
 * - issue + verify roundtrip 正常
 * - superAdminPayload 结构正确（无 tenant_id/role 夹带）
 * - tenantMemberPayload 结构正确
 * - 非法 token 抛 RuntimeException + 40104
 * - 篡改签名的 token 抛 40103
 * - 自定义 claims（iss/iat/exp/jti）由 issue 注入
 */

use app\service\JwtService;

it('issue + verify roundtrip 正常工作', function () {
    $payload = JwtService::superAdminPayload(42);
    $token = JwtService::issue($payload);

    expect($token)->toBeString()
        ->and(strlen($token))->toBeGreaterThan(20);

    $decoded = JwtService::verify($token);
    expect($decoded)
        ->toHaveKey('is_super_admin', true)
        ->toHaveKey('sadmin_id', 42)
        ->toHaveKey('iss')
        ->toHaveKey('iat')
        ->toHaveKey('exp')
        ->toHaveKey('jti');
});

it('superAdminPayload 只含 is_super_admin + sadmin_id', function () {
    $payload = JwtService::superAdminPayload(99);

    expect($payload)
        ->toBe(['is_super_admin' => true, 'sadmin_id' => 99])
        ->not->toHaveKey('tenant_id')
        ->not->toHaveKey('member_id')
        ->not->toHaveKey('role');
});

it('tenantMemberPayload 含 member_id + tenant_id + role', function () {
    $payload = JwtService::tenantMemberPayload(5, 7, 2);

    expect($payload)
        ->toBe([
            'is_super_admin' => false,
            'member_id' => 5,
            'tenant_id' => 7,
            'role' => 2,
        ])
        ->not->toHaveKey('sadmin_id');
});

it('非法 token verify 抛 RuntimeException + 40104', function () {
    try {
        JwtService::verify('not.a.valid.jwt');
        expect(true)->toBeFalse('应该抛 RuntimeException');
    } catch (\RuntimeException $e) {
        expect($e->getCode())->toBe(40104);
    }
});

it('篡改签名的 token 抛 40103', function () {
    $token = JwtService::issue(JwtService::superAdminPayload(1));
    // 破坏签名最后 5 个字符
    $tampered = substr($token, 0, -5) . 'XXXXX';

    try {
        JwtService::verify($tampered);
        expect(true)->toBeFalse('应该抛 RuntimeException');
    } catch (\RuntimeException $e) {
        expect($e->getCode())->toBe(40103);
    }
});

it('空 token verify 抛 RuntimeException', function () {
    JwtService::verify('');
})->throws(\RuntimeException::class);
