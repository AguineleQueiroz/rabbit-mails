FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    librabbitmq-dev \
    libssl-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql sockets pcntl \
    && pecl install amqp redis \
    && docker-php-ext-enable amqp redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

COPY . .
RUN composer install --no-interaction

# php-fpm must listen on all interfaces so nginx (separate container) can reach it
RUN echo '[www]\nlisten = 0.0.0.0:9000' >> /usr/local/etc/php-fpm.d/zz-docker.conf

CMD ["php-fpm"]
