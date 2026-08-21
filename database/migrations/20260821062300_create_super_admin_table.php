<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * 平台超管账号表（独立于 tenant_member）
 *
 * 安全约束（§13.8.3）：
 * - 平台超管独立身份体系，绝不与 tenant_member 混用
 * - JWT payload 只写 is_super_admin=true + sadmin_id，绝不夹带 tenant_id 或 tenant_role
 * - 所有跨租户写操作必须记录 super_admin_audit_log
 */
class CreateSuperAdminTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('super_admin', ['id' => false, 'primary_key' => 'id', 'comment' => '平台超管账号表（独立于租户成员）']);
        $table->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('username', 'string', ['limit' => 64, 'comment' => '登录账号（全局唯一）'])
            ->addColumn('password', 'string', ['limit' => 100, 'comment' => '密码哈希（bcrypt）'])
            ->addColumn('name', 'string', ['limit' => 32, 'null' => true, 'comment' => '姓名'])
            ->addColumn('status', 'smallinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addColumn('last_login_at', 'timestamp', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['username'], ['unique' => true])
            ->create();
    }
}
