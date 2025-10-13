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

# Instal·lar WP-CLI
RUN php -r "copy('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar', 'wp-cli.phar');" && \
    chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp

# Crear tema personalitzat
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY *.php /var/www/html/wp-content/themes/malet-torrent/
COPY style.css /var/www/html/wp-content/themes/malet-torrent/
COPY assets/ /var/www/html/wp-content/themes/malet-torrent/assets/
COPY inc/ /var/www/html/wp-content/themes/malet-torrent/inc/

# Configurar permisos inicials
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Script per corregir permisos dels volums
RUN cat > /usr/local/bin/fix-volume-permissions.sh << 'EOF'
#!/bin/bash
set -e
echo "Configurant permisos dels volums persistents..."
mkdir -p /var/www/html/wp-content/{plugins,uploads,upgrade,cache}
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content
echo "✅ Permisos configurats correctament"
EOF

RUN chmod +x /usr/local/bin/fix-volume-permissions.sh

# Script d’instal·lació asíncrona de WordPress (simplificat)
RUN cat > /usr/local/bin/wp-auto-install.sh << 'EOF'
#!/bin/bash
set -e
echo "=== Iniciant instal·lació automàtica de WordPress (async) ==="

if ! wp core is-installed --allow-root --path=/var/www/html >/dev/null 2>&1; then
  echo "⚙️ Instal·lant WordPress..."
  wp config create \
    --dbname="${WORDPRESS_DB_NAME}" \
    --dbuser="${WORDPRESS_DB_USER}" \
    --dbpass="${WORDPRESS_DB_PASSWORD}" \
    --dbhost="${WORDPRESS_DB_HOST}" \
    --allow-root --path=/var/www/html

  wp core install \
    --url="${WORDPRESS_URL}" \
    --title="${WORDPRESS_TITLE:-WordPress Site}" \
    --admin_user="${WORDPRESS_ADMIN_USER:-admin}" \
    --admin_password="${WORDPRESS_ADMIN_PASSWORD:-password}" \
    --admin_email="${WORDPRESS_ADMIN_EMAIL:-admin@example.com}" \
    --skip-email --allow-root --path=/var/www/html

  echo "✅ WordPress instal·lat correctament!"
else
  echo "✓ WordPress ja està instal·lat"
fi

# Activar tema
if wp theme is-installed malet-torrent --allow-root --path=/var/www/html; then
  wp theme activate malet-torrent --allow-root --path=/var/www/html || true
fi

echo "🎉 Configuració WordPress completada!"
EOF

RUN chmod +x /usr/local/bin/wp-auto-install.sh

# Entrypoint personalitzat
RUN cat > /usr/local/bin/docker-entrypoint-with-permissions.sh << 'EOF'
#!/bin/bash
set -e

# Fixar permisos inicials
/usr/local/bin/fix-volume-permissions.sh

# Executar instal·lació de WordPress de manera asíncrona (no bloquejant)
(sleep 15 && /usr/local/bin/wp-auto-install.sh) &

# Arrencar Apache
exec docker-entrypoint.sh apache2-foreground
EOF

RUN chmod +x /usr/local/bin/docker-entrypoint-with-permissions.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-with-permissions.sh"]
CMD ["apache2-foreground"]
