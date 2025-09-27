FROM php:8.4-fpm-alpine3.20

# Устанавливаем системные пакеты и зависимости для PHP расширений
RUN apk add --no-cache \
    bash \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    autoconf \
    make \
    g++ \
    envsubst \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd zip intl bcmath

# Устанавливаем redis расширение для PHP
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer внутрь контейнера
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Создаем директорию для supervisor
RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor

# Копируем конфигурации
COPY ./supervisor.conf /etc/supervisor/conf.d/supervisor.conf
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh

# Даем права на выполнение
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Задаем entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Запускаем supervisor по умолчанию
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]