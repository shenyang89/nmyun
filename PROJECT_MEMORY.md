# 项目记忆文件 - 智慧农贸云 (nmyun)

## 一、基本信息
- **项目名称**: 智慧农贸云
- **项目代号**: nmyun
- **创建日期**: 2026-08-21
- **项目状态**: 初始化阶段（多市场 SaaS + IoT 已纳入全局规范）
- **项目本质**: **多市场 SaaS 化的分布式生鲜零售操作系统** —— 以 N 个农贸市场（租户）为服务单元，横向 SaaS 订阅 + IoT 智能硬件（水电/监控/灯/空调/收银称）硬件服务费 + 纵向交易佣金/供应链金融，构建「人+货+场+物」四维数据闭环
- **平台模式**: **SaaS 多租户（Multi-Tenant, Share-Nothing at Data Layer）** —— 每个市场（Market/Tenant）逻辑独立，共享一套应用部署实例；未来可按大客户需求升级为物理隔离部署（独立 DB Schema）
- **IoT 定位**: 不是单纯卖硬件，而是「硬件 SaaS 化」—— 设备即服务（Device-as-a-Service），按设备/点位按月收服务费 + 超量阶梯计费（智能水电超额用量、监控云存储天数等）

## 二、形态跃迁路径
```
SaaS工具 → 交易平台 → 供应链基础设施 → 金融与数据引擎 → 行业操作系统
```

## 三、核心目标体系
| 层级 | 目标 | 核心指标 |
|------|------|----------|
| **平台运营层**（SaaS 根管理员） | 多租户生命周期管理（注册/开通/续费/冻结/销户）、全局配置下发、跨市场数据大盘 | 付费租户数 + 租户月续费率（ARR/MRR） |
| **市场/租户层**（市场运营方） | 单市场独立后台：市场商户管理、IoT 设备看板、收费账单、能耗分析、异常告警 | 单市场 IoT 设备在线率 + 市场端月活 |
| 商户层 | 让商户「多卖快卖、管好钱货」 | 日活商户数 |
| B端采购层 | 稳定供应、合规可查 | B端客户数 + 续约率 |
| **IoT 物联层** | 水电/监控/灯/空调/收银称等设备统一接入，实时在线、异常秒级告警、能耗可追溯、视频云端留存 | 设备在线率 ≥ 99%、告警响应时延 ≤ 30s |
| 平台层 | 数据密度 > 交易规模：「人+货+场+物」四维数据联合建模，数据驱动市场运营决策 | 日均 IoT 数据点上报数 + 日均交易笔数 |

## 四、十条核心业务链路
### 4.1 核心交易链路（A-E，原五条）
1. **链路A**：产地 → 市场（上游供应链）
2. **链路B**：市场 → B端采购（B2B大宗采购）
3. **链路C**：市场 → C端消费者（即时配+自提）
4. **链路D**：市场 → 社区（社区团购）
5. **链路E**：交易 → 金融（供应链金融）

### 4.2 IoT 物联链路（F-J，智慧场域）
6. **链路F：智能水电表** —— 多租户分表计量 + 自动抄表计费：
   设备（每档口/每区域水电表）→ MQTT 上报用量 → 按租户/商户分账 → 月度账单生成 → 阈值告警（漏水/漏电）→ 欠费自动断供 / 充值恢复
7. **链路G：智能监控摄像头** —— 场域安防 + 合规留痕：
   摄像头（每市场 N 路）→ RTSP/WebRTC 实时流 → 云存储 NVR（按天数阶梯计费）→ AI 智能分析（占道经营/打架/摔倒/夜间入侵识别）→ 告警推送到市场管理端
8. **链路H：智能灯控 + 智能空调** —— 场域节能 + 环境舒适度：
   智能灯（每摊位/公区）+ 空调（市场/档口）→ 定时/人体感应/温湿度联动开关 → 能耗统计（按租户分摊公区能耗）→ 节能策略自动下发（错峰、夜间休眠）→ 月度用电对比分析
9. **链路I：智能收银称（溯源秤）** —— 称重 + 收银 + 溯源三位一体：
   智能收银称（每商户一台）→ 称重+打码+微信/支付宝/数字人民币聚合支付 → 交易流水实时上报平台 → 打印商品溯源二维码（产地→商户→消费者全链路可查）→ 商户对账报表
10. **链路J：IoT 统一设备运营平台** —— 设备全生命周期管理：
    设备注册（SN 码激活绑定租户）→ 在线状态监控（心跳超时判定离线）→ 固件/配置 OTA 批量升级 → 故障告警自动派单 → 工单流转（市场运维/厂商售后）→ 硬件服务费账单（按设备数/月 + 流量/存储超量）→ 租户续约

