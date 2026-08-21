<?php

/**
 * Feature 测试：TenantContextMiddleware 三段式职责中间件
 *
 * §1.3 验收标准：PHPUnit 写 7 个 DataProvider 用例，每个状态分别验证 GET/POST 返回的错误码与 body
 *
 * 用 Pest dataset 实现 DataProvider 风格：
 *   7 种状态 × 2 种方法（GET/POST）= 14 个用例
 *   覆盖：ACTIVE 全放行 / UNPAID 读放行写拦截 20003 / PENDING 读放行写拦截 20005
 *        DISABLED/LOCKED/REJECTED/PENDING_DELETE 登录阶段拦截（401）
 */

use app\middleware\TenantContextMiddleware;
use app\model\Tenant;
use app\support\TenantContext;
use app\support\TenantMemberRole;
use app\support\TenantStatus;
use think\Request;

beforeEach(function () {
    // 启用开发模式，允许从 HTTP 头注入身份（仅测试用）
    putenv('TENANT_DEV_MODE=true');
    TenantContext::reset();

    // 兼容 Pest：恢复 Model::$db / Event / Container 单例
    $app = \think\Container::getInstance();
    if (!$app instanceof \think\App) {
        throw new \RuntimeException('Container is not App: ' . (is_object($app) ? get_class($app) : 'null'));
    }
    $db = $app->db;
    if (!$db) {
        throw new \RuntimeException('app->db is null');
    }
    \think\Model::setDb($db);
    \think\Model::setEvent($app->event);
    \think\Model::setInvoker([$app, 'invoke']);
});

afterEach(function () {
    putenv('TENANT_DEV_MODE=false');
});

/**
 * 在 DB 中创建指定状态的租户并返回 id
 */
function createTestTenant(TenantStatus $status): int
{
    $tenant = new Tenant();
    $tenant->save([
        'name' => "测试市场_{$status->name}",
        'code' => "test_{$status->value}_" . uniqid(),
        'status' => $status->value,
        'contact_name' => '测试联系人',
        'contact_phone' => '13800000000',
        'address' => '测试地址',
    ]);
    return (int) $tenant->id;
}

/**
 * 构造带身份头的 Request
 */
function makeRequest(string $method, int $tenantId, ?int $memberId = 1, ?int $memberRole = 1): Request
{
    $req = new Request();
    $req->setMethod($method);
    $headers = ['x-tenant-id' => (string) $tenantId];
    if ($memberId !== null) {
        $headers['x-tenant-member-id'] = (string) $memberId;
        $headers['x-tenant-member-role'] = (string) $memberRole;
    }
    $req->withHeader($headers);
    return $req;
}

/**
 * 中间件闭包 next：返回 200 OK 用于放行验证
 */
$okNext = fn (Request $r) => json(['code' => 0, 'message' => 'OK', 'data' => [], 'timestamp' => time()], 200);

describe('7 种状态 GET/POST 完整校验（DataProvider 风格）', function () use ($okNext) {
    $dataset = [
        // [status, GET allowed, GET code, POST allowed, POST code, POST message]
        'PENDING - 读放行 写拦截 20005' => [TenantStatus::PENDING, true, 0, false, 20005, '市场正在审核中，暂不可编辑'],
        'ACTIVE - 全部放行' => [TenantStatus::ACTIVE, true, 0, true, 0, ''],
        'DISABLED - 登录拦截 401 20002' => [TenantStatus::DISABLED, false, 20002, false, 20002, '市场已被禁用，请联系平台管理员'],
        'LOCKED - 登录拦截 401 20006' => [TenantStatus::LOCKED, false, 20006, false, 20006, '市场已被锁定，请联系平台管理员'],
        'UNPAID - 读放行 写拦截 20003（核心）' => [TenantStatus::UNPAID, true, 0, false, 20003, '您的市场已欠费，请续费后再操作'],
        'REJECTED - 登录拦截 401 20007' => [TenantStatus::REJECTED, false, 20007, false, 20007, '市场审核未通过，请补充资料后重新提交'],
        'PENDING_DELETE - 登录拦截 401 20008' => [TenantStatus::PENDING_DELETE, false, 20008, false, 20008, '市场已进入预删除状态，不可登录'],
    ];

    foreach ($dataset as $name => $data) {
        [$status, $getAllowed, $getCode, $postAllowed, $postCode, $postMessage] = $data;

        it("{$name} → GET", function () use ($status, $getAllowed, $getCode, $okNext) {
            $tenantId = createTestTenant($status);
            $req = makeRequest('GET', $tenantId);

            $middleware = new TenantContextMiddleware();
            $resp = $middleware->handle($req, $okNext);
            $body = json_decode($resp->getContent(), true);

            if ($getAllowed) {
                expect($resp->getCode())->toBe(200)
                    ->and($body['code'])->toBe(0);
            } else {
                expect($resp->getCode())->toBe(401)
                    ->and($body['code'])->toBe($getCode);
            }
        });

        it("{$name} → POST", function () use ($status, $postAllowed, $postCode, $postMessage, $okNext) {
            $tenantId = createTestTenant($status);
            $req = makeRequest('POST', $tenantId);

            $middleware = new TenantContextMiddleware();
            $resp = $middleware->handle($req, $okNext);
            $body = json_decode($resp->getContent(), true);

            if ($postAllowed) {
                expect($resp->getCode())->toBe(200)
                    ->and($body['code'])->toBe(0);
            } else {
                expect($body['code'])->toBe($postCode)
                    ->and($body['message'])->toBe($postMessage);
                // UNPAID/PENDING 是登录放行但写拦截 → 403；其他是登录拦截 → 401
                $expectHttp = in_array($status, [TenantStatus::UNPAID, TenantStatus::PENDING], true) ? 403 : 401;
                expect($resp->getCode())->toBe($expectHttp);
            }
        });
    }
});

