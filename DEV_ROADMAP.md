# 智慧农贸云（nmyun）开发任务规划（执行路线图）

> **文档定位**：本文件是开发任务执行的**单一真源（Single Source of Truth）**，所有任务的阶段归属、优先级、前置依赖、验收标准、预估工时均以本文件为准。项目状态、决策、全局规范、错误码、技术红线仍以 `PROJECT_MEMORY.md` 为准，二者是"战略/战略约束"与"执行任务清单"的关系。
>
> **最后更新日期**：2026-08-21
> **当前阶段**：Phase 0 — 工程化基础设施（环境与脚手架）

---

## 0. 阶段全景图

```
Phase 0: 工程化基础（环境+脚手架）─── 1~2 周
          │
          ▼
Phase 1: SaaS 多租户安全底座（最高红线）─── 2~3 周 ⚠️ 必须验收通过后才能进入 Phase 2+
          │
          ▼
Phase 2: 交易业务核心（链路 A-E 商户-采购-订单-团购-金融骨架）─── 3~4 周
          │
          ▼
Phase 3: IoT 物联接入底座 + 5 类设备 MVP（链路 F-J）─── 4~6 周
          │
          ▼
Phase 4: 平台治理/合规/计费/超管运维后台 ─── 2~3 周
          │
          ▼
Phase 5: 压测、安全审计、灰度上线、商业模型验证 ─── 3~4 周
```

**里程碑总工期预估（串行）：15~22 周 / 约 4~6 个月**
**可并行压缩：** Phase 2 与 Phase 3 前半段（IoT 接入底座）可并行；Phase 4 可与 Phase 3 后半段并行。

---

## 1. 优先级定义（全局统一）

| 优先级 | 含义 | 阻塞性 | 示例 |
|--------|------|--------|------|
| **P0** | 里程碑交付的最小必要条件，不做完无法进入下一阶段 | **强阻塞**，该阶段所有 P0 100% 完成才能通过里程碑验收 | TenantContextMiddleware；BaseModel 全局 tenant_id Scope；EMQX 一机一密 |
| **P1** | 该阶段的重要功能，**若时间紧可在里程碑验收前暂欠 1~2 项，但必须在进入下一阶段后 1 周内补齐** | 中阻塞（允许少量欠账） | 超管审计日志接口；Redis 穿透/击穿/雪崩防护；链路 F 智能水电抄表入库 |
| **P2** | 体验优化 / 非核心特性 / 预留扩展点，**允许在阶段末进入 Backlog，排到后续迭代集中开发** | 不阻塞里程碑验收，转入 Backlog | AI 智能分析接入；WebRTC 低延迟优化；移动端小程序适配 |

---

## 2. Phase 0 — 工程化基础设施（环境与脚手架）

🎯 **阶段目标**：把"项目能跑起来 + 后续开发不踩坑"基础打牢，统一代码规范、环境、开发工具链。
⏳ **预估工期**：1~2 周
🧱 **前置依赖**：无（启动项）
✅ **里程碑验收标准**：所有开发者 `git clone && composer install && cp .env.example .env && docker compose up -d && php think run` 后本地环境全链路通过（PHP/PG/Redis/EMQX 均健康检查 OK），`phpunit`/`pest` 基础测试 100% 绿

---

| ID | 任务名称 | 优先级 | 前置依赖（同阶段内） | 验收标准（交付物） | 工时(人天) | 负责人 |
|----|---------|--------|---------------------|--------------------|-----------|--------|
| **0.1** | ⚠️ 安装 PHP 运行时扩展 `pdo_pgsql` + `redis`，更新 Dockerfile 与 Docker Compose 一键拉起整套环境（pg+redis+emqx+zlmediakit+workerman-sse） | **P0** | 无 | `php -m | grep pgsql` / `php -m | grep redis` 均有输出；`docker compose up -d` 后 `docker ps` 5 个容器全部 healthy；编写 `docs/LOCAL_DEV.md` 说明 5 步内启动 | 2d | 后端 |
| **0.2** | 安装 `topthink/think-migration` 迁移工具并配置命令空间；写迁移执行规范（migration 命名 `YYYYMMDD_HHMMSS_desc.php`，所有表必须加 `tenant_id` + 注释 + 索引语句）+ 首个 migration 占位文件 | **P0** | 0.1 | `php think migrate:create Demo` 可用；迁移规范落地在 `docs/MIGRATION_SPEC.md` | 1d | 后端 |
| **0.3** | 代码规范落地：安装 `friendsofphp/php-cs-fixer` + `phpstan/phpstan`（或 Psalm）→ 写 `.php-cs-fixer.dist.php` 规则 + `phpstan.neon`（level 5 起步）→ 写 Makefile 命令 `make cs-check / make cs-fix / make stan` → CI 钩子（GitHub Action / Travis 已有）加入这两步失败即 red | **P0** | 0.2 | `make stan` 0 errors；`make cs-check` 所有 PHP 文件通过；CI PR 不通过无法合并；规则说明写入 `docs/CODE_STYLE.md` | 2d | 后端 |
| **0.4** | 单元测试脚手架：安装 `pestphp/pest`（或 phpunit）→ 在 `tests/` 下建立 `Unit/Feature/Integration/IoTSimulator` 4 个目录，写 TenantContext 与 BaseModel Scope 的测试脚手架占位 | P1 | 0.3 | `./vendor/bin/pest` 能跑，至少有 2 个示例测试通过；`make test` 命令可用 | 1d | 后端 |
| **0.5** | Composer 依赖完善：加入 `php-mqtt/client`（MQTT 下行发布）、`firebase/php-jwt`（Token 生成校验）、`aliyuncs/oss-sdk-php`（对象存储，录像与固件）、`topthink/think-multi-app`（如需多应用分离）+ 写版本锁定策略（`composer.lock` 允许回加入 .gitignore 吗？— 默认入仓，部署环境 `composer install --no-dev`） | P1 | 0.1 | `composer show` 列出新增 4 个包；`php -r "new \PhpMqtt\Client\MqttClient('127.0.0.1',1883);"` 无类不存在错误 | 1d | 后端 |
| **0.6** | 开发工具辅助：写 Makefile 顶层命令集合（`make up/down/logs/shell/test/cs/stan/migrate/seed/clear`），把 docker compose + php think + php pest 全部封装为 2~3 字符短命令 | P2 | 0.1 | 新人 5 分钟内能熟练使用所有常用命令，`make help` 输出完整命令列表与说明 | 1d | 后端 |