## 五、核心业务实体速查
### 5.1 SaaS 多租户体系（全局核心隔离边界）
| 实体 | 说明 | 唯一标识 |
|------|------|----------|
| **租户（Tenant / 市场）** | 平台的付费与使用单位，一个租户对应一个真实的农贸市场；所有业务数据（商户/IoT/交易）必须挂在 tenant_id 下 | tenant_id (UUID) |
| **平台超级管理员（Super Admin）** | 不属于任何租户，拥有跨租户运维权限（开通/冻结租户、全局配置下发、查看跨租户数据大盘）；禁止复用租户内 owner/admin 角色 | super_admin_id |
| **租户成员（Tenant Member）** | 单个市场内部的角色体系：租户 Owner（市场老板/法人）→ 管理员（市场经理）→ 普通运营（收租、客服、设备运维）→ 只读审计 | member_id + tenant_id 联合唯一 |
| **租户订阅套餐（Tenant Plan）** | 每个租户当前生效的 SaaS 订阅套餐：基础版/专业版/旗舰版；决定 IoT 接入设备上限、云存储天数、AI 分析次数等配额 | plan_id |
| **租户账单（Tenant Invoice）** | SaaS 订阅费 + IoT 硬件服务费 + 交易佣金/金融服务费 合并账单，按租户按月生成 | invoice_id + tenant_id |

### 5.2 原交易类核心实体（链路 A-E，挂在 tenant_id 下）
| 实体 | 说明 | 唯一标识 |
|------|------|----------|
| 产地供应商 | 农产品源头供应方（归属于某租户或平台级共享） | supplier_id + tenant_id |
| 批发商户/档口 | 市场内的独立经营摊位（严格归属于某 tenant_id，跨租户不可见） | merchant_id + tenant_id |
| B端采购方 | 餐厅/食堂/生鲜店（归属于某租户或跨租户） | buyer_id + tenant_id |
| C端消费者 | 个人消费者 | user_id |
| 团长 | 社区团购组织者 | group_leader_id + tenant_id |

### 5.3 IoT 物联类核心实体（链路 F-J，挂在 tenant_id 下，设备级隔离）
| 实体 | 说明 | 唯一标识 |
|------|------|----------|
| **IoT 产品（Device Product）** | 平台级设备型号定义（如「NB-IoT 单相智能电表 V2.1」「海康 400W 球机」），包含：设备品类、通信协议（MQTT/CoAP/Modbus）、物模型 TSL（属性/事件/服务）、固件版本列表 | product_id |
| **智能水电表（Water/Electricity Meter）** | 归属某租户下的具体设备实例，按商户/区域绑定，上报：当前读数/瞬时用量/电压/异常告警；支持远程断通 | device_id (SN 码) + tenant_id + (merchant_id 可空，公区表不绑定商户) |
| **智能监控摄像头（Camera）** | 归属某租户下的点位，上报：心跳在线状态、存储占用、AI 事件推送；支持 RTSP/WebRTC 拉流、云端录像回放 | device_id (SN/国标编码) + tenant_id |
| **智能灯控（Smart Light）** | 归属某租户下的灯位（单灯/区域回路），上报：开关状态、功率、在线率；支持远程控制、定时策略、人体感应联动 | device_id + tenant_id + 区域 ID（可空） |
| **智能空调（Smart AC）** | 归属某租户/商户，上报：开关状态、设定温度、当前室温、用电量；支持温控策略、远程开关、错峰用电联动 | device_id + tenant_id + (merchant_id 可空) |
| **智能收银称（Cashier Scale）** | 归属某商户（强绑定 merchant_id），上报：称重流水、交易笔数、打印溯源码次数；与商户交易流水关联对账 | device_id (SN 码) + tenant_id + merchant_id |
| **IoT 设备数据点（Device Telemetry）** | 按设备上报的时序数据（水电读数、温湿度、人流统计等），使用 PostgreSQL TimescaleDB 扩展或单独时序库存储，按 tenant_id + device_id + 小时分区 | data_point_id (自增/时序) |
| **IoT 告警事件（Device Alert）** | 设备异常/阈值触发事件：离线告警、漏水漏电、摄像头遮挡、能耗超标、固件升级失败等；按租户路由推送 | alert_id + tenant_id + device_id |

