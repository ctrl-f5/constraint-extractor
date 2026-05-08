ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-cli-alpine AS php

ARG XDEBUG=0

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git zip openssh-client && rm -rf /var/cache/apk/*

RUN if [ "${XDEBUG}" = "1" ]; then \
        apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
        && pecl install xdebug \
        && docker-php-ext-enable xdebug \
        && apk del .build-deps; \
    fi