**Phase 0 合计：9~10 人天 / P0：5d，P1：3d，P2：1d**

---

## 3. Phase 1 — SaaS 多租户安全底座（⚠️ 最高红线阶段）

🎯 **阶段目标**：把"租户隔离"做透，做到**任何业务代码哪怕不写一行 `tenant_id` 也不会横向越权**。这是第一优先级的安全红线，必须 100% 验收通过后才能开 Phase 2/3。
⏳ **预估工期**：2~3 周
🧱 **前置依赖**：Phase 0 全部 P0 完成
✅ **里程碑验收标准**：通过 3 个安全 UAT 用例——（1）A 租户 Token 拼 B 租户 merchant_id 调接口，HTTP 403/404 且 SQL 日志里确认带了 A tenant_id；（2）A 租户执行批量删除接口，DB 行数影响数 ≤A 租户自身行数；（3）超管调非白名单接口，被拒绝 40301。

---

| ID | 任务名称 | 优先级 | 前置依赖（同阶段内） | 验收标准（交付物） | 工时(人天) | 负责人 |
|----|---------|--------|---------------------|--------------------|-----------|--------|
| **1.1** | 迁移脚本：SaaS 租户体系 5 表（`tenant` / `tenant_member` / `tenant_plan` / `tenant_invoice` / `super_admin_audit_log`），字段完整、枚举注释到位、`tenant_id` 联合索引与外键约束全部加上 | **P0** | — | `php think migrate:run` 全部绿；`\d nmyun_tenant` 能看到 7 种 status 枚举注释 | 2d | 后端+DBA |
| **1.2** | `TenantContext` 单例类 + `TenantStatus` 枚举（7 种状态） + `TenantMemberRole` 枚举（Owner/Admin/Operator/Auditor）：提供 `setId()/getId()/isSuperAdmin()/currentMember()/canWrite():bool` 方法 | **P0** | 1.1 | 单元测试覆盖 `canWrite()` 对 7 种状态的正确返回（欠费=读放行写拦截） | 1.5d | 后端 |
| **1.3** | `TenantContextMiddleware` 中间件：**严格按 §13.8.2 三段式职责**——（a）上下文注入；（b）7 种状态→错误码+HTTP 码+读写权限**全部分支显式写出，无 default 兜底**；（c）漏状态枚举直接抛异常提醒开发者补 | **P0** | 1.2 | PHPUnit 写 7 个 DataProvider 用例，每个状态分别验证 GET/POST 返回的错误码与 body | 2.5d | 后端（核心） |
| **1.4** | `BaseModel` ORM 全局 Scope + `BaseRepository` 二次校验：（a）所有继承 BaseModel 的查询自动加 `tenant_id=TenantContext::getId()`；（b）INSERT/UPDATE/DELETE 若检测不到 tenant_id 条件抛 `LogCritical + E_USER_ERROR`；（c）`BaseRepository::findById/updateById/deleteById/findOrFail` 执行完**再次判断 model.tenant_id === ctx.tenant_id**，不一致立即抛 40301 | **P0** | 1.3 | 伪造测试：手工在 DB 中插入一条 tenant_id=9999 的 merchant，ctx tenant=1 去查 → 必须 404；直接删 → 0 rows affected；集成测试写好留档 | 3d | 后端+安全 |
| **1.5** | 平台根超管体系：（a）`super_admin` 表（独立于 tenant_member）+ 登录接口签发 JWT，payload 只写 `is_super_admin=true + sadmin_id`，**绝不夹带 tenant_id 或 tenant_role**；（b）`X-Target-Tenant-Id` 请求头 + 中间件分支：仅超管用，解析后校验目标租户存在 + 注入 ctx | **P0** | 1.3 | 测试：超管不传 `X-Target-Tenant-Id` → 422 提示必须指定目标租户；传了租户 2 → 所有查询自动用 tenant_id=2，ctx 同时保留 `is_super_admin=true` | 2d | 后端 |
| **1.6** | 超管审计与白名单机制：（a）所有继承自 `BaseController` 的写接口（POST/PUT/PATCH/DELETE），若 `ctx.is_super_admin==true` 自动记录 `super_admin_audit_log`（sadmin_id、target_tenant_id、route、关键参数、结果、耗时、IP、UA）；（b）新增 `#[AllowSuperAdminBypass]` 路由注解，仅该注解标识的接口允许超管跳租户成员角色校验，其他接口即使超管也必须作为该租户成员存在 | **P0** | 1.5 | 2 条集成测试：① 超管调非白名单接口 → 40302（不是该租户成员）；② 调白名单接口 → 200 且 audit_log 出现 1 行 | 3d | 后端+安全 |
| **1.7** | 平台超管后台 4 个 MVP 接口：（a）租户创建/开通；（b）租户 7 种状态切换（欠费一键冻结，非删除）；（c）套餐与配额配置（基础版/专业版/旗舰版 IoT 设备数上限、云存储天数）；（d）账单概览（按租户按月） | P1 | 1.1+1.5 | Postman 跑通 4 个接口 CRUD；前端（若有）可独立对接 | 2d | 后端 |
| **1.8** | 租户自助登录 + 成员邀请链路：（a）租户 Owner 登录（走 tenant_member）；（b）邀请成员邮件/短信链接生成（24h 过期）；（c）接受邀请→写入 tenant_member（默认 Admin 以下角色） | P1 | 1.7 | 邀请链接 24h 后打开 20504；已加入成员点击提示已加入 | 2d | 后端 |
| **1.9** | Think Command 孤儿数据巡检脚本：遍历所有业务表（自发现，不 hardcode 表名）`LEFT JOIN tenant WHERE t.id IS NULL` 统计，>0 即发邮件告警；可选 `--repair={drop|assign}` 模式 | P1 | 1.4 | 手工构造孤儿数据 1 条 → 命令执行 1 次 → 日志/邮件命中；写 crontab 配置文档 | 1.5d | 后端 |
| **1.10** | 性能基准测试：带 tenant_id 后 PG 查询性能对比（相同 SQL 加/不加 tenant_id + 索引），出 1 页基准报告，确认查询回退 < 10% 即可 | P2 | 1.4 | 基准报告存在 `docs/perf-phase1-benchmark.md` | 1d | 后端 |

