<?php

declare(strict_types=1);

namespace app\service;

use app\exceptions\BusinessException;
use app\exceptions\UnauthorizedException;
use app\exceptions\ValidationException;
use app\model\TenantMember;
use app\repository\TenantMemberRepository;
use app\support\TenantContext;
use app\support\TenantMemberRole;
use app\support\TenantStatus;

/**
 * 租户成员认证 + 成员管理 Service
 *
 * 职责（§13.8.3 + DEV_ROADMAP Phase 1.8）：
 * - login()           租户成员登录（依赖 ctx.tenant_id 隔离查询）
 * - createInvite()    Owner/Admin 邀请新成员（生成 24h 有效 invite_token）
 * - acceptInvite()    接受邀请 → 设置密码 + 激活 + 清 token
 * - listMembers()     租户内成员列表（分页）
 * - changeRole()      修改成员角色（含 Admin 不能改 Admin / 不能改 Owner 等约束）
 * - removeMember()    移除成员（不能移除 Owner / 不能移除自己）
 *
 * 安全约束：
 * - JWT payload 严格按 §13.8.3：member_id + tenant_id + role，绝不夹带 is_super_admin
 * - 登录失败合并账号不存在与密码错误（防枚举）
 * - 邀请 token 64 位随机串 + 24h 过期；接受后立即失效（status=PENDING → ACTIVE 并清空 token）
 * - Owner 唯一不可移除；Admin 不能管理其他 Admin；任何角色都不能改/移除 Owner
 *
 * 错误码规划：
 * - 21xxx：认证类（21001 账号或密码错误 / 21002 已禁用 / 21003 尚未激活）
 * - 20503：无权管理成员
 * - 20504：邀请链接已过期
 * - 20505：邀请 token 无效
 * - 20506：邀请已被使用 / 已加入
 * - 20507：不能操作 Owner 角色
 * - 20508：用户名租户内已存在
 * - 20509：不能邀请/改为 Owner
 * - 20510：不能修改自己的角色
 * - 20511：不能移除自己
 */
class TenantMemberAuthService
{
    /** 邀请 token 有效期（秒），默认 24 小时 */
    public const INVITE_TTL = 24 * 3600;

    public function __construct(
        private readonly TenantMemberRepository $repo = new TenantMemberRepository()
    ) {
    }