## 六、关键决策记录
| 日期 | 决策内容 | 决策人 | 备注 |
|------|---------|--------|------|
| 2026-08-21 | 创建项目记忆文件 | - | 项目初始化 |
| 2026-08-21 | 架构原则：分层架构，通过API通信，禁止跨层调用 | - | 核心约束 |
| 2026-08-21 | 盈利节奏：初期靠SaaS订阅+交易佣金；中期靠金融服务；长期靠数据产品 | - | 商业模型 |
| 2026-08-21 | 轻重分离：核心技术+数据层自研，加工/冷链等资产类采用加盟或租赁模式 | - | 运营策略 |
| 2026-08-21 | 金融合规：平台主体与金融业务法律隔离，不碰资金池 | - | 红线约束 |
| 2026-08-21 | 核心护城河：不是技术，是关系——用数字化工具放大市场内的人际关系价值 | - | 战略定位 |
| 2026-08-21 | 确定技术栈：后端 ThinkPHP 8.0 + PostgreSQL 15+ + Redis 7+ | - | 技术选型 |
| 2026-08-21 | **SaaS 多租户隔离：行级 tenant_id + 中间件强制注入，禁止任何 SQL 漏带 tenant_id** | - | 🚨 安全红线，详见下方 §13.8 |
| 2026-08-21 | **超管身份单独标识：is_super_admin=true 上下文，不得伪造租户内 owner/admin 角色** | - | 防止横向越权，跨租户访问接口需走独立白名单或中间件 |
| 2026-08-21 | **租户状态策略分层：7 种状态枚举（启用/禁用/锁定/欠费/待审核/审核不通过/预删除），区分「是否允许登录」和「是否允许写操作」两层策略** | - | 欠费租户可登录只读但禁止创建/更新，避免一刀切引发投诉 |
| 2026-08-21 | **IoT 接入协议：南向（设备→平台）统一 MQTT 3.1.1/5.0，北向（平台→前端）WebSocket/SSE 推送实时数据；Modbus/私有协议走独立网关转 MQTT** | - | 降低协议碎片化，便于统一鉴权与观测 |
| 2026-08-21 | **IoT 鉴权：一机一密（每个设备实例独立 ProductKey + DeviceSecret + SN 绑定 tenant_id），MQTT Connect 时由 EMQX/VerneMQ 调用平台 HTTP Auth API 校验，禁止设备间跨订阅** | - | 设备被破解后只能影响自身，无法横向窥探他租户数据 |
| 2026-08-21 | **IoT 时序数据冷热分层：热数据（近 7 天）PGSQL/TimescaleDB 快速查询；冷数据（>7 天）归档对象存储/ClickHouse，查询走统一查询路由服务** | - | 控制主库体积，水电读数/监控事件写入量大必须分离 |
| 2026-08-21 | **IoT 设备控制命令走下行 MQTT Topic + 消息队列异步解耦，HTTP API 不直接与设备长连接同步；命令状态（已下发/已送达/已执行/失败）可追踪** | - | 网络抖动下命令不丢，可审计 |
| 2026-08-21 | **监控视频合规：所有录像/回看接口必须走租户权限校验；超管跨租户看录像需走单独审计接口并记录操作日志；涉事人脸自动模糊（PG 端裁剪或前端 CSS 滤镜）** | - | 个人信息保护法 / 民法典 隐私权红线 |
| 2026-08-21 | **智能水电欠费策略：告警→预警→断电三步走，必须保留 48h 商户宽限期，断电前短信+APP 双通知，紧急恢复通道 24h 可启用** | - | 民生红线，防止商户因断水电无法经营引发客诉 |
| 2026-08-21 | **收银称交易流水与平台交易流水必须对账，差异率 >0.1% 自动触发告警并冻结商户提现** | - | 财务审计红线，防止收银称本地改单逃单 |

## 七、技术栈
- **后端框架**: ThinkPHP 8.1.4（`topthink/framework ^8.0`）
- **ORM**: Think-ORM 3.x（`topthink/think-orm ^3.0`，官方内置，支持 PgSQL）
- **数据库**: PostgreSQL 15+（PDO pdo_pgsql 驱动），带 **TimescaleDB** 扩展存 IoT 时序数据
- **缓存**: Redis 7+（ThinkPHP Redis 驱动 + `predis/predis ^3.6` 纯 PHP 客户端兜底）
- **CORS 中间件**: `topthink/think-cors ^1.0`（自动注册，无需手动加中间件）
- **文件系统**: `topthink/think-filesystem ^2.0`（内置）—— 监控录像/设备固件放对象存储（OSS/S3/MinIO）
- **消息队列**: **Redis Stream / RabbitMQ**（IoT 告警、设备下行命令、账单生成等异步任务）
- **MQTT Broker（IoT 南向）**: **EMQX 5.x 企业版 / VerneMQ**（一机一密鉴权、ACL、规则引擎写 PG/Redis）
- **MQTT PHP 客户端**: `php-mqtt/client` 或自研轻量封装（发下行命令、订阅设备上下线事件）
- **实时推送（IoT 北向→前端）**: **SSE（Server-Sent Events）** + 降级 WebSocket（ThinkPHP S/Workerman 或独立 Node Gateway）
- **视频流媒体（监控）**: **ZLMediaKit / SRS**（RTSP→WebRTC/FLV/HLS 转码，云端录像存储对象存储）
- **前端框架**: （待补充），建议 管理后台 Vue3 + 市场租户端小程序 + C 端 H5/小程序 三套
- **部署方式**: （待补充），推荐 Docker Compose（PG+Redis+EMQX）+ K8s 规模化