**Phase 1 合计：20.5 人天 / P0：14d，P1：7.5d，P2：1d**

---

## 4. Phase 2 — 交易业务核心（链路 A-E 商户-采购-订单-团购-金融骨架）

🎯 **阶段目标**：把"人+货"两大业务闭环跑通，覆盖商户→采购方→消费者完整交易链路，同时让 SaaS 订阅费+交易佣金可以实际出账单。
⏳ **预估工期**：3~4 周
🧱 **前置依赖**：Phase 1 所有 P0 验收通过 + 关键 UAT 安全用例全绿
✅ **里程碑验收标准**：（1）用 Owner 账号登录租户后台，完成 1 个商户从创建→状态切换→B 端客户下单→商户收款→佣金入账完整流程，API 层面无错；（2）月底 `php think invoice:generate 2026-09` 命令可生成该租户 9 月 SaaS 订阅费+交易佣金合并账单，金额核对无误。

---

| ID | 任务名称 | 优先级 | 前置依赖 | 验收标准（交付物） | 工时(人天) | 负责人 |
|----|---------|--------|---------|--------------------|-----------|--------|
| **2.1** | 迁移脚本：链路 A-E 业务 6 表（`supplier`/`merchant` 追加 tenant_id 索引/`buyer`/`c_user`（消费者）/`group_leader`/`trade_order`（A/B/C 三端订单统一表，用 type 区分）），所有表含 `tenant_id` + 业务唯一约束联合索引 | **P0** | Phase 1.1 | migrate run 成功；`merchant.tenant_id+merchant_no` 唯一索引正确，避免跨市场商户号冲突 | 2d | 后端+DBA |
| **2.2** | 交易类 6 大实体的完整 Model+Repository+Service+Controller+Validate 链路（复用 Merchant 示例模式），**所有 Controller 调 Service，Service 不得裸 SQL** | **P0** | 2.1 + Phase1.4（确认 Scope 生效） | 每个模块 Postman CRUD 6 个接口全跑通；跑"越权用例"：删 B 租户供应商→404 | 5d | 后端 |
| **2.3** | 链路 B B2B 下单 → 链路 C C 端微信/支付宝下单（先接沙箱） + 订单状态机（draft→paid→shipping→done/refund），所有写操作**事务包裹**，生成 `trade_order_flow_log` 流水不可篡改 | **P0** | 2.2 | B 端采购下单→微信沙箱成功支付→订单状态 paid→对账流水 log 行数 3；退款成功→done→refund 两条流水 | 4d | 后端 |
| **2.4** | Redis 缓存策略：（a）租户级 key 前缀 `nmyun:{tenant_id}:xx`；（b）穿透（布隆过滤器占位）+ 击穿（互斥锁 `set nx`）+ 雪崩（TTL ±10% 抖动）防护；（c）封装 `TenantCache` Trait 统一加前缀 | P1 | 2.2 | 压测脚本 1000 次缓存 miss 后缓存命中率 ≥95%；无穿透击穿雪崩告警日志 | 2d | 后端 |
| **2.5** | 三合一账单生成（Think Command `invoice:generate YYYY-MM`）：SaaS 订阅费（plan.fee/月 × 月数比例）+ 交易佣金（order.amount × 费率，按品类分档）+ IoT 硬件服务费占位（Phase 3 接入后补）→ 生成 `tenant_invoice` 明细行 | P1 | 2.3 | 生成 1 个模拟租户当月账单→金额 3 项加总核对无误；账单 PDF（或 CSV）导出接口可用 | 2.5d | 后端+财务 |
| **2.6** | 链路 D 社区团购 MVP：团长注册→开团→C 端参团→截团→发货→团长分佣（与账单模块佣金记录打通） | P1 | 2.3 | 截团后分佣记录写入 invoice 明细项；佣金金额手动核算一致 | 2.5d | 后端 |
| **2.7** | 链路 E 供应链金融 MVP 骨架：商户授信额度表 +「订单完成后 30 天账期保理」占位 Service（先不真实放款，只写额度占用/释放逻辑与账单关联） | P2 | 2.5 | 商户申请保理→额度扣减→还款→额度释放；账单明细里"金融服务费"记录正确 | 2d | 后端 |
| **2.8** | 需求文档+泳道图：A-E 每条链路写 1 张泳道图（Mermaid 放 docs/process/*.md），涉及角色/系统/外部接口 3 个泳道 | P2 | 2.3 | docs 下 5 个 md，每个含 mermaid 图 | 2d | 产品+后端 |
| **2.9** | 三合一费率模型可配置化：在 `tenant_plan` 表写 SaaS 基础费、IoT 设备超额阶梯单价、交易佣金分档费率数组（JSON 字段）；`BaseInvoiceService` 统一读取计算，不得硬编码 | P2 | 2.5 | 修改套餐配置→重新生成账单→金额变化符合公式；无需改代码 | 1d | 后端 |

**Phase 2 合计：23 人天 / P0：11d，P1：9d，P2：5d**

---

## 5. Phase 3 — IoT 物联接入底座 + 5 类设备 MVP（链路 F-J）

🎯 **阶段目标**：把水电/监控/灯/空调/收银称 5 类设备的"接入+鉴权+双向通信+租户路由"做通，让每类设备至少有 MVP 场景能在真实/模拟设备上联调通过。
⏳ **预估工期**：4~6 周（其中 3.1~3.3 可与 Phase 2 并行）
🧱 **前置依赖**：Phase 1 全部 P0（因为所有设备表都要带 tenant_id，Scope 必须先落地）
✅ **里程碑验收标准**：（1）5 类设备各 1 台通过 MQTT 模拟脚本或真实硬件接入，SN 绑定租户→上线→心跳→读数/事件→后端入库→SSE 推送到前端看板，全流程 ≤3s；（2）EMQX ACL 测试：A 设备尝试订阅 B 租户 Topic，立即断开；（3）监控录像回放：非租户 Owner 调接口 40301。

---

| ID | 任务名称 | 优先级 | 前置依赖 | 验收标准（交付物） | 工时(人天) | 负责人 |
|----|---------|--------|---------|--------------------|-----------|--------|
| **3.1** | EMQX 5.x 与 ZLMediaKit 部署：docker compose 扩展 emqx+zlmediakit 服务，暴露端口（1883/8083/8084/18083 管理后台、80/554/1935 流媒体）+ 写 `docs/IOT_SETUP.md` | **P0** | Phase0.1 已写 docker compose 基本框架 | 本地 `make up` → EMQX 管理后台 `http://localhost:18083` 登录；ZLMediaKit HTTP API `/index/api/getServerConfig` 返回 JSON | 2d | 后端+运维 |
| **3.2** | 迁移脚本：IoT 7 表（`device_product` 平台级共享/`device` 统一 5 类设备用 type 字段/`device_telemetry` TimescaleDB 超表/`device_alert`/`device_ota_firmware`/`device_work_order`/`device_online_log`），**除 device_product 外全部带 tenant_id 且与 tenant 外键** | **P0** | Phase 1.1 | `SELECT create_hypertable('nmyun_device_telemetry', 'report_time');` 成功；插入 10w 模拟时序数据查询<100ms | 2d | 后端+DBA |
| **3.3** | MQTT 一机一鉴 + ACL：（a）EMQX 配置 HTTP Auth 钩子（认证+ACL 两个 URL）→ 调用平台 `/internal/mqtt/auth` 与 `/internal/mqtt/acl`；（b）校验 ProductKey + DeviceSecret + SN；（c）**ACL 返回的 subscribe/publish 白名单必须精确到 `{tenant_id}/{product_id}/{device_sn}/#`，禁止跨租户** | **P0** | 3.1+3.2 | 模拟设备：① DeviceSecret 错误→Connect 被拒；② 正确→连接成功；③ 订阅 `other_tenant/+/+/status`→ACL 拒→断开；④ 订阅自己 Topic→OK | 3.5d | 后端+IoT |
| **3.4** | `PhpMqttClient` 封装 + Redis Stream 异步队列：（a）下行命令（灯开关、水电断通等）走 `mqtt:{tenant_id}:{sn}:cmd` 主题，QoS 1；（b）订阅 EMQX 系统主题 `$SYS/brokers/+/clients/+/connected/disconnected` → 写 Redis Stream → Workerman/Think Queue 消费者异步更新 device.online_status 与 `device_online_log`；（c）所有命令带 `cmd_id` 状态机追踪（已下发→已送达→已执行→失败） | **P0** | 3.3 | 发一条关灯命令→设备端模拟器响应→cmd_id 状态机流转 4 步记录完整；模拟器下线→30s 内 device.online_status=offline | 4d | 后端+IoT |
| **3.5** | 北向 SSE 实时推送：`GET /api/device/stream` 接口（SSE），鉴权后按 ctx.tenant_id 过滤事件，推送 3 类：设备在线/离线（轻量）、水电用量更新（30s 聚合）、告警事件（立即推）。实现 Heartbeat 30s 一次防止浏览器断开 | **P0** | 3.4 | 浏览器 `EventSource('/api/device/stream')` 打开后 → 模拟器报警→ 500ms 内收到 alert 事件；刷新 100 次无连接泄漏（`netstat -anp | grep 8000`） | 3d | 后端 |
| **3.6** | **链路 F 智能水电 MVP**：（a）`POST /api/device/water-meter/reading` MQTT 上报入库；（b）每日 `php think meter:daily-settle` 按 tenant→merchant→公区分摊；（c）阈值规则：瞬时流量>阈值→生成 device_alert 31001；（d）断电策略：账单逾期>48h→发 MQTT 断电命令，宽限期内充值自动合闸；紧急恢复通道单独接口 + 审计日志 | **P0** | 3.5 | 模拟 1 台商户水电表→24h 上报 96 次→日结后账单明细正确；触发漏水→告警；逾期 3 天→断电命令下发 | 4d | 后端+IoT |
| **3.7** | **链路 G 智能监控 MVP**：（a）`POST /api/device/camera/register` 注册国标编码+RTSP 地址；（b）调用 ZLMediaKit `index/api/addStreamProxy` 代理 RTSP→返回 WebRTC/FLV/HLS 播放地址；（c）录像计划（全天/移动侦测）→ 写对象存储 + 录像索引表（强 tenant_id）；（d）**录像回放接口强制：租户 Owner/超管白名单接口 2 选 1，超管访问必须留痕 audit_log**；（e）AI 算法服务 HTTP 回调占位（/internal/ai/event）→ 生成 device_alert | **P0** | 3.5 | 推一路模拟 RTSP→前端 WebRTC 出画面；非 Owner 成员调回放→40303；超管调→播放 + audit_log 写入 1 行 | 5d | 后端+视频 |
| **3.8** | **链路 H 智能灯控+空调 MVP**：（a）灯/空调 device.type 区分；（b）定时策略表 `device_timing_rule`（cron 表达式）；（c）Think 每 1min 扫 `php think device:dispatch-timing` 批量下发命令；（d）公区能耗按楼层/区域面积系数分摊到商户；（e）手动控制与定时冲突→32003 自动降级（手动优先 1h） | P1 | 3.6 | 设一个"工作日 06:00 开灯、20:00 关灯"策略→调度触发→命令下发→状态更新；手动开空调后定时关触发→日志里 32003 降级记录 | 3d | 后端+IoT |
| **3.9** | **链路 I 智能收银称 MVP**：（a）每台收银称强绑定 merchant_id（device.merchant_id 非空）；（b）`/internal/scale/weighing` 称重流水+交易流水双向校验：`SHA256(amount+nonce+secret)` 比对，**差异率 >0.1% 立即：①发告警 32502；②冻结该商户提现账户（写 merchant.freeze_withdraw=true）**；（c）溯源二维码生成（短链跳 `/trace/{code}` 页，展示产地→档口→称重→支付 4 步数据） | P1 | Phase2.3 + 3.5 | 收银称模拟器 100 笔交易全部匹配→无告警；手工改 1 笔金额→差异>0.1%→告警+冻结；扫码溯源页展示完整链路 | 4d | 后端 |
| **3.10** | **链路 J IoT 统一运营 MVP**：（a）SN 激活 API `POST /api/device/activate`：校验 product.valid=true → device 未绑定 → 写入 tenant_id+绑定时间；（b）设备在线率看板接口：`GET /api/dashboard/device/online` 按设备类型返回在线率/离线 Top10；（c）OTA：上传固件→签名校验→灰度 10%→100%→版本统计；（d）告警自动派单：未处理告警>30min→创建设备工单→分配租户运维→超时 2h→升级（33002） | P1 | 3.3+3.6/3.7/3.8 任一 | 注册固件→3 台设备在线 2 台离线→看板显示 66.67%；1 台持续告警→工单生成→超时升级记录完整 | 3.5d | 后端 |
| **3.11** | EMQX 规则引擎（Rule Engine）写 PG / 写 Redis：设备 telemetry 高频写入不经过 PHP，直接由 EMQX Rule 用 PgSQL 动作批量 INSERT，降低 PHP 消费压力；ThinkPHP 端只做查询与低频校验 | P2 | 3.3 | Rule 写入 1000 条/s PHP-FPM CPU < 20%；关闭 Rule 用 PHP 消费 CPU > 80% 对比报告 | 1.5d | 后端+IoT |
| **3.12** | 设备模拟器工具：在 `tests/IoTSimulator/` 下写 5 个 CLI 脚本（模拟水表/摄像头心跳/灯/空调/收银称），`php think simulate:water-meter {sn} --rate=5m` 可在本地压测 1000 台设备并发 | P2 | 3.6 | 本地开 1000 台水电表模拟器 → 5 分钟后数据库写入行数 = 1000×5=5000 行 ± 0.5% | 2d | 后端 |

**Phase 3 合计：37.5 人天 / P0：23.5d，P1：11.5d，P2：3.5d**

---

## 6. Phase 4 — 平台治理/合规/计费/超管运维后台

🎯 **阶段目标**：从"功能可用"到"可运营、可审计、合规"，覆盖财务计费闭环、视频合规、民生红线、防逃单等项目关键决策条款。
⏳ **预估工期**：2~3 周（可与 Phase 3 后半段并行）
🧱 **前置依赖**：Phase 2.5 账单生成模块可用 + Phase 3.6/3.7/3.9 IoT F/G/I 链路基础可用
✅ **里程碑验收标准**：（1）审计一条超管跨租户看录像完整链路：登录→切换租户→播放录像，audit_log 表中存在 3 条关键日志（切租户、取播放地址、拉流）；（2）模拟水电欠费到断电全流程：告警(48h)→预警(24h)→断电前 1h 双通知→断电→紧急恢复通道开启→操作人/原因/时长 4 字段齐全且不可删改；（3）收银称本地改单 1 笔→差异率>0.1%→提现冻结成功。

---

| ID | 任务名称 | 优先级 | 前置依赖 | 验收标准（交付物） | 工时(人天) | 负责人 |
|----|---------|--------|---------|--------------------|-----------|--------|
| **4.1** | 监控视频合规落地：（a）回放接口返回的 m3u8/FLV 走独立签名 URL（5min 过期），**禁止直接暴露对象存储永久 URL**；（b）前端播放器加入人脸自动模糊 CSS/SVG filter（涉及自然人录像段，若后续有 AI 检测结果可按时间段模糊，否则默认播放提示）；（c）超管跨租户播放 100% 走 `device/camera/replay/super-admin` 独立接口并强制写入 audit_log（接口层做，非业务层） | **P0** | Phase3.7 + Phase1.6 | ① 回放 URL 生成后 5min 过期→403；② 超管直接调普通回放→403；③ 超管调独立接口→200 且 audit_log 有 1 行 | 2.5d | 后端+安全 |
| **4.2** | 智能水电民生红线（48h 宽限期）落地：（a）欠费→进入 grace 表（tenant_invoice_grace_period），48h 倒计时，到点才真正发 MQTT 断电命令；（b）倒计时 48h/24h/1h 三个节点分别调用短信/APP 推送（2 种都发）失败自动重试 3 次；（c）紧急恢复通道：POST `/api/device/meter/emergency-restore` 仅租户 Owner + 平台超管可调用，恢复时长=24h 不可配置，**强制写入 meter_emergency_log（操作人/原因/时长/关联工单/联系方式）永久不可删改** | **P0** | Phase3.6 | 账单逾期 T=0→T=47h 仍欠费→日志里记录 2 次通知成功；T=48h→断电命令下发；Owner 点紧急恢复→grace 表延期 24h；emergency_log 有完整记录 | 3d | 后端 |
| **4.3** | 收银称防逃单（差异率>0.1% 冻结提现）闭环：（a）`/internal/scale/weighing` 写流水时同步算 rolling hash（双向签名）；（b）Think 每 10min `php think scale:reconcile 2026-08-21` 对 1 天内逐商户算差异率；（c）>0.1% → ① 写入 scale_fraud_alert；② 置 `merchant.freeze_withdraw=1`；③ 短信通知 Owner + 市场管理员；（d）平台超管白名单接口 `POST /tenant/merchant/unfreeze-withdraw`（附处理意见，审计） | **P0** | Phase3.9 | 构造 1000 笔正常 + 3 笔改单 → 差异率 0.3% > 0.1% → 商户提现冻结；超管解冻→freeze_withdraw=0 + audit_log 1 行 | 2.5d | 后端+财务 |
| **4.4** | 三合一账单支付与订阅续费：（a）租户后台账单详情 + 微信/支付宝扫码支付（沙箱）；（b）支付回调更新 invoice.status → 欠费状态租户自动从"欠费=只读"切回"启用"；（c）`invoice:generate` 每月 1 号凌晨 crontab 自动跑，生成前一月账单；（d）到期前 7/3/1 天发续费提醒（短信+APP） | P1 | Phase2.5 | 手动 `invoice:generate 2026-09` → 10 月 1 号 crontab 生成 9 月账单→账单未付→租户写请求→20003；扫码支付成功→状态恢复"启用" | 3d | 后端 |
| **4.5** | 超管运维大盘：（a）跨租户数据看板（总租户数、总设备数、总在线率、总告警数今日、孤儿数据巡检结果）；（b）租户一键降级（紧急只读模式，绕过欠费策略立即切全部写操作拦截，记录原因）；（c）租户一键销户（软删除：tenant.status=pre_delete，30 天物理删除倒计时，需超管双审批） | P1 | Phase1.7 + Phase3.10b | 大盘接口返回 6 项数据正确；一键降级→租户所有写 20006；一键销户→pre_delete，30 天 TTL 记录 | 2.5d | 后端 |
| **4.6** | 平台级消息通知中心：（a）短信/APP/邮件/企业微信 4 通道统一抽象 `NotifierService`，失败自动降级；（b）租户级通知策略配置（告警发到谁、用哪个通道、告警严重度阈值）；（c）消息已读/未读状态 + 历史记录查询（带 tenant_id） | P2 | 4.2+4.3 | 同一告警同时发到 Owner 短信+APP；短信失败→企业微信 fallback；记录成功率报表 | 2d | 后端 |
| **4.7** | 操作与合规报告生成：Think Command `php think audit:monthly-report 2026-08` → 导出 CSV 报告（孤儿数据检测结果清单、超管所有跨租户写入操作清单、监控录像跨租户查看清单、紧急恢复通道使用清单），满足每月合规审计要求 | P2 | 4.1+4.2 | 命令执行→导出 4 个 sheet 的 CSV；抽查 3 条超管写入操作与实际 audit_log 对应行 100% 一致 | 1.5d | 后端 |

**Phase 4 合计：17 人天 / P0：8d，P1：8.5d，P2：0.5d**

---

## 7. Phase 5 — 压测/安全审计/灰度上线/商业模型验证

🎯 **阶段目标**：从"可运行"到"生产可用"，做压力、安全、灰度，同时小范围 2~3 个真实市场上线，收集 ARR 与反馈。
⏳ **预估工期**：3~4 周
🧱 **前置依赖**：Phase 0~4 所有 P0 完成，Phase 1/3/4 安全合规 UAT 全部绿。

---

| ID | 任务名称 | 优先级 | 前置依赖 | 验收标准（交付物） | 工时(人天) | 负责人 |
|----|---------|--------|---------|--------------------|-----------|--------|
| **5.1** | 全链路压测：（a）k6 / wrk2 脚本：模拟 1000 租户并发 API 访问 + 10w IoT MQTT 设备 5min 一次上报；（b）目标：API p95 < 300ms，IoT 写入 ≥ 5000 msg/s；（c）慢查询报告 → 优化索引 → Phase 1/2/3 回滚；（d）压测报告文档 `docs/LOAD_TEST_REPORT.md` | **P0** | 所有阶段 P0 | 压测 2 轮后 p95 达标；无 MySQL 级死锁；Redis 命中率 ≥ 98%；报告提交 | 4d | 后端+测试 |
| **5.2** | 安全审计：（a）外聘第三方渗透测试（OWASP Top10）；（b）水平越权专项测试（自动化脚本 20000 条跨租户 API 调用全跑，403 率必须 100%）；（c）静态代码扫描基线（phpstan 升 level 7 + 自定义规则检测 `Db::name()` 绕过 ORM Scope）；（d）隐私合规自查报告：PIPL/ GDPR 对照项 | **P0** | Phase 1 + 4.1 | 渗透报告高危 0，中危 ≤ 2；越权自动化脚本 100% 拦截；静态扫描 0 绕过；合规文档提交 | 5d | 安全+后端 |
| **5.3** | 灰度上线方案：（a）蓝绿/金丝雀发布脚本；（b）按租户灰度开关：`tenant.gray_flag=true` 路由到新版本；（c）回滚 5min 内完成；（d）真实市场 MVP：挑选 2~3 个合作市场（1 个基础版、1 个专业版、1 个旗舰 IoT 完整包）小范围试运行 4 周 | **P0** | 5.1 + 5.2 | 3 个市场 2 周稳定运行无 P0 故障；月活跃商户 ≥ 80%；IoT 设备在线率 ≥ 97% | 4d | 运维+产品 |
| **5.4** | 商业模型验证：（a）搭建数据看板（BI 工具 / 自建 Metabase）：ARR/MRR、租户续费率、IoT 设备续约率、交易佣金、单租户 LTV/CAC；（b）月度业务复盘模板；（c）根据 2~3 个真实市场反馈，形成 Phase 6 Backlog 初稿 | P1 | 5.3 | 上线满 30 天 → 出具《商业模型验证 V1》PDF，至少 5 条关键数据结论；Backlog 初稿 30+ items | 3d | 产品+运营 |
| **5.5** | 高可用架构升级：（a）PHP-FPM + ThinkPHP 部署多实例，Nginx upstream；（b）PG 主从（一主一从，读写分离改造 database.php）；（c）EMQX 集群（3 节点）；（d）SSE 服务独立部署（Workerman/Node Gateway） | P2 | 5.3 | 模拟任一节点宕机，服务不中断；切换时间 < 15s；HA 架构文档提交 | 4d | 运维+后端 |

**Phase 5 合计：20 人天 / P0：13d，P1：3d，P2：4d**

---

## 8. 汇总一览

### 8.1 总工时（人天）

| 阶段 | P0（强阻塞） | P1（中阻塞） | P2（不阻塞） | 阶段总计 |
|------|-------------|-------------|-------------|---------|
| Phase 0 工程化基础 | 5d | 3d | 1d | **9d** |
| Phase 1 SaaS 多租户底座 | 14d | 7.5d | 1d | **22.5d** |
| Phase 2 交易业务核心 | 11d | 9d | 5d | **25d** |
| Phase 3 IoT 物联 5 类设备 | 23.5d | 11.5d | 3.5d | **38.5d** |
| Phase 4 平台治理合规 | 8d | 8.5d | 0.5d | **17d** |
| Phase 5 压测/灰度/上线 | 13d | 3d | 4d | **20d** |
| **合计** | **74.5d** | **42.5d** | **15d** | **132 人天 ≈ 约 6.5 人月** |

> 若后端团队 = 2 人全职，按每周 5d 算：
> **串行工期 ≈ 132 / 2 / 5 ≈ 13.2 周 ≈ 3.3 个月**
> **并行压缩（Phase 2 & 3 前半并行、Phase 4&3 后半并行）后 ≈ 9~11 周 ≈ 2.5~3 个月（进入 Phase 5）**

### 8.2 跨阶段可并行事项（压缩工期的关键）

```
Week 1-2    Phase 0
Week 2-5 ┌─ Phase 1（全 P0 阻塞）
         │
Week 5-8  └────────────── Phase 2（交易 A-E）
Week 5-9  ┌────────────── Phase 3.1 ~ 3.5（IoT 底座） ──────── Phase 3.6~3.10
         │
Week 8-10 └──────────────────────── Phase 4（合规计费）───────┘
Week 10+                        Phase 5（压测+上线）
```

### 8.3 阶段 Gate 清单（进入下一阶段前必须 100% 通过，不允许欠账）

| Gate | 名称 | 必须通过的 P0 验收项 | 不能跳过的签字人 |
|------|------|---------------------|------------------|
| G0→G1 | Phase0 → Phase1 | Docker Compose 一键拉起全环境；迁移+测试工具链可用；CS+Stan CI | 后端 TL |
| **G1→G2（最关键 Gate）** | Phase1 → Phase2/3 | 3 个安全 UAT（跨租户查询拦截、批量删除隔离、超管非白名单拒绝）；1.3/1.4/1.6 自动化集成测试 ≥20 个用例全绿 | 后端 TL + 安全负责人（必须 2 人签字） |
| G2→G3 | Phase2 → Phase4 | 端到端下单+佣金账单生成正确；Redis 压测无雪崩；7 类孤儿巡检脚本 | 后端 TL + 财务 |
| G3→G4 | Phase3 → Phase4 | 5 类设备 MQTT 联调全通过；ACL 跨租户订阅拦截 100%；录像回放权限 4 条用例全绿 | 后端 TL + IoT 负责人 |
| G4→G5 | Phase4 → Phase5 | 合规 3 条红线（视频审计、水电 48h、防逃单冻结）端到端用例；账单支付+续费闭环 | 后端 TL + 合规/运营 |
| G5→Prod | Phase5 → 上线 | 5.1 压测达标；5.2 安全渗透高危 0 + 越权 100% 拦截；5.3 灰度 3 个市场 2 周稳定 P0 故障 0 | 项目发起人 + CTO + 安全官 |

---

## 9. 风险登记册（Top 5 高风险）

| 风险 | 概率 | 影响 | 缓解措施 | 负责人 |
|------|------|------|---------|--------|
| **R1. 多租户隔离被绕过（ORM Scope 失效/开发者裸 Db::name）** | 中 | 致命（SaaS 头号事故） | （a）Phase1.4 + 1.9 双保险；（b）代码规范 3.2 新增 `Db::name` 自定义 PHPStan 规则 PR 必红；（c）每季度做 1 次横向越权自动化审计 | 安全 + 后端 TL |
| **R2. IoT 时序数据写入量超过 PG 单实例上限** | 高 | 严重（设备 10w+ 台后） | （a）Phase3.11 EMQX 规则引擎绕过 PHP 直接批量 INSERT；（b）规划 Phase 6 迁移 TimescaleDB 多节点或单独 ClickHouse；（c）冷热分层严格执行，>7d 归档对象存储 | DBA + IoT |
| **R3. 水电断错电（民生红线事故）** | 低 | 致命（舆情+监管处罚） | （a）Phase4.2 三重通知 + 48h 宽限期 + 紧急恢复通道 3 层保护；（b）断电命令加"白名单测试模式"，首次上线前 2 周所有断电命令只记日志不执行；（c）断电路由需要 Owner + 运营双重签名（Phase6 升级） | 产品+运营+后端 |
| **R4. EMQX 单机故障导致所有设备离线** | 中 | 严重 | （a）Phase5.5 提前规划 3 节点集群；（b）设备端内置"离线缓存+重连指数退避"；（c）关键命令（断电/合闸）本地持久化队列，Broker 恢复后重放 | 运维 + IoT |
| **R5. 收银称被物理破解逃单** | 高 | 高（直接经济损失） | （a）Phase3.9 双向哈希签名 + 4.3 差异率冻结提现；（b）设备固件签名校验，篡改固件立即变砖；（c）Phase6 引入 TEE 可信执行环境，签名私钥不出 SE | 硬件+后端+财务 |

---

**🔗 关联文件**：
- 项目战略/决策/红线/错误码/技术规范：[PROJECT_MEMORY.md](file:///workspace/PROJECT_MEMORY.md)
- 本路线图（任务执行单一真源）：[DEV_ROADMAP.md](file:///workspace/DEV_ROADMAP.md)
- 分层架构示例代码（已完成）：[app/controller/MerchantController.php](file:///workspace/app/controller/MerchantController.php)、[app/service/MerchantService.php](file:///workspace/app/service/MerchantService.php)、[app/repository/BaseRepository.php](file:///workspace/app/repository/BaseRepository.php)
- SaaS 多租户最高红线规范：§十三.8 在 [PROJECT_MEMORY.md#L359-L386](file:///workspace/PROJECT_MEMORY.md#L359-L386)
