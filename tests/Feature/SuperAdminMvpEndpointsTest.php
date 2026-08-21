<?php

/**
 * Feature 测试：Phase 1.7 超管后台 4 个 MVP 接口
 *
 * 覆盖验收标准（§1.7）：
 * - GET    /api/sadmin/tenants             超管查看租户列表（分页）
 * - POST   /api/sadmin/tenants             超管创建租户
 * - PATCH  /api/sadmin/tenants/:id/status  超管切换租户状态
 * - GET    /api/sadmin/plans               超管查看套餐列表
 * - POST   /api/sadmin/plans               超管创建套餐
 * - GET    /api/sadmin/invoices            超管跨租户查询账单
 * - GET    /api/sadmin/audit-logs           超管查询审计日志
 *
 * 鉴权约束：
 * - 超管 JWT 调用全部放行（类级 #[AllowSuperAdminBypass]）
 * - 非超管调用 → 走白名单中间件，未带 X-Target-Tenant-Id 但平台路由放行 → 401（无超管身份）
 */

use app\service\JwtService;
use app\support\TenantContext;
use app\support\TenantStatus;
use think\facade\Db;
use think\Request;

beforeEach(function () {
    putenv('TENANT_DEV_MODE=false');
    TenantContext::reset();

    // 恢复 Pest 测试环境的 Model::$db / Container
    $app = \think\Container::getInstance();
    if (!$app instanceof \think\App) {
        throw new \RuntimeException('Container is not App');
    }
    \think\Model::setDb($app->db);
    \think\Model::setEvent($app->event);
    \think\Model::setInvoker([$app, 'invoke']);

    // 清理测试数据
    Db::name('super_admin_audit_log')->where('1=1')->delete();
    Db::name('tenant_invoice')->where('invoice_no', 'like', 'TEST_%')->delete();
    Db::name('tenant_plan')->where('plan_code', 'like', 'TEST_%')->delete();
});

afterEach(function () {
    putenv('TENANT_DEV_MODE=true');
    TenantContext::reset();

    Db::name('super_admin_audit_log')->where('1=1')->delete();
    Db::name('tenant_invoice')->where('invoice_no', 'like', 'TEST_%')->delete();
    Db::name('tenant_plan')->where('plan_code', 'like', 'TEST_%')->delete();
});

function issueSuperAdminTokenForMvp(int $sadminId = 1): string
{
    return JwtService::issue(JwtService::superAdminPayload($sadminId));
}

function makeMvpRequest(string $method, string $token, array $extraHeaders = [], string $path = ''): Request
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

