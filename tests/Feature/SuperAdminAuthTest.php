<?php

/**
 * Feature 测试：Phase 1.5 平台超管认证全链路
 *
 * 覆盖验收标准（§1.5）：
 * - 超管不传 X-Target-Tenant-Id → 422 提示必须指定目标租户
 * - 超管传 X-Target-Tenant-Id=2 → ctx.tenant_id=2 且 ctx.is_super_admin=true
 * - 登录成功签发 JWT，payload 仅含 is_super_admin + sadmin_id
 * - 登录失败统一错误码（防枚举）
 * - JWT 解析失败 → 401
 * - 超管 JWT payload 夹带 tenant_id 视为伪造 → 401
 *
 * 测试维度：
 *   1. SuperAdminAuthService::login 业务逻辑（4 用例）
 *   2. TenantContextMiddleware JWT 解析 + X-Target-Tenant-Id 校验（8 用例）
 */

use app\middleware\TenantContextMiddleware;
use app\model\SuperAdmin;
use app\model\Tenant;
use app\service\JwtService;
use app\service\SuperAdminAuthService;
use app\support\TenantContext;
use app\support\TenantStatus;
use think\facade\Db;
use think\Request;

beforeEach(function () {
    // 启用开发模式，方便初始化测试数据时跳过 JWT
    putenv('TENANT_DEV_MODE=true');
    TenantContext::reset();

    // 兼容 Pest：恢复 Model::$db / Event / Container 单例
    $app = \think\Container::getInstance();
    if (!$app instanceof \think\App) {
        throw new \RuntimeException('Container is not App');
    }
    \think\Model::setDb($app->db);
    \think\Model::setEvent($app->event);
    \think\Model::setInvoker([$app, 'invoke']);

    // 清理 super_admin 表，插入测试超管
    Db::name('super_admin')->where('1=1')->delete();
    $now = date('Y-m-d H:i:s');
    Db::name('super_admin')->insert([
        'username' => 'sadmin_test',
        'password' => password_hash('sadmin_password_123', PASSWORD_BCRYPT),
        'name' => '测试超管',
        'status' => SuperAdmin::STATUS_ACTIVE,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    Db::name('super_admin')->insert([
        'username' => 'sadmin_disabled',
        'password' => password_hash('sadmin_password_123', PASSWORD_BCRYPT),
        'name' => '已禁用超管',
        'status' => SuperAdmin::STATUS_DISABLED,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

afterEach(function () {
    putenv('TENANT_DEV_MODE=false');
    TenantContext::reset();
    Db::name('super_admin')->where('1=1')->delete();
});

/* =============================================================
 * Part 1: SuperAdminAuthService::login 业务逻辑
 * ============================================================= */

describe('SuperAdminAuthService::login', function () {
    it('登录成功返回 JWT + super_admin 信息', function () {
        $service = new SuperAdminAuthService();
        $result = $service->login('sadmin_test', 'sadmin_password_123');

        expect($result)
            ->toHaveKey('token')
            ->toBeArray()
            ->and($result['token'])->toBeString()
            ->and($result['super_admin'])->toBeInstanceOf(SuperAdmin::class);

        // JWT payload 严格按 §13.8.3 约束：仅 is_super_admin + sadmin_id
        $payload = JwtService::verify($result['token']);
        expect($payload)
            ->toHaveKey('is_super_admin', true)
            ->toHaveKey('sadmin_id')
            ->not->toHaveKey('tenant_id')
            ->not->toHaveKey('role');
    });

    it('密码错误返回 21001（与账号不存在同错误码防枚举）', function () {
        $service = new SuperAdminAuthService();
        try {
            $service->login('sadmin_test', 'wrong_password');
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\UnauthorizedException $e) {
            expect($e->getCode())->toBe(21001)
                ->and($e->getMessage())->toBe('账号或密码错误');
        }
    });

    it('账号不存在返回 21001（不暴露账号是否存在）', function () {
        $service = new SuperAdminAuthService();
        try {
            $service->login('non_existent_user', 'anything');
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\UnauthorizedException $e) {
            expect($e->getCode())->toBe(21001);
        }
    });

    it('已禁用账号返回 21002', function () {
        $service = new SuperAdminAuthService();
        try {
            $service->login('sadmin_disabled', 'sadmin_password_123');
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\UnauthorizedException $e) {
            expect($e->getCode())->toBe(21002)
                ->and($e->getMessage())->toContain('禁用');
        }
    });
});

/* =============================================================
 * Part 2: TenantContextMiddleware JWT 解析 + X-Target-Tenant-Id
 * ============================================================= */

function createActiveTenant(): int
{
    $tenant = new Tenant();
    $tenant->save([
        'name' => '超管测试市场_' . uniqid(),
        'code' => 'sadmin_test_' . uniqid(),
        'status' => TenantStatus::ACTIVE->value,
        'contact_name' => '测试',
        'contact_phone' => '13800000000',
        'address' => '测试地址',
    ]);
    return (int) $tenant->id;
}

function issueSuperAdminToken(int $sadminId = 1): string
{
    return JwtService::issue(JwtService::superAdminPayload($sadminId));
}

function issueTamperedSuperAdminToken(): string
{
    // 故意夹带 tenant_id → 违反 §13.8.3
    return JwtService::issue([
        'is_super_admin' => true,
        'sadmin_id' => 1,
        'tenant_id' => 2, // ← 非法字段
    ]);
}

/**
 * 构造带 Authorization 头的 Request
 */
function makeJwtRequest(string $method, string $token, array $extraHeaders = [], string $path = ''): Request
{
    $req = new Request();
    $req->setMethod($method);
    $req->withHeader(array_merge(['Authorization' => 'Bearer ' . $token], $extraHeaders));
    // 设置 pathinfo 让 isPlatformRoute 判定生效
    if ($path !== '') {
        $req->setPathinfo($path);
    }
    return $req;
}

$okNext = fn (Request $r) => json(['code' => 0, 'message' => 'OK', 'data' => [], 'timestamp' => time()], 200);

describe('超管 JWT + X-Target-Tenant-Id 全链路', function () use ($okNext) {
    it('超管 JWT + X-Target-Tenant-Id 注入 ctx：is_super_admin=true 且 tenant_id=target', function () use ($okNext) {
        $tenantId = createActiveTenant();
        $token = issueSuperAdminToken(1);

        $req = makeJwtRequest('POST', $token, [
            'X-Target-Tenant-Id' => (string) $tenantId,
        ]);

        // 关闭 dev mode，强制走 JWT 路径
        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);

        $ctx = TenantContext::getInstance();
        expect($ctx->isSuperAdmin())->toBeTrue()
            ->and($ctx->getSuperAdminId())->toBe(1)
            ->and($ctx->getTenantId())->toBe($tenantId);
    });

    it('超管 JWT 不传 X-Target-Tenant-Id 访问租户作用域路由 → 422 + 40302', function () use ($okNext) {
        $token = issueSuperAdminToken(1);

        // 路由 /api/merchant/list 不在白名单，属于租户作用域
        $req = makeJwtRequest('GET', $token, [], '/api/merchant/list');

        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(422)
            ->and($body['code'])->toBe(40302)
            ->and($body['message'])->toContain('X-Target-Tenant-Id');
    });

    it('超管 JWT 不传 X-Target-Tenant-Id 访问平台作用域路由 → 200 放行', function () use ($okNext) {
        $token = issueSuperAdminToken(1);

        // /api/sadmin/tenants 属于平台白名单
        $req = makeJwtRequest('GET', $token, [], '/api/sadmin/tenants');

        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
        $ctx = TenantContext::getInstance();
        expect($ctx->isSuperAdmin())->toBeTrue()
            ->and($ctx->getTenantId())->toBeNull();
    });

    it('无 Authorization 头访问 /api/sadmin/login → 白名单放行（用于登录接口）', function () use ($okNext) {
        $req = makeJwtRequest('POST', '', [], '/api/sadmin/login');
        // makeJwtRequest 会设 Authorization: Bearer （空），移除
        $req->withHeader(['Authorization' => '']);

        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
        expect(TenantContext::getInstance()->isSuperAdmin())->toBeFalse();
    });

    it('无效 JWT → 401', function () use ($okNext) {
        $req = makeJwtRequest('GET', 'invalid.token.value', [], '/api/merchant/list');

        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(401)
            ->and($body['code'])->toBeIn([40103, 40104, 40105]);
    });

    it('超管 JWT 夹带 tenant_id 视为伪造 → 401', function () use ($okNext) {
        $token = issueTamperedSuperAdminToken();
        $req = makeJwtRequest('GET', $token, [], '/api/merchant/list');

        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(401)
            ->and($body['code'])->toBe(40104);
    });

    it('超管 JWT + X-Target-Tenant-Id 指向不存在租户 → 404 + 20001', function () use ($okNext) {
        $token = issueSuperAdminToken(1);
        $req = makeJwtRequest('GET', $token, [
            'X-Target-Tenant-Id' => '999999999',
        ], '/api/merchant/list');

        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(404)
            ->and($body['code'])->toBe(20001);
    });

    it('超管 JWT 跨租户访问 DISABLED 状态租户 → 放行（超管可绕过状态校验）', function () use ($okNext) {
        $tenantId = createActiveTenant();
        // 改成 DISABLED
        Db::name('tenant')->where('id', $tenantId)->update(['status' => TenantStatus::DISABLED->value]);

        $token = issueSuperAdminToken(1);
        $req = makeJwtRequest('POST', $token, [
            'X-Target-Tenant-Id' => (string) $tenantId,
        ], '/api/merchant');

        putenv('TENANT_DEV_MODE=false');

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
    });
});
