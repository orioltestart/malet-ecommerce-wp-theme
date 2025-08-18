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

# Script opcional per configurar constants GitHub (no bloquejant)
RUN cat > /usr/local/bin/setup-github-constants.sh << 'EOF'
#!/bin/bash
# Script opcional per configurar constants GitHub
# Execució manual: docker exec -it container_name /usr/local/bin/setup-github-constants.sh

if [ -f /var/www/html/wp-config.php ] && wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "Configurant constants GitHub per actualitzacions automàtiques..."
    wp config set MALET_TORRENT_GITHUB_USER "orioltestart" --allow-root --path=/var/www/html 2>/dev/null || echo "Error configurant GITHUB_USER"
    wp config set MALET_TORRENT_GITHUB_REPO "malet-ecommerce-wp-theme" --allow-root --path=/var/www/html 2>/dev/null || echo "Error configurant GITHUB_REPO"
    wp config set MALET_TORRENT_UPDATE_CHECK_INTERVAL 21600 --raw --allow-root --path=/var/www/html 2>/dev/null || echo "Error configurant UPDATE_CHECK_INTERVAL"
    wp config set MALET_TORRENT_ALLOW_PRERELEASES false --raw --allow-root --path=/var/www/html 2>/dev/null || echo "Error configurant ALLOW_PRERELEASES"
    echo "Constants GitHub configurades correctament!"
else
    echo "WordPress no està instal·lat. Instal·la WordPress primer."
fi
EOF

chmod +x /usr/local/bin/setup-github-constants.sh

# Configurar permisos correctes
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Usar entrypoint estàndard de WordPress (sense modificacions)
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]