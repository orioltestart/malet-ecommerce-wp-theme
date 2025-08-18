#!/bin/bash
set -euo pipefail

echo "🚀 Inicialitzant WordPress amb tema Malet Torrent..."

# Verificar que WordPress està disponible
echo "⏳ Esperant que WordPress estigui disponible..."
until wp core is-installed --allow-root 2>/dev/null; do
    sleep 2
done

echo "✅ WordPress està disponible"

# Activar tema
echo "🎨 Activant tema malet-torrent..."
wp theme activate malet-torrent --allow-root

# Instal·lar plugins necessaris
echo "🔌 Instal·lant plugins essencials..."

# WooCommerce
if ! wp plugin is-installed woocommerce --allow-root; then
    wp plugin install woocommerce --activate --allow-root
fi

# Redis Object Cache
if ! wp plugin is-installed redis-cache --allow-root; then
    wp plugin install redis-cache --activate --allow-root
    wp redis enable --allow-root
fi

# Wordfence Security
if ! wp plugin is-installed wordfence --allow-root; then
    wp plugin install wordfence --activate --allow-root
fi

# Yoast SEO
if ! wp plugin is-installed wordpress-seo --allow-root; then
    wp plugin install wordpress-seo --activate --allow-root
fi

# Contact Form 7
if ! wp plugin is-installed contact-form-7 --allow-root; then
    wp plugin install contact-form-7 --activate --allow-root
fi

# WP Mail SMTP
if ! wp plugin is-installed wp-mail-smtp --allow-root; then
    wp plugin install wp-mail-smtp --activate --allow-root
fi

# Configurar opcions bàsiques
echo "⚙️ Configurant opcions bàsiques..."
wp option update blogname "Malet Torrent - Pastisseria Artesana" --allow-root
wp option update blogdescription "Melindros artesans i pastisseria tradicional catalana" --allow-root
wp option update start_of_week 1 --allow-root
wp option update timezone_string "Europe/Madrid" --allow-root
wp option update date_format "d/m/Y" --allow-root
wp option update time_format "H:i" --allow-root

# Configurar permalinks
echo "🔗 Configurant permalinks..."
wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root

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

echo "✅ Inicialització completada!"
echo "🌐 El vostre lloc web està llest a: ${WP_HOME:-http://localhost}"