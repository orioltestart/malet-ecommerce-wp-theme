#!/usr/bin/env php
<?php
/**
 * Actualitzar template d'autoresponder del formulari de contacte
 * Per fer-lo més amigable i cordial pel client
 */

define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php');

$form_id = 85;
$form = WPCF7_ContactForm::get_instance($form_id);

if (!$form) {
    echo "Error: Formulari amb ID {$form_id} no trobat\n";
    exit(1);
}

// Nou template més amigable
$new_autoresponder = array(
    'active' => true,
    'subject' => 'Hem rebut el teu missatge - Malet Torrent',
    'sender' => 'Malet Torrent <[_site_admin_email]>',
    'recipient' => '[email]',
    'body' => 'Hola [full-name],

Gràcies per contactar amb nosaltres!

Hem rebut el teu missatge i t\'el llegirem amb atenció. Et respondrem el més aviat possible.

Mentrestant, si tens qualsevol dubte urgent, també pots trucar-nos o visitar la nostra pastisseria.

Una forta abraçada,
L\'equip de Malet Torrent

--
Malet Torrent - Pastisseria Tradicional Catalana des de 1973
📍 Arbúcies, La Selva
🌐 https://malet.cat',
    'additional_headers' => '',
    'attachments' => '',
    'use_html' => true,
    'exclude_blank' => false,
);

// Guardar configuració
$form->set_properties(array('mail_2' => $new_autoresponder));
$result = $form->save();

if ($result) {
    echo "✅ Template d'autoresponder actualitzat correctament!\n\n";
    echo "Nou missatge:\n";
    echo "─────────────────────────────────────────\n";
    echo $new_autoresponder['body'] . "\n";
    echo "─────────────────────────────────────────\n";
} else {
    echo "❌ Error al guardar la configuració\n";
    exit(1);
}
