# Build stage for frontend assets
FROM node:22-alpine AS frontend-builder

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY . .

RUN npm run build

# PHP application stage
FROM php:8.5-fpm-alpine

WORKDIR /app

RUN apk add --no-cache \
    postgresql-dev \
    sqlite \
    sqlite-dev \
    libxml2-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    supervisor \
    nginx \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    pdo_pgsql \
    intl \
    zip \
    xml \
    bcmath \
    mbstring \
    gd

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN addgroup -g 1000 laravel && \
    adduser -D -u 1000 -G laravel laravel

# Run PHP-FPM workers as laravel (matching the app files' ownership below),
# not the base image's default www-data.
RUN sed -i \
    -e 's/^user = .*/user = laravel/' \
    -e 's/^group = .*/group = laravel/' \
    /usr/local/etc/php-fpm.d/www.conf

COPY --chown=laravel:laravel . .

COPY --from=frontend-builder --chown=laravel:laravel /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader

RUN chown -R laravel:laravel /app

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/laravel.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# nginx.conf runs workers as `laravel` (to share file ownership with php-fpm),
# but the nginx package's own dirs are nginx:nginx by default — without this,
# worker processes can't even traverse into /var/lib/nginx/tmp/client_body to
# buffer request bodies to disk, so any POST body too large for nginx's
# in-memory buffer (e.g. a file upload) 500s immediately with an EACCES in
# the nginx error log, before the request ever reaches PHP.
RUN mkdir -p /var/run/nginx && \
    chown -R laravel:laravel /var/run/nginx /var/lib/nginx

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
