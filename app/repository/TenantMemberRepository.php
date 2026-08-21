<?php

declare(strict_types=1);

namespace app\repository;

use app\model\TenantMember;

/**
 * 租户成员 Repository
 *
 * @extends BaseRepository<TenantMember>
 */
class TenantMemberRepository extends BaseRepository
{
    /** @var class-string<TenantMember> */
    protected static string $modelClass = TenantMember::class;

    /**
     * 通过 username 查找成员（租户内唯一）
     * 注意：必须在 TenantContext 已设置 tenant_id 的上下文调用，
     * 全局 Scope 会自动加上 tenant_id 条件
     */
    public function findByUsername(string $username): ?TenantMember
    {
        /** @var TenantMember|null */
        return $this->findBy('username', $username);
    }

    /**
     * 通过邀请 token 查找成员（跨租户查询，绕过 Scope）
     *
     * 邀请 token 是平台级唯一，但激活流程不依赖 ctx.tenant_id（用户未登录场景）
     */
    public function findByInviteToken(string $token): ?TenantMember
    {
        // 邀请链接接受时，用户还没登录，没有 ctx.tenant_id
        // 直接用 Db 查询绕过全局 Scope
        $row = \think\facade\Db::name('tenant_member')
            ->where('invite_token', $token)
            ->find();

        if ($row === null) {
            return null;
        }

        $member = new TenantMember();
        $member->data($row, true);
        return $member;
    }
}
