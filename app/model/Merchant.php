<?php

declare (strict_types=1);

namespace app\model;

/**
 * 批发商户 / 档口（merchant_id）
 * 对应五大核心实体里的「批发商户/档口」
 *
 * 字段（迁移脚本 20260821062200_create_merchant_table）：
 *   id          BIGSERIAL PK
 *   tenant_id   BIGINT                -- 所属租户ID（SaaS 行级隔离）
 *   merchant_no VARCHAR(32) UNIQUE   -- 商户编号（市场内唯一）
 *   name        VARCHAR(64)           -- 档口/商户名称
 *   owner_name  VARCHAR(32)           -- 老板姓名
 *   phone       VARCHAR(20)           -- 联系电话
 *   stall_no    VARCHAR(16)           -- 档口号 A-105
 *   status      SMALLINT DEFAULT 1    -- 0禁用 1正常
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP
 */
class Merchant extends BaseModel
{
    protected $name = 'merchant'; // 不含表前缀的真实表名

    /** 时间字段（迁移脚本使用 created_at/updated_at，非 BaseModel 默认 create_time/update_time） */
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $json = [];

    public function allowSearchFields(): array
    {
        return ['id', 'merchant_no', 'name', 'phone', 'status'];
    }

    public function allowCreateFields(): array
    {
        return ['merchant_no', 'name', 'owner_name', 'phone', 'stall_no', 'status'];
    }

    public function allowUpdateFields(): array
    {
        return ['name', 'owner_name', 'phone', 'stall_no', 'status'];
    }
}
