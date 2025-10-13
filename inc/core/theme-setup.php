<?php

/**
 * Configuració bàsica del tema Malet Torrent
 *
 * @package MaletTorrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuració inicial del tema
 */
function malet_torrent_setup()
{
    // Suport per títols automàtics
    add_theme_support('title-tag');

    // Suport per thumbnails
    add_theme_support('post-thumbnails');

    // Suport per logo personalitzat
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ));

    // Suport per feeds automàtics
    add_theme_support('automatic-feed-links');

    // Suport per HTML5
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));

    // Localització es carregarà més tard en el hook 'init'
}
add_action('after_setup_theme', 'malet_torrent_setup');

/**
 * Carregar traduccions del tema i assegurar WooCommerce
 */
function malet_torrent_load_textdomain()
{
    // Carregar traduccions del tema
    load_theme_textdomain('malet-torrent', MALETNEXT_THEME_DIR . '/languages');

    // Forçar càrrega de traduccions WooCommerce si està actiu
    if (class_exists('WooCommerce')) {
        load_plugin_textdomain('woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}

/**
 * Configuració específica per headless
 */
function malet_torrent_headless_setup()
{
    // Activar CORS per la API
    add_action('rest_api_init', 'malet_torrent_add_cors_support');

    // CORS per admin-ajax
    add_action('wp_ajax_nopriv_*', 'malet_torrent_add_cors_support', 1);
    add_action('wp_ajax_*', 'malet_torrent_add_cors_support', 1);

    // CORS per wp-json requests
    add_action('init', 'malet_torrent_wp_json_cors', 1);

    // Carregar traduccions del tema (prioritat alta per evitar conflictes)
    add_action('init', 'malet_torrent_load_textdomain', 5);

    // Millorar la API REST per WooCommerce (després que WooCommerce carregui)
    add_action('rest_api_init', 'malet_torrent_enhance_woocommerce_api', 20);

    // Afegir endpoints personalitzats (després que WooCommerce carregui)
    add_action('rest_api_init', 'malet_torrent_register_custom_endpoints', 20);
    error_log('MALET DEBUG: Hook rest_api_init registrat');
}
add_action('init', 'malet_torrent_headless_setup');

/**
 * Redirigir frontend al lloc Next.js
 * Permet accés a wp-admin i API REST
 */
function malet_torrent_redirect_to_nextjs()
{
    // No redirigir si estem a l'admin
    if (is_admin()) {
        return;
    }

    // No redirigir si és una petició AJAX
    if (wp_doing_ajax()) {
        return;
    }

    // No redirigir si és una petició a l'API REST
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    // No redirigir si és wp-login.php o wp-cron.php
    $script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    if (strpos($script_name, 'wp-login.php') !== false ||
        strpos($script_name, 'wp-cron.php') !== false ||
        strpos($script_name, 'xmlrpc.php') !== false) {
        return;
    }

    // No redirigir si és una petició a /gestio-torrent
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($request_uri, '/gestio-torrent') !== false) {
        return;
    }

    // Redirigir al frontend Next.js
    $frontend_url = FRONTEND_URL;

    // Mantenir la ruta actual per redirigir a la mateixa pàgina a Next.js
    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $redirect_url = rtrim($frontend_url, '/') . $current_path;

    wp_redirect($redirect_url, 301);
    exit;
}
add_action('template_redirect', 'malet_torrent_redirect_to_nextjs');

/**
 * Registrar endpoint simple per debug
 */
function malet_debug_endpoint()
{
    register_rest_route('malet-torrent/v1', '/debug', array(
        'methods' => 'GET',
        'callback' => function () {
            return array('debug' => 'working');
        },
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'malet_debug_endpoint');
