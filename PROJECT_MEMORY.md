# 项目记忆文件 - 智慧农贸云 (nmyun)

## 一、基本信息
- **项目名称**: 智慧农贸云
- **项目代号**: nmyun
- **创建日期**: 2026-08-21
- **项目状态**: 初始化阶段
- **项目本质**: 分布式生鲜零售操作系统 —— 以农贸市场为核心，激活存量、数字赋能、多线变现

## 二、形态跃迁路径
```
SaaS工具 → 交易平台 → 供应链基础设施 → 金融与数据引擎 → 行业操作系统
```

## 三、核心目标体系
| 层级 | 目标 | 核心指标 |
|------|------|----------|
| 商户层 | 让商户「多卖快卖、管好钱货」 | 日活商户数 |
| B端采购层 | 稳定供应、合规可查 | B端客户数 + 续约率 |
| 平台层 | 数据密度 > 交易规模 | 日均交易笔数 |

## 四、五条核心业务链路
1. **链路A**：产地 → 市场（上游供应链）
2. **链路B**：市场 → B端采购（B2B大宗采购）
3. **链路C**：市场 → C端消费者（即时配+自提）
4. **链路D**：市场 → 社区（社区团购）
5. **链路E**：交易 → 金融（供应链金融）

## 五、核心业务实体速查
| 实体 | 说明 | 唯一标识 |
|------|------|----------|
| 产地供应商 | 农产品源头供应方 | supplier_id |
| 批发商户/档口 | 市场内的独立经营摊位 | merchant_id |
| B端采购方 | 餐厅/食堂/生鲜店 | buyer_id |
| C端消费者 | 个人消费者 | user_id |
| 团长 | 社区团购组织者 | group_leader_id |

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

## 七、技术栈
- **后端框架**: ThinkPHP 8.1.4（`topthink/framework ^8.0`）
- **ORM**: Think-ORM 3.x（`topthink/think-orm ^3.0`，官方内置，支持 PgSQL）
- **数据库**: PostgreSQL 15+（PDO pdo_pgsql 驱动）
- **缓存**: Redis 7+（ThinkPHP Redis 驱动 + `predis/predis ^3.6` 纯 PHP 客户端兜底）
- **CORS 中间件**: `topthink/think-cors ^1.0`（自动注册，无需手动加中间件）
- **文件系统**: `topthink/think-filesystem ^2.0`（内置）
- **前端框架**: （待补充）
- **消息队列**: （待补充）
- **部署方式**: （待补充）

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
- [x] 确定技术栈（ThinkPHP 8.0 + PostgreSQL 15+ + Redis 7+）
- [x] 初始化 ThinkPHP 8.0 项目并配置基础环境
- [x] 安装核心扩展包（think-orm / think-cors / predis）
- [x] 配置 PostgreSQL 默认连接 + nmyun_ 表前缀
- [x] 配置 Redis 默认缓存驱动 + file 备选
- [x] 配置 CORS 跨域策略（开发阶段开放，生产需收紧）
- [x] 创建 .env.example 环境变量模板
- [x] 烟雾测试：`php think run` 返回 HTTP 200，CORS OPTIONS 返回 204
- [ ] ⚠️ 安装 PHP 运行时扩展：`pdo_pgsql` + `redis`（连接 PG/Redis 前必须）
- [ ] 搭建分层架构基础框架（controller / service / repository / model 四层）
- [ ] 设计五条业务链路的详细流程
- [ ] 数据库设计（五大核心实体 + 交易模型）
- [ ] 设计 PostgreSQL 表结构并创建迁移脚本
- [ ] API 接口设计（遵循分层架构原则，禁止跨层调用）
- [ ] 配置 Redis 缓存策略（缓存穿透/击穿/雪崩防护）
- [ ] 编写详细的项目需求文档
- [ ] 明确 SaaS 订阅与交易佣金的费率模型

## 十三、备注 & 踩坑记录
### 1. topthink/think-cors 类名易错
中间件类名不是 `think\middleware\Cors`，而是 **`think\cors\HandleCors`**，且由 Service 通过 `HttpRun` 事件**自动注册**，无需在 `app/middleware.php` 中手动声明。手动声明错误类名会导致「类不存在」500 错误。

### 2. 环境变量加载机制
ThinkPHP 8 通过 `vlucas/phpdotenv` 读取 `.env` 文件，**shell 环境变量优先级低于 `.env` 文件**。若未创建 `.env`，配置中 `env('KEY', 'default')` 将回退到 default 值。

### 3. 分层架构约束
关键决策明确：**禁止跨层调用**。建议目录分层：
```
app/
  controller/   → 仅接收请求 + 参数校验 + 返回响应
  service/      → 业务逻辑编排
  repository/   → 数据访问（封装 ThinkORM 查询）
  model/        → 纯数据模型 + 关联关系定义
```
每层只能调用下一层（Controller → Service → Repository → Model），禁止反向或跳层。

### 4. PostgreSQL 小提示
- PGSQL 的自增主键用 `SERIAL` 或 `BIGSERIAL`，迁移脚本里注意和 MySQL 的 `AUTO_INCREMENT` 不同
- 字符集直接用 `UTF8`，无需 `utf8mb4`（PG 原生支持 4 字节 Unicode）
- 表前缀 `nmyun_` 已在配置中统一，创建迁移时无需手工加前缀

