FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libmagickwand-dev \
    libcurl4-gnutls-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-install zip curl gd pdo_mysql

RUN pecl install imagick && docker-php-ext-enable imagick

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

# Install the GD extension
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd zip
# Install the WebP library
RUN apt-get install -y libwebp-dev && \
    docker-php-ext-configure gd --with-webp=/usr/include/ && \
    docker-php-ext-install gd

CMD ["php-fpm"]
