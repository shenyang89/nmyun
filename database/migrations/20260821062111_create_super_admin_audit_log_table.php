<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * 平台超管审计日志表（平台级，无 tenant_id）
 * 所有跨租户写入操作（创建/更新/删除/设备控制）必须记录：
 *   谁（admin_id）+ 什么时间 + 对哪个租户（target_tenant_id）+ 调了哪个接口
 *   + 传了什么关键参数 + 结果 + IP + UA
 * 永久留存，不可删除（合规审计要求 §13.8.3）
 */
class CreateSuperAdminAuditLogTable extends Migrator
{
    public function change(): void
    {
        $table = $this->table('super_admin_audit_log', ['id' => false, 'primary_key' => 'id', 'comment' => '平台超管审计日志（永久留存）']);
        $table->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('admin_id', 'biginteger', ['comment' => '超管账号ID'])
            ->addColumn('target_tenant_id', 'biginteger', ['comment' => '目标租户ID（X-Target-Tenant-Id 头）'])
            ->addColumn('action', 'string', ['limit' => 16, 'comment' => '操作类型：create/update/delete/control/login/tenant_status_switch'])
            ->addColumn('api_path', 'string', ['limit' => 255, 'comment' => '接口路径'])
            ->addColumn('method', 'string', ['limit' => 8, 'comment' => 'HTTP 方法 GET/POST/PUT/DELETE'])
            ->addColumn('request_params', 'jsonb', ['null' => true, 'comment' => '关键请求参数（敏感字段脱敏）'])
            ->addColumn('response_status', 'smallinteger', ['default' => 200, 'comment' => 'HTTP 响应状态码'])
            ->addColumn('ip', 'string', ['limit' => 45, 'comment' => '操作来源 IP（支持 IPv6）'])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'null' => true, 'comment' => 'User-Agent'])
            ->addColumn('created_at', 'timestamp', ['null' => true])
            // 审计日志只增不改不删，不建 updated_at
            ->addIndex(['admin_id', 'created_at'])
            ->addIndex(['target_tenant_id', 'created_at'])
            ->addIndex(['action', 'created_at'])
            ->create();
    }
}
