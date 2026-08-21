<?php

/**
 * 单元测试：TenantContext / TenantStatus / TenantMemberRole
 *
 * §1.2 验收标准：覆盖 canWrite() 对 7 种状态的正确返回
 * 重点 case：欠费（UNPAID）读放行 + 写拦截返回 20003
 */

use app\support\TenantContext;
use app\support\TenantMemberRole;
use app\support\TenantStatus;

beforeEach(function () {
    // 每个用例独立上下文，避免状态污染
    TenantContext::reset();
});

describe('TenantStatus 7 种状态读写规则', function () {
    it('ACTIVE 全部放行', function () {
        $s = TenantStatus::ACTIVE;
        expect($s->canRead())->toBeTrue()
            ->and($s->canWrite())->toBeTrue()
            ->and($s->canLogin())->toBeTrue()
            ->and($s->writeErrorCode())->toBe(0) // 放行时返回 0（不该走到）
            ->and($s->writeErrorMessage())->toBe('');
    });

    it('PENDING 读放行 写拦截 返回 20005', function () {
        $s = TenantStatus::PENDING;
        expect($s->canRead())->toBeTrue()
            ->and($s->canWrite())->toBeFalse()
            ->and($s->canLogin())->toBeTrue()
            ->and($s->writeErrorCode())->toBe(20005)
            ->and($s->writeErrorMessage())->toBe('市场正在审核中，暂不可编辑');
    });

    it('DISABLED 登录拦截 读拦截 写拦截', function () {
        $s = TenantStatus::DISABLED;
        expect($s->canRead())->toBeFalse()
            ->and($s->canWrite())->toBeFalse()
            ->and($s->canLogin())->toBeFalse();

        $err = $s->loginError();
        expect($err['code'])->toBe(20002)
            ->and($err['message'])->toBe('市场已被禁用，请联系平台管理员');
    });

    it('LOCKED 登录拦截 读拦截 写拦截', function () {
        $s = TenantStatus::LOCKED;
        expect($s->canRead())->toBeFalse()
            ->and($s->canWrite())->toBeFalse()
            ->and($s->canLogin())->toBeFalse();

        $err = $s->loginError();
        expect($err['code'])->toBe(20006)
            ->and($err['message'])->toBe('市场已被锁定，请联系平台管理员');
    });

    it('UNPAID 读放行 写拦截 返回 20003（核心 case）', function () {
        $s = TenantStatus::UNPAID;
        expect($s->canRead())->toBeTrue()        // 读放行
            ->and($s->canWrite())->toBeFalse()  // 写拦截
            ->and($s->canLogin())->toBeTrue()
            ->and($s->writeErrorCode())->toBe(20003)
            ->and($s->writeErrorMessage())->toBe('您的市场已欠费，请续费后再操作');
    });

    it('REJECTED 登录拦截 读拦截 写拦截', function () {
        $s = TenantStatus::REJECTED;
        expect($s->canRead())->toBeFalse()
            ->and($s->canWrite())->toBeFalse()
            ->and($s->canLogin())->toBeFalse();

        $err = $s->loginError();
        expect($err['code'])->toBe(20007);
    });

    it('PENDING_DELETE 登录拦截 读拦截 写拦截', function () {
        $s = TenantStatus::PENDING_DELETE;
        expect($s->canRead())->toBeFalse()
            ->and($s->canWrite())->toBeFalse()
            ->and($s->canLogin())->toBeFalse();

        $err = $s->loginError();
        expect($err['code'])->toBe(20008);
    });
});

describe('TenantMemberRole 4 种角色权限', function () {
    it('OWNER 全权', function () {
        $r = TenantMemberRole::OWNER;
        expect($r->canWrite())->toBeTrue()
            ->and($r->canDelete())->toBeTrue()
            ->and($r->canManageMember())->toBeTrue()
            ->and($r->canManageOwner())->toBeTrue()
            ->and($r->label())->toBe('老板');
    });

    it('ADMIN 可写删改成员 但不可管理 Owner', function () {
        $r = TenantMemberRole::ADMIN;
        expect($r->canWrite())->toBeTrue()
            ->and($r->canDelete())->toBeTrue()
            ->and($r->canManageMember())->toBeTrue()
            ->and($r->canManageOwner())->toBeFalse();
    });

    it('OPERATOR 可写不可删不可管成员', function () {
        $r = TenantMemberRole::OPERATOR;
        expect($r->canWrite())->toBeTrue()
            ->and($r->canDelete())->toBeFalse()
            ->and($r->canManageMember())->toBeFalse();
    });

    it('AUDITOR 全部写操作拦截', function () {
        $r = TenantMemberRole::AUDITOR;
        expect($r->canWrite())->toBeFalse()
            ->and($r->canDelete())->toBeFalse()
            ->and($r->canManageMember())->toBeFalse()
            ->and($r->label())->toBe('只读审计');
    });
});

