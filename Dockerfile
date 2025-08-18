FROM wordpress:latest

# Instal·lar dependències per WP-CLI
RUN apt-get update && apt-get install -y \
    curl \
    less \
    default-mysql-client \
    sudo \
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

# Crear directori del tema
RUN mkdir -p /usr/src/themes/malet-torrent

# Copiar fitxers del tema
COPY *.php /usr/src/themes/malet-torrent/
COPY style.css /usr/src/themes/malet-torrent/
COPY assets/ /usr/src/themes/malet-torrent/assets/
COPY inc/ /usr/src/themes/malet-torrent/inc/

# Script de configuració automàtica amb WP-CLI
RUN cat > /usr/local/bin/wp-auto-install.sh << 'EOF'
#!/bin/bash
set -euo pipefail

echo "🚀 Iniciant configuració automàtica de WordPress..."

# Esperar que Apache s'iniciï
sleep 5

# Esperar que WordPress estigui disponible
until curl -f http://localhost >/dev/null 2>&1; do
    echo "⏳ Esperant que WordPress estigui disponible..."
    sleep 2
done

# Verificar si WordPress ja està instal·lat
if ! wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "📦 Instal·lant WordPress..."
    
    # Descarregar WordPress core si no existeix
    if [ ! -f /var/www/html/wp-config.php ]; then
        wp core download --allow-root --path=/var/www/html --force
    fi
    
    # Crear wp-config.php
    wp config create \
        --dbname="$WORDPRESS_DB_NAME" \
        --dbuser="$WORDPRESS_DB_USER" \
        --dbpass="$WORDPRESS_DB_PASSWORD" \
        --dbhost="$WORDPRESS_DB_HOST" \
        --allow-root \
        --path=/var/www/html
    
    # Instal·lar WordPress
    wp core install \
        --url="https://wp2.malet.testart.cat" \
        --title="Malet Torrent - Pastisseria Tradicional" \
        --admin_user="admin" \
        --admin_password="MaletAdmin2024!" \
        --admin_email="admin@malet.testart.cat" \
        --allow-root \
        --path=/var/www/html
    
    echo "✅ WordPress instal·lat correctament!"
else
    echo "ℹ️ WordPress ja està instal·lat"
fi

# Copiar i activar tema
echo "🎨 Configurant tema malet-torrent..."
cp -r /usr/src/themes/malet-torrent /var/www/html/wp-content/themes/
chown -R www-data:www-data /var/www/html/wp-content/themes/malet-torrent

# Activar tema
wp theme activate malet-torrent --allow-root --path=/var/www/html

# Configurar permalinks
wp rewrite structure '/%postname%/' --allow-root --path=/var/www/html

# Configurar opcions bàsiques
wp option update blogdescription "Pastisseria tradicional catalana amb melindros artesans" --allow-root --path=/var/www/html
wp option update start_of_week 1 --allow-root --path=/var/www/html
wp option update timezone_string "Europe/Madrid" --allow-root --path=/var/www/html

echo "🎉 Configuració completada!"
echo "📋 Credencials d'accés:"
echo "   URL: https://wp2.malet.testart.cat/wp-admin/"
echo "   Usuari: admin"
echo "   Password: MaletAdmin2024!"

EOF

chmod +x /usr/local/bin/wp-auto-install.sh

# Script d'entrypoint personalitzat
RUN cat > /usr/local/bin/custom-entrypoint.sh << 'EOF'
#!/bin/bash
set -euo pipefail

# Copiar tema
echo "📂 Preparant fitxers del tema..."
cp -r /usr/src/themes/malet-torrent /var/www/html/wp-content/themes/ 2>/dev/null || true
chown -R www-data:www-data /var/www/html/wp-content/themes/malet-torrent 2>/dev/null || true

# Executar configuració automàtica en background
/usr/local/bin/wp-auto-install.sh &

# Executar entrypoint original de WordPress
exec docker-entrypoint.sh "$@"
EOF

chmod +x /usr/local/bin/custom-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["apache2-foreground"]