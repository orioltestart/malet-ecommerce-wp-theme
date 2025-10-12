#!/usr/bin/env php
<?php
/**
 * Configurar camps de Flamingo per al formulari de contacte
 *
 * Això utilitza l'API oficial de CF7 per indicar a Flamingo
 * quins camps usar per subject, name i email
 */

// Executar dins de WordPress
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php');

$form_id = 85;
$form = WPCF7_ContactForm::get_instance($form_id);

if (!$form) {
    echo "Error: Formulari amb ID {$form_id} no trobat\n";
    exit(1);
}

// Obtenir settings actuals
$settings = $form->prop('additional_settings');

// Convertir a array si és string
if (is_string($settings)) {
    $settings = !empty($settings) ? explode("\n", $settings) : array();
} else if (!is_array($settings)) {
    $settings = array();
}

// Filtrar settings existents de flamingo per no duplicar
$settings = array_filter($settings, function($line) {
    return strpos($line, 'flamingo_') !== 0;
});

// Afegir configuració Flamingo
$flamingo_settings = array(
    'flamingo_subject: "[subject]"',
    'flamingo_name: "[full-name]"',
    'flamingo_email: "[email]"'
);

$settings = array_merge($settings, $flamingo_settings);
$settings_string = implode("\n", $settings);

// Guardar configuració
$form->set_properties(array(
    'additional_settings' => $settings_string
));

$result = $form->save();

if ($result) {
    echo "✅ Configuració Flamingo actualitzada correctament!\n\n";
    echo "Settings aplicats:\n";
    echo "- flamingo_subject: [subject]\n";
    echo "- flamingo_name: [full-name]\n";
    echo "- flamingo_email: [email]\n\n";
    echo "Ara Flamingo guardarà automàticament els valors correctes.\n";
} else {
    echo "❌ Error al guardar la configuració\n";
    exit(1);
}
