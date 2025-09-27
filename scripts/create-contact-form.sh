#!/bin/bash

# Script per crear un formulari de contacte Contact Form 7 personalitzat
echo "📝 Creant formulari de contacte personalitzat..."

# Verificar que Contact Form 7 està instal·lat
if ! wp plugin is-active contact-form-7 --allow-root --path=/var/www/html; then
    echo "❌ Error: Contact Form 7 no està actiu. Activant..."
    wp plugin activate contact-form-7 --allow-root --path=/var/www/html
fi

# Crear el formulari de contacte
wp post create \
  --post_type=wpcf7_contact_form \
  --post_title="Formulari de contacte Malet Torrent" \
  --post_status=publish \
  --allow-root --path=/var/www/html

# Obtenir l'ID del formulari creat
FORM_ID=$(wp post list --post_type=wpcf7_contact_form --field=ID --posts_per_page=1 --orderby=date --order=DESC --allow-root --path=/var/www/html)

echo "✅ Formulari creat amb ID: $FORM_ID"

# Configurar el contingut del formulari
wp post meta update $FORM_ID _form '
<div class="form-group name">
  [text* your-name placeholder "El vostre nom"]
</div>

<div class="form-group email">
  [email* your-email placeholder "example@correu.com"]
</div>

<div class="form-group phone">
  [tel your-phone placeholder "666 777 888"]
</div>

<div class="form-group subject">
  [text* your-subject placeholder "De què voleu parlar?"]
</div>

<div class="form-group message">
  [textarea* your-message placeholder "Escriviu aquí el vostre missatge..."]
</div>

[submit "Enviar missatge"]
' --allow-root --path=/var/www/html

# Configurar el correu de l'administrador
wp post meta update $FORM_ID _mail '
a:18:{
s:7:"subject";s:30:"[_site_title] [your-subject]";
s:6:"sender";s:41:"[your-name] <wordpress@wp2.malet.testart.cat>";
s:4:"body";s:168:"Nou missatge de contacte rebut:

Nom: [your-name]
Email: [your-email]
Telèfon: [your-phone]
Assumpte: [your-subject]

Missatge:
[your-message]

--
Aquest missatge ha estat enviat des del formulari de contacte de _site_title (_site_url)";
s:9:"recipient";s:19:"info@malet.cat";
s:18:"additional_headers";s:22:"Reply-To: [your-email]";
s:11:"attachments";s:0:"";
s:8:"use_html";i:1;
s:13:"exclude_blank";i:0;
}
' --allow-root --path=/var/www/html

# Configurar el correu de confirmació per l'usuari
wp post meta update $FORM_ID _mail_2 '
a:9:{
s:6:"active";i:1;
s:7:"subject";s:48:"Gràcies per contactar amb Malet Torrent";
s:6:"sender";s:45:"Malet Torrent <info@wp2.malet.testart.cat>";
s:4:"body";s:280:"Estimat/da [your-name],

Gràcies per contactar amb nosaltres. Hem rebut el vostre missatge i us respondrem tan aviat com sigui possible.

Detalls del vostre missatge:
Assumpte: [your-subject]
Data: [_date]

Cordialment,
L'\''equip de Malet Torrent
Pastisseria Tradicional Catalana";
s:9:"recipient";s:12:"[your-email]";
s:18:"additional_headers";s:0:"";
s:11:"attachments";s:0:"";
s:8:"use_html";i:1;
s:13:"exclude_blank";i:0;
}
' --allow-root --path=/var/www/html

# Configurar missatges de resposta personalitzats
wp post meta update $FORM_ID _messages '
a:22:{
s:12:"mail_sent_ok";s:90:"Gràcies per el vostre missatge. Ha estat enviat correctament i us respondrem aviat.";
s:12:"mail_sent_ng";s:80:"Hi ha hagut un error enviant el missatge. Intenteu-ho de nou més tard.";
s:16:"validation_error";s:61:"Un o més camps tenen errors. Reviseu-los i torneu a intentar.";
s:4:"spam";s:51:"El missatge no s'\''ha pogut enviar per seguretat.";
s:12:"accept_terms";s:69:"Heu d'\''acceptar els termes abans d'\''enviar el vostre missatge.";
s:16:"invalid_required";s:23:"El camp és obligatori.";
s:16:"invalid_too_long";s:32:"El camp és massa llarg.";
s:17:"invalid_too_short";s:31:"El camp és massa curt.";
}
' --allow-root --path=/var/www/html

echo "✅ Formulari de contacte configurat correctament"

# Mostrar shortcode per usar al tema
echo ""
echo "🔗 SHORTCODE PER USAR:"
echo "====================="
echo "[contact-form-7 id=\"$FORM_ID\" title=\"Formulari de contacte Malet Torrent\"]"
echo ""
echo "📋 INFORMACIÓ DEL FORMULARI:"
echo "============================"
echo "ID: $FORM_ID"
echo "Títol: Formulari de contacte Malet Torrent"
echo "Email destinatari: info@malet.cat"
echo "Confirmació automàtica: Activada"
echo ""
echo "✅ Formulari llest per usar!"