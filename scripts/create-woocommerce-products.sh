#!/bin/bash

# Script per crear productes WooCommerce basats en PRODUCTES.md
# Executa aquest script dins del contenidor Docker

echo "🛒 Creant productes WooCommerce per Malet Torrent..."

# Verificar que WooCommerce està instal·lat
if ! wp plugin is-active woocommerce --allow-root --path=/var/www/html; then
    echo "❌ Error: WooCommerce no està actiu. Activant..."
    wp plugin activate woocommerce --allow-root --path=/var/www/html
fi

# Crear categories de productes
echo "📁 Creant categories de productes..."

# Categories principals
wp wc product_cat create --name="Productes Estrella" --slug="productes-estrella" --description="Els productes més emblemàtics de Malet Torrent" --allow-root --path=/var/www/html
wp wc product_cat create --name="Galetes Tradicionals" --slug="galetes-tradicionals" --description="Galetes elaborades seguint receptes ancestrals catalanes" --allow-root --path=/var/www/html
wp wc product_cat create --name="Especialitats de la Casa" --slug="especialitats-casa" --description="Productes únics que defineixen la identitat de Malet Torrent" --allow-root --path=/var/www/html
wp wc product_cat create --name="Amb Fruits Secs" --slug="amb-fruits-secs" --description="Dolços elaborats amb ametlles i altres fruits secs seleccionats" --allow-root --path=/var/www/html
wp wc product_cat create --name="Dolços Tradicionals" --slug="dolcos-tradicionals" --description="Dolços tradicionals catalans de sempre" --allow-root --path=/var/www/html
wp wc product_cat create --name="Productes de Temporada" --slug="productes-temporada" --description="Productes disponibles en èpoques específiques de l'any" --allow-root --path=/var/www/html

echo "✅ Categories creades"

# PRODUCTES PERMANENTS
echo "🍪 Creant productes permanents..."

# 1. Melindros - Producte Estrella
wp wc product create \
  --name="Melindros" \
  --type="simple" \
  --regular_price="4.70" \
  --description="Els nostres melindros tradicionals, elaborats seguint la recepta familiar de sempre. Dolços artesans de textura tova i sabor inconfusible que han fet famosa la nostra pastisseria." \
  --short_description="Melindros tradicionals artesans de Malet Torrent. Recepta familiar de sempre." \
  --categories='[{"id":1}]' \
  --weight="290" \
  --status="publish" \
  --catalog_visibility="visible" \
  --featured="true" \
  --manage_stock="true" \
  --stock_quantity="50" \
  --allow-root --path=/var/www/html

# 2. Carquinyolis - Galetes Tradicionals
wp wc product create \
  --name="Carquinyolis" \
  --type="simple" \
  --regular_price="4.50" \
  --description="Galetes tradicionals catalanes cruixents, ideals per acompanyar amb un bon cafè o vi dolç. Elaborades amb ametlles seleccionades." \
  --short_description="Galetes cruixents tradicionals catalanes amb ametlles." \
  --categories='[{"id":2},{"id":4}]' \
  --weight="150" \
  --status="publish" \
  --catalog_visibility="visible" \
  --manage_stock="true" \
  --stock_quantity="30" \
  --allow-root --path=/var/www/html

# 3. Malets - Especialitat de la Casa
wp wc product create \
  --name="Malets" \
  --type="simple" \
  --regular_price="4.70" \
  --description="Dolços amb forma característica de malet, d'aquí el nom de la pastisseria. Elaborats amb ingredients seleccionats i siguint la tradició familiar." \
  --short_description="Dolços amb forma de malet, la especialitat distintiva de la casa." \
  --categories='[{"id":3}]' \
  --weight="150" \
  --status="publish" \
  --catalog_visibility="visible" \
  --featured="true" \
  --manage_stock="true" \
  --stock_quantity="40" \
  --allow-root --path=/var/www/html

