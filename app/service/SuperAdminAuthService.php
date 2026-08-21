<?php

declare(strict_types=1);

namespace app\service;

use app\exceptions\UnauthorizedException;
use app\model\SuperAdmin;
use app\repository\SuperAdminRepository;

/**
 * 平台超管认证服务
 *
 * 职责：
 * - 超管登录（账号 + 密码 → 签发 JWT）
 * - 不参与任何租户成员登录（租户成员登录见 Phase 1.8）
 *
 * 安全约束（§13.8.3）：
 * - JWT payload 只写 is_super_admin=true + sadmin_id，绝不夹带 tenant_id 或 tenant_role
 * - 登录失败统一错误提示，不区分「账号不存在」与「密码错误」（防爆破枚举）
 * - 超管表独立于 tenant_member，绝不混用
 */
class SuperAdminAuthService
{
    public function __construct(
        private readonly SuperAdminRepository $repo = new SuperAdminRepository()
    ) {
    }

    /**
     * 超管登录
     *
     * 业务流程：
     * 1. 按 username 查询超管记录
     * 2. password_verify 校验密码哈希（bcrypt）
     * 3. 校验账号状态为 STATUS_ACTIVE
     * 4. 更新 last_login_at
     * 5. 签发 JWT（payload 严格按 §13.8.3）
     *
     * @param  string $username 登录账号
     * @param  string $password 明文密码
     * @return array{token: string, super_admin: SuperAdmin}
     * @throws UnauthorizedException 账号不存在 / 密码错误 / 账号已禁用
     */
    public function login(string $username, string $password): array
    {
        $admin = $this->repo->findByUsername($username);

        // 账号不存在 → 与密码错误合并为同一错误（防枚举）
        if ($admin === null) {
            throw new UnauthorizedException('账号或密码错误', 21001);
        }

        // 密码哈希校验
        if (!password_verify($password, (string) $admin->getAttr('password'))) {
            throw new UnauthorizedException('账号或密码错误', 21001);
        }

        // 账号状态校验
        if ((int) $admin->getAttr('status') !== SuperAdmin::STATUS_ACTIVE) {
            throw new UnauthorizedException('账号已被禁用，请联系系统管理员', 21002);
        }

        // 更新最后登录时间（不阻塞主流程）
        try {
            $this->repo->updateLastLoginAt((int) $admin->id);
        } catch (\Throwable $e) {
            // 登录时间更新失败不影响签发 token
        }

        // 签发 JWT：payload 严格按 §13.8.3 约束
        $token = JwtService::issue(JwtService::superAdminPayload((int) $admin->id));

        return [
            'token' => $token,
            'super_admin' => $admin,
        ];
    }
}
