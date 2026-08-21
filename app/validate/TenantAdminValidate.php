<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 租户管理参数验证器
 *
 * 场景：
 * - create：name + code + contact_name + contact_phone 必填
 * - switchStatus：status 必填 + 0-6 区间
 */
class TenantAdminValidate extends Validate
{
    protected $rule = [
        'name'          => 'require|length:1,100',
        'code'          => 'require|length:1,32|alphaDash',
        'contact_name'  => 'require|length:1,32',
        'contact_phone' => 'require|length:1,20',
        'status'        => 'require|in:0,1,2,3,4,5,6',
        'address'       => 'length:0,255',
    ];

    protected $message = [
        'name.require'          => '市场名称不能为空',
        'name.length'           => '市场名称长度为 1-100 位',
        'code.require'          => '市场编码不能为空',
        'code.length'           => '市场编码长度为 1-32 位',
        'code.alphaDash'        => '市场编码只能包含字母、数字、下划线、横线',
        'contact_name.require'  => '联系人姓名不能为空',
        'contact_phone.require' => '联系电话不能为空',
        'status.require'        => '状态不能为空',
        'status.in'             => '状态值非法（0-6）',
        'address.length'        => '市场地址长度不超过 255 位',
    ];

    protected $scene = [
        'create'       => ['name', 'code', 'contact_name', 'contact_phone', 'address'],
        'switchStatus' => ['status'],
    ];
}