## 八、目录结构
```
/workspace/                           # 项目根目录
├── app/                              # 应用代码（MVC 分层核心）
│   ├── controller/                   # 控制器层
│   │   └── Index.php                 #   示例控制器
│   ├── middleware.php                #   全局中间件注册（CORS 由扩展自动注入）
│   ├── BaseController.php            #   控制器基类
│   ├── ExceptionHandle.php           #   异常处理器
│   ├── Request.php                   #   请求类
│   ├── AppService.php                #   应用服务提供者
│   ├── common.php                    #   全局公共函数
│   ├── event.php                     #   事件监听
│   ├── provider.php                  #   服务容器绑定
│   └── service.php                   #   自定义服务
├── config/                           # 所有配置文件
│   ├── app.php                       #   应用基础配置
│   ├── database.php                  #   数据库（默认 pgsql + nmyun_ 表前缀）
│   ├── cache.php                     #   缓存（默认 redis，file 备选）
│   ├── cors.php                      #   CORS 跨域配置（think-cors 自动发布）
│   ├── cache.php / session.php       #   会话/缓存
│   ├── log.php / trace.php           #   日志/调试
│   └── ...
├── public/                           # Web 根目录（DocumentRoot）
│   ├── index.php                     #   应用入口文件
│   ├── router.php                    #   内置服务器路由
│   └── static/                       #   静态资源
├── route/
│   └── app.php                       # 路由定义
├── runtime/                          # 运行时写入目录（缓存、日志、模板编译）
│   └── log/                          #   日志（按日期分目录）
├── extend/                           # 扩展类库（非 Composer）
├── view/                             # 视图模板
├── vendor/                           # Composer 依赖
├── .env.example                      # ⭐ 环境变量模板（首次部署复制为 .env）
├── .gitignore                        # 已忽略 .env / runtime / vendor
├── composer.json / composer.lock     # 依赖清单与锁定版本
├── think                             # ThinkPHP CLI 入口
├── PROJECT_MEMORY.md                 # 项目记忆文件（本文件）
├── README.md                         # 项目说明
└── LICENSE.txt                       # Apache-2.0 许可证
```

## 九、重要配置
### 9.1 环境变量
- 配置文件位置：`.env`（从 `.env.example` 复制后填写）
- `.env` 已加入 `.gitignore`，**切勿提交到代码库**
- 涉及数据库账号密码、Redis 密码等敏感项，由部署时在 `.env` 中手动填写

### 9.2 数据库关键配置（`config/database.php`）
- 默认驱动：`pgsql`（可通过 `DB_DRIVER` 环境变量切换）
- 默认端口：`5432`
- 默认库名：`nmyun`
- 默认表前缀：`nmyun_`（多租户/多项目隔离用）
- 断线重连：开启
- 字段严格检查：开启（避免 PGSQL 字段名拼写错误静默失败）

### 9.3 缓存关键配置（`config/cache.php`）
- 默认驱动：`redis`（可通过 `CACHE_DRIVER=file` 环境变量临时切文件缓存）
- 默认 DB：`0`（建议业务分库：0通用 1会话 2队列）
- 默认过期：`3600` 秒
- 统一前缀：`nmyun_`

### 9.4 CORS 跨域配置（`config/cors.php`）
- 开发阶段：允许所有 Origin / Header / Method（`*`）
- 预检缓存：86400 秒（减少重复 OPTIONS 请求）
- 暴露响应头：`Authorization`、`X-Trace-Id`、`Content-Disposition`
- ⚠️ 生产注意：若 `supports_credentials=true`，`allowed_origins` 必须写具体域名，不能用 `*`

## 十、开发命令
### Composer 命令
- **安装依赖**：`composer install`
- **新增依赖**：`composer require <vendor/package>`
- **更新依赖**：`composer update`
- **忽略平台要求**（如 pgsql/redis 扩展未装）：加 `--ignore-platform-req=ext-pdo_pgsql` 等参数

### ThinkPHP CLI 命令（`php think`）
- **启动内置开发服务器**：`php think run -H 0.0.0.0 -p 8000`
- **查看版本**：`php think --version`
- **创建控制器**：`php think make:controller Merchant`
- **创建模型**：`php think make:model Merchant`
- **创建中间件**：`php think make:middleware Auth`
- **创建验证类**：`php think make:validate Merchant`
- **创建服务类**：`php think make:service MerchantService`
- **清理运行时缓存**：`php think clear`
- **优化配置/路由/Schema 缓存**：`php think optimize` / `optimize:config` / `optimize:route` / `optimize:schema`
- **查看路由列表**：`php think route:list`
- **服务发现（安装新扩展后执行）**：`php think service:discover`
- **发布扩展配置**：`php think vendor:publish`
- **运行数据库迁移**：（需先安装 migrations 扩展）`php think migrate:run`

## 十一、关键依赖
| Composer 包名 | 版本约束 | 用途 | 状态 |
|--------------|---------|------|------|
| `php` | `>=8.0.0` | PHP 运行时 | ✅ PHP 8.5.10-dev |
| `topthink/framework` | `^8.0` | ThinkPHP 8 核心框架 | ✅ 8.1.4 |
| `topthink/think-orm` | `^3.0` | 官方 ORM（支持 PgSQL） | ✅ 3.0.34 |
| `topthink/think-filesystem` | `^2.0` | 文件系统抽象层 | ✅ 2.0.3 |
| `topthink/think-cors` | `^1.0` | 官方 CORS 跨域中间件 | ✅ 1.0.2（自动注册 Service） |
| `predis/predis` | `^3.6` | 纯 PHP Redis 客户端（无 redis 扩展时兜底） | ✅ 3.6.0 |
| `topthink/think-trace` | `^1.0` | 调试 Trace（dev 环境） | ✅ dev |
| `symfony/var-dumper` | `>=4.2` | `dump()` / `dd()` 调试函数 | ✅ dev |

