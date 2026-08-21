<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * SaaS 租户成员表（租户内 RBAC 角色体系）
 * 一个 tenant_id 下挂多个成员，username 在租户内唯一
 */
class CreateTenantMemberTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('tenant_member', ['id' => false, 'primary_key' => 'id', 'comment' => 'SaaS 租户成员表']);
        $table->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('tenant_id', 'biginteger', ['comment' => '所属租户ID（SaaS 隔离）'])
            ->addColumn('username', 'string', ['limit' => 64, 'comment' => '登录用户名'])
            ->addColumn('password', 'string', ['limit' => 100, 'comment' => '密码哈希（bcrypt）'])
            ->addColumn('role', 'integer', ['default' => 3, 'comment' => '角色：1 Owner(老板) 2 Admin(管理员) 3 Operator(运营) 4 Auditor(只读审计)'])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'comment' => '联系电话'])
            ->addColumn('email', 'string', ['limit' => 128, 'null' => true, 'comment' => '邮箱（邀请链接发送目标）'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：0待激活 1启用 2禁用'])
            ->addColumn('last_login_at', 'timestamp', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['tenant_id', 'username'], ['unique' => true])
            ->addIndex(['tenant_id', 'status'])
            ->create();
    }
}
