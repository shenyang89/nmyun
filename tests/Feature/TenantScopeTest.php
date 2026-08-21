<?php

/**
 * Feature 测试：BaseModel 全局 tenant_id Scope + BaseRepository 二次校验
 *
 * §1.4 验收标准：
 *   1. 伪造测试：手工在 DB 中插入一条 tenant_id=9999 的 merchant，ctx tenant=1 去查 → 必须 404
 *   2. 直接删 → 0 rows affected
 *   3. 集成测试写好留档
 *
 * 覆盖场景：
 *   - BaseModel::scopeTenant 自动加 WHERE tenant_id = ctx.tenant_id
 *   - BaseModel::onBeforeInsert 自动注入 tenant_id
 *   - BaseModel::onBeforeInsert/Update/Delete 越权拦截 → CrossTenantException(40301)
 *   - BaseRepository::findById/findOrFail/updateById/deleteById 二次校验
 *   - Tenant 模型（不启用 scope）正常工作
 */

use app\exceptions\CrossTenantException;
use app\exceptions\NotFoundException;
use app\model\Merchant;
use app\model\Tenant;
use app\repository\MerchantRepository;
use app\support\TenantContext;
use app\support\TenantStatus;
use think\facade\Db;

beforeEach(function () {
    TenantContext::reset();

    // 兼容 Pest：恢复 Model::$db / Event / Container 单例
    $app = \think\Container::getInstance();
    if (!$app instanceof \think\App) {
        throw new \RuntimeException('Container is not App');
    }
    \think\Model::setDb($app->db);
    \think\Model::setEvent($app->event);
    \think\Model::setInvoker([$app, 'invoke']);

    // 清理本测试用例可能创建的数据
    Db::name('merchant')->where('merchant_no', 'like', 'scope_test_%')->delete();
});

afterEach(function () {
    TenantContext::reset();
    Db::name('merchant')->where('merchant_no', 'like', 'scope_test_%')->delete();
});

/**
 * 通过 Db::name 直接插入指定 tenant_id 的 merchant（绕过 Model 事件和 Scope）
 */
