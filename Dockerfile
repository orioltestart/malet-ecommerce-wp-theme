FROM wordpress:latest

# Instal·lar dependències bàsiques i WP-CLI
RUN apt-get update && apt-get install -y \
    curl \
    default-mysql-client \
    less \
    nano \
    && rm -rf /var/lib/apt/lists/*

# Configuració PHP optimitzada
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

# Habilitar mod_rewrite per permalinks
RUN a2enmod rewrite

# Instal·lar WP-CLI (versió estable)
RUN curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.12.0/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Crear directori del tema i copiar tots els fitxers
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY *.php /var/www/html/wp-content/themes/malet-torrent/
COPY style.css /var/www/html/wp-content/themes/malet-torrent/
COPY assets/ /var/www/html/wp-content/themes/malet-torrent/assets/
COPY inc/ /var/www/html/wp-content/themes/malet-torrent/inc/
COPY updater/ /var/www/html/wp-content/themes/malet-torrent/updater/

# Crear directoris per volums persistents amb permisos correctes
RUN mkdir -p /var/www/html/wp-content/uploads /var/www/html/wp-content/plugins && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/wp-content

# Script per configurar permisos dels volums al iniciar
RUN cat > /usr/local/bin/fix-volume-permissions.sh << 'EOF'
#!/bin/bash
# Assegurar permisos correctes per volums persistents amb permisos més permissius
echo "Configurant permisos dels volums persistents..."

if [ -d "/var/www/html/wp-content/uploads" ]; then
    echo "Configurant permisos per uploads..."
    chown -R www-data:www-data /var/www/html/wp-content/uploads
    chmod -R 777 /var/www/html/wp-content/uploads
    echo "Permisos uploads configurats: 777"
fi

if [ -d "/var/www/html/wp-content/plugins" ]; then
    echo "Configurant permisos per plugins..."
    chown -R www-data:www-data /var/www/html/wp-content/plugins
    chmod -R 775 /var/www/html/wp-content/plugins
    echo "Permisos plugins configurats: 775"
fi

echo "Permisos configurats correctament"
EOF

RUN chmod +x /usr/local/bin/fix-volume-permissions.sh

# Definir volums persistents per plugins i uploads
VOLUME ["/var/www/html/wp-content/uploads", "/var/www/html/wp-content/plugins"]

# Executar script de permisos i després docker-entrypoint.sh original
RUN cat > /usr/local/bin/docker-entrypoint-with-permissions.sh << 'EOF'
#!/bin/bash
set -e

# Executar script de permisos
/usr/local/bin/fix-volume-permissions.sh

# Executar entrypoint original de WordPress
exec docker-entrypoint.sh "$@"
EOF

RUN chmod +x /usr/local/bin/docker-entrypoint-with-permissions.sh

# Usar entrypoint personalitzat
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-with-permissions.sh"]
CMD ["apache2-foreground"]