### ⚠️ 缺失的 PHP 运行时扩展（需安装后才能连 PG/Redis）
| 扩展 | 作用 | 状态 | 建议安装方式 |
|------|------|------|-------------|
| `pdo_pgsql` | ThinkORM 连接 PostgreSQL 必需 | ❌ 当前未加载 | `apt-get install php-pgsql` 或从源码编译 |
| `redis` (phpredis) | ThinkPHP 缓存 `Redis` 驱动调用 `\Redis` 类 | ❌ 当前未加载 | `apt-get install php-redis` 或 `pecl install redis` |
> 说明：在未安装上述扩展时，开发验证可临时用环境变量切换：
> - 缓存切文件：`CACHE_DRIVER=file php think run`
> - `predis/predis` 可在无 `redis` 扩展时操作 Redis（需自行封装驱动调用）

## 十二、TODO / 待办事项
### 12.1 项目基础（已完成 ✅ / 进行中）
- [x] 确定技术栈（ThinkPHP 8.0 + PostgreSQL 15+ + Redis 7+）
- [x] 初始化 ThinkPHP 8.0 项目并配置基础环境
- [x] 安装核心扩展包（think-orm / think-cors / predis）
- [x] 配置 PostgreSQL 默认连接 + nmyun_ 表前缀
- [x] 配置缓存驱动（file 默认，redis 可选；新手阶段用 file 无需起 Redis 服务）
- [x] 配置 CORS 跨域策略（开发阶段开放，生产需收紧）
- [x] 创建 .env.example 环境变量模板
- [x] 烟雾测试：`php think run` 返回 HTTP 200，CORS OPTIONS 返回 204
- [x] 搭建分层架构基础框架（BaseController / BaseService / BaseRepository / BaseModel）
- [x] 编写核心 Trait（ApiResponseTrait、PaginatesTrait）+ 自定义异常体系 + 统一 JSON 错误处理
- [x] 完成 Merchant 商户端完整示例链路（Model→Repository→Service→Controller→Validate→路由）并验证通过
- [x] 🆕 全局核心规范升级：多市场 SaaS 租户模型 + IoT 水电/监控/灯/空调/收银称 纳入规范（本记忆文件 §1-§6、§13.8）
- [ ] ⚠️ 安装 PHP 运行时扩展：`pdo_pgsql` + `redis`（真实连接 PG/Redis 前必须）

### 12.2 SaaS 多租户体系（最高优先级，必须在其他业务代码前落地）
- [ ] 设计 PostgreSQL 迁移脚本：**SaaS 租户体系表**（tenant + tenant_member + tenant_plan + tenant_invoice + super_admin_audit_log），所有表含 `tenant_id` 外键
- [ ] 实现 `TenantContext` 单例类（静态方法 `setId()/getId()/isSuperAdmin()/currentMemberRole()`）
- [ ] 实现 `TenantContextMiddleware` 中间件：**严格按 §13.8.2 三段式职责**（上下文注入 + 7 种状态完整校验 + 拒绝策略分层）
- [ ] 实现 `BaseModel` ORM 全局 Scope：自动注入 `tenant_id` WHERE 条件；INSERT/UPDATE/DELETE 漏带直接抛 500 异常
- [ ] 升级 `BaseRepository` 4 个方法（findById/updateById/deleteById/findOrFail）：**二次校验 tenant_id 一致性**，不一致抛 40301 + 告警
- [ ] 实现平台超管登录与跨租户切换：`X-Target-Tenant-Id` 请求头机制，超管身份独立字段不伪造租户角色
- [ ] 超管操作审计：所有写入接口打日志到 `super_admin_audit_log`，提供独立审计查询接口（仅超管可读）

### 12.3 交易类核心实体（链路 A-E，基于租户隔离继续开发）
- [ ] 创建 PostgreSQL 迁移脚本：**原五大核心实体表（supplier/merchant/buyer/user/group_leader）+ 交易模型**，全部追加 `tenant_id` 字段并建联合索引
- [ ] 设计**十条**业务链路（A-J）的详细流程（泳道图/时序图）
- [ ] API 接口设计（遵循分层架构原则，禁止跨层调用；禁止绕 Repository 裸用 Db::name）
- [ ] 配置 Redis 缓存策略（缓存穿透/击穿/雪崩防护；租户级 key 前缀 `nmyun_cache:{tenant_id}:xx`）
- [ ] 编写详细的项目需求文档
- [ ] 明确 SaaS 订阅 + IoT 硬件服务费 + 交易佣金的**三合一费率模型**（基础版/专业版/旗舰版设备数上限 + 超量单价）

