<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 套餐参数验证器
 */
class TenantPlanValidate extends Validate
{
    protected $rule = [
        'plan_code'     => 'require|length:1,32|alphaDash',
        'plan_name'     => 'require|length:1,64',
        'tier'          => 'require|in:1,2,3',
        'price_monthly' => 'integer|egt:0',
        'price_yearly'  => 'integer|egt:0',
        'max_devices'    => 'integer|egt:0',
        'max_merchants'  => 'integer|egt:0',
        'max_storage_days' => 'integer|egt:0',
        'status'        => 'in:0,1,2',
    ];

    protected $message = [
        'plan_code.require' => '套餐编码不能为空',
        'plan_name.require' => '套餐名称不能为空',
        'tier.require'      => '套餐等级不能为空',
        'tier.in'           => '等级必须是 1（基础）/2（专业）/3（旗舰）',
        'status.in'          => '状态值非法（0草稿 1启用 2停用）',
    ];

    protected $scene = [
        'create' => ['plan_code', 'plan_name', 'tier', 'price_monthly', 'price_yearly', 'max_devices', 'max_merchants', 'max_storage_days', 'status'],
    ];
}
