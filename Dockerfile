FROM wordpress:latest

# Instal·lar dependències bàsiques
RUN apt-get update && apt-get install -y \
    curl \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Configuració PHP bàsica
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/custom.ini

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Instal·lar WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.12.0/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Crear directori del tema i copiar fitxers
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY *.php /var/www/html/wp-content/themes/malet-torrent/
COPY style.css /var/www/html/wp-content/themes/malet-torrent/
COPY assets/ /var/www/html/wp-content/themes/malet-torrent/assets/
COPY inc/ /var/www/html/wp-content/themes/malet-torrent/inc/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80