### 12.4 IoT 物联体系（链路 F-J，依赖 §12.2 租户体系先就绪）
- [ ] 安装 EMQX 5.x（docker compose 一键起：emqx + postgres + redis + zlmediakit）
- [ ] 设计 PostgreSQL 迁移脚本：**IoT 体系表**（device_product / device（含水电表/摄像头/灯/空调/收银称统一表用 type 区分）/ device_telemetry（TimescaleDB 超表）/ device_alert / device_ota_firmware / device_work_order），**全部带 tenant_id**
- [ ] 实现 MQTT 一机一鉴权 HTTP API：EMQX auth 钩子调用，校验 ProductKey + DeviceSecret + SN 绑定的 tenant_id，返回 ACL（只允许订阅/发布自己的 Topic，禁止跨租户订阅 `{tenant_id}/#`）
- [ ] 实现 `PhpMqttClient` 封装：发下行命令、订阅设备上下线事件到 Redis Stream；异步消费写 device_online_log
- [ ] 链路 F（智能水电）：抄表入库 → 按商户/区域分账 → 月度账单 → 阈值告警（漏水漏电 31001）→ 断电宽限期策略（48h + 双通知 + 紧急恢复）
- [ ] 链路 G（智能监控）：ZLMediaKit 接入 RTSP → WebRTC/FLV 前端播放 → 云存储录像（按套餐天数自动清理）→ AI 事件（接入独立算法服务 HTTP 回调）→ 录像回放强制权限校验 + 跨租户审计日志
- [ ] 链路 H（智能灯控 + 空调）：定时策略 + 人体感应联动 → 能耗统计（按租户分摊公区）→ 节能策略下发 → 手动/自动冲突降级（32003）
- [ ] 链路 I（智能收银称）：称重流水与交易流水平台端对账（差异率 >0.1% 32502 告警冻结提现）→ 溯源二维码生成 → 商户对账报表
- [ ] 链路 J（IoT 统一运营）：设备 SN 激活绑定租户 → 在线率看板（心跳超时 离线告警）→ OTA 批量升级（固件签名校验 + 灰度）→ 故障自动派单 + 工单流转（33001/33002）
- [ ] 北向实时推送：SSE `/api/device/stream`（鉴权 + tenant_id 过滤事件）→ 前端 EventSource 订阅实时水电读数/告警/设备上下线

### 12.5 平台级治理与合规
- [ ] 孤儿数据巡检脚本：Think Command 每天凌晨核查所有业务表 `LEFT JOIN tenant WHERE t.id IS NULL`，命中发邮件到技术负责人
- [ ] 监控视频合规：前端录像播放器人脸自动模糊；超管跨租户查看录像必须走独立接口并强制留痕
- [ ] 民生红线机制：智能水电断电前 48h/24h/1h 三次短信 + APP 推送；紧急恢复通道 24h 可操作（操作人 + 原因 + 时长全部审计）
- [ ] 收银称防逃单：设备端固件加签，交易流水与平台端双向哈希校验，本地改单 100% 能检出，首次警告再次直接设备锁定

## 十三、备注 & 踩坑记录
### 1. topthink/think-cors 类名易错
中间件类名不是 `think\middleware\Cors`，而是 **`think\cors\HandleCors`**，且由 Service 通过 `HttpRun` 事件**自动注册**，无需在 `app/middleware.php` 中手动声明。手动声明错误类名会导致「类不存在」500 错误。

### 2. 环境变量加载机制
ThinkPHP 8 通过 `vlucas/phpdotenv` 读取 `.env` 文件，**shell 环境变量优先级低于 `.env` 文件**。若未创建 `.env`，配置中 `env('KEY', 'default')` 将回退到 default 值。

### 3. 分层架构约束（核心红线）
项目已严格按「禁止跨层调用」决策落地，调用方向为：

```
controller → service → repository → model
    ↓           ↓            ↓          ↓
参数校验   业务规则/编排   封装CRUD    表定义 + 关联
```

| 层级 | 禁止事项 | 正确做法 |
|------|---------|---------|
| Controller | 禁止直接 `use` Model / Repository，禁止写业务 if-else | 调 `$this->validate()` → 调 Service → 调 Trait 返回 JSON |
| Service | 禁止直接 `use` ThinkORM 类写 SQL | 只通过 `$this->repo()` 或其他 Service 读写数据；业务判断写在 Service |
| Repository | 禁止出现业务判断 | 只做通用查询封装 + CRUD，找不到记录可抛 NotFoundException |
| Model | 禁止出现任何业务逻辑 | 只定义表名/主键/关联/允许写入字段白名单 |

