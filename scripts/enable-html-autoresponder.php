#!/usr/bin/env php
<?php
/**
 * Activar HTML per l'autoresponder i aplicar template elegant
 */

define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php');

$form_id = 85;
$form = WPCF7_ContactForm::get_instance($form_id);

if (!$form) {
    echo "Error: Formulari amb ID {$form_id} no trobat\n";
    exit(1);
}

// Obtenir autoresponder actual
$mail_2 = $form->prop('mail_2');

// Activar HTML i millorar el missatge
$mail_2['use_html'] = true;
$mail_2['body'] = '<p>Hola <strong>[full-name]</strong>,</p>

<p>Gràcies per contactar amb nosaltres!</p>

<p>Hem rebut el teu missatge i t\'el llegirem amb atenció. Et respondrem el més aviat possible.</p>

<p>Mentrestant, si tens qualsevol dubte urgent, també pots trucar-nos o visitar la nostra pastisseria.</p>

<p>Una forta abraçada,<br>
<em>L\'equip de Malet Torrent</em></p>

<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">

<p style="font-size: 12px; color: #666;">
<strong>Malet Torrent</strong> - Pastisseria Tradicional Catalana des de 1973<br>
📍 Arbúcies, La Selva<br>
🌐 <a href="https://malet.cat">https://malet.cat</a>
</p>';

// Guardar configuració
$form->set_properties(array('mail_2' => $mail_2));
$result = $form->save();

if ($result) {
    echo "✅ HTML activat per l'autoresponder!\n";
    echo "Ara l'email del client tindrà format HTML elegant.\n";
} else {
    echo "❌ Error al guardar\n";
    exit(1);
}