describe('TenantContext canWrite 7 状态综合判定', function () {
    $cases = [
        'PENDING' => [TenantStatus::PENDING, false, 20005, '市场正在审核中，暂不可编辑'],
        'ACTIVE' => [TenantStatus::ACTIVE, true, 0, ''],
        'DISABLED' => [TenantStatus::DISABLED, false, 20002, '市场已被禁用，请联系平台管理员'],
        'LOCKED' => [TenantStatus::LOCKED, false, 20006, '市场已被锁定，请联系平台管理员'],
        'UNPAID' => [TenantStatus::UNPAID, false, 20003, '您的市场已欠费，请续费后再操作'],
        'REJECTED' => [TenantStatus::REJECTED, false, 20007, '市场审核未通过，请补充资料后重新提交'],
        'PENDING_DELETE' => [TenantStatus::PENDING_DELETE, false, 20008, '市场已进入预删除状态，不可操作'],
    ];

    foreach ($cases as $name => [$status, $allowed, $code, $message]) {
        it("{$name} canWrite 返回 allowed={$allowed} code={$code}", function () use ($status, $allowed, $code, $message) {
            $ctx = TenantContext::getInstance()
                ->setTenantId(100)
                ->setMember(1, TenantMemberRole::OWNER)
                ->setTenantStatus($status);

            $result = $ctx->canWrite();
            expect($result['allowed'])->toBe($allowed)
                ->and($result['code'])->toBe($code)
                ->and($result['message'])->toBe($message);
        });
    }

    it('UNPAID 读放行（canRead allowed=true）', function () {
        $ctx = TenantContext::getInstance()
            ->setTenantId(100)
            ->setMember(1, TenantMemberRole::OPERATOR)
            ->setTenantStatus(TenantStatus::UNPAID);

        $r = $ctx->canRead();
        expect($r['allowed'])->toBeTrue()
            ->and($r['code'])->toBe(0);
    });

    it('DISABLED 读拦截（canRead allowed=false）', function () {
        $ctx = TenantContext::getInstance()
            ->setTenantId(100)
            ->setMember(1, TenantMemberRole::OWNER)
            ->setTenantStatus(TenantStatus::DISABLED);

        $r = $ctx->canRead();
        expect($r['allowed'])->toBeFalse()
            ->and($r['code'])->toBe(20002);
    });
});

describe('TenantContext 超管身份独立判定', function () {
    it('isSuperAdmin 走独立路径 canWrite 直接放行', function () {
        $ctx = TenantContext::getInstance()
            ->setSuperAdmin(true, 999) // 超管 adminId=999
            ->setTenantId(100);         // 选入目标租户

        expect($ctx->isSuperAdmin())->toBeTrue()
            ->and($ctx->getSuperAdminId())->toBe(999)
            ->and($ctx->canWrite()['allowed'])->toBeTrue() // 超管独立路径放行
            ->and($ctx->canRead()['allowed'])->toBeTrue();
    });

    it('AUDITOR 角色优先于 ACTIVE 状态拦截写操作', function () {
        // 租户 ACTIVE 但成员是 AUDITOR → 仍拦截
        $ctx = TenantContext::getInstance()
            ->setTenantId(100)
            ->setMember(1, TenantMemberRole::AUDITOR)
            ->setTenantStatus(TenantStatus::ACTIVE);

        $r = $ctx->canWrite();
        expect($r['allowed'])->toBeFalse()
            ->and($r['code'])->toBe(20502)
            ->and($r['message'])->toBe('当前角色无写权限');
    });

    it('requireTenantId 在未注入时抛异常（兜底）', function () {
        $ctx = TenantContext::getInstance(); // 不注入 tenantId
        expect(fn () => $ctx->requireTenantId())
            ->toThrow(\RuntimeException::class);
    });

    it('reset 后状态归零', function () {
        TenantContext::getInstance()
            ->setTenantId(100)
            ->setSuperAdmin(true, 1)
            ->setMember(1, TenantMemberRole::OWNER);

        TenantContext::reset();
        $ctx = TenantContext::getInstance();
        expect($ctx->getTenantId())->toBeNull()
            ->and($ctx->isSuperAdmin())->toBeFalse()
            ->and($ctx->getMemberId())->toBeNull();
    });
});
