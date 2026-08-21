<?php

declare(strict_types=1);

use think\migration\Migrator;

/**
 * Phase 1.8: 给 tenant_member 表加邀请相关字段
 *
 * - invite_token: 邀请链接 token（接受邀请时校验）
 * - invite_expires_at: 邀请 token 过期时间
 *
 * 设计要点：
 * - 同一成员可被反复邀请（重发邀请 → 重置 token + 过期时间）
 * - token 仅在 status=0（待激活）时有效，激活后清空
 */
class AddInviteFieldsToTenantMember extends Migrator
{
    public function change(): void
    {
        $table = $this->table('tenant_member');
        $table->addColumn('invite_token', 'string', [
                'limit' => 64,
                'null' => true,
                'after' => 'email',
                'comment' => '邀请 token（仅 status=0 待激活时有效）',
            ])
            ->addColumn('invite_expires_at', 'timestamp', [
                'null' => true,
                'after' => 'invite_token',
                'comment' => '邀请 token 过期时间',
            ])
            ->addIndex(['invite_token'], ['unique' => true, 'name' => 'uniq_invite_token'])
            ->update();
    }
}