# 4. Ametllats - Amb Fruits Secs
wp wc product create \
  --name="Ametllats" \
  --type="simple" \
  --regular_price="4.50" \
  --description="Dolços amb una alta proporció d'ametlles que els dona un sabor intens i una textura única. Perfectes per als amants dels fruits secs." \
  --short_description="Dolços amb alt contingut d'ametlles, sabor intens i única textura." \
  --categories='[{"id":4}]' \
  --weight="150" \
  --status="publish" \
  --catalog_visibility="visible" \
  --manage_stock="true" \
  --stock_quantity="25" \
  --allow-root --path=/var/www/html

# 5. Melindros amb Xocolata - Producte Estrella / Variació
wp wc product create \
  --name="Melindros amb Xocolata" \
  --type="simple" \
  --regular_price="4.50" \
  --description="Variació dels nostres melindros tradicionals amb cobertura de xocolata. La combinació perfecta entre la tradició i el sabor de la xocolata." \
  --short_description="Melindros tradicionals amb deliciosa cobertura de xocolata negra." \
  --categories='[{"id":1}]' \
  --weight="200" \
  --status="publish" \
  --catalog_visibility="visible" \
  --featured="true" \
  --manage_stock="true" \
  --stock_quantity="35" \
  --allow-root --path=/var/www/html

# 6. Borrecs - Dolços Tradicionals
wp wc product create \
  --name="Borrecs" \
  --type="simple" \
  --regular_price="4.50" \
  --description="Dolços tradicionals Catalans amb una textura esponjosa i un sabor suau. Ideals per a qualsevol moment del dia." \
  --short_description="Dolços tradicionals esponjosos i suaus, perfectes per qualsevol ocasió." \
  --categories='[{"id":5}]' \
  --weight="150" \
  --status="publish" \
  --catalog_visibility="visible" \
  --manage_stock="true" \
  --stock_quantity="30" \
  --allow-root --path=/var/www/html

# 7. Sequillos - Galetes Tradicionals
wp wc product create \
  --name="Sequillos" \
  --type="simple" \
  --regular_price="4.50" \
  --description="Galetes cruixents tradicionals, perfectes per acompanyar el cafè o el te. Elaborades seguint receptes ancestrals." \
  --short_description="Galetes cruixents tradicionals seguint receptes ancestrals catalanes." \
  --categories='[{"id":2}]' \
  --weight="180" \
  --status="publish" \
  --catalog_visibility="visible" \
  --manage_stock="true" \
  --stock_quantity="25" \
  --allow-root --path=/var/www/html

echo "✅ Productes permanents creats"

# PRODUCTES DE TEMPORADA
echo "🎄 Creant productes de temporada..."

# 8. Torrons - Nadal (exhaurit)
wp wc product create \
  --name="Torrons" \
  --type="simple" \
  --regular_price="6.50" \
  --description="Torrons artesans elaborats per Nadal seguint les receptes tradicionals catalanes. Disponible només durant les festes nadalenques." \
  --short_description="Torrons artesans tradicionals per les festes de Nadal." \
  --categories='[{"id":6}]' \
  --status="private" \
  --catalog_visibility="hidden" \
  --manage_stock="true" \
  --stock_quantity="0" \
  --stock_status="outofstock" \
  --allow-root --path=/var/www/html

# 9. Panellets - Tots Sants (exhaurit)
wp wc product create \
  --name="Panellets" \
  --type="simple" \
  --regular_price="5.80" \
  --description="Dolços tradicionals catalans per la festivitat de Tots Sants, elaborats amb massapà i recobertos amb pinyons. Disponibles en octubre i novembre." \
  --short_description="Panellets tradicionals de Tots Sants amb massapà i pinyons." \
  --categories='[{"id":6}]' \
  --status="private" \
  --catalog_visibility="hidden" \
  --manage_stock="true" \
  --stock_quantity="0" \
  --stock_status="outofstock" \
  --allow-root --path=/var/www/html

