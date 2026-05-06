# 👷‍♀️ 第一阶段：构建阶段，包含所有开发依赖
FROM php:8.2-fpm-alpine AS builder

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libxml2-dev \
    libwebp-dev \
    gmp-dev \
    oniguruma-dev \
    postgresql-dev \
    linux-headers

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

RUN docker-php-ext-install -j$(nproc) \
    bcmath \
    pdo_mysql \
    pdo_pgsql \
    mysqli \
    gd \
    pcntl \
    sockets \
    gmp \
    zip \
    intl \
    opcache

# 检查 pecl.php.net 可用性
RUN curl -Is https://pecl.php.net | head -n 1 | grep "200" \
    || (echo "❌ pecl.php.net unreachable, aborting build." && exit 1)


# 安装 redis 扩展
RUN pecl channel-update pecl.php.net \
    && pecl install redis \
    && docker-php-ext-enable redis

# 👨‍🍳 第二阶段：运行阶段，仅包含必要运行环境
FROM php:8.2-fpm-alpine

# 复制已构建的扩展
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# 安装运行时所需依赖
RUN apk add --no-cache \
    wget \
    rsync \
    libzip \
    libpng \
    libjpeg-turbo \
    libwebp \
    freetype \
    icu \
    libxml2 \
    gmp \
    oniguruma \
    git \
    mysql-client \
    mariadb-connector-c \
    libpq

# 配置 www.conf
RUN sed -i \
    -e 's/;catch_workers_output = .*/catch_workers_output = yes/g' \
    -e 's/;php_admin_flag\[log_errors\] = .*/php_admin_flag\[log_errors\] = on/g' \
    -e 's/;php_admin_value\[error_log\] = .*/php_admin_value\[error_log\] = \/dev\/stderr/g' \
    /usr/local/etc/php-fpm.d/www.conf

# 配置 PHP 错误日志输出到 stderr
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/error-logging.ini
# 上传配置
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/upload.ini
RUN echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/upload.ini

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV LOG_CHANNEL=stderr
ENV RUNNING_IN_DOCKER=1

COPY .docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
