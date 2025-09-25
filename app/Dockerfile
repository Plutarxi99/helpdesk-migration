FROM php:8.4-fpm-alpine3.20

# Устанавливаем системные пакеты и зависимости для PHP расширений
RUN apk add \
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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip intl

# Composer внутрь контейнера
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем entrypoint.sh внутрь контейнера
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh

# Даем права на выполнение
RUN chmod +x /usr/local/bin/entrypoint.sh

# Задаем entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Запускаем php-fpm по умолчанию
CMD ["php-fpm"]
