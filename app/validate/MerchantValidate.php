<?php

declare (strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 商户参数验证器
 * 用法：在 Controller 里 $this->validate($data, MerchantValidate::class);
 *       或 $this->validate($data, 'MerchantValidate.create');
 */
class MerchantValidate extends Validate
{
    protected $rule = [
        'merchant_no' => 'require|length:1,32|alphaNum',
        'name' => 'require|length:1,64',
        'owner_name' => 'length:1,32',
        'phone' => 'length:1,20|regex:/^1[3-9]\d{9}$/',
        'stall_no' => 'length:1,16',
        'status' => 'in:0,1',
    ];

    protected $message = [
        'merchant_no.require' => '商户编号不能为空',
        'merchant_no.length' => '商户编号长度为 1-32 位字母数字',
        'merchant_no.alphaNum' => '商户编号只能包含字母和数字',
        'name.require' => '商户名称不能为空',
        'name.length' => '商户名称长度为 1-64 位',
        'owner_name.length' => '经营者姓名长度为 1-32 位',
        'phone.length' => '手机号长度为 1-20 位',
        'phone.regex' => '手机号格式不正确',
        'stall_no.length' => '档口号长度为 1-16 位',
        'status.in' => '状态只能是 0 或 1',
    ];

    // 场景验证
    protected $scene = [
        'create' => ['merchant_no', 'name', 'owner_name', 'phone', 'stall_no', 'status'],
        'update' => ['name', 'owner_name', 'phone', 'stall_no', 'status'],
    ];
}