describe('TenantAdminService 业务逻辑', function () {
    it('create 租户成功（默认状态 PENDING）', function () {
        $service = new \app\service\TenantAdminService();
        $tenant = $service->create([
            'name' => 'MVP测试市场_' . uniqid(),
            'code' => 'mvp_' . uniqid(),
            'contact_name' => '测试',
            'contact_phone' => '13800000000',
        ]);

        expect($tenant->id)->toBeGreaterThan(0)
            ->and((int) $tenant->status)->toBe(TenantStatus::PENDING->value);
    });

    it('create 重复 code 抛 BusinessException 20009', function () {
        $service = new \app\service\TenantAdminService();
        $code = 'mvp_dup_' . uniqid();
        $service->create([
            'name' => '测试1',
            'code' => $code,
            'contact_name' => '测试',
            'contact_phone' => '138',
        ]);

        try {
            $service->create([
                'name' => '测试2',
                'code' => $code,
                'contact_name' => '测试',
                'contact_phone' => '138',
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\BusinessException $e) {
            expect($e->getCode())->toBe(20009);
        }
    });

    it('switchStatus 把 PENDING 改成 ACTIVE', function () {
        $service = new \app\service\TenantAdminService();
        $tenant = $service->create([
            'name' => '状态测试_' . uniqid(),
            'code' => 'status_' . uniqid(),
            'contact_name' => '测试',
            'contact_phone' => '138',
        ]);

        $updated = $service->switchStatus((int) $tenant->id, TenantStatus::ACTIVE->value);
        expect((int) $updated->status)->toBe(TenantStatus::ACTIVE->value);
    });

    it('switchStatus 非法状态抛 BusinessException 20010', function () {
        $service = new \app\service\TenantAdminService();
        $tenant = $service->create([
            'name' => '非法状态_' . uniqid(),
            'code' => 'invalid_' . uniqid(),
            'contact_name' => '测试',
            'contact_phone' => '138',
        ]);

        try {
            $service->switchStatus((int) $tenant->id, 99);
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\BusinessException $e) {
            expect($e->getCode())->toBe(20010);
        }
    });
});

describe('TenantPlanService 业务逻辑', function () {
    it('create 套餐成功（默认状态 DRAFT）', function () {
        $service = new \app\service\TenantPlanService();
        $plan = $service->create([
            'plan_code' => 'TEST_' . uniqid(),
            'plan_name' => '测试套餐',
            'tier' => 1,
            'price_monthly' => 9900,
            'price_yearly' => 99000,
            'max_devices' => 100,
            'max_merchants' => 50,
            'max_storage_days' => 7,
            'features' => ['saas_fee' => 9900, 'commission_tiers' => []],
        ]);

        expect($plan->id)->toBeGreaterThan(0)
            ->and((int) $plan->status)->toBe(\app\model\TenantPlan::STATUS_DRAFT);
    });

    it('create 重复 plan_code 抛 BusinessException 22001', function () {
        $service = new \app\service\TenantPlanService();
        $code = 'TEST_DUP_' . uniqid();
        $service->create([
            'plan_code' => $code,
            'plan_name' => '测试1',
            'tier' => 1,
        ]);

        try {
            $service->create([
                'plan_code' => $code,
                'plan_name' => '测试2',
                'tier' => 1,
            ]);
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\BusinessException $e) {
            expect($e->getCode())->toBe(22001);
        }
    });
});

describe('TenantInvoiceService 跨租户查询', function () {
    beforeEach(function () {
        // 插入测试账单
        $now = date('Y-m-d H:i:s');
        Db::name('tenant_invoice')->insert([
            'tenant_id' => 1,
            'plan_id' => 1,
            'billing_period' => '2026-08',
            'invoice_no' => 'TEST_202608_001',
            'saas_fee' => 9900,
            'commission_fee' => 0,
            'iot_service_fee' => 0,
            'total_amount' => 9900,
            'paid_amount' => 0,
            'status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Db::name('tenant_invoice')->insert([
            'tenant_id' => 2,
            'plan_id' => 1,
            'billing_period' => '2026-08',
            'invoice_no' => 'TEST_202608_002',
            'saas_fee' => 9900,
            'commission_fee' => 0,
            'iot_service_fee' => 0,
            'total_amount' => 9900,
            'paid_amount' => 9900,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    });

    it('超管跨租户查询账单列表', function () {
        TenantContext::getInstance()->setSuperAdmin(true, 1);
        $service = new \app\service\TenantInvoiceService();
        $result = $service->listForPlatformAdmin([], 1, 20);

        expect($result['total'])->toBeGreaterThanOrEqual(2);
    });

    it('超管按 tenant_id 筛选账单', function () {
        TenantContext::getInstance()->setSuperAdmin(true, 1);
        $service = new \app\service\TenantInvoiceService();
        $result = $service->listForPlatformAdmin(['tenant_id' => 1], 1, 20);

        expect($result['total'])->toBe(1);
    });

    it('非超管调用 listForPlatformAdmin 抛 CrossTenantException', function () {
        TenantContext::getInstance()->setTenantId(1); // 非超管
        $service = new \app\service\TenantInvoiceService();

        try {
            $service->listForPlatformAdmin([], 1, 20);
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\CrossTenantException $e) {
            expect($e->getCode())->toBe(40301);
        }
    });
});

describe('SuperAdminAuditQueryService 审计查询', function () {
    beforeEach(function () {
        // 插入测试审计日志
        $repo = new \app\repository\SuperAdminAuditLogRepository();
        TenantContext::getInstance()->setSuperAdmin(true, 99);
        $repo->create([
            'admin_id' => 99,
            'target_tenant_id' => 1,
            'action' => 'create',
            'api_path' => '/api/sadmin/tenants',
            'method' => 'POST',
            'request_params' => ['name' => '测试'],
            'response_status' => 200,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
        $repo->create([
            'admin_id' => 99,
            'target_tenant_id' => 2,
            'action' => 'delete',
            'api_path' => '/api/merchant/1',
            'method' => 'DELETE',
            'request_params' => [],
            'response_status' => 200,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
    });

    it('超管查询审计日志列表', function () {
        $service = new \app\service\SuperAdminAuditQueryService();
        $result = $service->listForSuperAdmin([], 1, 20);

        expect($result['total'])->toBeGreaterThanOrEqual(2);
    });

    it('超管按 target_tenant_id 筛选审计日志', function () {
        $service = new \app\service\SuperAdminAuditQueryService();
        $result = $service->listForSuperAdmin(['target_tenant_id' => 1], 1, 20);

        expect($result['total'])->toBe(1);
    });

    it('非超管调用 listForSuperAdmin 抛 UnauthorizedException 21003', function () {
        TenantContext::reset(); // 清除超管身份
        $service = new \app\service\SuperAdminAuditQueryService();

        try {
            $service->listForSuperAdmin([], 1, 20);
            expect(true)->toBeFalse('应该抛异常');
        } catch (\app\exceptions\UnauthorizedException $e) {
            expect($e->getCode())->toBe(21003);
        }
    });
});

describe('SuperAdminBypassMiddleware 平台路由跳过白名单', function () use ($okNext) {
    it('超管访问 /api/sadmin/tenants 跳过白名单（应放行）', function () use ($okNext) {
        $token = issueSuperAdminTokenForMvp(1);
        $req = makeMvpRequest('GET', $token, [], '/api/sadmin/tenants');

        $middleware = new \app\middleware\SuperAdminBypassMiddleware(app());
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
    });

    it('超管 POST /api/sadmin/tenants 跳过白名单（应放行，但不审计——平台路由不审计）', function () use ($okNext) {
        $token = issueSuperAdminTokenForMvp(1);
        $req = makeMvpRequest('POST', $token, [], '/api/sadmin/tenants');

        $middleware = new \app\middleware\SuperAdminBypassMiddleware(app());
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
        expect(Db::name('super_admin_audit_log')->count())->toBe(0); // 平台路由不审计
    });

    it('超管访问 /api/sadmin/audit-logs 跳过白名单', function () use ($okNext) {
        $token = issueSuperAdminTokenForMvp(1);
        $req = makeMvpRequest('GET', $token, [], '/api/sadmin/audit-logs');

        $middleware = new \app\middleware\SuperAdminBypassMiddleware(app());
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
    });
});

describe('#[AllowSuperAdminBypass] 类级注解反射', function () {
    it('SuperAdminTenantController 类标注了 #[AllowSuperAdminBypass]', function () {
        $reflection = new \ReflectionClass(\app\controller\SuperAdminTenantController::class);
        $attrs = $reflection->getAttributes(\app\attribute\AllowSuperAdminBypass::class);

        expect($attrs)->toHaveCount(1);
    });

    it('SuperAdminPlanController 类标注了 #[AllowSuperAdminBypass]', function () {
        $reflection = new \ReflectionClass(\app\controller\SuperAdminPlanController::class);
        $attrs = $reflection->getAttributes(\app\attribute\AllowSuperAdminBypass::class);

        expect($attrs)->toHaveCount(1);
    });

    it('SuperAdminInvoiceController 类标注了 #[AllowSuperAdminBypass]', function () {
        $reflection = new \ReflectionClass(\app\controller\SuperAdminInvoiceController::class);
        $attrs = $reflection->getAttributes(\app\attribute\AllowSuperAdminBypass::class);

        expect($attrs)->toHaveCount(1);
    });

    it('SuperAdminAuditLogController 类标注了 #[AllowSuperAdminBypass]', function () {
        $reflection = new \ReflectionClass(\app\controller\SuperAdminAuditLogController::class);
        $attrs = $reflection->getAttributes(\app\attribute\AllowSuperAdminBypass::class);

        expect($attrs)->toHaveCount(1);
    });
});
