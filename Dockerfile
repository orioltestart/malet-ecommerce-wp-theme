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

# Crear directori wp-content amb permisos correctes
RUN mkdir -p /var/www/html/wp-content && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/wp-content

# Script per configurar permisos dels volums al iniciar
RUN cat > /usr/local/bin/fix-volume-permissions.sh << 'EOF'
#!/bin/bash
# Assegurar permisos correctes per volums persistents
echo "Configurant permisos dels volums persistents..."

# Crear i configurar directoris necessaris per WordPress
mkdir -p /var/www/html/wp-content/plugins
mkdir -p /var/www/html/wp-content/uploads
mkdir -p /var/www/html/wp-content/upgrade
mkdir -p /var/www/html/wp-content/cache

# Configurar permisos per tots els directoris
echo "Configurant permisos per wp-content..."
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content

# Assegurar permisos específics per volums muntats
if [ -d "/var/www/html/wp-content/plugins" ]; then
    echo "Configurant permisos per plugins..."
    chown -R www-data:www-data /var/www/html/wp-content/plugins
    chmod -R 775 /var/www/html/wp-content/plugins
fi

if [ -d "/var/www/html/wp-content/uploads" ]; then
    echo "Configurant permisos per uploads..."
    chown -R www-data:www-data /var/www/html/wp-content/uploads
    chmod -R 775 /var/www/html/wp-content/uploads
fi

echo "✅ Permisos configurats correctament"
EOF

RUN chmod +x /usr/local/bin/fix-volume-permissions.sh

# Definir volums persistents específics
VOLUME ["/var/www/html/wp-content/plugins", "/var/www/html/wp-content/uploads"]

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

# Configurar WP_ENVIRONMENT_TYPE
echo "🔧 Configurando WP_ENVIRONMENT_TYPE: ${WP_ENVIRONMENT_TYPE}..."
wp config set WP_ENVIRONMENT_TYPE "${WP_ENVIRONMENT_TYPE}" --allow-root --path=/var/www/html

# Habilitar Application Passwords per REST API
echo "🔑 Habilitando Application Passwords para REST API..."
wp config set APPLICATION_PASSWORDS_ENABLED true --raw --allow-root --path=/var/www/html

# Detectar entorn i configurar URLs adequadament
if [[ "${WP_HOME}" == *"localhost"* ]] || [[ "${WORDPRESS_URL}" == *"localhost"* ]] || [[ "${WORDPRESS_URL}" == *"http://"* ]]; then
    echo "🔧 Entorn de desenvolupament detectat - Configurant HTTP..."
    wp config set WP_HOME "${WP_HOME:-${WORDPRESS_URL}}" --allow-root --path=/var/www/html
    wp config set WP_SITEURL "${WP_SITEURL:-${WORDPRESS_URL}}" --allow-root --path=/var/www/html
    wp config set FORCE_SSL_ADMIN false --raw --allow-root --path=/var/www/html
    
    # Eliminar configuracions HTTPS si existeixen
    wp config delete WP_SSLPROXY --allow-root --path=/var/www/html 2>/dev/null || true
    echo "✅ WordPress configurat per HTTP (desenvolupament)"
else
    echo "🔧 Entorn de producció detectat - Configurant HTTPS amb proxy..."
    wp config set WP_HOME "${WP_HOME:-${WORDPRESS_URL}}" --allow-root --path=/var/www/html
    wp config set WP_SITEURL "${WP_SITEURL:-${WORDPRESS_URL}}" --allow-root --path=/var/www/html
    
    # Configurar SSL per proxy invers (Traefik/Dokploy)
    wp config set FORCE_SSL_ADMIN true --raw --allow-root --path=/var/www/html
    wp config set WP_SSLPROXY 1 --raw --allow-root --path=/var/www/html
    echo "✅ WordPress configurat per HTTPS (producció)"
fi

# Configurar Redis per al plugin Redis Object Cache
echo "🔧 Configurando Redis para Object Cache..."
wp config set WP_REDIS_HOST "${REDIS_HOST:-redis}" --allow-root --path=/var/www/html
wp config set WP_REDIS_PORT "${REDIS_PORT:-6379}" --raw --allow-root --path=/var/www/html
wp config set WP_REDIS_PASSWORD "${REDIS_PASSWORD}" --allow-root --path=/var/www/html
wp config set WP_REDIS_DATABASE "${REDIS_DATABASE:-0}" --raw --allow-root --path=/var/www/html
wp config set WP_CACHE true --raw --allow-root --path=/var/www/html

# Configurar Redis URL si es proporciona
if [ ! -z "${REDIS_URL}" ]; then
    echo "🔧 Configurando Redis URL: ${REDIS_URL}"
    wp config set WP_REDIS_URL "${REDIS_URL}" --allow-root --path=/var/www/html
fi

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

# Instal·lar plugins requerits automàticament
echo "📦 Instalando plugins requeridos..."

