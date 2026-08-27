FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    autoconf \
    make \
    g++ \
    supervisor

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath sockets

RUN pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . /var/www

RUN mkdir -p storage/logs && \
    touch storage/logs/worker.log && \
    chown -R www-data:www-data /var/www/storage && \
    chmod -R 775 /var/www/storage

COPY laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

CMD ["/bin/sh", "-lc", "rm -f /var/run/supervisor.sock /var/run/supervisord.pid && exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf"]
