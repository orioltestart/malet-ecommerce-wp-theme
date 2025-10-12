<?php
/**
 * Sistema d'emails de WooCommerce - Malet Torrent
 *
 * Sistema simplificat que utilitza només templates personalitzats en català
 * Elimina la complexitat de filtres de traducció múltiples
 *
 * @package MaletTorrent
 * @since 1.0.0
 */

// Prevenir accés directe
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configurar filtres d'email de WooCommerce
 * Només utilitza templates personalitzats - sense traduccions automàtiques
 */
function malet_setup_email_customization_filters() {
    // Només el template override - res més
    add_filter('woocommerce_locate_template', 'malet_force_woocommerce_template_override', 999, 3);
}

/**
 * Forçar ús dels templates de WooCommerce en català
 *
 * @param string $template Path del template original
 * @param string $template_name Nom del template
 * @param array $args Arguments del template
 * @return string Path del template personalitzat o original
 */
function malet_force_woocommerce_template_override($template, $template_name, $args) {
    // Només per emails
    if (strpos($template_name, 'emails/') !== 0) {
        return $template;
    }

    // Path dels nostres templates personalitzats
    $custom_template = get_template_directory() . '/woocommerce/' . $template_name;

    if (file_exists($custom_template)) {
        return $custom_template;
    }

    return $template;
}

// Inicialitzar el sistema d'emails quan WooCommerce estigui carregat
add_action('woocommerce_loaded', 'malet_setup_email_customization_filters');