FROM wordpress:6.8-php8.2-apache AS wordpress

RUN curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

RUN { \
        echo 'display_errors = On'; \
        echo 'display_startup_errors = On'; \
        echo 'error_reporting = E_ALL'; \
    } > /usr/local/etc/php/conf.d/zz-dev.ini

FROM php:8.2-cli AS ci

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip rsync curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
