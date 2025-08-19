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

# Instal·lar WP-CLI via PHP dins del contenidor
RUN php -r "copy('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar', 'wp-cli.phar');" && \
    php wp-cli.phar --info && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp && \
    wp --info

# Crear directori del tema i copiar tots els fitxers
RUN mkdir -p /var/www/html/wp-content/themes/malet-torrent
COPY *.php /var/www/html/wp-content/themes/malet-torrent/
COPY style.css /var/www/html/wp-content/themes/malet-torrent/
COPY assets/ /var/www/html/wp-content/themes/malet-torrent/assets/
COPY inc/ /var/www/html/wp-content/themes/malet-torrent/inc/
COPY updater/ /var/www/html/wp-content/themes/malet-torrent/updater/

# Crear directori wp-content amb permisos correctes
RUN mkdir -p /var/www/html/wp-content && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/wp-content

# Script per configurar permisos dels volums al iniciar
RUN cat > /usr/local/bin/fix-volume-permissions.sh << 'EOF'
#!/bin/bash
# Assegurar permisos correctes per volum wp-content persistent
echo "Configurant permisos del volum wp-content..."

if [ -d "/var/www/html/wp-content" ]; then
    echo "Configurant permisos per wp-content..."
    chown -R www-data:www-data /var/www/html/wp-content
    chmod -R 775 /var/www/html/wp-content
    echo "Permisos wp-content configurats: 775"
    
    # Permisos especials per uploads (777 per Autoptimize)
    if [ -d "/var/www/html/wp-content/uploads" ]; then
        echo "Configurant permisos especials per uploads..."
        chmod -R 777 /var/www/html/wp-content/uploads
        
        # Crear directori específic per Autoptimize amb permisos correctes
        mkdir -p /var/www/html/wp-content/uploads/ao_ccss
        chown -R www-data:www-data /var/www/html/wp-content/uploads/ao_ccss
        chmod -R 777 /var/www/html/wp-content/uploads/ao_ccss
        
        echo "Permisos uploads i ao_ccss configurats: 777"
    fi
fi

echo "Permisos configurats correctament"
EOF

RUN chmod +x /usr/local/bin/fix-volume-permissions.sh

# Variables d'entorn definides al docker-compose.yml o Dokploy
# Valors per defecte per si no es defineixen externament
ENV WORDPRESS_URL="${WORDPRESS_URL:-https://wp2.malet.testart.cat}"
ENV WORDPRESS_TITLE="${WORDPRESS_TITLE:-Malet Torrent - Pastisseria Artesana}"
ENV WORDPRESS_ADMIN_USER="${WORDPRESS_ADMIN_USER:-admin}"
ENV WORDPRESS_ADMIN_PASSWORD="${WORDPRESS_ADMIN_PASSWORD:-WZd6&F#@d\$oAqSW!A)}"
ENV WORDPRESS_ADMIN_EMAIL="${WORDPRESS_ADMIN_EMAIL:-admin@malet.testart.cat}"
ENV MALET_TORRENT_GITHUB_USER="${MALET_TORRENT_GITHUB_USER:-orioltestart}"
ENV MALET_TORRENT_GITHUB_REPO="${MALET_TORRENT_GITHUB_REPO:-malet-ecommerce-wp-theme}"
ENV MALET_TORRENT_UPDATE_CHECK_INTERVAL="${MALET_TORRENT_UPDATE_CHECK_INTERVAL:-21600}"
ENV MALET_TORRENT_ALLOW_PRERELEASES="${MALET_TORRENT_ALLOW_PRERELEASES:-false}"
ENV WORDPRESS_THEME_NAME="${WORDPRESS_THEME_NAME:-malet-torrent}"

# Definir volum persistent per tot wp-content
VOLUME ["/var/www/html/wp-content"]

# Script per inicialitzar WordPress automàticament
RUN cat > /usr/local/bin/wp-auto-install.sh << 'EOF'
#!/bin/bash
# Script per installar WordPress automàticament amb WP-CLI

echo "=== INICIANDO CONFIGURACIÓN AUTOMÁTICA DE WORDPRESS ==="

# Esperar que WordPress estigui disponible (més fiable que check DB directe)
echo "Esperando que WordPress esté disponible..."
max_attempts=15
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if wp core version --allow-root --path=/var/www/html >/dev/null 2>&1; then
        echo "✓ WordPress está disponible!"
        break
    fi
    echo "Intento $((attempt + 1))/$max_attempts - WordPress no disponible, esperando..."
    sleep 3
    attempt=$((attempt + 1))
done

if [ $attempt -eq $max_attempts ]; then
    echo "❌ Error: WordPress no disponible después de $max_attempts intentos"
    exit 1
fi

