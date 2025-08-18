FROM wordpress:6.8-apache

# Instal·lar dependències
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    default-mysql-client \
    imagemagick \
    libmagickwand-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Configurar extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Instal·lar extensions PHP
RUN docker-php-ext-install -j$(nproc) \
    gd \
    zip \
    intl \
    pdo_mysql \
    mysqli \
    opcache \
    exif \
    bcmath

# Instal·lar Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Configuració PHP bàsica
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Copiar tema
COPY . /var/www/html/wp-content/themes/malet-torrent/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]