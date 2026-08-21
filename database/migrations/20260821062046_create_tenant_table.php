<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * SaaS 租户表（市场）
 * 一个租户 = 一个农贸市场，所有业务数据的隔离边界
 */
class CreateTenantTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('tenant', ['id' => false, 'primary_key' => 'id', 'comment' => 'SaaS 租户表（市场）']);
        $table->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '市场名称'])
            ->addColumn('code', 'string', ['limit' => 32, 'comment' => '市场编码（全局唯一，用于 URL/标识）'])
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态：0待审核 1启用 2禁用 3锁定 4欠费 5审核不通过 6预删除'])
            ->addColumn('contact_name', 'string', ['limit' => 32, 'comment' => '联系人姓名'])
            ->addColumn('contact_phone', 'string', ['limit' => 20, 'comment' => '联系电话'])
            ->addColumn('address', 'string', ['limit' => 255, 'null' => true, 'comment' => '市场地址'])
            ->addColumn('expired_at', 'date', ['null' => true, 'comment' => '套餐到期时间'])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['code'], ['unique' => true])
            ->create();
    }
}
