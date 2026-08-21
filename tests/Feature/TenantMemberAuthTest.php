<?php

/**
 * Feature 测试：Phase 1.8 租户成员认证 + 成员邀请全链路
 *
 * 覆盖验收标准（§1.8）：
 * - 租户 Owner 登录（走 tenant_member）→ 签发 JWT（payload 含 member_id + tenant_id + role）
 * - 邀请成员链接生成（24h 过期）
 * - 接受邀请 → 写入 tenant_member（status=ACTIVE + 清空 token）
 * - 邀请链接 24h 后打开 → 20504
 * - 已加入成员点击 → 20506
 * - Owner 唯一不可移除；Admin 不能管理其他 Admin
 *
 * 测试维度：
 *   1. TenantMemberAuthService 业务逻辑（login / createInvite / acceptInvite / listMembers / changeRole / removeMember）
 *   2. TenantContextMiddleware 成员 JWT 解析 + X-Tenant-Id 头注入
 */

use app\exceptions\BusinessException;
use app\exceptions\UnauthorizedException;
use app\middleware\TenantContextMiddleware;
use app\model\Tenant;
use app\model\TenantMember;
use app\service\JwtService;
use app\service\TenantMemberAuthService;
use app\support\TenantContext;
use app\support\TenantMemberRole;
use app\support\TenantStatus;
use think\facade\Db;
use think\Request;

