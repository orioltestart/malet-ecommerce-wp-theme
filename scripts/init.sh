#!/bin/bash
set -euo pipefail

# ===== CONFIGURACIÓ I VARIABLES D'ENTORN =====

# Verificar que existeix el fitxer .env
if [ ! -f ".env" ]; then
    echo "❌ Error: Fitxer .env no trobat!"
    echo "Copia .env.example a .env i configura les variables"
    exit 1
fi

# Carregar variables d'entorn
source .env

# Variables d'entorn necessàries (sense valors per defecte)
WORDPRESS_URL="${WORDPRESS_URL}"
WORDPRESS_TITLE="${WORDPRESS_TITLE}"
WORDPRESS_ADMIN_USER="${WORDPRESS_ADMIN_USER}"
WORDPRESS_ADMIN_EMAIL="${WORDPRESS_ADMIN_EMAIL}"
WORDPRESS_THEME_NAME="${WORDPRESS_THEME_NAME}"

# Variables GitHub
MALET_TORRENT_GITHUB_USER="${MALET_TORRENT_GITHUB_USER}"
MALET_TORRENT_GITHUB_REPO="${MALET_TORRENT_GITHUB_REPO}"

echo "🚀 Inicialitzant WordPress amb tema $WORDPRESS_THEME_NAME..."

# ===== COMPROVACIONS INICIALS =====
echo "🔍 Verificant configuració..."

# Comprovar variables obligatòries
REQUIRED_VARS=(
    "WORDPRESS_URL"
    "WORDPRESS_TITLE"
    "WORDPRESS_ADMIN_USER"
    "WORDPRESS_ADMIN_EMAIL"
    "WORDPRESS_THEME_NAME"
    "MALET_TORRENT_GITHUB_USER"
    "MALET_TORRENT_GITHUB_REPO"
)

missing_vars=()
for var in "${REQUIRED_VARS[@]}"; do
    var_value="${!var:-}"
    if [ -z "$var_value" ]; then
        missing_vars+=("$var")
    fi
done

