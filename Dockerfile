FROM php:8.4-fpm

ARG WWWGROUP=1000
ARG WWWUSER=1000

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    librabbitmq-dev \
    && docker-php-ext-install pdo_pgsql sockets bcmath pcntl opcache \
    && pecl install redis amqp \
    && docker-php-ext-enable redis amqp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --force -g $WWWGROUP sail \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u $WWWUSER sail

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