    /**
     * 租户成员登录
     *
     * 业务流程：
     * 1. 通过 ctx.tenant_id 隔离查询 username（中间件已注入）
     * 2. password_verify 校验 bcrypt
     * 3. 成员状态校验（PENDING/ACTIVE/DISABLED）
     * 4. 租户状态校验（DISABLED/LOCKED 等不可登录）
     * 5. 更新 last_login_at
     * 6. 签发 JWT（payload 严格按 §13.8.3）
     *
     * @param  string $username 登录账号
     * @param  string $password 明文密码
     * @return array{token: string, member: TenantMember}
     * @throws UnauthorizedException 账号不存在 / 密码错误 / 状态异常
     */
    public function login(string $username, string $password): array
    {
        // 依赖中间件已注入 ctx.tenant_id（路由白名单允许无 JWT 访问，但 X-Tenant-Id 必填）
        $ctx = TenantContext::getInstance();
        $tenantId = $ctx->requireTenantId();

        $member = $this->repo->findByUsername($username);

        // 账号不存在 → 与密码错误合并（防枚举）
        if ($member === null) {
            throw new UnauthorizedException('账号或密码错误', 21001);
        }

        // 密码哈希校验（PENDING 状态下 password='' 也无法通过 password_verify）
        if (!password_verify($password, (string) $member->getAttr('password'))) {
            throw new UnauthorizedException('账号或密码错误', 21001);
        }

        // 成员状态校验
        $status = (int) $member->getAttr('status');
        if ($status === TenantMember::STATUS_PENDING) {
            throw new UnauthorizedException('账号尚未激活，请查收邀请邮件完成激活', 21003);
        }
        if ($status !== TenantMember::STATUS_ACTIVE) {
            throw new UnauthorizedException('账号已被禁用，请联系市场管理员', 21002);
        }

        // 租户状态校验（中间件已注入 tenantStatus；登录路由可能在状态校验前就放行）
        $tenantStatus = $ctx->getTenantStatus();
        if ($tenantStatus !== null && !$tenantStatus->canLogin()) {
            $err = $tenantStatus->loginError();
            throw new UnauthorizedException($err['message'], $err['code']);
        }

        // 更新最后登录时间（不阻塞主流程）
        try {
            $this->repo->updateById((int) $member->id, [
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // 登录时间更新失败不影响签发 token
        }

        // 签发 JWT：payload 严格按 §13.8.3 约束
        $role = (int) $member->getAttr('role');
        $token = JwtService::issue(JwtService::tenantMemberPayload(
            (int) $member->id,
            $tenantId,
            $role
        ));

        return [
            'token' => $token,
            'member' => $member,
        ];
    }

    /**
     * 创建邀请（生成 invite_token + 24h 过期，写入 PENDING 状态成员）
     *
     * 权限约束：
     * - 仅 Owner / Admin 可调用（canManageMember）
     * - 目标角色不能是 Owner（每租户唯一 Owner，通过 Owner 转让流程处理）
     *
     * @param  array{username:string, role?:int, phone?:?string, email?:?string} $input
     * @return array{member: TenantMember, invite_token: string, invite_url: string, invite_expires_at: string}
     * @throws UnauthorizedException 无权管理成员
     * @throws ValidationException   用户名为空 / 非法角色值
     * @throws BusinessException     不能邀请 Owner / 用户名已存在
     */
    public function createInvite(array $input): array
    {
        $ctx = TenantContext::getInstance();

        // 权限校验：仅 Owner/Admin 可邀请（canManageMember）
        $currentRole = $ctx->getMemberRole();
        if ($currentRole === null || !$currentRole->canManageMember()) {
            throw new UnauthorizedException('无权邀请成员（仅 Owner/Admin 可操作）', 20503);
        }

        // 解析目标角色（默认 OPERATOR）
        $targetRoleValue = (int) ($input['role'] ?? TenantMemberRole::OPERATOR->value);
        try {
            $targetRole = TenantMemberRole::from($targetRoleValue);
        } catch (\ValueError) {
            throw new ValidationException('非法的角色值：' . $targetRoleValue, 40001);
        }

        // 不能邀请为 Owner
        if ($targetRole === TenantMemberRole::OWNER) {
            throw new BusinessException('不能邀请为 Owner 角色（每租户有且仅有一个 Owner，请走转让流程）', 20509);
        }

        // Admin 不能邀请其他人为 Admin（仅 Owner 可提升 Admin）
        if ($currentRole === TenantMemberRole::ADMIN
            && $targetRole === TenantMemberRole::ADMIN) {
            throw new UnauthorizedException('Admin 不能将成员提升为 Admin（仅 Owner 可）', 20503);
        }

        // 租户内用户名唯一校验
        $username = (string) ($input['username'] ?? '');
        if ($username === '') {
            throw new ValidationException('用户名不能为空', 40001);
        }
        $existing = $this->repo->findByUsername($username);
        if ($existing !== null) {
            throw new BusinessException('用户名在租户内已存在：' . $username, 20508);
        }

        // 生成邀请 token（64 位 hex）+ 24h 过期
        $inviteToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::INVITE_TTL);

        // 创建一条 PENDING 状态的成员记录
        $data = [
            'username' => $username,
            'password' => '', // 邀请阶段未设密码，acceptInvite 时由用户填写
            'role' => $targetRole->value,
            'phone' => $input['phone'] ?? null,
            'email' => $input['email'] ?? null,
            'status' => TenantMember::STATUS_PENDING,
            'invite_token' => $inviteToken,
            'invite_expires_at' => $expiresAt,
        ];

        /** @var TenantMember */
        $member = $this->repo->create($data);

        // 邀请链接（前端可拼接域名 + 路径展示给用户点击）
        $inviteUrl = '/api/member/invite/accept?token=' . $inviteToken;

        return [
            'member' => $member,
            'invite_token' => $inviteToken,
            'invite_url' => $inviteUrl,
            'invite_expires_at' => $expiresAt,
        ];
    }

    /**
     * 接受邀请 → 设置密码 + 激活 + 清空 token
     *
     * 流程：
     * 1. 跨租户按 invite_token 查询（绕过 scope，用户未登录）
     * 2. 校验状态必须为 PENDING
     * 3. 校验未过期
     * 4. 临时注入 ctx.tenant_id（从 token 反查，可信）
     * 5. 更新：写密码哈希、status=ACTIVE、清空 invite_token/invite_expires_at
     * 6. finally 还原 ctx（防止跨请求污染）
     *
     * @param  string      $token   邀请 token
     * @param  string      $password 用户设置的密码（明文，会被 bcrypt 哈希后存储）
     * @param  string|null $username 可选，若提供则覆盖原 username（允许用户自定义登录名）
     * @return TenantMember
     * @throws BusinessException 邀请无效 / 已使用 / 已过期
     */
    public function acceptInvite(string $token, string $password, ?string $username = null): TenantMember
    {
        if ($token === '' || strlen($password) < 6) {
            throw new ValidationException('邀请 token 或密码格式不合法', 40001);
        }

        // 跨租户查询（用户未登录，绕过 scope）
        $member = $this->repo->findByInviteToken($token);
        if ($member === null) {
            throw new BusinessException('邀请链接无效', 20505);
        }

        // 状态校验：必须是 PENDING（已激活的点击 → 提示已加入）
        $status = (int) $member->getAttr('status');
        if ($status !== TenantMember::STATUS_PENDING) {
            throw new BusinessException('该邀请已被使用或已失效', 20506);
        }

        // 过期校验
        $expiresAt = $member->getAttr('invite_expires_at');
        if ($expiresAt === null || strtotime((string) $expiresAt) < time()) {
            throw new BusinessException('邀请链接已过期，请联系管理员重新邀请', 20504);
        }

        // 临时注入 ctx.tenant_id（从 token 反查得到，可信）
        // 后续 repo->updateById 走 BaseModel::onBeforeUpdate / BaseRepository::assertTenantMatch 校验
        $ctx = TenantContext::getInstance();
        $originalTenantId = $ctx->getTenantId();
        $ctx->setTenantId((int) $member->getAttr('tenant_id'));

        try {
            $update = [
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'status' => TenantMember::STATUS_ACTIVE,
                'invite_token' => null,
                'invite_expires_at' => null,
            ];

            // 可选 username 覆盖（若提供且非空）
            if ($username !== null && $username !== '') {
                $update['username'] = $username;
            }

            /** @var TenantMember */
            return $this->repo->updateById((int) $member->id, $update);
        } finally {
            $ctx->setTenantId($originalTenantId);
        }
    }

    /**
     * 租户内成员列表（分页）
     *
     * @return array{list: mixed, total: int, page: int, page_size: int}
     */
    public function listMembers(int $page, int $pageSize): array
    {
        // 默认按 id 倒序（最新加入在前）
        return $this->repo->paginateWhere([], $page, $pageSize, 'id DESC');
    }

    /**
     * 修改成员角色
     *
     * 权限约束：
     * - 仅 Owner/Admin 可调用
     * - 不能修改自己的角色（防 Owner 自降为 Auditor 锁死管理通道）
     * - 不能将成员改为 Owner（请走转让流程）
     * - 不能修改 Owner 的角色（防篡位）
     * - Admin 不能修改其他 Admin 的角色，也不能将成员提升为 Admin
     *
     * @param  int $memberId      目标成员ID
     * @param  int $newRoleValue  目标角色值
     * @return TenantMember
     * @throws UnauthorizedException 无权管理 / Admin 越权
     * @throws BusinessException     不能改自己 / 不能改 Owner / 不能改为 Owner
     * @throws ValidationException   非法角色值
     */
    public function changeRole(int $memberId, int $newRoleValue): TenantMember
    {
        $ctx = TenantContext::getInstance();

        // 权限校验
        $currentRole = $ctx->getMemberRole();
        if ($currentRole === null || !$currentRole->canManageMember()) {
            throw new UnauthorizedException('无权修改成员角色', 20503);
        }

        // 不能修改自己的角色
        if ($ctx->getMemberId() === $memberId) {
            throw new BusinessException('不能修改自己的角色', 20510);
        }

        // 校验新角色值
        try {
            $newRole = TenantMemberRole::from($newRoleValue);
        } catch (\ValueError) {
            throw new ValidationException('非法的角色值：' . $newRoleValue, 40001);
        }

        // 不能改为 Owner（Owner 转让走独立流程）
        if ($newRole === TenantMemberRole::OWNER) {
            throw new BusinessException('不能直接将成员改为 Owner（请走 Owner 转让流程）', 20509);
        }

        /** @var TenantMember $member */
        $member = $this->repo->findOrFail($memberId);

        // 不能修改 Owner 的角色
        $targetCurrentRoleValue = (int) $member->getAttr('role');
        if ($targetCurrentRoleValue === TenantMemberRole::OWNER->value) {
            throw new BusinessException('不能修改 Owner 的角色', 20507);
        }

        // 解析目标当前角色（用于 Admin 越权判定）
        $targetCurrentRole = null;
        try {
            $targetCurrentRole = TenantMemberRole::from($targetCurrentRoleValue);
        } catch (\ValueError) {
            // 数据异常的角色值，让上层感知；这里直接拒绝
            throw new BusinessException('目标成员角色数据异常：' . $targetCurrentRoleValue, 20507);
        }

        // Admin 越权约束：
        // - Admin 不能修改其他 Admin
        // - Admin 不能将任何成员提升为 Admin
        if ($currentRole === TenantMemberRole::ADMIN) {
            if ($targetCurrentRole === TenantMemberRole::ADMIN) {
                throw new UnauthorizedException('Admin 不能修改其他 Admin 的角色', 20503);
            }
            if ($newRole === TenantMemberRole::ADMIN) {
                throw new UnauthorizedException('Admin 不能将成员提升为 Admin（仅 Owner 可）', 20503);
            }
        }

        /** @var TenantMember */
        return $this->repo->updateById($memberId, ['role' => $newRole->value]);
    }

    /**
     * 移除成员（物理删除）
     *
     * 权限约束：
     * - 仅 Owner/Admin 可调用
     * - 不能移除自己
     * - 不能移除 Owner（唯一不可移除，需先转让 Owner）
     * - Admin 不能移除其他 Admin
     *
     * @param  int $memberId 目标成员ID
     * @throws UnauthorizedException 无权移除 / Admin 越权
     * @throws BusinessException     不能移除自己 / 不能移除 Owner
     */
    public function removeMember(int $memberId): void
    {
        $ctx = TenantContext::getInstance();

        // 权限校验
        $currentRole = $ctx->getMemberRole();
        if ($currentRole === null || !$currentRole->canManageMember()) {
            throw new UnauthorizedException('无权移除成员', 20503);
        }

        // 不能移除自己
        if ($ctx->getMemberId() === $memberId) {
            throw new BusinessException('不能移除自己', 20511);
        }

        /** @var TenantMember $member */
        $member = $this->repo->findOrFail($memberId);

        // 不能移除 Owner
        $targetCurrentRoleValue = (int) $member->getAttr('role');
        if ($targetCurrentRoleValue === TenantMemberRole::OWNER->value) {
            throw new BusinessException('不能移除 Owner（请先转让 Owner 后再移除原 Owner）', 20507);
        }

        // Admin 不能移除其他 Admin
        if ($currentRole === TenantMemberRole::ADMIN) {
            try {
                $targetCurrentRole = TenantMemberRole::from($targetCurrentRoleValue);
            } catch (\ValueError) {
                throw new BusinessException('目标成员角色数据异常：' . $targetCurrentRoleValue, 20507);
            }
            if ($targetCurrentRole === TenantMemberRole::ADMIN) {
                throw new UnauthorizedException('Admin 不能移除其他 Admin', 20503);
            }
        }

        $this->repo->deleteById($memberId);
    }
}
