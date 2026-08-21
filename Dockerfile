# 智慧农贸云（nmyun）PHP-FPM 运行镜像
# 生产部署用，开发环境建议直接用宿主机 `php think run`
# 构建：docker build -t nmyun-php .
# 运行：docker run -d --name nmyun-php -p 9000:9000 -v $(pwd):/var/www/html nmyun-php

FROM php:8.3-fpm-alpine

# 系统依赖 + PG 客户端库
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libxml-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure pgsql --with-pgsql=/usr/local \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pdo \
        pgsql \
        zip \
        gd \
        mbstring \
        bcmath \
        opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ThinkPHP 入口
WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chmod -R 755 runtime public

EXPOSE 9000
CMD ["php-fpm"]