if [ ${#missing_vars[@]} -gt 0 ]; then
    echo "❌ Error: Variables obligatòries no definides al fitxer .env:"
    for var in "${missing_vars[@]}"; do
        echo "   - $var"
    done
    echo ""
    echo "💡 Assegura't que el fitxer .env conté totes les variables necessàries"
    exit 1
fi

# Verificar que WordPress està disponible
echo "⏳ Esperant que WordPress estigui disponible..."
max_attempts=30
attempt=0

until wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; do
    if [ $attempt -ge $max_attempts ]; then
        echo "❌ Timeout esperant WordPress"
        exit 1
    fi
    sleep 2
    ((attempt++))
done

echo "✅ WordPress està disponible"

# Verificar que el tema existeix
if ! wp theme is-installed "$WORDPRESS_THEME_NAME" --allow-root --path=/var/www/html; then
    echo "❌ Error: Tema '$WORDPRESS_THEME_NAME' no trobat"
    echo "Temes disponibles:"
    wp theme list --allow-root --path=/var/www/html
    exit 1
fi

# Activar tema
echo "🎨 Activant tema $WORDPRESS_THEME_NAME..."
wp theme activate "$WORDPRESS_THEME_NAME" --allow-root --path=/var/www/html

# Instal·lar plugins necessaris
echo "🔌 Instal·lant plugins essencials..."

# WooCommerce
if ! wp plugin is-installed woocommerce --allow-root --path=/var/www/html; then
    wp plugin install woocommerce --activate --allow-root --path=/var/www/html
fi

# Redis Object Cache
if ! wp plugin is-installed redis-cache --allow-root --path=/var/www/html; then
    wp plugin install redis-cache --activate --allow-root --path=/var/www/html
    wp redis enable --allow-root --path=/var/www/html
fi

# Wordfence Security
if ! wp plugin is-installed wordfence --allow-root --path=/var/www/html; then
    wp plugin install wordfence --activate --allow-root --path=/var/www/html
fi

# Yoast SEO
if ! wp plugin is-installed wordpress-seo --allow-root --path=/var/www/html; then
    wp plugin install wordpress-seo --activate --allow-root --path=/var/www/html
fi

# Contact Form 7
if ! wp plugin is-installed contact-form-7 --allow-root --path=/var/www/html; then
    wp plugin install contact-form-7 --activate --allow-root --path=/var/www/html
fi

# WP Mail SMTP
if ! wp plugin is-installed wp-mail-smtp --allow-root --path=/var/www/html; then
    wp plugin install wp-mail-smtp --activate --allow-root --path=/var/www/html
fi

# Configurar opcions bàsiques
echo "⚙️ Configurant opcions bàsiques..."
wp option update blogname "$WORDPRESS_TITLE" --allow-root --path=/var/www/html
wp option update blogdescription "Melindros artesans i pastisseria tradicional catalana" --allow-root --path=/var/www/html
wp option update admin_email "$WORDPRESS_ADMIN_EMAIL" --allow-root --path=/var/www/html
wp option update start_of_week 1 --allow-root --path=/var/www/html
wp option update timezone_string "Europe/Madrid" --allow-root --path=/var/www/html
wp option update date_format "d/m/Y" --allow-root --path=/var/www/html
wp option update time_format "H:i" --allow-root --path=/var/www/html

# Configurar permalinks
echo "🔗 Configurant permalinks..."
wp rewrite structure '/%postname%/' --allow-root --path=/var/www/html
wp rewrite flush --allow-root --path=/var/www/html

# Crear pàgines bàsiques
echo "📄 Creant pàgines bàsiques..."

# Pàgina d'inici
if ! wp post exists --post_type=page --post_title="Inici" --allow-root; then
    wp post create --post_type=page --post_title="Inici" --post_content="Benvinguts a Malet Torrent, la vostra pastisseria artesana de confiança." --post_status=publish --allow-root
    HOMEPAGE_ID=$(wp post list --post_type=page --post_title="Inici" --format=ids --allow-root)
    wp option update show_on_front page --allow-root
    wp option update page_on_front $HOMEPAGE_ID --allow-root
fi

# Pàgina de contacte
if ! wp post exists --post_type=page --post_title="Contacte" --allow-root; then
    wp post create --post_type=page --post_title="Contacte" --post_content="Poseu-vos en contacte amb nosaltres." --post_status=publish --allow-root
fi

# Pàgina de privacitat
if ! wp post exists --post_type=page --post_title="Política de Privacitat" --allow-root; then
    wp post create --post_type=page --post_title="Política de Privacitat" --post_content="La vostra privacitat és important per a nosaltres." --post_status=publish --allow-root
    PRIVACY_ID=$(wp post list --post_type=page --post_title="Política de Privacitat" --format=ids --allow-root)
    wp option update wp_page_for_privacy_policy $PRIVACY_ID --allow-root
fi

# Configurar WooCommerce si està actiu
if wp plugin is-active woocommerce --allow-root; then
    echo "🛒 Configurant WooCommerce..."
    
    # Configuració bàsica
    wp option update woocommerce_store_address "Carrer Example, 123" --allow-root
    wp option update woocommerce_store_city "Barcelona" --allow-root
    wp option update woocommerce_default_country "ES:B" --allow-root
    wp option update woocommerce_store_postcode "08001" --allow-root
    wp option update woocommerce_currency "EUR" --allow-root
    wp option update woocommerce_currency_pos "right_space" --allow-root
    wp option update woocommerce_price_thousand_sep "." --allow-root
    wp option update woocommerce_price_decimal_sep "," --allow-root
    
    # Crear pàgines de WooCommerce
    wp wc install --user=admin --allow-root || true
fi

# Configurar constants GitHub per actualitzacions automàtiques
echo "🔧 Configurant sistema d'actualitzacions GitHub..."
wp config set MALET_TORRENT_GITHUB_USER "$MALET_TORRENT_GITHUB_USER" --allow-root --path=/var/www/html
wp config set MALET_TORRENT_GITHUB_REPO "$MALET_TORRENT_GITHUB_REPO" --allow-root --path=/var/www/html
wp config set MALET_TORRENT_UPDATE_CHECK_INTERVAL "21600" --raw --allow-root --path=/var/www/html
wp config set MALET_TORRENT_ALLOW_PRERELEASES "false" --raw --allow-root --path=/var/www/html

echo "✅ Inicialització completada!"
echo "🌐 El vostre lloc web està llest a: $WORDPRESS_URL"
echo "👤 Usuari admin: $WORDPRESS_ADMIN_USER"
echo "🎨 Tema actiu: $WORDPRESS_THEME_NAME"
echo "📧 Email admin: $WORDPRESS_ADMIN_EMAIL"
echo "🔗 Accés admin: $WORDPRESS_URL/wp-admin/"