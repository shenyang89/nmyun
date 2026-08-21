<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 租户成员认证 + 成员管理参数验证器
 *
 * 场景：
 * - login        : username + password 必填
 * - invite       : username 必填，role 可选（1-4），email 可选
 * - acceptInvite : token + password 必填
 * - changeRole   : role 必填（1-4）
 */
class TenantMemberAuthValidate extends Validate
{
    protected $rule = [
        'username' => 'require|length:1,64',
        'password' => 'require|length:6,128',
        'token'    => 'require|length:1,128',
        'role'     => 'require|in:1,2,3,4',
        'phone'    => 'length:0,20',
        'email'    => 'email|length:0,128',
    ];

    protected $message = [
        'username.require' => '用户名不能为空',
        'username.length'  => '用户名长度为 1-64 位',
        'password.require' => '密码不能为空',
        'password.length'  => '密码长度为 6-128 位',
        'token.require'    => '邀请 token 不能为空',
        'token.length'     => '邀请 token 长度不合法',
        'role.require'     => '角色不能为空',
        'role.in'          => '角色值非法（1=Owner 2=Admin 3=Operator 4=Auditor）',
        'phone.length'     => '联系电话长度不超过 20 位',
        'email.email'       => '邮箱格式不正确',
        'email.length'      => '邮箱长度不超过 128 位',
    ];

    protected $scene = [
        'login'        => ['username', 'password'],
        'invite'       => ['username', 'role', 'phone', 'email'],
        'acceptInvite' => ['token', 'password'],
        'changeRole'   => ['role'],
    ];
}