beforeEach(function () {
    putenv('TENANT_DEV_MODE=false');
    TenantContext::reset();

    // 兼容 Pest：恢复 Model::$db / Event / Container 单例
    $app = \think\Container::getInstance();
    if (!$app instanceof \think\App) {
        throw new \RuntimeException('Container is not App');
    }
    \think\Model::setDb($app->db);
    \think\Model::setEvent($app->event);
    \think\Model::setInvoker([$app, 'invoke']);

    // 清理测试数据
    Db::name('tenant_member')->where('1=1')->delete();
    Db::name('tenant')->where('code', 'like', 'tmtest_%')->delete();

    // 创建两个测试租户（ACTIVE 状态）
    $now = date('Y-m-d H:i:s');
    $this->tenantId1 = (int) Db::name('tenant')->insertGetId([
        'name' => '成员测试市场1_' . uniqid(),
        'code' => 'tmtest_t1_' . uniqid(),
        'status' => TenantStatus::ACTIVE->value,
        'contact_name' => '测试',
        'contact_phone' => '13800000001',
        'address' => '测试地址1',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->tenantId2 = (int) Db::name('tenant')->insertGetId([
        'name' => '成员测试市场2_' . uniqid(),
        'code' => 'tmtest_t2_' . uniqid(),
        'status' => TenantStatus::ACTIVE->value,
        'contact_name' => '测试',
        'contact_phone' => '13800000002',
        'address' => '测试地址2',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 为租户1创建 Owner + Admin + Operator + Auditor 四种角色成员（已激活）
    $this->ownerMemberId = insertMember($this->tenantId1, 'owner_user', 'password_123', TenantMemberRole::OWNER->value);
    $this->adminMemberId = insertMember($this->tenantId1, 'admin_user', 'password_123', TenantMemberRole::ADMIN->value);
    $this->operatorMemberId = insertMember($this->tenantId1, 'operator_user', 'password_123', TenantMemberRole::OPERATOR->value);
    $this->auditorMemberId = insertMember($this->tenantId1, 'auditor_user', 'password_123', TenantMemberRole::AUDITOR->value);
});

afterEach(function () {
    putenv('TENANT_DEV_MODE=false');
    TenantContext::reset();
    Db::name('tenant_member')->where('1=1')->delete();
    Db::name('tenant')->where('code', 'like', 'tmtest_%')->delete();
});

/**
 * 插入一条 tenant_member 记录（绕过 scope，直接写 DB）
 */
function insertMember(int $tenantId, string $username, string $password, int $role, int $status = TenantMember::STATUS_ACTIVE): int
{
    $now = date('Y-m-d H:i:s');
    return (int) Db::name('tenant_member')->insertGetId([
        'tenant_id' => $tenantId,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'role' => $role,
        'status' => $status,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

/**
 * 在 ctx 中以指定成员身份登录（用于测试成员管理 Service）
 */
function loginAs(int $tenantId, int $memberId, TenantMemberRole $role): void
{
    TenantContext::getInstance()
        ->setTenantId($tenantId)
        ->setMember($memberId, $role)
        ->setTenantStatus(TenantStatus::ACTIVE);
}

/* =============================================================
 * Part 1: TenantMemberAuthService::login
 * ============================================================= */

describe('TenantMemberAuthService::login', function () {
    it('Owner 登录成功 → 签发 JWT 含 member_id + tenant_id + role', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        $result = $service->login('owner_user', 'password_123');

        expect($result)
            ->toHaveKey('token')
            ->toBeArray()
            ->and($result['token'])->toBeString()
            ->and($result['member'])->toBeInstanceOf(TenantMember::class);

        // JWT payload 严格按 §13.8.3：member_id + tenant_id + role，绝不夹带 is_super_admin
        $payload = JwtService::verify($result['token']);
        expect($payload)
            ->toHaveKey('member_id')
            ->toHaveKey('tenant_id')
            ->toHaveKey('role')
            ->toHaveKey('is_super_admin', false)
            ->and($payload['member_id'])->toBe($this->ownerMemberId)
            ->and($payload['tenant_id'])->toBe($this->tenantId1)
            ->and($payload['role'])->toBe(TenantMemberRole::OWNER->value);
    });

    it('密码错误返回 21001（与账号不存在同错误码防枚举）', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->login('owner_user', 'wrong_password');
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(21001)
                ->and($e->getMessage())->toBe('账号或密码错误');
        }
    });

    it('账号不存在返回 21001（不暴露账号是否存在）', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->login('non_existent_user', 'anything');
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(21001);
        }
    });

    it('租户隔离：tenant1 的 owner 在 tenant2 上下文登录失败（21001）', function () {
        // ctx.tenant_id = tenant2，查不到 owner_user
        loginAs($this->tenantId2, 0, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->login('owner_user', 'password_123');
            expect(true)->toBeFalse('应该抛异常（跨租户查不到该用户名）');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(21001);
        }
    });

    it('未激活成员登录返回 21003（邀请中）', function () {
        $pendingMemberId = insertMember(
            $this->tenantId1,
            'pending_user',
            'password_123',
            TenantMemberRole::OPERATOR->value,
            TenantMember::STATUS_PENDING
        );
        loginAs($this->tenantId1, $pendingMemberId, TenantMemberRole::OPERATOR);

        $service = new TenantMemberAuthService();
        try {
            $service->login('pending_user', 'password_123');
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(21003)
                ->and($e->getMessage())->toContain('尚未激活');
        }
    });

    it('禁用成员登录返回 21002', function () {
        $disabledMemberId = insertMember(
            $this->tenantId1,
            'disabled_user',
            'password_123',
            TenantMemberRole::OPERATOR->value,
            TenantMember::STATUS_DISABLED
        );
        loginAs($this->tenantId1, $disabledMemberId, TenantMemberRole::OPERATOR);

        $service = new TenantMemberAuthService();
        try {
            $service->login('disabled_user', 'password_123');
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(21002)
                ->and($e->getMessage())->toContain('禁用');
        }
    });
});

/* =============================================================
 * Part 2: TenantMemberAuthService::createInvite
 * ============================================================= */

describe('TenantMemberAuthService::createInvite', function () {
    it('Owner 邀请新成员成功：生成 invite_token + 24h 过期 + PENDING 状态', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        $result = $service->createInvite([
            'username' => 'invited_user_' . uniqid(),
            'role' => TenantMemberRole::OPERATOR->value,
            'email' => 'invite@example.com',
        ]);

        expect($result)
            ->toHaveKey('member')
            ->toHaveKey('invite_token')
            ->toHaveKey('invite_url')
            ->toHaveKey('invite_expires_at')
            ->and($result['invite_token'])->toBeString()
            ->and(strlen($result['invite_token']))->toBe(64)
            ->and($result['member'])->toBeInstanceOf(TenantMember::class);

        // DB 校验：状态 PENDING，token 与 expires_at 已写入
        $memberId = (int) $result['member']->id;
        $row = Db::name('tenant_member')->where('id', $memberId)->find();
        expect((int) $row['status'])->toBe(TenantMember::STATUS_PENDING)
            ->and($row['invite_token'])->toBe($result['invite_token'])
            ->and($row['invite_expires_at'])->not->toBeNull();

        // 过期时间应在 23h~25h 之后（允许 1h 误差）
        $expiresTs = strtotime($row['invite_expires_at']);
        expect($expiresTs - time())->toBeGreaterThan(23 * 3600)
            ->and($expiresTs - time())->toBeLessThan(25 * 3600);
    });

    it('Admin 邀请新成员成功', function () {
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        $result = $service->createInvite([
            'username' => 'admin_invited_' . uniqid(),
            'role' => TenantMemberRole::OPERATOR->value,
        ]);

        expect($result['member'])->toBeInstanceOf(TenantMember::class);
    });

    it('Operator 邀请抛 20503（无权管理成员）', function () {
        loginAs($this->tenantId1, $this->operatorMemberId, TenantMemberRole::OPERATOR);

        $service = new TenantMemberAuthService();
        try {
            $service->createInvite([
                'username' => 'should_fail_' . uniqid(),
                'role' => TenantMemberRole::OPERATOR->value,
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('Auditor 邀请抛 20503（无权管理成员）', function () {
        loginAs($this->tenantId1, $this->auditorMemberId, TenantMemberRole::AUDITOR);

        $service = new TenantMemberAuthService();
        try {
            $service->createInvite([
                'username' => 'should_fail_' . uniqid(),
                'role' => TenantMemberRole::OPERATOR->value,
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('邀请为 Owner 抛 20509', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->createInvite([
                'username' => 'should_fail_' . uniqid(),
                'role' => TenantMemberRole::OWNER->value,
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20509);
        }
    });

    it('用户名租户内已存在抛 20508', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->createInvite([
                'username' => 'owner_user', // 已存在
                'role' => TenantMemberRole::OPERATOR->value,
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20508);
        }
    });

    it('Admin 邀请为 Admin 抛 20503（仅 Owner 可提升 Admin）', function () {
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        try {
            $service->createInvite([
                'username' => 'should_fail_' . uniqid(),
                'role' => TenantMemberRole::ADMIN->value,
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('未注入 ctx.tenant_id 抛 20503（角色校验先于 requireTenantId）', function () {
        // 不调用 loginAs，ctx.tenant_id 和 memberRole 都为 null
        // 角色校验 if ($currentRole === null || !$currentRole->canManageMember()) 先触发 20503
        $service = new TenantMemberAuthService();
        try {
            $service->createInvite([
                'username' => 'should_fail_' . uniqid(),
                'role' => TenantMemberRole::OPERATOR->value,
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });
});

/* =============================================================
 * Part 3: TenantMemberAuthService::acceptInvite
 * ============================================================= */

describe('TenantMemberAuthService::acceptInvite', function () {
    it('接受邀请成功：写密码 + 激活 + 清 token', function () {
        // 先以 Owner 身份创建邀请
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);
        $service = new TenantMemberAuthService();
        $inviteResult = $service->createInvite([
            'username' => 'accept_test_' . uniqid(),
            'role' => TenantMemberRole::OPERATOR->value,
        ]);
        $token = $inviteResult['invite_token'];
        $memberId = (int) $inviteResult['member']->id;

        // 重置 ctx（模拟用户未登录场景，仅通过 token 反查）
        TenantContext::reset();

        // 接受邀请
        $member = $service->acceptInvite($token, 'new_password_456');

        expect($member)->toBeInstanceOf(TenantMember::class)
            ->and((int) $member->getAttr('status'))->toBe(TenantMember::STATUS_ACTIVE);

        // DB 校验：status=ACTIVE，password 已 hash，token 已清空
        $row = Db::name('tenant_member')->where('id', $memberId)->find();
        expect((int) $row['status'])->toBe(TenantMember::STATUS_ACTIVE)
            ->and($row['password'])->not->toBe('')
            ->and($row['password'])->not->toBe('new_password_456') // 不是明文
            ->and(password_verify('new_password_456', $row['password']))->toBeTrue()
            ->and($row['invite_token'])->toBeNull()
            ->and($row['invite_expires_at'])->toBeNull();

        // 验证可以用新密码登录
        loginAs($this->tenantId1, $memberId, TenantMemberRole::OPERATOR);
        $loginUsername = (string) $inviteResult['member']->getAttr('username');
        $loginResult = $service->login($loginUsername, 'new_password_456');
        expect($loginResult)->toHaveKey('token');
    });

    it('邀请 token 无效抛 20505', function () {
        $service = new TenantMemberAuthService();
        try {
            $service->acceptInvite('invalid_token_value', 'new_password_456');
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20505);
        }
    });

    it('邀请 token 已使用（成员已 ACTIVE，token 已清空）抛 20505', function () {
        // 创建邀请
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);
        $service = new TenantMemberAuthService();
        $inviteResult = $service->createInvite([
            'username' => 'twice_test_' . uniqid(),
            'role' => TenantMemberRole::OPERATOR->value,
        ]);
        $token = $inviteResult['invite_token'];

        // 接受邀请（成功后清空 invite_token）
        TenantContext::reset();
        $service->acceptInvite($token, 'new_password_456');

        // 再次接受：token 已被清空（null），findByInviteToken 查不到记录 → 20505
        TenantContext::reset();
        try {
            $service->acceptInvite($token, 'new_password_456');
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20505);
        }
    });

    it('成员已 ACTIVE 但 token 仍残留（极端场景）抛 20506', function () {
        // 模拟边界：成员状态已是 ACTIVE 但 token 未清空
        $memberId = insertMember(
            $this->tenantId1,
            'already_active_' . uniqid(),
            password_hash('pw', PASSWORD_BCRYPT),
            TenantMemberRole::OPERATOR->value,
            TenantMember::STATUS_ACTIVE
        );
        $residueToken = bin2hex(random_bytes(32));
        Db::name('tenant_member')->where('id', $memberId)->update([
            'invite_token' => $residueToken,
            'invite_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $service = new TenantMemberAuthService();
        try {
            $service->acceptInvite($residueToken, 'new_password_456');
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20506);
        }
    });

    it('邀请 token 过期抛 20504', function () {
        // 创建邀请
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);
        $service = new TenantMemberAuthService();
        $inviteResult = $service->createInvite([
            'username' => 'expired_test_' . uniqid(),
            'role' => TenantMemberRole::OPERATOR->value,
        ]);
        $token = $inviteResult['invite_token'];
        $memberId = (int) $inviteResult['member']->id;

        // 手动将过期时间改为过去
        Db::name('tenant_member')->where('id', $memberId)->update([
            'invite_expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        TenantContext::reset();
        try {
            $service->acceptInvite($token, 'new_password_456');
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20504)
                ->and($e->getMessage())->toContain('过期');
        }
    });

    it('密码长度 < 6 抛 ValidationException 40001', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);
        $service = new TenantMemberAuthService();
        $inviteResult = $service->createInvite([
            'username' => 'short_pw_test_' . uniqid(),
            'role' => TenantMemberRole::OPERATOR->value,
        ]);

        TenantContext::reset();
        try {
            $service->acceptInvite($inviteResult['invite_token'], '123');
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\ValidationException $e) {
            expect($e->getCode())->toBe(40001);
        }
    });
});

/* =============================================================
 * Part 4: TenantMemberAuthService::listMembers
 * ============================================================= */

describe('TenantMemberAuthService::listMembers', function () {
    it('列表返回当前租户内全部成员（不跨租户）', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        $result = $service->listMembers(1, 20);

        // tenant1 有 4 个成员（Owner/Admin/Operator/Auditor）
        expect($result['total'])->toBe(4)
            ->and($result['page'])->toBe(1)
            ->and($result['page_size'])->toBe(20);
    });

    it('租户隔离：tenant2 上下文查不到 tenant1 的成员', function () {
        loginAs($this->tenantId2, 0, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        $result = $service->listMembers(1, 20);

        // tenant2 没有任何成员
        expect($result['total'])->toBe(0);
    });
});

/* =============================================================
 * Part 5: TenantMemberAuthService::changeRole
 * ============================================================= */

describe('TenantMemberAuthService::changeRole', function () {
    it('Owner 把 Operator 改为 Auditor 成功', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        $updated = $service->changeRole($this->operatorMemberId, TenantMemberRole::AUDITOR->value);

        expect((int) $updated->getAttr('role'))->toBe(TenantMemberRole::AUDITOR->value);

        // DB 校验
        $row = Db::name('tenant_member')->where('id', $this->operatorMemberId)->find();
        expect((int) $row['role'])->toBe(TenantMemberRole::AUDITOR->value);
    });

    it('Admin 把 Operator 改为 Auditor 成功', function () {
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        $updated = $service->changeRole($this->operatorMemberId, TenantMemberRole::AUDITOR->value);

        expect((int) $updated->getAttr('role'))->toBe(TenantMemberRole::AUDITOR->value);
    });

    it('Operator 改角色抛 20503（无权管理成员）', function () {
        loginAs($this->tenantId1, $this->operatorMemberId, TenantMemberRole::OPERATOR);

        $service = new TenantMemberAuthService();
        try {
            $service->changeRole($this->auditorMemberId, TenantMemberRole::OPERATOR->value);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('改自己角色抛 20510', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->changeRole($this->ownerMemberId, TenantMemberRole::ADMIN->value);
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20510);
        }
    });

    it('改为 Owner 抛 20509', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->changeRole($this->operatorMemberId, TenantMemberRole::OWNER->value);
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20509);
        }
    });

    it('修改 Owner 角色抛 20507', function () {
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        try {
            $service->changeRole($this->ownerMemberId, TenantMemberRole::ADMIN->value);
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20507);
        }
    });

    it('Admin 把 Operator 改为 Admin 抛 20503（仅 Owner 可提升 Admin）', function () {
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        try {
            $service->changeRole($this->operatorMemberId, TenantMemberRole::ADMIN->value);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('Admin 修改其他 Admin 抛 20503', function () {
        // 先添加另一个 Admin
        $anotherAdminId = insertMember($this->tenantId1, 'admin2_user', 'password_123', TenantMemberRole::ADMIN->value);
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        try {
            $service->changeRole($anotherAdminId, TenantMemberRole::OPERATOR->value);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('非法角色值抛 ValidationException 40001', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->changeRole($this->operatorMemberId, 99);
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\ValidationException $e) {
            expect($e->getCode())->toBe(40001);
        }
    });
});

/* =============================================================
 * Part 6: TenantMemberAuthService::removeMember
 * ============================================================= */

describe('TenantMemberAuthService::removeMember', function () {
    it('Owner 移除 Operator 成功', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        $service->removeMember($this->operatorMemberId);

        // DB 校验：已删除
        $row = Db::name('tenant_member')->where('id', $this->operatorMemberId)->find();
        expect($row)->toBeNull();
    });

    it('移除自己抛 20511', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->removeMember($this->ownerMemberId);
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20511);
        }
    });

    it('移除 Owner 抛 20507', function () {
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        try {
            $service->removeMember($this->ownerMemberId);
            expect(true)->toBeFalse('应该抛异常');
        } catch (BusinessException $e) {
            expect($e->getCode())->toBe(20507);
        }
    });

    it('Admin 移除其他 Admin 抛 20503', function () {
        $anotherAdminId = insertMember($this->tenantId1, 'admin2_user', 'password_123', TenantMemberRole::ADMIN->value);
        loginAs($this->tenantId1, $this->adminMemberId, TenantMemberRole::ADMIN);

        $service = new TenantMemberAuthService();
        try {
            $service->removeMember($anotherAdminId);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('Operator 移除抛 20503（无权管理）', function () {
        loginAs($this->tenantId1, $this->operatorMemberId, TenantMemberRole::OPERATOR);

        $service = new TenantMemberAuthService();
        try {
            $service->removeMember($this->auditorMemberId);
            expect(true)->toBeFalse('应该抛异常');
        } catch (UnauthorizedException $e) {
            expect($e->getCode())->toBe(20503);
        }
    });

    it('移除不存在成员抛 NotFoundException 40401', function () {
        loginAs($this->tenantId1, $this->ownerMemberId, TenantMemberRole::OWNER);

        $service = new TenantMemberAuthService();
        try {
            $service->removeMember(999999999);
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\NotFoundException $e) {
            expect($e->getCode())->toBe(40401);
        }
    });
});

/* =============================================================
 * Part 7: TenantContextMiddleware 成员 JWT + X-Tenant-Id
 * ============================================================= */

function issueMemberToken(int $memberId, int $tenantId, int $role): string
{
    return JwtService::issue(JwtService::tenantMemberPayload($memberId, $tenantId, $role));
}

function makeMemberRequest(string $method, string $token, array $extraHeaders = [], string $path = ''): Request
{
    $req = new Request();
    $req->setMethod($method);
    $req->withHeader(array_merge(['Authorization' => 'Bearer ' . $token], $extraHeaders));
    if ($path !== '') {
        $req->setPathinfo($path);
    }
    return $req;
}

$okNext = fn (Request $r) => json(['code' => 0, 'message' => 'OK', 'data' => [], 'timestamp' => time()], 200);

describe('TenantContextMiddleware 成员 JWT 解析', function () use ($okNext) {
    it('成员 JWT 注入 ctx：memberId + tenantId + role', function () use ($okNext) {
        $token = issueMemberToken($this->ownerMemberId, $this->tenantId1, TenantMemberRole::OWNER->value);
        $req = makeMemberRequest('GET', $token, [], '/api/member');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);

        $ctx = TenantContext::getInstance();
        expect($ctx->isSuperAdmin())->toBeFalse()
            ->and($ctx->getMemberId())->toBe($this->ownerMemberId)
            ->and($ctx->getTenantId())->toBe($this->tenantId1)
            ->and($ctx->getMemberRole())->toBe(TenantMemberRole::OWNER);
    });

    it('Auditor 成员 JWT 调写接口 → 403 + 20502', function () use ($okNext) {
        $token = issueMemberToken($this->auditorMemberId, $this->tenantId1, TenantMemberRole::AUDITOR->value);
        $req = makeMemberRequest('POST', $token, [], '/api/merchant');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(403)
            ->and($body['code'])->toBe(20502);
    });

    it('Auditor 成员 JWT 调读接口 → 200 放行', function () use ($okNext) {
        $token = issueMemberToken($this->auditorMemberId, $this->tenantId1, TenantMemberRole::AUDITOR->value);
        $req = makeMemberRequest('GET', $token, [], '/api/member');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
    });
});

describe('TenantContextMiddleware X-Tenant-Id 头注入（登录路径）', function () use ($okNext) {
    it('无 JWT + X-Tenant-Id 头 → ctx.tenant_id 注入（用于 /api/auth/login）', function () use ($okNext) {
        $req = new Request();
        $req->setMethod('POST');
        $req->withHeader(['X-Tenant-Id' => (string) $this->tenantId1]);
        $req->setPathinfo('/api/auth/login');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        // 登录路由是认证类白名单 → 跳过读写拦截 → 200 放行
        expect($resp->getCode())->toBe(200);

        $ctx = TenantContext::getInstance();
        expect($ctx->getTenantId())->toBe($this->tenantId1)
            ->and($ctx->getMemberId())->toBeNull() // 不注入 memberId
            ->and($ctx->isSuperAdmin())->toBeFalse();
    });

    it('无 JWT + 无 X-Tenant-Id 头访问 /api/member/invite/accept → 200 放行（白名单）', function () use ($okNext) {
        $req = new Request();
        $req->setMethod('POST');
        $req->setPathinfo('/api/member/invite/accept');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        // 邀请接受是白名单路由，ctx.tenant_id 为 null，直接放行
        expect($resp->getCode())->toBe(200);
        expect(TenantContext::getInstance()->getTenantId())->toBeNull();
    });

    it('无 JWT + 不存在的 tenant_id → 404 + 20001', function () use ($okNext) {
        $req = new Request();
        $req->setMethod('POST');
        $req->withHeader(['X-Tenant-Id' => '999999999']);
        $req->setPathinfo('/api/auth/login');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(404)
            ->and($body['code'])->toBe(20001);
    });

    it('DISABLED 租户登录 → 401 + 20002（认证路由仍走状态校验）', function () use ($okNext) {
        // 把 tenant1 改为 DISABLED
        Db::name('tenant')->where('id', $this->tenantId1)->update(['status' => TenantStatus::DISABLED->value]);

        $req = new Request();
        $req->setMethod('POST');
        $req->withHeader(['X-Tenant-Id' => (string) $this->tenantId1]);
        $req->setPathinfo('/api/auth/login');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(401)
            ->and($body['code'])->toBe(20002);
    });

    it('PENDING 租户登录 → 200 放行（认证路由跳过读写拦截）', function () use ($okNext) {
        // 把 tenant1 改为 PENDING
        Db::name('tenant')->where('id', $this->tenantId1)->update(['status' => TenantStatus::PENDING->value]);

        $req = new Request();
        $req->setMethod('POST');
        $req->withHeader(['X-Tenant-Id' => (string) $this->tenantId1]);
        $req->setPathinfo('/api/auth/login');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        // PENDING 允许登录（canLogin=true），认证路由跳过 canWrite 拦截
        expect($resp->getCode())->toBe(200);
    });
});