# Verificar si WordPress ja està instal·lat
if ! wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "⚙️ WordPress no está instalado. Iniciando instalación automática..."
    
    # Crear wp-config.php si no existeix
    if [ ! -f /var/www/html/wp-config.php ]; then
        echo "📝 Creando wp-config.php..."
        wp config create \
            --dbname="${WORDPRESS_DB_NAME}" \
            --dbuser="${WORDPRESS_DB_USER}" \
            --dbpass="${WORDPRESS_DB_PASSWORD}" \
            --dbhost="${WORDPRESS_DB_HOST}" \
            --allow-root \
            --path=/var/www/html
    fi
    
    # Instalar WordPress
    echo "🚀 Instalando WordPress..."
    wp core install \
        --url="${WORDPRESS_URL}" \
        --title="${WORDPRESS_TITLE}" \
        --admin_user="${WORDPRESS_ADMIN_USER}" \
        --admin_password="${WORDPRESS_ADMIN_PASSWORD}" \
        --admin_email="${WORDPRESS_ADMIN_EMAIL}" \
        --skip-email \
        --allow-root \
        --path=/var/www/html
    
    echo "✅ WordPress instalado correctamente!"
else
    echo "✓ WordPress ya está instalado, saltando instalación..."
fi

# Configurar constants GitHub
echo "🔧 Configurando constantes GitHub..."
wp config set MALET_TORRENT_GITHUB_USER "${MALET_TORRENT_GITHUB_USER}" --allow-root --path=/var/www/html
wp config set MALET_TORRENT_GITHUB_REPO "${MALET_TORRENT_GITHUB_REPO}" --allow-root --path=/var/www/html  
wp config set MALET_TORRENT_UPDATE_CHECK_INTERVAL "${MALET_TORRENT_UPDATE_CHECK_INTERVAL}" --raw --allow-root --path=/var/www/html
wp config set MALET_TORRENT_ALLOW_PRERELEASES "${MALET_TORRENT_ALLOW_PRERELEASES}" --raw --allow-root --path=/var/www/html
echo "✅ Constantes GitHub configuradas!"

# Configurar Redis per al plugin Redis Object Cache
echo "🔧 Configurando Redis para Object Cache..."
wp config set WP_REDIS_HOST "redis" --allow-root --path=/var/www/html
wp config set WP_REDIS_PORT 6379 --raw --allow-root --path=/var/www/html
wp config set WP_REDIS_PASSWORD "${REDIS_PASSWORD}" --allow-root --path=/var/www/html
wp config set WP_REDIS_DATABASE 0 --raw --allow-root --path=/var/www/html
wp config set WP_CACHE true --raw --allow-root --path=/var/www/html

# Instal·lar i activar plugin Redis Object Cache si no existeix
if ! wp plugin is-installed redis-cache --allow-root --path=/var/www/html 2>/dev/null; then
    echo "📦 Instal·lant plugin Redis Object Cache..."
    wp plugin install redis-cache --activate --allow-root --path=/var/www/html
else
    echo "✓ Plugin Redis Object Cache ja està instal·lat"
    wp plugin activate redis-cache --allow-root --path=/var/www/html 2>/dev/null || true
fi

# Activar Redis Object Cache amb retry logic
echo "⚡ Activant Redis Object Cache..."
max_redis_attempts=5
redis_attempt=0
while [ $redis_attempt -lt $max_redis_attempts ]; do
    if wp redis enable --allow-root --path=/var/www/html 2>/dev/null; then
        echo "✅ Redis Object Cache activat correctament!"
        break
    fi
    echo "Intento $((redis_attempt + 1))/$max_redis_attempts - Redis no disponible, esperant..."
    sleep 3
    redis_attempt=$((redis_attempt + 1))
done

if [ $redis_attempt -eq $max_redis_attempts ]; then
    echo "⚠️  Warning: No s'ha pogut activar Redis Object Cache. Es pot activar manualment més tard."
else
    echo "✅ Configuración Redis completada!"
fi

# Activar tema
echo "🎨 Activando tema ${WORDPRESS_THEME_NAME}..."
if wp theme activate "${WORDPRESS_THEME_NAME}" --allow-root --path=/var/www/html; then
    echo "✅ Tema ${WORDPRESS_THEME_NAME} activado correctamente!"
else
    echo "❌ Error activando tema ${WORDPRESS_THEME_NAME}"
fi

echo "🎉 CONFIGURACIÓN WORDPRESS COMPLETADA!"
EOF

RUN chmod +x /usr/local/bin/wp-auto-install.sh

# Script que executa instalació en un procés separat després que Apache estigui funcionant
RUN cat > /usr/local/bin/docker-entrypoint-with-permissions.sh << 'EOF'
#!/bin/bash
set -e

# Executar script de permisos
/usr/local/bin/fix-volume-permissions.sh

# Executar script d'instalació en background després d'un delay més llarg per Redis
(sleep 20 && /usr/local/bin/wp-auto-install.sh) &

# Executar entrypoint original de WordPress
exec docker-entrypoint.sh "$@"
EOF

RUN chmod +x /usr/local/bin/docker-entrypoint-with-permissions.sh

# Usar entrypoint personalitzat
ENTRYPOINT ["/usr/local/bin/docker-entrypoint-with-permissions.sh"]
CMD ["apache2-foreground"]