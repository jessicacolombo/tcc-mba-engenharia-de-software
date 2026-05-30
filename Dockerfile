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
    g++

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath sockets

RUN pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

RUN chown -R www-data:www-data /var/www

CMD ["tail", "-f", "/dev/null"]