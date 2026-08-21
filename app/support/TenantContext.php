<?php

declare(strict_types=1);

namespace app\support;

/**
 * 租户上下文单例
 *
 * 一次请求生命周期内的「身份 + 租户 + 成员」状态容器：
 *   - 中间件解析后注入 tenantId / isSuperAdmin / memberId / memberRole
 *   - Controller / Service / Repository 任何层都通过本类读取上下文
 *   - 单元测试用 setId() / setSuperAdmin() / setMember() 注入测试数据
 *
 * 安全约束：
 *   - 单进程内单例，请求结束自动清空（防止跨请求污染）
 *   - 严禁 setMemberId() 不带 role 的情况（防止越权）
 *   - isSuperAdmin() 是独立维度，不与 memberRole 混用（§13.8.3）
 */
final class TenantContext
{
    private static ?TenantContext $instance = null;

    /** 当前请求归属的租户ID（X-Tenant-Id 或超管 X-Target-Tenant-Id） */
    private ?int $tenantId = null;

    /** 超管身份标识（独立维度，不通过 role 伪造） */
    private bool $isSuperAdmin = false;

    /** 超管账号ID（仅 isSuperAdmin=true 时有值） */
    private ?int $superAdminId = null;

    /** 当前登录的成员ID（tenant_member.id） */
    private ?int $memberId = null;

    /** 当前登录的成员角色 */
    private ?TenantMemberRole $memberRole = null;

    /** 当前租户状态（中间件查表后注入） */
    private ?TenantStatus $tenantStatus = null;

    private function __construct()
    {
    }

    /**
     * 获取单例（请求生命周期内共享）
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 重置为初始状态（请求结束/单元测试 setUp 调用）
     */
    public static function reset(): void
    {
        self::$instance = new self();
    }

    /* ============ Tenant ID ============ */

    public function setTenantId(?int $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * 任何业务路径调用此方法前必须确保 tenantId 已注入。
     * 未注入 → 立即抛异常，防止跨租户数据泄漏（§13.8.4 兜底）。
     */
    public function requireTenantId(): int
    {
        if ($this->tenantId === null) {
            throw new \RuntimeException('TenantContext tenantId not set: middleware did not inject tenant context', 50000);
        }
        return $this->tenantId;
    }

    /* ============ Super Admin ============ */

    public function setSuperAdmin(bool $isSuperAdmin, ?int $adminId = null): self
    {
        $this->isSuperAdmin = $isSuperAdmin;
        $this->superAdminId = $adminId;
        return $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function getSuperAdminId(): ?int
    {
        return $this->superAdminId;
    }

    /* ============ Member ============ */

    public function setMember(?int $memberId, ?TenantMemberRole $role = null): self
    {
        $this->memberId = $memberId;
        $this->memberRole = $role;
        return $this;
    }

    public function getMemberId(): ?int
    {
        return $this->memberId;
    }

    public function getMemberRole(): ?TenantMemberRole
    {
        return $this->memberRole;
    }

    /**
     * 当前成员角色（超管无 memberRole，调用此方法应先判 isSuperAdmin）
     */
    public function currentMemberRole(): ?TenantMemberRole
    {
        return $this->memberRole;
    }

    /* ============ Tenant Status ============ */

    public function setTenantStatus(?TenantStatus $status): self
    {
        $this->tenantStatus = $status;
        return $this;
    }

    public function getTenantStatus(): ?TenantStatus
    {
        return $this->tenantStatus;
    }

    /* ============ 综合判定 ============ */

    /**
     * 当前请求是否允许写操作（Controller / Service 在执行前调用）
     *
     * 判定顺序（短路）：
     *   1. 超管 → 看路由白名单（本方法默认放行，由路由级白名单中间件再卡）
     *   2. 成员角色 → Auditor 拦截
     *   3. 租户状态 → UNPAID/PENDING 等拦截
     *
     * @return array{allowed: bool, code: int, message: string}
     */
    public function canWrite(): array
    {
        // 超管独立路径，本方法只返回 true（路由级白名单控制另算）
        if ($this->isSuperAdmin) {
            return ['allowed' => true, 'code' => 0, 'message' => ''];
        }

        // 成员角色层：Auditor 一律拦截
        if ($this->memberRole === TenantMemberRole::AUDITOR) {
            return ['allowed' => false, 'code' => 20502, 'message' => '当前角色无写权限'];
        }

        // 租户状态层
        if ($this->tenantStatus !== null && !$this->tenantStatus->canWrite()) {
            return [
                'allowed' => false,
                'code' => $this->tenantStatus->writeErrorCode(),
                'message' => $this->tenantStatus->writeErrorMessage(),
            ];
        }

        return ['allowed' => true, 'code' => 0, 'message' => ''];
    }

    /**
     * 当前请求是否允许读操作
     */
    public function canRead(): array
    {
        if ($this->isSuperAdmin) {
            return ['allowed' => true, 'code' => 0, 'message' => ''];
        }

        if ($this->tenantStatus !== null && !$this->tenantStatus->canRead()) {
            $err = $this->tenantStatus->loginError();
            return ['allowed' => false, 'code' => $err['code'], 'message' => $err['message']];
        }

        return ['allowed' => true, 'code' => 0, 'message' => ''];
    }
}
