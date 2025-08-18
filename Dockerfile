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
COPY updater/ /var/www/html/wp-content/themes/malet-torrent/updater/

# Script d'inicialització automàtica amb WP-CLI
RUN cat > /usr/local/bin/init-wordpress.sh << 'EOF'
#!/bin/bash
set -e

echo "🚀 Inicialitzant WordPress amb WP-CLI..."

# Esperar que Apache estigui en marxa
while ! curl -f http://localhost >/dev/null 2>&1; do
    echo "⏳ Esperant Apache..."
    sleep 2
done

# Esperar que la base de dades estigui disponible
while ! wp db check --allow-root --path=/var/www/html 2>/dev/null; do
    echo "⏳ Esperant connexió a la base de dades..."
    sleep 3
done

echo "✅ Connexions establertes"

# Verificar si WordPress ja està instal·lat
if wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "ℹ️ WordPress ja està instal·lat"
    
    # Verificar si el tema està actiu
    ACTIVE_THEME=$(wp theme status --allow-root --path=/var/www/html | grep "Active Theme" | awk '{print $3}')
    if [ "$ACTIVE_THEME" != "malet-torrent" ]; then
        echo "🎨 Activant tema malet-torrent..."
        wp theme activate malet-torrent --allow-root --path=/var/www/html
        echo "✅ Tema activat"
    else
        echo "ℹ️ Tema malet-torrent ja està actiu"
    fi
    
    # Assegurar constants GitHub sempre actualitzades
    echo "🔧 Verificant constants GitHub per actualitzacions..."
    wp config set MALET_TORRENT_GITHUB_USER "orioltestart" --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_GITHUB_REPO "malet-ecommerce-wp-theme" --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_UPDATE_CHECK_INTERVAL 21600 --raw --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_ALLOW_PRERELEASES false --raw --allow-root --path=/var/www/html
else
    echo "📦 Instal·lant WordPress..."
    
    # Crear wp-config.php si no existeix
    if [ ! -f /var/www/html/wp-config.php ]; then
        wp config create \
            --dbname="$WORDPRESS_DB_NAME" \
            --dbuser="$WORDPRESS_DB_USER" \
            --dbpass="$WORDPRESS_DB_PASSWORD" \
            --dbhost="$WORDPRESS_DB_HOST" \
            --allow-root \
            --path=/var/www/html
    fi
    
    # Instal·lar WordPress
    wp core install \
        --url="https://wp2.malet.testart.cat" \
        --title="Malet Torrent - Pastisseria Tradicional" \
        --admin_user="admin" \
        --admin_password="MaletAdmin2024!" \
        --admin_email="admin@malet.testart.cat" \
        --skip-email \
        --allow-root \
        --path=/var/www/html

    echo "✅ WordPress instal·lat"
    
    # Configurar idioma a català
    wp language core install ca --allow-root --path=/var/www/html
    wp site switch-language ca --allow-root --path=/var/www/html
    
    # Activar tema
    echo "🎨 Activant tema malet-torrent..."
    wp theme activate malet-torrent --allow-root --path=/var/www/html
    
    # Configurar permalinks
    wp rewrite structure '/%postname%/' --allow-root --path=/var/www/html
    
    # Configurar opcions bàsiques
    wp option update blogdescription "Pastisseria tradicional catalana amb melindros artesans" --allow-root --path=/var/www/html
    wp option update start_of_week 1 --allow-root --path=/var/www/html
    wp option update timezone_string "Europe/Madrid" --allow-root --path=/var/www/html
    
    # Configurar constants GitHub per actualitzacions automàtiques del tema
    echo "🔧 Configurant constants GitHub per actualitzacions automàtiques..."
    wp config set MALET_TORRENT_GITHUB_USER "orioltestart" --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_GITHUB_REPO "malet-ecommerce-wp-theme" --allow-root --path=/var/www/html
    wp config set MALET_TORRENT_UPDATE_CHECK_INTERVAL 21600 --raw --allow-root --path=/var/www/html  # 6 hores
    wp config set MALET_TORRENT_ALLOW_PRERELEASES false --raw --allow-root --path=/var/www/html
    
    echo "🎉 Configuració WordPress completada!"
fi

echo "📋 Informació del lloc:"
echo "   URL: https://wp2.malet.testart.cat/"
echo "   Admin: https://wp2.malet.testart.cat/wp-admin/"
echo "   Usuari: admin"
echo "   Password: MaletAdmin2024!"
echo "   Tema: malet-torrent"
EOF

chmod +x /usr/local/bin/init-wordpress.sh

# Script d'entrypoint que combina WordPress i la nostra inicialització
RUN cat > /usr/local/bin/docker-entrypoint-custom.sh << 'EOF'
#!/bin/bash
set -e

# Executar l'entrypoint original de WordPress en background
docker-entrypoint.sh apache2-foreground &
APACHE_PID=$!

# Esperar un moment perquè Apache s'iniciï
sleep 5

# Executar la nostra inicialització en background
/usr/local/bin/init-wordpress.sh &

# Esperar que Apache continuï funcionant
wait $APACHE_PID
EOF

chmod +x /usr/local/bin/docker-entrypoint-custom.sh

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-custom.sh"]