<?php

declare(strict_types=1);

namespace app\attribute;

use Attribute;

/**
 * 超管绕过白名单注解（§13.8.3 业务层授权判定）
 *
 * 用法：在 Controller 方法上标注 #[AllowSuperAdminBypass]
 *
 * 行为（由 BaseController 后置钩子解析）：
 * - 标注的方法：超管 JWT 调用直接放行（不要求超管是该租户成员）
 * - 未标注的方法：即使超管调用，也必须按租户内成员角色判定；
 *   若超管不在该租户的 tenant_member 表中 → 40302 拒绝
 *
 * 防止「超管误操作改了 A 市场数据」：
 *  默认所有接口对超管关闭，必须显式标注的方法才允许超管调用
 *
 * 示例：
 *   class MerchantController extends BaseController {
 *       #[AllowSuperAdminBypass]
 *       public function list() { ... }   // 超管可调
 *
 *       public function create() { ... } // 超管调用需先加入该租户成员
 *   }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class AllowSuperAdminBypass
{
}
