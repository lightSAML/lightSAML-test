FROM php:7.4-cli-buster

RUN set -e \
    && apt-get update && apt-get install -y \
        zip unzip \
        libzip-dev \
        libicu-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libcurl4-openssl-dev \
        libmcrypt-dev \
        pkg-config \
        libssl-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure opcache \
    && docker-php-ext-install -j$(nproc) zip intl opcache \
    && pecl install xdebug \
    && pecl install mcrypt \
    && docker-php-ext-enable xdebug mcrypt \
    && curl -sS https://get.symfony.com/cli/installer | bash

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY php-my.ini /usr/local/etc/php/conf.d/

ENV PATH="/root/.symfony/bin:${PATH}"