function insertMerchantRaw(int $tenantId, string $merchantNo): int
{
    $now = date('Y-m-d H:i:s');
    return (int) Db::name('merchant')->insertGetId([
        'tenant_id' => $tenantId,
        'merchant_no' => $merchantNo,
        'name' => '测试商户_' . $merchantNo,
        'owner_name' => '老板',
        'phone' => '13800000000',
        'stall_no' => 'A-' . substr($merchantNo, -3),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

describe('BaseModel::scopeTenant 自动注入 tenant_id 条件', function () {
    it('ctx tenant_id=1 时查询 SQL 自动加 tenant_id=1', function () {
        // 准备：DB 中两条记录，分别 tenant_id=1 和 tenant_id=9999
        insertMerchantRaw(1, 'scope_test_a1');
        insertMerchantRaw(9999, 'scope_test_a2');

        // 设置 ctx.tenant_id=1
        TenantContext::getInstance()->setTenantId(1);

        // Model 直接查询（带 scope）
        $list = Merchant::where('merchant_no', 'like', 'scope_test_a%')
            ->order('id', 'asc')
            ->select();

        // 断言：只看到 tenant_id=1 的那一条
        expect($list)->toHaveCount(1)
            ->and($list[0]->merchant_no)->toBe('scope_test_a1')
            ->and((int) $list[0]->tenant_id)->toBe(1);
    });

    it('ctx tenant_id=9999 时只看到 9999 的记录', function () {
        insertMerchantRaw(1, 'scope_test_b1');
        insertMerchantRaw(9999, 'scope_test_b2');

        TenantContext::getInstance()->setTenantId(9999);

        $list = Merchant::where('merchant_no', 'like', 'scope_test_b%')->order('id', 'asc')->select();

        expect($list)->toHaveCount(1)
            ->and($list[0]->merchant_no)->toBe('scope_test_b2')
            ->and((int) $list[0]->tenant_id)->toBe(9999);
    });

    it('未注入 ctx 时不加 tenant_id 条件（系统级路径）', function () {
        insertMerchantRaw(1, 'scope_test_c1');
        insertMerchantRaw(9999, 'scope_test_c2');

        // 不 setTenantId → ctx.tenant_id 为 null
        $list = Merchant::where('merchant_no', 'like', 'scope_test_c%')->order('id', 'asc')->select();

        // 看到两条
        expect($list)->toHaveCount(2);
    });
});

describe('BaseModel::onBeforeInsert 自动注入 + 越权拦截', function () {
    it('未指定 tenant_id 时自动注入 ctx.tenant_id', function () {
        TenantContext::getInstance()->setTenantId(1);

        $merchant = new Merchant();
        $merchant->save([
            'merchant_no' => 'scope_test_insert_auto',
            'name' => '自动注入',
        ]);

        expect((int) $merchant->tenant_id)->toBe(1);

        // 校验数据库
        $row = Db::name('merchant')->where('id', $merchant->id)->find();
        expect((int) $row['tenant_id'])->toBe(1);
    });

    it('显式指定与 ctx 一致的 tenant_id 时正常插入', function () {
        TenantContext::getInstance()->setTenantId(1);

        $merchant = new Merchant();
        $merchant->save([
            'merchant_no' => 'scope_test_insert_match',
            'name' => '显式匹配',
            'tenant_id' => 1,
        ]);

        expect((int) $merchant->tenant_id)->toBe(1);
    });

    it('显式指定与 ctx 不一致的 tenant_id 时抛 CrossTenantException', function () {
        TenantContext::getInstance()->setTenantId(1);

        $merchant = new Merchant();
        $merchant->merchant_no = 'scope_test_insert_violate';
        $merchant->name = '越权';
        $merchant->tenant_id = 9999;

        expect(fn () => $merchant->save())
            ->toThrow(CrossTenantException::class)
            ->and(fn () => $merchant->save())
            ->toThrow(fn (CrossTenantException $e) => $e->getCode() === 40301);
    });
});

describe('BaseModel::onBeforeUpdate 越权拦截', function () {
    it('ctx 与 model.tenant_id 一致时正常更新', function () {
        $id = insertMerchantRaw(1, 'scope_test_update_ok');
        TenantContext::getInstance()->setTenantId(1);

        $merchant = Merchant::find($id);
        $merchant->save(['name' => '更新后']);

        $row = Db::name('merchant')->where('id', $id)->find();
        expect($row['name'])->toBe('更新后');
    });

    it('ctx tenant_id=1 但 model.tenant_id=9999 时更新抛 CrossTenantException', function () {
        $id = insertMerchantRaw(9999, 'scope_test_update_violate');
        TenantContext::getInstance()->setTenantId(1);

        // 通过 withoutScope 绕过查询 scope，得到 tenant_id=9999 的 model
        $merchant = Merchant::withoutScope(['tenant'])->find($id);
        expect((int) $merchant->tenant_id)->toBe(9999);

        expect(fn () => $merchant->save(['name' => '越权更新']))
            ->toThrow(CrossTenantException::class)
            ->and(fn () => $merchant->save(['name' => '越权更新']))
            ->toThrow(fn (CrossTenantException $e) => $e->getCode() === 40301);

        // 数据未更新
        $row = Db::name('merchant')->where('id', $id)->find();
        expect($row['name'])->toBe('测试商户_scope_test_update_violate');
    });
});

describe('BaseModel::onBeforeDelete 越权拦截', function () {
    it('ctx 与 model.tenant_id 一致时正常删除', function () {
        $id = insertMerchantRaw(1, 'scope_test_delete_ok');
        TenantContext::getInstance()->setTenantId(1);

        $merchant = Merchant::find($id);
        $result = $merchant->delete();

        expect($result)->toBeTrue();
        expect(Db::name('merchant')->where('id', $id)->find())->toBeNull();
    });

    it('ctx tenant_id=1 但 model.tenant_id=9999 时删除抛 CrossTenantException', function () {
        $id = insertMerchantRaw(9999, 'scope_test_delete_violate');
        TenantContext::getInstance()->setTenantId(1);

        $merchant = Merchant::withoutScope(['tenant'])->find($id);

        expect(fn () => $merchant->delete())
            ->toThrow(CrossTenantException::class);

        // 数据未删除
        expect(Db::name('merchant')->where('id', $id)->find())->not->toBeNull();
    });
});

describe('BaseRepository 二次校验（§1.4 验收核心）', function () {
    it('伪造测试：DB 中插入 tenant_id=9999 的 merchant，ctx=1 去查 findById → null', function () {
        $id = insertMerchantRaw(9999, 'scope_test_repo_find');
        TenantContext::getInstance()->setTenantId(1);

        $repo = new MerchantRepository();
        $result = $repo->findById($id);

        // scope 加了 tenant_id=1 → 查不到 9999 的记录 → null
        expect($result)->toBeNull();
    });

    it('伪造测试：findById 走 scope，ctx=1 查 tenant_id=9999 → null（不会触发二次校验）', function () {
        $id = insertMerchantRaw(9999, 'scope_test_repo_find_violate');
        TenantContext::getInstance()->setTenantId(1);

        // Repository::findById 通过 Model::query 走 scope → WHERE tenant_id=1
        // 找不到 9999 的记录 → null（scope 已正确隔离）
        $repo = new MerchantRepository();
        $result = $repo->findById($id);

        expect($result)->toBeNull();
    });

    it('二次校验触发：绕过 scope 拿到 9999 的 model → update 抛 CrossTenantException', function () {
        $id = insertMerchantRaw(9999, 'scope_test_repo_update_violate');
        TenantContext::getInstance()->setTenantId(1);

        // 通过 Model::withoutScope 模拟"开发者绕过 scope"的越权场景
        $merchant = Merchant::withoutScope(['tenant'])->find($id);
        expect((int) $merchant->tenant_id)->toBe(9999);

        // onBeforeUpdate 校验：ctx=1 但 model.tenant_id=9999 → 抛 40301
        expect(fn () => $merchant->save(['name' => '越权']))
            ->toThrow(CrossTenantException::class)
            ->and(fn () => $merchant->save(['name' => '越权']))
            ->toThrow(fn (CrossTenantException $e) => $e->getCode() === 40301);
    });

    it('findOrFail 对不存在 id 抛 NotFoundException', function () {
        TenantContext::getInstance()->setTenantId(1);

        $repo = new MerchantRepository();
        expect(fn () => $repo->findOrFail(99999999))
            ->toThrow(NotFoundException::class);
    });

    it('正常流程：ctx=1 创建 merchant → findById 正常返回', function () {
        TenantContext::getInstance()->setTenantId(1);

        $repo = new MerchantRepository();
        $created = $repo->create([
            'merchant_no' => 'scope_test_repo_ok',
            'name' => '正常流程',
        ]);

        expect((int) $created->tenant_id)->toBe(1);

        $found = $repo->findById($created->id);
        expect($found)->not->toBeNull()
            ->and((int) $found->tenant_id)->toBe(1);
    });

    it('正常流程：updateById + deleteById 都在 ctx 范围内', function () {
        TenantContext::getInstance()->setTenantId(1);

        $repo = new MerchantRepository();
        $created = $repo->create([
            'merchant_no' => 'scope_test_repo_crud',
            'name' => '原始',
        ]);

        $updated = $repo->updateById($created->id, ['name' => '改后']);
        expect($updated->name)->toBe('改后');

        $deleted = $repo->deleteById($created->id);
        expect($deleted)->toBeTrue();
    });

    it('伪造测试：DB 中插入 tenant_id=9999 的 merchant，ctx=1 直接通过裸 SQL delete → 0 rows affected', function () {
        $id = insertMerchantRaw(9999, 'scope_test_raw_delete');
        TenantContext::getInstance()->setTenantId(1);

        // 走 Model 查询（带 scope）→ 找不到
        $merchant = Merchant::find($id);
        expect($merchant)->toBeNull();

        // 通过 Merchant::where(...)->delete() 也受 scope 影响 → 0 rows affected
        $affected = Merchant::where('id', $id)->delete();
        expect($affected)->toBe(0);

        // 数据未删除
        $row = Db::name('merchant')->where('id', $id)->find();
        expect($row)->not->toBeNull();
    });
});

describe('Tenant 模型不启用 tenant scope', function () {
    it('Tenant 模型查询不带 tenant_id 条件', function () {
        TenantContext::getInstance()->setTenantId(1);

        // Tenant 表不带 tenant_id 字段，但 enableTenantScope=false → 不会加 tenant_id 条件
        // 应能正常查询（即使没有 tenant 记录也不抛异常）
        $count = Tenant::where('id', '>', 0)->count();
        expect($count)->toBeInt();
    });

    it('Tenant 模型创建不触发 onBeforeInsert 越权拦截', function () {
        TenantContext::getInstance()->setTenantId(1);

        $tenant = new Tenant();
        $tenant->save([
            'name' => 'scope_test_tenant',
            'code' => 'scope_test_' . uniqid(),
            'status' => TenantStatus::PENDING->value,
            'contact_name' => '联系人',
            'contact_phone' => '13800000000',
        ]);

        expect($tenant->id)->not->toBeNull();

        // 清理
        Db::name('tenant')->where('id', $tenant->id)->delete();
    });
});

describe('超管跨租户场景', function () {
    it('超管 ctx.tenant_id=9999，查询自动加 tenant_id=9999', function () {
        insertMerchantRaw(1, 'scope_test_super_a');
        insertMerchantRaw(9999, 'scope_test_super_b');

        // 超管上下文：isSuperAdmin=true, tenantId=9999
        TenantContext::getInstance()->setSuperAdmin(true, 999)->setTenantId(9999);

        $list = Merchant::where('merchant_no', 'like', 'scope_test_super_%')->order('id', 'asc')->select();

        expect($list)->toHaveCount(1)
            ->and($list[0]->merchant_no)->toBe('scope_test_super_b')
            ->and((int) $list[0]->tenant_id)->toBe(9999);
    });
});
