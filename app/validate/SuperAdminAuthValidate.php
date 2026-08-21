<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 平台超管登录参数验证器
 *
 * 用法：$this->validate($data, SuperAdminAuthValidate::class);
 */
class SuperAdminAuthValidate extends Validate
{
    protected $rule = [
        'username' => 'require|length:1,64',
        'password' => 'require|length:1,128',
    ];

    protected $message = [
        'username.require' => '登录账号不能为空',
        'username.length'  => '登录账号长度为 1-64 位',
        'password.require' => '密码不能为空',
        'password.length'  => '密码长度为 1-128 位',
    ];
}
