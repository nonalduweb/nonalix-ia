# syntax=docker/dockerfile:1.7
#
# Nonalix IA — image applicative multi-stage.
# Le runtime final ne contient ni Composer, ni Node, ni outils de compilation.
#
#   docker build --target app  -t nonalix/app:latest .
#   docker build --target dev  -t nonalix/app:dev .

# ---------------------------------------------------------------------------
# 1. Base PHP commune (extensions + configuration)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        bash curl git icu-dev libzip-dev linux-headers oniguruma-dev \
        postgresql-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        supervisor tzdata \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath gd intl opcache pcntl pdo pdo_pgsql pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

ENV TZ=Europe/Paris
WORKDIR /var/www/html

COPY docker/php/php.ini       /usr/local/etc/php/conf.d/99-nonalix.ini
COPY docker/php/opcache.ini   /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/php/www.conf      /usr/local/etc/php-fpm.d/zz-nonalix.conf

# ---------------------------------------------------------------------------
# 2. Dépendances PHP
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress

# ---------------------------------------------------------------------------
# 3. Assets front (Vue 3 + Inertia + Tailwind)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN --mount=type=cache,target=/root/.npm \
    if [ -f package-lock.json ]; then npm ci; else npm install; fi

# Les variables VITE_* sont figées dans le bundle au moment du build : elles
# doivent être fournies ici, pas au démarrage du conteneur. Sans elles, Echo
# se connecterait à `undefined` et le temps réel serait muet en production.
ARG VITE_APP_NAME="Nonalix IA"
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST="app.nonalixia.com"
ARG VITE_REVERB_PORT="443"
ARG VITE_REVERB_SCHEME="https"
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

# Tailwind 4 est chargé par le plugin Vite : il n'y a ni tailwind.config.js
# ni postcss.config.js à copier.
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# 4. Image de production
# ---------------------------------------------------------------------------
FROM base AS app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --chown=www-data:www-data . .
COPY --from=vendor  --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets  --chown=www-data:www-data /app/public/build    ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php artisan nonalix:health --quiet || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# 4 bis. Serveur web de production
# ---------------------------------------------------------------------------
# En production il n'y a pas de bind mount : Nginx doit donc porter lui-même
# public/ (index.php et les assets hashés par Vite). Les passer par un volume
# partagé ne marcherait pas — Docker ne repeuple un volume nommé qu'à sa
# création, et un redéploiement servirait les assets de la version précédente
# avec le manifeste de la nouvelle.
FROM nginx:1.27-alpine AS web

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --from=app /var/www/html/public /var/www/html/public

# ---------------------------------------------------------------------------
# 5. Image de développement (Composer + Xdebug + sources montées en volume)
# ---------------------------------------------------------------------------
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .xdebug-deps \
    && rm -rf /tmp/pear

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
COPY docker/entrypoint.sh  /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENV COMPOSER_MEMORY_LIMIT=-1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
