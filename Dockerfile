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

# Crear directoris per volums persistents
RUN mkdir -p /var/www/html/wp-content/uploads && \
    mkdir -p /var/www/html/wp-content/plugins && \
    mkdir -p /var/www/html/wp-content/upgrade

# Script per sincronitzar tema actualitzat amb volum persistent
RUN cat > /usr/local/bin/sync-theme.sh << 'EOF'
#!/bin/bash
# Sincronitzar tema des del build al volum persistent
if [ ! -d "/persistent/themes/malet-torrent" ] || [ "$FORCE_THEME_UPDATE" = "true" ]; then
    echo "Sincronitzant tema malet-torrent..."
    mkdir -p /persistent/themes
    cp -rf /var/www/html/wp-content/themes/malet-torrent /persistent/themes/
    echo "Tema sincronitzat!"
fi

# Enllaçar tema des del volum persistent
if [ -d "/persistent/themes/malet-torrent" ]; then
    rm -rf /var/www/html/wp-content/themes/malet-torrent
    ln -sf /persistent/themes/malet-torrent /var/www/html/wp-content/themes/malet-torrent
fi
EOF

chmod +x /usr/local/bin/sync-theme.sh

# Configurar permisos correctes
RUN chown -R www-data:www-data /var/www/html

# Definir volums persistents
VOLUME ["/var/www/html/wp-content/uploads", "/var/www/html/wp-content/plugins", "/persistent"]

EXPOSE 80

# Script d'inicialització que sincronitza tema
RUN cat > /usr/local/bin/docker-entrypoint-persistent.sh << 'EOF'
#!/bin/bash
set -e

# Sincronitzar tema si és necessari
/usr/local/bin/sync-theme.sh

# Executar entrypoint original de WordPress
exec docker-entrypoint.sh "$@"
EOF

chmod +x /usr/local/bin/docker-entrypoint-persistent.sh

# Usar entrypoint personalitzat que gestiona persistència
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-persistent.sh"]
CMD ["apache2-foreground"]