### 4. 统一响应格式 & 错误码规范
所有 API 统一响应（在 `app/traits/ApiResponseTrait.php` + `app/ExceptionHandle.php` 中实现）：
```json
{ "code": 0, "message": "success", "data": {}, "timestamp": 1787280000 }
```
错误码编码规则（**全局按模块分配，不得冲突**）：
| code 范围 | 模块 | 含义 | 示例 |
|-----------|------|------|------|
| `0` | 全局 | 成功 | code=0 |
| `40001` | 全局 | 参数校验失败（ValidateException） | 商户编号不能为空 |
| `40101` | 全局 | 未登录（UnauthorizedException） | 请先登录 |
| `40301` | 全局 | 无权限（UnauthorizedException） | 禁止访问 |
| `40400` | 全局 | 路由层 404（HttpException） | controller not exists |
| `40401` | 全局 | 业务资源不存在（NotFoundException） | 商户不存在 |
| `50000` | 全局 | 未捕获运行时 BUG | APP_DEBUG=true 时附 file:line + trace |
| **`10000~10999`** | 商户模块（Merchant） | 商户/档口相关业务错误 | 10001 商户编号已存在；10002 状态值无效 |
| **`11000~11999`** | 产地供应商（Supplier） | 上游供应链模块错误 | 11001 供应商编码重复 |
| **`12000~12999`** | B端采购（Buyer） | B2B 采购相关错误 | 12001 采购方资质未审核 |
| **`13000~13999`** | C端消费者/订单（Order-C） | C端交易相关错误 | 13001 订单已取消不可重复支付 |
| **`14000~14999`** | 社区团购（Group Leader） | 链路 D 相关错误 | 14001 团长已被其他市场锁定 |
| **`15000~15999`** | 供应链金融（Finance） | 链路 E 相关错误 | 15001 商户授信额度不足 |
| **`20000~24999`** | 🆕 SaaS 租户/市场模块（Tenant） | 多租户生命周期、套餐、账单、成员角色 | 见下表 |
| **`30000~39999`** | 🆕 IoT 物联模块（Device） | 水电/监控/灯/空调/收银称 + 产品/告警/OTA | 见下表 |
| **`50000~59999`** | 预留 | 平台级通用业务（后续扩展） | — |

**SaaS 租户模块（20xxx）细分配额**：
| 子范围 | 子模块 | 示例 |
|--------|--------|------|
| `20000~20499` | 租户生命周期（创建/开通/冻结/销户/续费） | 20001 租户不存在；20002 租户已禁用；20003 租户欠费已锁定（只读）；20004 租户域名/标识已被占用 |
| `20500~20799` | 租户成员与角色 | 20501 成员不在此租户；20502 无权限执行该操作；20503 不能移除唯一 Owner；20504 邀请链接已过期 |
| `20800~20999` | 订阅套餐与账单 | 20801 套餐配额不足（IoT 设备数超上限）；20802 账单逾期；20803 套餐升级差价计算失败 |

**IoT 物联模块（30xxx）细分配额（按链路 F-J 再切分）**：
| 子范围 | 子模块（链路） | 示例 |
|--------|----------------|------|
| `30000~30999` | IoT 通用（产品/注册/鉴权/OTA-J） | 30001 DeviceSecret 不匹配；30002 设备 SN 已绑定其他租户；30003 固件版本已是最新；30004 设备当前离线命令下发失败 |
| `31000~31499` | 智能水电（链路 F） | 31001 水电表读数异常（小于上次抄表值，疑似倒转）；31002 商户欠费但仍在宽限期不可断电；31003 远程合闸失败继电器无响应 |
| `31500~31999` | 智能监控摄像头（链路 G） | 31501 摄像头 RTSP 拉流超时；31502 云存储配额已满无法录像；31503 录像回放需租户 Owner 权限；31504 AI 分析触发频率超出套餐限制 |
| `32000~32499` | 智能灯控 + 空调（链路 H） | 32001 灯控群组中部分设备执行失败；32002 空调温控范围非法（16-30℃外）；32003 节能策略与手动控制冲突已自动降级 |
| `32500~32999` | 智能收银称（链路 I） | 32501 收银称 SN 与绑定商户不一致（疑似移机）；32502 称重流水与交易流水差异超限；32503 溯源码打印纸缺料告警 |
| `33000~33999` | IoT 告警/工单（链路 J 配套） | 33001 告警已被其他运维认领；33002 工单超时未响应已升级；33003 设备故障重复派单（节流） |

### 5. 路由分组写法
ThinkPHP 8 中使用 `Route::group('api', function(){ ... })` 才是「为组内路由加前缀 api」的正确写法；不要用 `Route::group(function(){...})->prefix('api/')`，否则会把 `api/` 当成控制器命名空间的一部分导致找不到控制器。

### 6. 缓存驱动默认值
为方便新手入门，`config/cache.php` 中 `CACHE_DRIVER` 的 env 默认值已改为 **`file`**（这样即使本地没运行 Redis 服务也能直接启动项目）。生产环境或本地起了 Redis 后，只要在 `.env` 中加一行：
```
CACHE_DRIVER=redis
```
即可无缝切换到 Redis。