describe('租户不存在 404', function () use ($okNext) {
    it('访问不存在的 tenant_id 返回 20001 + 404', function () use ($okNext) {
        $req = makeRequest('GET', 999999999);
        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(404)
            ->and($body['code'])->toBe(20001)
            ->and($body['message'])->toBe('租户不存在');
    });
});

describe('未注入身份白名单放行', function () use ($okNext) {
    it('无 X-Tenant-Id 头直接放行（用于登录/健康检查接口）', function () use ($okNext) {
        $req = new Request();
        $req->setMethod('GET');
        // 不设 X-Tenant-Id

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
    });
});

describe('AUDITOR 角色写拦截', function () use ($okNext) {
    it('ACTIVE 租户 + AUDITOR 角色 → POST 返回 20502', function () use ($okNext) {
        $tenantId = createTestTenant(TenantStatus::ACTIVE);
        $req = makeRequest('POST', $tenantId, 1, TenantMemberRole::AUDITOR->value);

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(403)
            ->and($body['code'])->toBe(20502)
            ->and($body['message'])->toBe('当前角色无写权限');
    });

    it('ACTIVE 租户 + AUDITOR 角色 → GET 放行', function () use ($okNext) {
        $tenantId = createTestTenant(TenantStatus::ACTIVE);
        $req = makeRequest('GET', $tenantId, 1, TenantMemberRole::AUDITOR->value);

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
    });
});

describe('超管跨租户访问', function () use ($okNext) {
    it('超管跨租户访问 ACTIVE 状态正常放行', function () use ($okNext) {
        $tenantId = createTestTenant(TenantStatus::ACTIVE);

        $req = new Request();
        $req->setMethod('POST');
        $req->withHeader([
            'x-super-admin' => 'true',
            'x-super-admin-id' => '999',
            'x-target-tenant-id' => (string) $tenantId,
        ]);

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
        // 验证 TenantContext 状态
        $ctx = TenantContext::getInstance();
        expect($ctx->isSuperAdmin())->toBeTrue()
            ->and($ctx->getTenantId())->toBe($tenantId);
    });

    it('超管访问 DISABLED 租户放行（超管可绕过状态校验进入预删除清理）', function () use ($okNext) {
        $tenantId = createTestTenant(TenantStatus::DISABLED);

        $req = new Request();
        $req->setMethod('POST');
        $req->withHeader([
            'x-super-admin' => 'true',
            'x-super-admin-id' => '999',
            'x-target-tenant-id' => (string) $tenantId,
        ]);

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        expect($resp->getCode())->toBe(200);
    });

    it('超管访问不存在的租户仍 404', function () use ($okNext) {
        $req = new Request();
        $req->setMethod('GET');
        $req->withHeader([
            'x-super-admin' => 'true',
            'x-super-admin-id' => '999',
            'x-target-tenant-id' => '999999999',
        ]);

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);
        $body = json_decode($resp->getContent(), true);

        expect($resp->getCode())->toBe(404)
            ->and($body['code'])->toBe(20001);
    });
});

describe('生产模式禁用开发头注入', function () use ($okNext) {
    it('TENANT_DEV_MODE=false 时不从头注入身份（白名单放行）', function () use ($okNext) {
        putenv('TENANT_DEV_MODE=false');
        $req = makeRequest('GET', 1);

        $middleware = new TenantContextMiddleware();
        $resp = $middleware->handle($req, $okNext);

        // 头被忽略，未注入 tenant_id → 白名单放行
        expect($resp->getCode())->toBe(200)
            ->and(TenantContext::getInstance()->getTenantId())->toBeNull();
    });
});
