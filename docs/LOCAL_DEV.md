# 本地开发环境启动指南

## 方式一：系统直装（推荐，沙盒/WSL/Mac 本地开发）

适合快速开发调试，PHP 用宿主机 `php think run` 内置服务器。

### 前置条件

| 组件 | 最低版本 | 安装命令（Ubuntu） |
|------|---------|-------------------|
| PHP | 8.0+ (本项目用 8.5-dev) | apt install php-cli php-pgsql php-redis php-mbstring php-xml |
| PostgreSQL | 15+ | apt install postgresql postgresql-contrib |
| Redis | 7+ | apt install redis-server |
| Composer | 2.x | 参考 https://getcomposer.org |

### 5 步启动

```bash
# 1. 克隆项目
git clone <repo-url> && cd nmyun

# 2. 安装 PHP 依赖
composer install

# 3. 复制环境配置
cp .env.example .env
# 编辑 .env 填入 PG/Redis 连接信息

# 4. 创建数据库（首次）
su - postgres -c "psql -c \"CREATE DATABASE nmyun ENCODING 'UTF8';\""
su - postgres -c "psql -c \"CREATE USER nmyun WITH PASSWORD 'nmyun123';\""
su - postgres -c "psql -c \"GRANT ALL PRIVILEGES ON DATABASE nmyun TO nmyun;\""

# 5. 启动开发服务器
php think run
# 访问 http://localhost:8000
```

### 启动基础设施服务（首次/重启后）

```bash
service postgresql start
service redis-server start
```

---

## 方式二：Docker Compose（推荐，纯净环境/团队协作）

适合不污染宿主机、需要完整 IoT 基础设施（EMQX + ZLMediaKit）的场景。

### 前置条件

- Docker 24+ 及 Docker Compose v2+

### 5 步启动

```bash
# 1. 克隆项目
git clone <repo-url> && cd nmyun

# 2. 复制环境配置
cp .env.example .env
# 编辑 .env：
#   DB_HOST=127.0.0.1（Docker 端口映射到宿主机）
#   REDIS_HOST=127.0.0.1

# 3. 启动全部基础设施（postgres + redis + emqx + zlmediakit）
docker compose up -d

# 4. 安装 PHP 依赖
composer install

# 5. 启动 ThinkPHP 开发服务器
php think run
```

### 验证容器健康状态

```bash
docker ps --format "table {{.Names}}\t{{.Status}}"
# 全部显示 "healthy" 即可
```

### 管理后台地址

| 服务 | 地址 | 账号/密码 |
|------|------|----------|
| PostgreSQL | localhost:5432 | nmyun / nmyun123 |
| Redis | localhost:6379 | 无密码 |
| EMQX Dashboard | http://localhost:18083 | admin / nmyun123 |
| ZLMediaKit API | http://localhost:80 | - |

### 停止/清理

```bash
docker compose down          # 停止容器
docker compose down -v       # 停止并删除数据卷（清空数据库）
```

---

## 常见问题

### `could not find driver`
PHP 缺少 `pdo_pgsql` 扩展。安装：`apt install php-pgsql` 后重启 PHP。

### `Connection refused 5432`
PostgreSQL 服务未启动：`service postgresql start`。

### `could not find driver (redis)`
PHP 缺少 `redis` 扩展。可通过 `pecl install redis` 或 `apt install php-redis` 安装。

### ThinkPHP 报 `No application namespace found`
确保 `composer install` 已执行且 `vendor/` 目录存在。