### 7. PostgreSQL 小提示
- PGSQL 的自增主键用 `SERIAL` 或 `BIGSERIAL`，迁移脚本里注意和 MySQL 的 `AUTO_INCREMENT` 不同
- 字符集直接用 `UTF8`，无需 `utf8mb4`（PG 原生支持 4 字节 Unicode）
- 表前缀 `nmyun_` 已在配置中统一，创建迁移时无需手工加前缀
- 如果运行时报 `could not find driver`：说明 `pdo_pgsql` PHP 扩展未加载；`apt install php-pgsql` 后重启 PHP 即可
- **多租户场景强烈建议启用 TimescaleDB**：`CREATE EXTENSION IF NOT EXISTS timescaledb;`，IoT 时序表（水电读数、设备心跳）用 `SELECT create_hypertable('nmyun_device_telemetry', 'report_time');` 建超表按时间分区，查询性能量级提升

### 8. 🚨 SaaS 多租户隔离与中间件红线规范（对应 §6 决策记录 2026-08-21 租户条款）

本章节是**多市场 SaaS 平台安全的最高优先级约束**，任何代码 PR 违反下列任一条即打回重写，不得合并。

#### 13.8.1 数据隔离模型
- **行级隔离（默认）**：所有业务表（含 merchant/supplier/buyer 及 全部 IoT device/meter/camera/alert）必须有 `tenant_id BIGINT NOT NULL` 字段；所有查询、更新、删除 SQL 必须**自动**带上 `tenant_id = 当前租户` 条件，**不得依赖开发者手写**。
- **Schema 级隔离（大客户可选）**：预留给年付费 ≥ XX 万的旗舰大客户，使用 `nmyun_tenant_{tid}_` 作为独立 Schema，由 `config/database.php` 动态切换连接；此模式代码层与行级完全兼容，不得为物理隔离单独维护一套分支。

#### 13.8.2 `TenantContextMiddleware` 中间件职责边界（三段式）
中间件只做 3 件事，不得在这里写业务逻辑：
1. **租户上下文注入**：从登录态 Token / 请求头 `X-Tenant-Id` / 平台超管指定目标租户三处按优先级解析 `tenant_id` → 写入 `request()->tenantId` 以及 `TenantContext::setId()` 单例，确保后续任何层都能取到。
2. **租户状态完整校验**：枚举「启用/禁用/锁定/欠费/待审核/审核不通过/预删除」7 种状态，**每种状态必须明确映射到错误码 + HTTP 状态 + 允许的操作（读/写/全部拒绝）**，不得用默认兜底分支。如：
   - 启用：全部放行
   - 欠费：读放行，写请求全部拦截返回 `20003 + 提示：您的市场已欠费，请续费后再操作`
   - 禁用/审核不通过/预删除：登录直接拦截
   - 待审核：登录允许，但所有写请求拦截返回 `20005 + 提示：市场正在审核中，暂不可编辑`
3. **ORM 全局 Scope 强制绑定**：在 `AppService::boot()` 或中间件里，通过 ThinkORM 的 `Db::listen` / `Event` 监听 SQL 构造，对所有继承 `BaseModel` 的模型自动加 `tenant_id = X`；**未带 tenant_id 的 INSERT/UPDATE/DELETE 直接抛出致命异常**（哪怕是测试环境也不许绕过）。

#### 13.8.3 超级管理员（Super Admin）越权红线
- **身份单独标识，不得伪造租户角色**：Token/JWT Payload 里用独立字段 `is_super_admin: true` 标识，不要同时塞 `tenant_role=owner/admin`。
- **跨租户访问需显式开关**：默认超管也必须"选入"一个目标租户才能操作（`X-Target-Tenant-Id` 请求头），此时中间件注入的 tenantId 是目标租户，但上下文同时携带 `is_super_admin=true`。
- **业务层授权判定**：Controller/Service 校验权限时，`if (TenantContext::isSuperAdmin()) return true;` 仅允许在**路由级白名单标注的接口**上生效；未标注的接口即使超管调用也必须按租户内成员角色判定，防止"超管误操作改了 A 市场数据"。
- **操作审计日志**：超管所有跨租户写入操作（创建/更新/删除/设备控制）必须写入独立审计表：`super_admin_audit_log`（谁、什么时间、对哪个租户、调了哪个接口、传了什么关键参数、结果），永久留存，不可删除。

#### 13.8.4 禁止绕过的兜底机制
- **Repository 层二次校验**：`BaseRepository` 的 `findById/updateById/deleteById/findOrFail` 执行完 ORM 查询后，额外判断返回模型的 `tenant_id === 当前上下文 tenant_id`，不一致时**立即抛出 40301 并触发告警**（说明 ORM 全局 Scope 失效了，不是正常业务路径）。
- **定时 SQL 巡检脚本**：每日凌晨跑一条 SQL：`SELECT count(*) FROM nmyun_merchant m LEFT JOIN nmyun_tenant t ON m.tenant_id=t.id WHERE t.id IS NULL;` 对所有业务表核查"孤儿数据"，命中即发邮件到平台技术负责人。
- **ThinkPHP 原生 `Db::table()`/`Db::name()` 禁用**：所有对业务表的裸 SQL 必须走 Model + Repository，绕过 ORM Scope 的写法一律视为违规（特殊平台级运维脚本走独立 `console` 命令并带 `--run-as=platform-root` 显式参数）。