# 10. Coca d'Ametlla - Temporada Variable (exhaurit)
wp wc product create \
  --name="Coca d'Ametlla" \
  --type="simple" \
  --regular_price="8.50" \
  --description="Coca tradicional catalana amb ametlles, ideal per acompanyar amb vi dolç. Disponibilitat segons temporada." \
  --short_description="Coca tradicional catalana amb ametlles, perfecta amb vi dolç." \
  --categories='[{"id":6}]' \
  --status="private" \
  --catalog_visibility="hidden" \
  --manage_stock="true" \
  --stock_quantity="0" \
  --stock_status="outofstock" \
  --allow-root --path=/var/www/html

# 11. Coca de Sant Joan - Juny (exhaurit)
wp wc product create \
  --name="Coca de Sant Joan" \
  --type="simple" \
  --regular_price="9.20" \
  --description="Coca tradicional per la festivitat de Sant Joan, decorada amb fruita confitada i pinyons. Disponible només durant el mes de juny." \
  --short_description="Coca festiva de Sant Joan amb fruita confitada i pinyons." \
  --categories='[{"id":6}]' \
  --status="private" \
  --catalog_visibility="hidden" \
  --manage_stock="true" \
  --stock_quantity="0" \
  --stock_status="outofstock" \
  --allow-root --path=/var/www/html

echo "✅ Productes de temporada creats (ocults fins a la seva temporada)"

# Afegir atributs globals per ingredients i al·lèrgens
echo "🏷️ Configurant atributs de producte..."

# Crear atributs globals
wp wc product_attribute create --name="Ingredients" --slug="ingredients" --type="text" --order_by="menu_order" --has_archives="true" --allow-root --path=/var/www/html
wp wc product_attribute create --name="Al·lèrgens" --slug="allergens" --type="text" --order_by="menu_order" --has_archives="true" --allow-root --path=/var/www/html
wp wc product_attribute create --name="Pes" --slug="pes" --type="text" --order_by="menu_order" --has_archives="false" --allow-root --path=/var/www/html

echo "✅ Atributs de producte configurats"

# Configurar pàgina de botiga
echo "🏪 Configurant pàgina de botiga..."

# Crear pàgina de botiga si no existeix
if ! wp post list --post_type=page --name=botiga --allow-root --path=/var/www/html | grep -q botiga; then
    wp post create --post_type=page --post_title="Botiga" --post_name="botiga" --post_status=publish --allow-root --path=/var/www/html
fi

# Configurar WooCommerce
wp option update woocommerce_shop_page_id "$(wp post list --post_type=page --name=botiga --field=ID --allow-root --path=/var/www/html)" --allow-root --path=/var/www/html
wp option update woocommerce_currency "EUR" --allow-root --path=/var/www/html
wp option update woocommerce_currency_pos "right_space" --allow-root --path=/var/www/html
wp option update woocommerce_price_decimal_sep "," --allow-root --path=/var/www/html
wp option update woocommerce_price_thousand_sep "." --allow-root --path=/var/www/html
wp option update woocommerce_price_num_decimals "2" --allow-root --path=/var/www/html

echo "✅ Configuració de botiga completada"

# Mostrar resum
echo ""
echo "🎉 RESUM DE PRODUCTES CREATS:"
echo "================================"
echo "📦 Categories creades: 6"
echo "🍪 Productes permanents: 7"
echo "🎄 Productes de temporada: 4"
echo "📊 Total productes: 11"
echo ""
echo "🔗 URLs importants:"
echo "   - Botiga: https://wp2.malet.testart.cat/botiga/"
echo "   - Admin WooCommerce: https://wp2.malet.testart.cat/wp-admin/admin.php?page=wc-admin"
echo "   - Productes: https://wp2.malet.testart.cat/wp-admin/edit.php?post_type=product"
echo ""
echo "✅ Tots els productes de PRODUCTES.md han estat creats a WooCommerce!"

# Llistar productes creats
echo ""
echo "📋 LLISTAT DE PRODUCTES CREATS:"
echo "==============================="
wp wc product list --field=name --allow-root --path=/var/www/html