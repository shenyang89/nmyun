<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\TenantMemberAuthService;
use app\validate\TenantMemberAuthValidate;

/**
 * 租户成员认证 + 成员管理控制器
 *
 * 接口设计（Phase 1.8）：
 * - POST   /api/auth/login             成员登录（白名单，无需 JWT，需 X-Tenant-Id）
 * - POST   /api/member/invite           创建邀请（需 JWT + Owner/Admin）
 * - POST   /api/member/invite/accept    接受邀请（白名单，无需 JWT）
 * - GET    /api/member                  成员列表（需 JWT）
 * - PATCH  /api/member/:id/role        修改成员角色（需 JWT + Owner/Admin）
 * - DELETE /api/member/:id             移除成员（需 JWT + Owner/Admin）
 *
 * 鉴权：
 * - 不加 #[AllowSuperAdminBypass]：超管访问这些接口必须先以租户成员身份存在
 *   （§13.8.3 + Phase 1.6 约束：超管仅通过 X-Target-Tenant-Id 跨租户管理业务资源，
 *    而成员管理是租户内自治事务，超管不该绕过 Owner/Admin 角色校验）
 * - login / invite/accept 走路由白名单（TenantContextMiddleware::PLATFORM_WHITELIST）
 *
 * 分层约束：
 * - Controller 只做：接收请求 → 参数校验 → 调 Service → 返回 JSON
 * - 不直接 use TenantMember / TenantMemberRepository
 * - 不在此处做角色权限判断（由 Service 内部根据 ctx.memberRole 校验）
 */
class TenantMemberController extends BaseController
{
    public function __construct(
        \think\App $app,
        protected TenantMemberAuthService $service
    ) {
        parent::__construct($app);
    }

    /**
     * POST /api/auth/login  租户成员登录
     *
     * Body: {"username": "string", "password": "string"}
     * Response: {"token": "jwt", "member": {...}}
     *
     * 注：路由白名单允许无 JWT 访问，但 X-Tenant-Id 必须携带
     *     （中间件从 X-Tenant-Id 注入 ctx.tenant_id，再查 username）
     */
    public function login()
    {
        $data = $this->request->post();
        $this->validate($data, TenantMemberAuthValidate::class . '.login');

        $result = $this->service->login(
            (string) $data['username'],
            (string) $data['password']
        );

        return $this->success([
            'token' => $result['token'],
            'member' => $result['member']->toArray(),
        ], '登录成功');
    }

    /**
     * POST /api/member/invite  创建邀请
     *
     * Body: {"username": "string", "role": 3, "phone": "", "email": ""}
     * Response: {"member": {...}, "invite_token": "xxx", "invite_url": "/api/...", "invite_expires_at": "..."}
     */
    public function invite()
    {
        $data = $this->request->post();
        $this->validate($data, TenantMemberAuthValidate::class . '.invite');

        $result = $this->service->createInvite([
            'username' => (string) $data['username'],
            'role'     => isset($data['role']) ? (int) $data['role'] : null,
            'phone'    => $data['phone'] ?? null,
            'email'    => $data['email'] ?? null,
        ]);

        // 输出时去除敏感字段（model 已 hidden password/invite_token，但 invite_token 此处需返回）
        return $this->success([
            'member' => $result['member']->toArray(),
            'invite_token' => $result['invite_token'],
            'invite_url' => $result['invite_url'],
            'invite_expires_at' => $result['invite_expires_at'],
        ], '邀请创建成功');
    }

    /**
     * POST /api/member/invite/accept  接受邀请
     *
     * Body: {"token": "xxx", "password": "string", "username": "可选覆盖"}
     * Response: {"member": {...}}
     *
     * 注：路由白名单允许无 JWT 访问（用户尚未登录）
     */
    public function acceptInvite()
    {
        $data = $this->request->post();
        $this->validate($data, TenantMemberAuthValidate::class . '.acceptInvite');

        $username = isset($data['username']) ? (string) $data['username'] : null;

        $member = $this->service->acceptInvite(
            (string) $data['token'],
            (string) $data['password'],
            $username
        );

        return $this->success([
            'member' => $member->toArray(),
        ], '邀请接受成功，账号已激活');
    }

    /**
     * GET /api/member  成员列表（分页）
     */
    public function list()
    {
        ['page' => $page, 'page_size' => $pageSize] = $this->resolvePagination();
        $result = $this->service->listMembers($page, $pageSize);

        return $this->paginated(
            $result['list']->toArray(),
            $result['total'],
            $result['page'],
            $result['page_size']
        );
    }

    /**
     * PATCH /api/member/:id/role  修改成员角色
     *
     * Body: {"role": 3}
     */
    public function changeRole(int $id)
    {
        $data = $this->request->post();
        $this->validate($data, TenantMemberAuthValidate::class . '.changeRole');

        $member = $this->service->changeRole($id, (int) $data['role']);
        return $this->success($member->toArray(), '角色已更新');
    }

    /**
     * DELETE /api/member/:id  移除成员
     */
    public function remove(int $id)
    {
        $this->service->removeMember($id);
        return $this->ok('成员已移除');
    }
}