# Plugins essencials sempre necessaris
required_plugins=(
    # REQUERITS - Crítics per al funcionament
    "woocommerce"
    "contact-form-7"
    "jwt-authentication-for-wp-rest-api"

    # MOLT RECOMANATS - Seguretat
    "wordfence"
    "limit-login-attempts-reloaded"

    # RECOMANATS - Rendiment
    "wp-super-cache"

    # FORMULARIS - Ja instal·lats
    "flamingo"
    "wp-mail-smtp"

    # UTILITATS
    "duplicate-post"
)

for plugin in "${required_plugins[@]}"; do
    if ! wp plugin is-installed "$plugin" --allow-root --path=/var/www/html 2>/dev/null; then
        echo "📦 Instalando $plugin..."
        if wp plugin install "$plugin" --activate --allow-root --path=/var/www/html; then
            echo "✅ Plugin $plugin instalado y activado"
        else
            echo "❌ Error instalando $plugin"
        fi
    else
        echo "✓ Plugin $plugin ya está instalado"
        wp plugin activate "$plugin" --allow-root --path=/var/www/html 2>/dev/null || true
    fi
done

# Crear formularis bàsics si no existeixen
echo "📝 Verificando formularios básicos..."
if [ $(wp post list --post_type=wpcf7_contact_form --format=count --allow-root --path=/var/www/html 2>/dev/null || echo "0") -eq 0 ]; then
    echo "📝 Creando formulario de contacte básico..."

    # Crear formulari de contacte general
    wp post create \
        --post_type=wpcf7_contact_form \
        --post_title="Contacte General" \
        --post_status=publish \
        --allow-root \
        --path=/var/www/html

    # Obtenir ID del formulari creat
    form_id=$(wp post list --post_type=wpcf7_contact_form --format=ids --allow-root --path=/var/www/html | head -1)

    if [ ! -z "$form_id" ]; then
        # Configurar contingut del formulari
        wp post meta update "$form_id" "_form" \
            '<label> Nom (obligatori)
    [text* your-name] </label>

<label> Email (obligatori)
    [email* your-email] </label>

<label> Telèfon
    [tel your-phone] </label>

<label> Assumpte
    [text* your-subject] </label>

<label> Missatge
    [textarea* your-message] </label>

[honeypot honeypot-472]

[submit "Enviar"]' \
            --allow-root --path=/var/www/html

        echo "✅ Formulario básico creado con ID: $form_id"
    fi
else
    echo "✓ Formularios ya existen, saltando creación..."
fi

# Crear usuari API si no existeix
echo "👤 Verificando usuario orioltestart..."
if ! wp user get orioltestart --allow-root --path=/var/www/html >/dev/null 2>&1; then
    echo "👤 Creando usuario orioltestart..."
    wp user create orioltestart oriol@testart.cat \
        --role=administrator \
        --user_pass='Arbucies8' \
        --display_name='Oriol Testart' \
        --first_name='Oriol' \
        --last_name='Testart' \
        --allow-root --path=/var/www/html

    # Crear Application Passwords
    echo "🔑 Creando Application Passwords..."
    wp user application-password create orioltestart "API Access Frontend" --allow-root --path=/var/www/html
    wp user application-password create orioltestart "Formularis REST API" --allow-root --path=/var/www/html

    echo "✅ Usuario orioltestart creado con Application Passwords"
else
    echo "✓ Usuario orioltestart ya existe"
fi

# Configurar WP Mail SMTP per MailHog
echo "📧 Configurando WP Mail SMTP para MailHog..."
if wp plugin is-active wp-mail-smtp --allow-root --path=/var/www/html 2>/dev/null; then
    # Configurar opcions bàsiques de WP Mail SMTP
    wp option update wp_mail_smtp '{"mail":{"from_email":"noreply@malet.local","from_name":"Malet Torrent","mailer":"smtp","return_path":true},"smtp":{"host":"mailhog","encryption":"none","port":1025,"auth":false,"user":"","pass":""},"license":{"key":""},"logs":{"enabled":true},"general":{"summary_report_email_disabled":false}}' --format=json --allow-root --path=/var/www/html

    echo "✅ WP Mail SMTP configurado para MailHog"
else
    echo "⚠️ WP Mail SMTP no está activo, saltando configuración"
fi

# Configurar WooCommerce bàsic si està actiu
echo "🛍️ Configurando WooCommerce básico..."
if wp plugin is-active woocommerce --allow-root --path=/var/www/html 2>/dev/null; then
    # Configuració bàsica de la botiga
    wp option update woocommerce_store_address "Carrer Principal, 123" --allow-root --path=/var/www/html
    wp option update woocommerce_store_city "Arbúcies" --allow-root --path=/var/www/html
    wp option update woocommerce_store_postcode "17401" --allow-root --path=/var/www/html
    wp option update woocommerce_default_country "ES:GI" --allow-root --path=/var/www/html
    wp option update woocommerce_currency "EUR" --allow-root --path=/var/www/html
    wp option update woocommerce_enable_guest_checkout "yes" --allow-root --path=/var/www/html

    # Saltar wizard de configuració
    wp option update woocommerce_onboarding_profile '{"completed":true}' --format=json --allow-root --path=/var/www/html
    wp option update woocommerce_task_list_complete "yes" --allow-root --path=/var/www/html

    echo "✅ WooCommerce configurado básicamente"
else
    echo "⚠️ WooCommerce no está activo, saltando configuración"
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