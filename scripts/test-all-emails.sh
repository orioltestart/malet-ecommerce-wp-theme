#!/bin/bash

# Script per disparar tots els emails de prova
echo "📧 Enviant tots els emails de prova a MailHog..."

cd /var/www/html

# Verificar MailHog està configurat
echo "📍 Configuració SMTP actual:"
wp option get admin_email --allow-root

# Test 1: Password Reset Email
echo ""
echo "🔑 Enviant Password Reset Email..."
wp user create testuser test@malet.cat --role=subscriber --display_name="Joan Pérez Test" --allow-root 2>/dev/null || echo "Usuari ja existeix"
wp user reset-password testuser --allow-root

# Test 2: New User Registration Email
echo ""
echo "🎉 Enviant New User Registration Email..."
RANDOM_USER="testuser$(date +%s)"
wp user create $RANDOM_USER ${RANDOM_USER}@malet.cat --role=subscriber --display_name="Maria García Test" --send-email --allow-root

# Test 3: Comment Notification Email
echo ""
echo "💬 Enviant Comment Notification Email..."
# Crear un post si no existeix
POST_ID=$(wp post list --post_type=post --field=ID --posts_per_page=1 --allow-root)
if [ -z "$POST_ID" ]; then
    POST_ID=$(wp post create --post_type=post --post_title="Article de prova per comentaris" --post_content="Contingut de l'article de prova." --post_status=publish --allow-root)
    echo "Post creat amb ID: $POST_ID"
fi

# Crear comentari
COMMENT_ID=$(wp comment create --comment_post_ID=$POST_ID --comment_content="Aquest és un comentari de prova per testejar les plantilles d'email." --comment_author="Comentarista Test" --comment_author_email="comentari@test.com" --allow-root)
echo "Comentari creat amb ID: $COMMENT_ID"

# Test 4: Contact Form 7 (si existeix)
echo ""
echo "📝 Enviant Contact Form Email..."
# Aquest s'haurà d'enviar manualment des del formulari

# Test 5: Password Change Notification
echo ""
echo "✅ Enviant Password Change Notification..."
wp user update testuser --user_pass=novapassword123 --allow-root

# Test 6: General plain text email test
echo ""
echo "📄 Enviant email de text pla de prova..."
wp eval '
$to = "admin@malet.cat";
$subject = "Email de prova - Text pla";
$message = "Hola!\n\nAquest és un email de prova de text pla que hauria de ser convertit automàticament al nostre format HTML personalitzat.\n\nIncloem:\n- Header amb logo\n- Contingut formatat\n- Footer amb informació\n\nGràcies per la vostra atenció!\n\nEquip Malet Torrent";

$result = wp_mail($to, $subject, $message);
echo $result ? "Email enviat correctament\n" : "Error enviant email\n";
' --allow-root

# Test 7: Admin email change notification (simulat)
echo ""
echo "⚠️ Enviant Admin Email Change Notification..."
wp eval '
$old_email = get_option("admin_email");
$new_email = "nouadmin@malet.cat";

// Simulate admin email change
$email_data = array(
    "to" => $old_email,
    "subject" => "Email d'\''administrador canviat - Malet Torrent",
    "message" => "L'\''email d'\''administrador ha estat canviat de $old_email a $new_email"
);

// This would normally be triggered by WordPress core
wp_mail($email_data["to"], $email_data["subject"], $email_data["message"]);
echo "Admin email change notification sent\n";
' --allow-root

# Test 8: WooCommerce order email (si hi ha comandes)
echo ""
echo "🛒 Comprovant emails de WooCommerce..."
ORDER_COUNT=$(wp wc order list --field=id --allow-root 2>/dev/null | wc -l)
if [ "$ORDER_COUNT" -gt 0 ]; then
    echo "Trobades $ORDER_COUNT comandes existents"
    FIRST_ORDER=$(wp wc order list --field=id --posts_per_page=1 --allow-root)
    echo "Reenviant email de la comanda #$FIRST_ORDER..."
    wp wc order update $FIRST_ORDER --status=processing --allow-root
    wp wc order update $FIRST_ORDER --status=completed --allow-root
else
    echo "No hi ha comandes. Creant comanda de prova..."

    # Crear comanda de prova
    wp wc order create \
        --billing_first_name="Pere" \
        --billing_last_name="Martínez" \
        --billing_email="pere@test.com" \
        --billing_phone="666777888" \
        --status="processing" \
        --allow-root
fi

# Test 9: Email address change
echo ""
echo "📧 Enviant Email Address Change..."
wp user update testuser --user_email=noujemail@test.com --allow-root

echo ""
echo "🎉 TOTS ELS EMAILS ENVIATS!"
echo "==============================="
echo "📧 Emails enviats per revisar al MailHog:"
echo "1. 🔑 Password Reset"
echo "2. 🎉 New User Registration"
echo "3. 💬 Comment Notification"
echo "4. ✅ Password Change"
echo "5. 📄 Plain Text Email"
echo "6. ⚠️ Admin Email Change"
echo "7. 🛒 WooCommerce Order (si aplicable)"
echo "8. 📧 Email Address Change"
echo ""
echo "🔗 Accedir a MailHog: http://localhost:8025"
echo ""
echo "📋 Usuaris de prova creats:"
echo "   - testuser (amb diferents passwords)"
echo "   - $RANDOM_USER"
echo ""
echo "🗑️ Per netejar usuaris de prova:"
echo "   wp user delete testuser --yes --allow-root"
echo "   wp user delete $RANDOM_USER --yes --allow-root"