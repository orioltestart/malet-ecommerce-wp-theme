<?php

/**
 * Functions and definitions for Malet Torrent
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

// Codi de desactivació d'Autoptimize eliminat - 7 setembre 2025

// Constants del tema
define('MALETNEXT_VERSION', '1.0.0');
define('MALETNEXT_THEME_DIR', get_template_directory());
define('MALETNEXT_THEME_URL', get_template_directory_uri());

// Constant per URL del frontend
if (!defined('FRONTEND_URL')) {
    define('FRONTEND_URL', getenv('FRONTEND_URL') ?: 'http://localhost:3000');
}

// Configuració Redis Object Cache
if (!defined('WP_REDIS_HOST')) {
    define('WP_REDIS_HOST', getenv('REDIS_HOST') ?: 'redis');
}
if (!defined('WP_REDIS_PORT')) {
    define('WP_REDIS_PORT', (int)(getenv('REDIS_PORT') ?: 6379));
}
if (!defined('WP_REDIS_PASSWORD')) {
    $redis_password = getenv('REDIS_PASSWORD');
    if ($redis_password) {
        define('WP_REDIS_PASSWORD', $redis_password);
    }
}
if (!defined('WP_REDIS_DATABASE')) {
    define('WP_REDIS_DATABASE', (int)(getenv('REDIS_DATABASE') ?: 0));
}

// URL Redis completa si està definida
$redis_url = getenv('REDIS_URL');
if ($redis_url && !defined('WP_REDIS_URL')) {
    define('WP_REDIS_URL', $redis_url);
}

// Carregar traduccions del tema
function malet_load_theme_textdomain() {
    load_theme_textdomain('malet-torrent', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'malet_load_theme_textdomain');

// Funcions de configuració del tema mogudes a inc/core/theme-setup.php
// Funcions de CORS mogudes a inc/api/cors.php
// Hooks d'SEO moguts a inc/seo/indexing.php

// Incloure sistema d'instal·lació de plugins
require_once get_template_directory() . '/inc/class-plugin-installer.php';

require_once get_template_directory() . '/inc/admin-notices.php';

// Incloure sistema JWT Auth
require_once get_template_directory() . '/inc/class-jwt-auth.php';

// Incloure API de formularis
require_once get_template_directory() . '/inc/forms-api.php';

// Incloure gestió d'usuaris
require_once get_template_directory() . '/inc/user-management.php';

// ================================================================================
// FITXERS MODULARS - Organització del codi
// ================================================================================

// Funcions de configuració del tema
require_once get_template_directory() . '/inc/core/theme-setup.php';

// Funcions d'API
require_once get_template_directory() . '/inc/api/cors.php';
require_once get_template_directory() . '/inc/api/rest-api.php';
require_once get_template_directory() . '/inc/api/woocommerce-api.php';

// Funcions de checkout i comandes
require_once get_template_directory() . '/inc/checkout-functions.php';

// Funcions de SEO
require_once get_template_directory() . '/inc/seo/indexing.php';

// Funcions de Google Analytics
require_once get_template_directory() . '/inc/analytics/google-analytics.php';

// Funcions d'administració
require_once get_template_directory() . '/inc/admin/settings.php';

// ================================================================================

// Sistema d'emails de WooCommerce (templates personalitzats)
require_once get_template_directory() . '/inc/email-system.php';
require_once get_template_directory() . '/inc/email-templates.php';


// Funcions de SEO i indexing mogudes a inc/seo/indexing.php
// Funcions de WooCommerce API mogudes a inc/api/woocommerce-api.php
// Funcions d'endpoints REST API mogudes a inc/api/rest-api.php


/**
 * Permisos personalitzats per customers a l'API WooCommerce
 */
// Permetre als customers accedir a les seves pròpies dades
add_filter('woocommerce_rest_check_permissions', 'malet_torrent_customer_api_permissions', 10, 4);

function malet_torrent_customer_api_permissions($permission, $context, $object_id, $post_type)
{
    $current_user = wp_get_current_user();

    // Si no hi ha usuari autenticat, mantenir permisos per defecte
    if (!$current_user->exists()) {
        return $permission;
    }

    // Només aplicar a usuaris amb rol 'customer'
    if (!in_array('customer', $current_user->roles)) {
        return $permission;
    }

    // Obtenir el context de la petició API actual
    $route = $_SERVER['REQUEST_URI'] ?? '';

    // Per customer data: verificar que només accedeixin al seu propi perfil
    if (strpos($route, '/wp-json/wc/') !== false && strpos($route, '/customers') !== false) {
        // Si s'especifica un ID específic
        if (preg_match('/customers\/(\d+)/', $route, $matches)) {
            $requested_customer_id = intval($matches[1]);
            if ($requested_customer_id != $current_user->ID) {
                // Denegar accés a altres perfils
                return new WP_Error('rest_customer_invalid_id', 'You can only access your own customer data.', array('status' => 403));
            }
        }
        // Permetre accés al endpoint general o al seu propi perfil
        return true;
    }

    // Per orders: només les seves pròpies
    if (strpos($route, '/wp-json/wc/') !== false && strpos($route, '/orders') !== false) {
        // Si s'especifica un ID específic d'order
        if (preg_match('/orders\/(\d+)/', $route, $matches)) {
            $order_id = intval($matches[1]);
            $order = wc_get_order($order_id);
            if (!$order || $order->get_customer_id() != $current_user->ID) {
                return new WP_Error('rest_order_invalid_id', 'You can only access your own orders.', array('status' => 403));
            }
        }
        // Permetre accés al endpoint general (es filtra després)
        return true;
    }

    // Per downloads: només els seus propis
    if (strpos($route, '/wp-json/wc/') !== false && strpos($route, '/downloads') !== false) {
        return true;
    }

    // Denegar accés a altres endpoints WooCommerce per customers
    if (strpos($route, '/wp-json/wc/') !== false) {
        return new WP_Error('rest_woocommerce_access_denied', 'You do not have permission to access this endpoint.', array('status' => 403));
    }

    return $permission;
}

/**
 * Filtrar consultes d'orders per mostrar només les del customer actual
 */
add_filter('woocommerce_rest_orders_prepare_object_query', 'malet_torrent_filter_customer_orders', 10, 2);

function malet_torrent_filter_customer_orders($args, $request)
{
    $current_user = wp_get_current_user();

    // Només aplicar a usuaris amb rol 'customer'
    if (!$current_user->exists() || !in_array('customer', $current_user->roles)) {
        return $args;
    }

    // Forçar que només es mostrin orders del customer actual
    $args['customer'] = $current_user->ID;

    return $args;
}

/**
 * Filtrar consultes de customers per mostrar només el perfil propi
 */
add_filter('woocommerce_rest_customer_query', 'malet_torrent_filter_customer_data', 10, 2);

function malet_torrent_filter_customer_data($args, $request)
{
    $current_user = wp_get_current_user();

    // Només aplicar a usuaris amb rol 'customer'  
    if (!$current_user->exists() || !in_array('customer', $current_user->roles)) {
        return $args;
    }

    // Forçar que només es mostri el perfil del customer actual
    $args['include'] = array($current_user->ID);

    return $args;
}

/**
 * Permetre Basic Authentication per desenvolupament 
 */
add_filter('determine_current_user', 'malet_torrent_basic_auth_handler', 5);

function malet_torrent_basic_auth_handler($user_id)
{
    // Només per peticions a l'API
    if (!defined('REST_REQUEST') || !REST_REQUEST) {
        return $user_id;
    }

    // Si ja hi ha un usuari autenticat, no fer res
    if ($user_id) {
        return $user_id;
    }

    // Verificar si hi ha credencials Basic Auth en diferents formats
    $username = '';
    $password = '';

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
        if (strpos($auth, 'Basic ') === 0) {
            $credentials = base64_decode(substr($auth, 6));
            if ($credentials && strpos($credentials, ':') !== false) {
                list($username, $password) = explode(':', $credentials, 2);
            }
        }
    } elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
    }

    if (empty($username) || empty($password)) {
        return $user_id;
    }

    // Verificar credencials
    $user = wp_authenticate($username, $password);

    if (is_wp_error($user)) {
        return $user_id;
    }
    return $user->ID;
}

/**
 * Solucionar problemes d'autenticació API WooCommerce en desenvolupament
 */
// Permetre autenticació HTTP per WooCommerce API
add_filter('woocommerce_api_check_authentication', function ($user, $consumer_key, $consumer_secret, $signature, $timestamp, $nonce) {
    return $user; // Deixar que WooCommerce gestioni l'autenticació
}, 10, 6);

// Forçar que WooCommerce accepti connexions HTTP (només desenvolupament)
add_filter('woocommerce_rest_check_permissions', function ($permission, $context, $object_id, $post_type) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return true; // Permetre accés en mode debug
    }
    return $permission;
}, 10, 4);

/**
 * Forçar HTTP per WooCommerce assets en desenvolupament local
 */
add_action('init', function () {
    if (
        defined('WP_DEBUG') && WP_DEBUG &&
        isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost:8080'
    ) {
        // Forçar HTTP per WooCommerce
        $_SERVER['HTTPS'] = 'off';
        unset($_SERVER['HTTPS']);
        // Assegurar que WooCommerce detecti HTTP correctament
        add_filter('is_ssl', '__return_false', 999);
        add_filter('woocommerce_force_ssl_checkout', '__return_false', 999);

        // Forçar HTTP per tots els assets
        add_filter('script_loader_src', 'malet_torrent_force_http_assets');
        add_filter('style_loader_src', 'malet_torrent_force_http_assets');
    }
}, 1);

/**
 * Forçar HTTP per tots els assets en desenvolupament local
 */
function malet_torrent_force_http_assets($src)
{
    if (
        defined('WP_DEBUG') && WP_DEBUG &&
        isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost:8080'
    ) {
        return str_replace('https://localhost:8080', 'http://localhost:8080', $src);
    }
    return $src;
}

/**
 * =============================================================================
 * MÈTODES D'ENVIAMENT PERSONALITZATS
 * =============================================================================
 */

/**
 * Registrar mètodes d'enviament personalitzats per Malet Torrent
 */
add_action('woocommerce_shipping_init', 'malet_torrent_shipping_methods_init');

function malet_torrent_shipping_methods_init()
{
    if (!class_exists('WC_Malet_Recollida_Botiga_Shipping')) {
        include_once('inc/class-malet-shipping-recollida-botiga.php');
    }
    if (!class_exists('WC_Malet_Enviament_Local_Shipping')) {
        include_once('inc/class-malet-shipping-enviament-local.php');
    }
    if (!class_exists('WC_Malet_Enviament_Nacional_Shipping')) {
        include_once('inc/class-malet-shipping-enviament-nacional.php');
    }
}

add_filter('woocommerce_shipping_methods', 'malet_torrent_add_shipping_methods');

function malet_torrent_add_shipping_methods($methods)
{
    $methods['malet_recollida_botiga'] = 'WC_Malet_Recollida_Botiga_Shipping';
    $methods['malet_enviament_local'] = 'WC_Malet_Enviament_Local_Shipping';
    $methods['malet_enviament_nacional'] = 'WC_Malet_Enviament_Nacional_Shipping';
    return $methods;
}

/**
 * Configurar zones d'enviament per defecte via WP-CLI
 */
add_action('init', 'malet_torrent_setup_default_shipping_zones');

function malet_torrent_setup_default_shipping_zones()
{
    // Només executar una vegada i si WooCommerce està actiu
    if (get_option('malet_shipping_zones_setup') || !class_exists('WooCommerce')) {
        return;
    }

    // Configurar zones d'enviament per defecte
    malet_torrent_create_shipping_zones();

    // Marcar com configurat
    update_option('malet_shipping_zones_setup', true);
}

function malet_torrent_create_shipping_zones()
{
    if (!class_exists('WC_Shipping_Zones')) {
        return;
    }

    // 1. Zona Local (Torrent i rodalies)
    $zona_local = new WC_Shipping_Zone();
    $zona_local->set_zone_name('Torrent i Rodalies');
    $zona_local->set_zone_order(1);
    $zona_local->save();

    // Afegir ubicacions a la zona local
    $zona_local->add_location('46900', 'postcode'); // Torrent
    $zona_local->add_location('46901', 'postcode');
    $zona_local->add_location('ES:V', 'state'); // València

    // Afegir mètodes d'enviament a zona local
    $zona_local->add_shipping_method('malet_recollida_botiga');
    $zona_local->add_shipping_method('malet_enviament_local');

    // 2. Zona Nacional (Espanya)
    $zona_nacional = new WC_Shipping_Zone();
    $zona_nacional->set_zone_name('Espanya');
    $zona_nacional->set_zone_order(2);
    $zona_nacional->save();

    // Afegir ubicacions a la zona nacional
    $zona_nacional->add_location('ES', 'country');

    // Afegir mètodes d'enviament a zona nacional
    $zona_nacional->add_shipping_method('malet_enviament_nacional');

    // Log per debug
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Malet Torrent: Zones d\'enviament configurades automàticament');
    }
}

/**
 * Afegir camps personalitzats als productes WooCommerce
 */

// Afegir camps personalitzats al backend dels productes
add_action('woocommerce_product_options_general_product_data', 'malet_add_custom_product_fields');
function malet_add_custom_product_fields()
{
    global $woocommerce, $post;

    echo '<div class="options_group">';

    // Camp de Pes (en grams)
    woocommerce_wp_text_input(array(
        'id' => '_malet_weight_grams',
        'label' => 'Pes (grams)',
        'placeholder' => 'Ex: 250',
        'description' => 'Pes del producte en grams',
        'type' => 'number',
        'custom_attributes' => array(
            'step' => '1',
            'min' => '0'
        )
    ));

    // Camp d'Ingredients
    woocommerce_wp_textarea_input(array(
        'id' => '_malet_ingredients',
        'label' => 'Ingredients',
        'placeholder' => 'Ex: Farina de blat, sucre, ous, mantega...',
        'description' => 'Llista d\'ingredients del producte',
        'rows' => 4
    ));

    // Camp d'Al·lèrgens
    woocommerce_wp_textarea_input(array(
        'id' => '_malet_allergens',
        'label' => 'Al·lèrgens',
        'placeholder' => 'Ex: Gluten, ou, lactis, fruits secs...',
        'description' => 'Llista d\'al·lèrgens del producte',
        'rows' => 4
    ));

    echo '</div>';
}

// Guardar els camps personalitzats
add_action('woocommerce_process_product_meta', 'malet_save_custom_product_fields');
function malet_save_custom_product_fields($post_id)
{
    // Guardar pes en grams
    $weight_grams = $_POST['_malet_weight_grams'];
    if (!empty($weight_grams)) {
        update_post_meta($post_id, '_malet_weight_grams', esc_attr($weight_grams));
    }

    // Guardar ingredients
    $ingredients = $_POST['_malet_ingredients'];
    if (!empty($ingredients)) {
        update_post_meta($post_id, '_malet_ingredients', esc_textarea($ingredients));
    }

    // Guardar al·lèrgens
    $allergens = $_POST['_malet_allergens'];
    if (!empty($allergens)) {
        update_post_meta($post_id, '_malet_allergens', esc_textarea($allergens));
    }
}

// Mostrar camps personalitzats al frontend (single product)
add_action('woocommerce_single_product_summary', 'malet_display_custom_product_fields', 25);
function malet_display_custom_product_fields()
{
    global $product;

    $weight_grams = get_post_meta($product->get_id(), '_malet_weight_grams', true);
    $ingredients = get_post_meta($product->get_id(), '_malet_ingredients', true);
    $allergens = get_post_meta($product->get_id(), '_malet_allergens', true);

    if ($weight_grams || $ingredients || $allergens) {
        echo '<div class="malet-product-details">';

        if ($weight_grams) {
            echo '<p class="malet-weight"><strong>Pes:</strong> ' . esc_html($weight_grams) . ' grams</p>';
        }

        if ($ingredients) {
            echo '<div class="malet-ingredients">';
            echo '<p><strong>Ingredients:</strong></p>';
            echo '<p>' . wp_kses_post(nl2br($ingredients)) . '</p>';
            echo '</div>';
        }

        if ($allergens) {
            echo '<div class="malet-allergens">';
            echo '<p><strong>Al·lèrgens:</strong></p>';
            echo '<p>' . wp_kses_post(nl2br($allergens)) . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }
}

// Afegir camps personalitzats a l'API REST de WooCommerce
add_action('rest_api_init', 'malet_add_custom_fields_to_api');
function malet_add_custom_fields_to_api()
{
    // Camp de pes
    register_rest_field('product', 'weight_grams', array(
        'get_callback' => function ($object) {
            return get_post_meta($object['id'], '_malet_weight_grams', true);
        },
        'update_callback' => function ($value, $object) {
            return update_post_meta($object->ID, '_malet_weight_grams', $value);
        },
        'schema' => array(
            'description' => 'Pes del producte en grams',
            'type' => 'integer'
        )
    ));

    // Camp d'ingredients
    register_rest_field('product', 'ingredients', array(
        'get_callback' => function ($object) {
            return get_post_meta($object['id'], '_malet_ingredients', true);
        },
        'update_callback' => function ($value, $object) {
            return update_post_meta($object->ID, '_malet_ingredients', $value);
        },
        'schema' => array(
            'description' => 'Llista d\'ingredients del producte',
            'type' => 'string'
        )
    ));

    // Camp d'al·lèrgens
    register_rest_field('product', 'allergens', array(
        'get_callback' => function ($object) {
            return get_post_meta($object['id'], '_malet_allergens', true);
        },
        'update_callback' => function ($value, $object) {
            return update_post_meta($object->ID, '_malet_allergens', $value);
        },
        'schema' => array(
            'description' => 'Llista d\'al·lèrgens del producte',
            'type' => 'string'
        )
    ));
}

/**
 * Registrar Custom Post Type de Receptes
 */
add_action('init', 'malet_register_recipe_post_type');
function malet_register_recipe_post_type()
{
    $labels = array(
        'name' => 'Receptes',
        'singular_name' => 'Recepta',
        'menu_name' => 'Receptes',
        'name_admin_bar' => 'Recepta',
        'add_new' => 'Afegir Nova',
        'add_new_item' => 'Afegir Nova Recepta',
        'new_item' => 'Nova Recepta',
        'edit_item' => 'Editar Recepta',
        'view_item' => 'Veure Recepta',
        'all_items' => 'Totes les Receptes',
        'search_items' => 'Buscar Receptes',
        'parent_item_colon' => 'Recepta Pare:',
        'not_found' => 'No s\'han trobat receptes.',
        'not_found_in_trash' => 'No s\'han trobat receptes a la paperera.'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true, // Per l'API REST
        'rest_base' => 'receptes',
        'query_var' => true,
        'rewrite' => array('slug' => 'recepta'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-book-alt',
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'),
        'taxonomies' => array('recipe_category', 'recipe_difficulty')
    );

    register_post_type('recipe', $args);
}

/**
 * Registrar taxonomies per receptes
 */
add_action('init', 'malet_register_recipe_taxonomies');
function malet_register_recipe_taxonomies()
{
    // Taxonomia de categories de receptes
    register_taxonomy('recipe_category', array('recipe'), array(
        'hierarchical' => true,
        'labels' => array(
            'name' => 'Categories de Receptes',
            'singular_name' => 'Categoria de Recepta',
            'search_items' => 'Buscar Categories',
            'all_items' => 'Totes les Categories',
            'parent_item' => 'Categoria Pare',
            'parent_item_colon' => 'Categoria Pare:',
            'edit_item' => 'Editar Categoria',
            'update_item' => 'Actualitzar Categoria',
            'add_new_item' => 'Afegir Nova Categoria',
            'new_item_name' => 'Nom de Nova Categoria',
            'menu_name' => 'Categories'
        ),
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rest_base' => 'recipe-categories',
        'query_var' => true,
        'rewrite' => array('slug' => 'categoria-recepta')
    ));

    // Taxonomia de dificultat de receptes
    register_taxonomy('recipe_difficulty', array('recipe'), array(
        'hierarchical' => false,
        'labels' => array(
            'name' => 'Dificultat',
            'singular_name' => 'Dificultat',
            'search_items' => 'Buscar Dificultat',
            'all_items' => 'Totes les Dificultats',
            'edit_item' => 'Editar Dificultat',
            'update_item' => 'Actualitzar Dificultat',
            'add_new_item' => 'Afegir Nova Dificultat',
            'new_item_name' => 'Nom de Nova Dificultat',
            'menu_name' => 'Dificultat'
        ),
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rest_base' => 'recipe-difficulties',
        'query_var' => true,
        'rewrite' => array('slug' => 'dificultat-recepta')
    ));
}

/**
 * Afegir camps personalitzats a les receptes
 */
add_action('add_meta_boxes', 'malet_add_recipe_meta_boxes');
function malet_add_recipe_meta_boxes()
{
    add_meta_box(
        'recipe_details',
        'Detalls de la Recepta',
        'malet_recipe_details_callback',
        'recipe',
        'normal',
        'high'
    );
}

function malet_recipe_details_callback($post)
{
    wp_nonce_field('malet_recipe_details_nonce', 'recipe_details_nonce');

    // Obtenir valors guardats
    $prep_time = get_post_meta($post->ID, '_recipe_prep_time', true);
    $cook_time = get_post_meta($post->ID, '_recipe_cook_time', true);
    $servings = get_post_meta($post->ID, '_recipe_servings', true);
    $ingredients_list = get_post_meta($post->ID, '_recipe_ingredients_list', true);
    $instructions = get_post_meta($post->ID, '_recipe_instructions', true);
    $notes = get_post_meta($post->ID, '_recipe_notes', true);

    echo '<table class="form-table">';

    echo '<tr>';
    echo '<th><label for="recipe_prep_time">Temps de Preparació (minuts)</label></th>';
    echo '<td><input type="number" id="recipe_prep_time" name="recipe_prep_time" value="' . esc_attr($prep_time) . '" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th><label for="recipe_cook_time">Temps de Cocció (minuts)</label></th>';
    echo '<td><input type="number" id="recipe_cook_time" name="recipe_cook_time" value="' . esc_attr($cook_time) . '" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th><label for="recipe_servings">Racions</label></th>';
    echo '<td><input type="number" id="recipe_servings" name="recipe_servings" value="' . esc_attr($servings) . '" /></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th><label for="recipe_ingredients_list">Llista d\'Ingredients</label></th>';
    echo '<td><textarea id="recipe_ingredients_list" name="recipe_ingredients_list" rows="10" cols="50">' . esc_textarea($ingredients_list) . '</textarea>';
    echo '<p class="description">Un ingredient per línia. Ex: 250g farina de blat</p></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th><label for="recipe_instructions">Instruccions</label></th>';
    echo '<td><textarea id="recipe_instructions" name="recipe_instructions" rows="15" cols="50">' . esc_textarea($instructions) . '</textarea>';
    echo '<p class="description">Instruccions pas a pas</p></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th><label for="recipe_notes">Notes</label></th>';
    echo '<td><textarea id="recipe_notes" name="recipe_notes" rows="5" cols="50">' . esc_textarea($notes) . '</textarea>';
    echo '<p class="description">Notes addicionals o consells</p></td>';
    echo '</tr>';

    echo '</table>';
}

// Guardar camps personalitzats de receptes
add_action('save_post', 'malet_save_recipe_details');
function malet_save_recipe_details($post_id)
{
    // Verificar nonce
    if (!isset($_POST['recipe_details_nonce']) || !wp_verify_nonce($_POST['recipe_details_nonce'], 'malet_recipe_details_nonce')) {
        return;
    }

    // Evitar auto-save
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Verificar permisos
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Només per post type 'recipe'
    if (get_post_type($post_id) !== 'recipe') {
        return;
    }

    // Guardar camps
    $fields = array(
        'recipe_prep_time' => '_recipe_prep_time',
        'recipe_cook_time' => '_recipe_cook_time',
        'recipe_servings' => '_recipe_servings',
        'recipe_ingredients_list' => '_recipe_ingredients_list',
        'recipe_instructions' => '_recipe_instructions',
        'recipe_notes' => '_recipe_notes'
    );

    foreach ($fields as $field_name => $meta_key) {
        if (isset($_POST[$field_name])) {
            $value = sanitize_textarea_field($_POST[$field_name]);
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}

/**
 * Afegir camps de receptes a l'API REST
 */
add_action('rest_api_init', 'malet_add_recipe_fields_to_api');
function malet_add_recipe_fields_to_api()
{
    $recipe_fields = array(
        'prep_time' => '_recipe_prep_time',
        'cook_time' => '_recipe_cook_time',
        'servings' => '_recipe_servings',
        'ingredients_list' => '_recipe_ingredients_list',
        'instructions' => '_recipe_instructions',
        'notes' => '_recipe_notes'
    );

    foreach ($recipe_fields as $field_name => $meta_key) {
        register_rest_field('recipe', $field_name, array(
            'get_callback' => function ($object) use ($meta_key) {
                return get_post_meta($object['id'], $meta_key, true);
            },
            'update_callback' => function ($value, $object) use ($meta_key) {
                return update_post_meta($object->ID, $meta_key, $value);
            },
            'schema' => array(
                'description' => 'Camp personalitzat de recepta: ' . $field_name,
                'type' => 'string'
            )
        ));
    }
}

/**
 * Relació entre Receptes i Productes
 */

// Afegir metabox per vincular receptes als productes
add_action('add_meta_boxes', 'malet_add_product_recipe_meta_box');
function malet_add_product_recipe_meta_box()
{
    add_meta_box(
        'product_recipes',
        'Receptes Relacionades',
        'malet_product_recipes_callback',
        'product',
        'side',
        'default'
    );
}

function malet_product_recipes_callback($post)
{
    wp_nonce_field('malet_product_recipes_nonce', 'product_recipes_nonce');

    // Obtenir receptes actuals vinculades
    $linked_recipes = get_post_meta($post->ID, '_linked_recipes', true);
    $linked_recipes = is_array($linked_recipes) ? $linked_recipes : array();

    // Obtenir totes les receptes disponibles
    $recipes = get_posts(array(
        'post_type' => 'recipe',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));

    echo '<p>Selecciona les receptes relacionades amb aquest producte:</p>';

    if (empty($recipes)) {
        echo '<p><em>No hi ha receptes disponibles.</em></p>';
        return;
    }

    foreach ($recipes as $recipe) {
        $checked = in_array($recipe->ID, $linked_recipes) ? 'checked="checked"' : '';
        echo '<label>';
        echo '<input type="checkbox" name="linked_recipes[]" value="' . $recipe->ID . '" ' . $checked . '> ';
        echo esc_html($recipe->post_title);
        echo '</label><br>';
    }
}

// Afegir metabox per vincular productes a les receptes
add_action('add_meta_boxes', 'malet_add_recipe_product_meta_box');
function malet_add_recipe_product_meta_box()
{
    add_meta_box(
        'recipe_products',
        'Productes Relacionats',
        'malet_recipe_products_callback',
        'recipe',
        'side',
        'default'
    );
}

function malet_recipe_products_callback($post)
{
    wp_nonce_field('malet_recipe_products_nonce', 'recipe_products_nonce');

    // Obtenir productes actuals vinculats
    $linked_products = get_post_meta($post->ID, '_linked_products', true);
    $linked_products = is_array($linked_products) ? $linked_products : array();

    // Obtenir tots els productes disponibles
    $products = get_posts(array(
        'post_type' => 'product',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));

    echo '<p>Selecciona els productes relacionats amb aquesta recepta:</p>';

    if (empty($products)) {
        echo '<p><em>No hi ha productes disponibles.</em></p>';
        return;
    }

    foreach ($products as $product) {
        $checked = in_array($product->ID, $linked_products) ? 'checked="checked"' : '';
        echo '<label>';
        echo '<input type="checkbox" name="linked_products[]" value="' . $product->ID . '" ' . $checked . '> ';
        echo esc_html($product->post_title);
        echo '</label><br>';
    }
}

// Guardar relacions de productes
add_action('save_post', 'malet_save_product_recipe_relationships');
function malet_save_product_recipe_relationships($post_id)
{
    // Verificar nonce per productes
    if (isset($_POST['product_recipes_nonce']) && wp_verify_nonce($_POST['product_recipes_nonce'], 'malet_product_recipes_nonce')) {
        if (get_post_type($post_id) === 'product') {
            // Guardar receptes vinculades al producte
            $linked_recipes = isset($_POST['linked_recipes']) ? array_map('intval', $_POST['linked_recipes']) : array();
            update_post_meta($post_id, '_linked_recipes', $linked_recipes);

            // Actualitzar relació inversa: afegir aquest producte a les receptes seleccionades
            $old_recipes = get_post_meta($post_id, '_linked_recipes', true);
            $old_recipes = is_array($old_recipes) ? $old_recipes : array();

            // Eliminar aquest producte de receptes que ja no estan vinculades
            $removed_recipes = array_diff($old_recipes, $linked_recipes);
            foreach ($removed_recipes as $recipe_id) {
                $recipe_products = get_post_meta($recipe_id, '_linked_products', true);
                $recipe_products = is_array($recipe_products) ? $recipe_products : array();
                $recipe_products = array_diff($recipe_products, array($post_id));
                update_post_meta($recipe_id, '_linked_products', $recipe_products);
            }

            // Afegir aquest producte a receptes noves
            foreach ($linked_recipes as $recipe_id) {
                $recipe_products = get_post_meta($recipe_id, '_linked_products', true);
                $recipe_products = is_array($recipe_products) ? $recipe_products : array();
                if (!in_array($post_id, $recipe_products)) {
                    $recipe_products[] = $post_id;
                    update_post_meta($recipe_id, '_linked_products', $recipe_products);
                }
            }
        }
    }

    // Verificar nonce per receptes
    if (isset($_POST['recipe_products_nonce']) && wp_verify_nonce($_POST['recipe_products_nonce'], 'malet_recipe_products_nonce')) {
        if (get_post_type($post_id) === 'recipe') {
            // Guardar productes vinculats a la recepta
            $linked_products = isset($_POST['linked_products']) ? array_map('intval', $_POST['linked_products']) : array();
            update_post_meta($post_id, '_linked_products', $linked_products);

            // Actualitzar relació inversa: afegir aquesta recepta als productes seleccionats
            $old_products = get_post_meta($post_id, '_linked_products', true);
            $old_products = is_array($old_products) ? $old_products : array();

            // Eliminar aquesta recepta de productes que ja no estan vinculats
            $removed_products = array_diff($old_products, $linked_products);
            foreach ($removed_products as $product_id) {
                $product_recipes = get_post_meta($product_id, '_linked_recipes', true);
                $product_recipes = is_array($product_recipes) ? $product_recipes : array();
                $product_recipes = array_diff($product_recipes, array($post_id));
                update_post_meta($product_id, '_linked_recipes', $product_recipes);
            }

            // Afegir aquesta recepta a productes nous
            foreach ($linked_products as $product_id) {
                $product_recipes = get_post_meta($product_id, '_linked_recipes', true);
                $product_recipes = is_array($product_recipes) ? $product_recipes : array();
                if (!in_array($post_id, $product_recipes)) {
                    $product_recipes[] = $post_id;
                    update_post_meta($product_id, '_linked_recipes', $product_recipes);
                }
            }
        }
    }
}

// Afegir relacions a l'API REST
add_action('rest_api_init', 'malet_add_relationship_fields_to_api');
function malet_add_relationship_fields_to_api()
{
    // Camp de receptes vinculades als productes
    register_rest_field('product', 'linked_recipes', array(
        'get_callback' => function ($object) {
            $linked_recipes = get_post_meta($object['id'], '_linked_recipes', true);
            return is_array($linked_recipes) ? $linked_recipes : array();
        },
        'update_callback' => function ($value, $object) {
            return update_post_meta($object->ID, '_linked_recipes', $value);
        },
        'schema' => array(
            'description' => 'IDs de receptes vinculades al producte',
            'type' => 'array',
            'items' => array('type' => 'integer')
        )
    ));

    // Camp de productes vinculats a les receptes
    register_rest_field('recipe', 'linked_products', array(
        'get_callback' => function ($object) {
            $linked_products = get_post_meta($object['id'], '_linked_products', true);
            return is_array($linked_products) ? $linked_products : array();
        },
        'update_callback' => function ($value, $object) {
            return update_post_meta($object->ID, '_linked_products', $value);
        },
        'schema' => array(
            'description' => 'IDs de productes vinculats a la recepta',
            'type' => 'array',
            'items' => array('type' => 'integer')
        )
    ));
}

/**
 * URL d'accés personalitzada /gestio-torrent (VERSIÓ SIMPLIFICADA)
 */
function malet_gestio_torrent_redirect()
{
    // Només processar si la URL és exactament /gestio-torrent
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if ($request_uri === '/gestio-torrent' || $request_uri === '/gestio-torrent/') {
        // Evitar bucles: si ja estem a wp-login.php no redirigir
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') === false) {
            wp_redirect(wp_login_url());
            exit;
        }
    }
}
add_action('template_redirect', 'malet_gestio_torrent_redirect');

/**
 * Sanititzar i validar reviews contra XSS i CSRF
 */
function malet_sanitize_review_content($comment_id)
{
    $comment = get_comment($comment_id);

    if (!$comment || $comment->comment_type !== 'review') {
        return;
    }

    $content = $comment->comment_content;
    $author = $comment->comment_author;

    // Detectar i bloquejar scripts maliciosos
    $malicious_patterns = array(
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload=/i',
        '/onclick=/i',
        '/onerror=/i',
        '/onmouseover=/i',
        '/<object[^>]*>/i',
        '/<embed[^>]*>/i',
        '/<applet[^>]*>/i',
        '/expression\s*\(/i',
        '/url\s*\(/i'
    );

    $is_malicious = false;
    foreach ($malicious_patterns as $pattern) {
        if (preg_match($pattern, $content) || preg_match($pattern, $author)) {
            $is_malicious = true;
            break;
        }
    }

    if ($is_malicious) {
        // Marcar comment com spam i notificar
        wp_set_comment_status($comment_id, 'spam');

        // Log de seguretat
        error_log("MALET SECURITY: Review maliciosa detectada i marcada com spam. ID: $comment_id, IP: " .
            $_SERVER['REMOTE_ADDR'] . ", Content: " . substr($content, 0, 100));

        // Notificar administradors de l'intent d'atac
        malet_notify_admin_security_threat($comment_id, $content, $author);

        return false;
    }

    // Sanititzar contingut (eliminar HTML perillós però conservar formatat bàsic)
    $sanitized_content = wp_kses($content, array(
        'p' => array(),
        'br' => array(),
        'strong' => array(),
        'b' => array(),
        'em' => array(),
        'i' => array(),
        'u' => array()
    ));

    $sanitized_author = sanitize_text_field($author);

    // Actualitzar comment amb contingut sanititzat
    if ($content !== $sanitized_content || $author !== $sanitized_author) {
        wp_update_comment(array(
            'comment_ID' => $comment_id,
            'comment_content' => $sanitized_content,
            'comment_author' => $sanitized_author
        ));
    }

    return true;
}

/**
 * Notificar administradors d'intents d'atac de seguretat
 */
function malet_notify_admin_security_threat($comment_id, $malicious_content, $author)
{
    $admin_emails = array();
    $users = get_users(array('role' => 'administrator'));
    foreach ($users as $user) {
        $admin_emails[] = $user->user_email;
    }

    if (empty($admin_emails)) {
        return;
    }

    $subject = '[ALERTA SEGURETAT] ' . get_bloginfo('name') . ' - Intent d\'atac XSS detectat';

    $message = sprintf(
        "🚨 ALERTA DE SEGURETAT 🚨\n\n" .
            "S'ha detectat un intent d'atac XSS en una review:\n\n" .
            "📍 Detalls de l'incident:\n" .
            "- Review ID: %s\n" .
            "- Autor: %s\n" .
            "- IP: %s\n" .
            "- Data: %s\n" .
            "- User Agent: %s\n\n" .
            "🔍 Contingut maliciós (primeres 200 caracters):\n%s\n\n" .
            "✅ Acció presa:\n" .
            "- Review marcada automàticament com SPAM\n" .
            "- Contingut bloquejat\n" .
            "- Incident registrat als logs\n\n" .
            "🛡️ Revisar activitat: %s\n" .
            "📊 Logs del servidor: /var/log/\n\n" .
            "Aquest és un email automàtic del sistema de seguretat.",
        $comment_id,
        $author,
        $_SERVER['REMOTE_ADDR'] ?? 'Desconeguda',
        current_time('Y-m-d H:i:s'),
        $_SERVER['HTTP_USER_AGENT'] ?? 'Desconegut',
        substr($malicious_content, 0, 200),
        admin_url('edit-comments.php?comment_status=spam')
    );

    $headers = array('Content-Type: text/plain; charset=UTF-8');
    wp_mail($admin_emails, $subject, $message, $headers);
}

/**
 * Enviar email als administradors quan es publica una review (segura)
 */
function malet_notify_admin_new_review($comment_id, $comment_approved)
{
    // Només per reviews aprovades
    if ($comment_approved !== 1) {
        return;
    }

    // SEGURETAT: Sanititzar primer el contingut
    if (!malet_sanitize_review_content($comment_id)) {
        // Si la review és maliciosa, no enviar email de notificació normal
        return;
    }

    $comment = get_comment($comment_id);

    // Verificar que és una review de WooCommerce
    if ($comment->comment_type !== 'review') {
        return;
    }

    $product = get_post($comment->comment_post_ID);
    $rating = get_comment_meta($comment_id, 'rating', true);

    // Obtenir emails dels administradors
    $admin_emails = array();
    $users = get_users(array('role' => 'administrator'));
    foreach ($users as $user) {
        $admin_emails[] = $user->user_email;
    }

    if (empty($admin_emails)) {
        return;
    }

    // Preparar contingut del email
    $subject = sprintf('[%s] Nova review per %s', get_bloginfo('name'), $product->post_title);

    $message = sprintf(
        "S'ha rebut una nova review per al producte: %s\n\n" .
            "Autor: %s (%s)\n" .
            "Puntuació: %s/5\n" .
            "Review: %s\n\n" .
            "Veure producte: %s\n" .
            "Gestionar reviews: %s",
        $product->post_title,
        $comment->comment_author,
        $comment->comment_author_email,
        $rating ? $rating : 'Sense puntuació',
        $comment->comment_content,
        get_permalink($product->ID),
        admin_url('edit-comments.php?comment_type=review')
    );

    // Enviar email a tots els administradors
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    wp_mail($admin_emails, $subject, $message, $headers);
}
add_action('comment_post', 'malet_notify_admin_new_review', 10, 2);

/**
 * També notificar quan una review passa de pendent a aprovada
 */
function malet_notify_admin_approved_review($comment_id)
{
    $comment = get_comment($comment_id);

    // Verificar que és una review de WooCommerce
    if ($comment->comment_type !== 'review') {
        return;
    }

    // Cridar la funció de notificació
    malet_notify_admin_new_review($comment_id, 1);
}
add_action('wp_set_comment_status', function ($comment_id, $status) {
    if ($status === 'approve') {
        malet_notify_admin_approved_review($comment_id);
    }
}, 10, 2);

/**
 * Hook per sanititzar reviews ABANS que es guardin (prevenció primària)
 */
function malet_sanitize_review_before_save($comment_data)
{
    // Només aplicar a reviews
    if (isset($comment_data['comment_type']) && $comment_data['comment_type'] === 'review') {

        // Detectar i bloquejar scripts maliciosos ABANS de guardar
        $content = $comment_data['comment_content'];
        $author = $comment_data['comment_author'];

        $malicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onclick=/i',
            '/onerror=/i',
            '/onmouseover=/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/<applet[^>]*>/i',
            '/expression\s*\(/i'
        );

        $is_malicious = false;
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content) || preg_match($pattern, $author)) {
                $is_malicious = true;
                break;
            }
        }

        if ($is_malicious) {
            // Marcar automàticament com spam
            $comment_data['comment_approved'] = 'spam';

            // Log de seguretat immediate
            error_log("MALET SECURITY: Review maliciosa bloquejada ABANS de guardar. IP: " .
                ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . ", Content: " . substr($content, 0, 100));

            // Canviar contingut per evitar execució
            $comment_data['comment_content'] = '[CONTINGUT BLOQUEJAT PER SEGURETAT]';
            $comment_data['comment_author'] = sanitize_text_field($author);
        } else {
            // Sanititzar contingut normal (conservar formatat bàsic)
            $comment_data['comment_content'] = wp_kses($content, array(
                'p' => array(),
                'br' => array(),
                'strong' => array(),
                'b' => array(),
                'em' => array(),
                'i' => array(),
                'u' => array()
            ));

            $comment_data['comment_author'] = sanitize_text_field($author);
        }
    }

    return $comment_data;
}
add_filter('preprocess_comment', 'malet_sanitize_review_before_save');

/**
 * Protecció adicional: Verificar nonces per reviews via formulari
 */
function malet_verify_review_nonce()
{
    // Només per requests de review via POST
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['comment']) &&
        isset($_POST['comment_post_ID'])
    ) {

        // Verificar que és un producte (WooCommerce review)
        $post = get_post($_POST['comment_post_ID']);
        if ($post && $post->post_type === 'product') {

            // WordPress ja té protecció CSRF built-in, però afegim validació extra
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'unfiltered-html-comment_' . $_POST['comment_post_ID'])) {
                // Si no hi ha nonce vàlid, usar el nonce de comment estàndard
                if (
                    !isset($_POST['_wp_http_referer']) ||
                    !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'comment_' . $_POST['comment_post_ID'])
                ) {

                    // Log intent sospitós
                    error_log("MALET SECURITY: Review sense nonce vàlid. IP: " .
                        ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));

                    wp_die('Error de seguretat: Petició no vàlida.', 'Error de Seguretat', array('response' => 403));
                }
            }
        }
    }
}
add_action('init', 'malet_verify_review_nonce', 1);

/**
 * Traduir errors d'autenticació al català
 */
function malet_translate_auth_errors($errors, $redirect_to = '')
{
    if (!is_wp_error($errors)) {
        return $errors;
    }

    $error_codes = $errors->get_error_codes();

    foreach ($error_codes as $code) {
        $original_message = $errors->get_error_message($code);
        $translated_message = '';

        switch ($code) {
            case 'invalid_username':
                $translated_message = 'Nom d\'usuari incorrecte o inexistent.';
                break;

            case 'incorrect_password':
                $translated_message = 'La contrasenya que has introduït no és correcta.';
                break;

            case 'empty_username':
                $translated_message = 'El camp nom d\'usuari és obligatori.';
                break;

            case 'empty_password':
                $translated_message = 'El camp contrasenya és obligatori.';
                break;

            case 'invalid_email':
                $translated_message = 'L\'adreça de correu electrònic no és vàlida.';
                break;

            case 'invalidcombo':
                $translated_message = 'Combinació d\'usuari i contrasenya incorrecta.';
                break;

            case 'authentication_failed':
                $translated_message = 'Autenticació fallida. Comprova les credencials.';
                break;

            case 'too_many_retries':
                $translated_message = 'Massa intents fallits. Prova-ho més tard.';
                break;

            case 'login_blocked':
                $translated_message = 'Accés bloquejat temporalment per seguretat.';
                break;

            case 'user_not_found':
                $translated_message = 'No s\'ha trobat cap usuari amb aquestes dades.';
                break;

            case 'expired_token':
                $translated_message = 'El token d\'autenticació ha expirat.';
                break;

            case 'invalid_token':
                $translated_message = 'Token d\'autenticació no vàlid.';
                break;

            case 'insufficient_permissions':
                $translated_message = 'No tens permisos suficients per accedir.';
                break;

            case 'account_suspended':
                $translated_message = 'El compte està suspès. Contacta amb l\'administrador.';
                break;

            case 'password_reset_required':
                $translated_message = 'Cal canviar la contrasenya abans de continuar.';
                break;
        }

        // Si tenim traducció, substituir el missatge
        if (!empty($translated_message)) {
            $errors->remove($code);
            $errors->add($code, $translated_message);
        }
    }

    return $errors;
}
add_filter('wp_login_errors', 'malet_translate_auth_errors', 10, 2);

/**
 * Traduir errors d'autenticació JWT i REST API
 */
function malet_translate_rest_auth_errors($error)
{
    if (is_wp_error($error)) {
        // Usar la mateixa lògica que per wp_login_errors
        $error = malet_translate_auth_errors($error);
    }

    return $error;
}
add_filter('rest_authentication_errors', 'malet_translate_rest_auth_errors');

/**
 * Traduir missatges d'error generals de login
 */
function malet_custom_login_messages($message)
{
    // Traduir missatges comuns del formulari de login
    $translations = array(
        'Lost your password?' => 'Has oblidat la contrasenya?',
        'Username or Email Address' => 'Nom d\'usuari o adreça de correu',
        'Password' => 'Contrasenya',
        'Remember Me' => 'Recorda\'m',
        'Log In' => 'Iniciar sessió',
        'Back to' => 'Tornar a',
        'Invalid username or email.' => 'Nom d\'usuari o email incorrecte.',
        'Password strength indicator' => 'Indicador de força de la contrasenya',
        'Username' => 'Nom d\'usuari',
        'Email' => 'Correu electrònic',
        'Registration complete' => 'Registre completat',
        'Check your email' => 'Comprova el teu correu',
    );

    foreach ($translations as $english => $catalan) {
        $message = str_replace($english, $catalan, $message);
    }

    return $message;
}
add_filter('gettext', 'malet_custom_login_messages', 20, 3);
add_filter('ngettext', 'malet_custom_login_messages', 20, 5);

/**
 * Personalitzar missatges d'error del formulari de login
 */
function malet_customize_login_error_message($message)
{
    // Si és la pàgina de login, personalitzar encara més
    if (
        strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false ||
        strpos($_SERVER['REQUEST_URI'], 'gestio-torrent') !== false
    ) {

        $custom_messages = array(
            '<strong>ERROR</strong>: Invalid username.' => '<strong>ERROR</strong>: Nom d\'usuari incorrecte.',
            '<strong>ERROR</strong>: The password you entered' => '<strong>ERROR</strong>: La contrasenya que has introduït',
            'is incorrect.' => 'no és correcta.',
            'Lost your password?' => '<a href="' . wp_lostpassword_url() . '">Has oblidat la contrasenya?</a>',
        );

        foreach ($custom_messages as $english => $catalan) {
            $message = str_replace($english, $catalan, $message);
        }
    }

    return $message;
}
add_filter('login_errors', 'malet_customize_login_error_message');

/**
 * Sistema de múltiples adreces per usuari (fins a 5)
 */

/**
 * Obtenir totes les adreces d'un usuari
 */
function malet_get_user_addresses($user_id, $type = 'both')
{
    $addresses = array();

    for ($i = 1; $i <= 5; $i++) {
        if ($type === 'billing' || $type === 'both') {
            $billing_address = get_user_meta($user_id, "malet_billing_address_{$i}", true);
            if (!empty($billing_address)) {
                $addresses['billing'][$i] = $billing_address;
            }
        }

        if ($type === 'shipping' || $type === 'both') {
            $shipping_address = get_user_meta($user_id, "malet_shipping_address_{$i}", true);
            if (!empty($shipping_address)) {
                $addresses['shipping'][$i] = $shipping_address;
            }
        }
    }

    return $addresses;
}

/**
 * Guardar una adreça d'usuari (suporta type='both')
 */
function malet_save_user_address($user_id, $address_data, $type = 'billing', $slot = 1)
{
    // Validar slot (1-5)
    if ($slot < 1 || $slot > 5) {
        return new WP_Error('invalid_slot', 'Slot d\'adreça ha de ser entre 1 i 5');
    }

    // Validar tipus
    if (!in_array($type, ['billing', 'shipping', 'both'])) {
        return new WP_Error('invalid_type', 'Tipus d\'adreça ha de ser billing, shipping o both');
    }

    // Validar camps obligatoris
    $required_fields = ['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country'];
    foreach ($required_fields as $field) {
        if (empty($address_data[$field])) {
            return new WP_Error('missing_field', "Camp obligatori: {$field}");
        }
    }

    // Sanititzar dades
    $sanitized_address = array(
        'first_name' => sanitize_text_field($address_data['first_name']),
        'last_name' => sanitize_text_field($address_data['last_name']),
        'company' => sanitize_text_field($address_data['company'] ?? ''),
        'address_1' => sanitize_text_field($address_data['address_1']),
        'address_2' => sanitize_text_field($address_data['address_2'] ?? ''),
        'city' => sanitize_text_field($address_data['city']),
        'state' => sanitize_text_field($address_data['state'] ?? ''),
        'postcode' => sanitize_text_field($address_data['postcode']),
        'country' => sanitize_text_field($address_data['country']),
        'label' => sanitize_text_field($address_data['label'] ?? ''),
        'is_default' => !empty($address_data['is_default']),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );

    // Si type='both', crear dues adreces (billing i shipping)
    if ($type === 'both') {
        $results = array();

        // Trobar slots lliures per billing i shipping
        $billing_slot = malet_find_free_slot($user_id, 'billing', $slot);
        $shipping_slot = malet_find_free_slot($user_id, 'shipping', $slot);

        if (!$billing_slot) {
            return new WP_Error('no_billing_slot', 'No hi ha slots disponibles per adreça de facturació');
        }

        if (!$shipping_slot) {
            return new WP_Error('no_shipping_slot', 'No hi ha slots disponibles per adreça d\'enviament');
        }

        // Guardar adreça de facturació
        $billing_result = malet_save_single_address($user_id, $sanitized_address, 'billing', $billing_slot);
        if (is_wp_error($billing_result)) {
            return $billing_result;
        }
        $results['billing'] = array('slot' => $billing_slot, 'data' => $billing_result);

        // Guardar adreça d'enviament
        $shipping_result = malet_save_single_address($user_id, $sanitized_address, 'shipping', $shipping_slot);
        if (is_wp_error($shipping_result)) {
            return $shipping_result;
        }
        $results['shipping'] = array('slot' => $shipping_slot, 'data' => $shipping_result);

        return $results;
    } else {
        // Guardar una sola adreça
        return malet_save_single_address($user_id, $sanitized_address, $type, $slot);
    }
}

/**
 * Trobar slot lliure per un tipus d'adreça
 */
function malet_find_free_slot($user_id, $type, $preferred_slot = 1)
{
    // Primer intentar el slot preferit
    $existing = get_user_meta($user_id, "malet_{$type}_address_{$preferred_slot}", true);
    if (empty($existing)) {
        return $preferred_slot;
    }

    // Si no està lliure, buscar altres slots
    for ($i = 1; $i <= 5; $i++) {
        if ($i !== $preferred_slot) {
            $existing = get_user_meta($user_id, "malet_{$type}_address_{$i}", true);
            if (empty($existing)) {
                return $i;
            }
        }
    }

    return null; // No hi ha slots lliures
}

/**
 * Guardar una sola adreça (funció interna)
 */
function malet_save_single_address($user_id, $sanitized_address, $type, $slot)
{
    // Si és per defecte, desmarcar altres adreces per defecte del mateix tipus
    if ($sanitized_address['is_default']) {
        for ($i = 1; $i <= 5; $i++) {
            if ($i !== $slot) {
                $existing_address = get_user_meta($user_id, "malet_{$type}_address_{$i}", true);
                if (!empty($existing_address)) {
                    $existing_address['is_default'] = false;
                    update_user_meta($user_id, "malet_{$type}_address_{$i}", $existing_address);
                }
            }
        }
    }

    // Guardar adreça
    $saved = update_user_meta($user_id, "malet_{$type}_address_{$slot}", $sanitized_address);

    if ($saved !== false) {
        return $sanitized_address;
    } else {
        return new WP_Error('save_failed', 'Error guardant l\'adreça');
    }
}

/**
 * Eliminar una adreça d'usuari
 */
function malet_delete_user_address($user_id, $type, $slot)
{
    if ($slot < 1 || $slot > 5) {
        return new WP_Error('invalid_slot', 'Slot d\'adreça ha de ser entre 1 i 5');
    }

    if (!in_array($type, ['billing', 'shipping'])) {
        return new WP_Error('invalid_type', 'Tipus d\'adreça ha de ser billing o shipping');
    }

    return delete_user_meta($user_id, "malet_{$type}_address_{$slot}");
}

/**
 * Obtenir adreça per defecte d'un usuari
 */
function malet_get_default_user_address($user_id, $type)
{
    for ($i = 1; $i <= 5; $i++) {
        $address = get_user_meta($user_id, "malet_{$type}_address_{$i}", true);
        if (!empty($address) && !empty($address['is_default'])) {
            $address['slot'] = $i;
            return $address;
        }
    }

    // Si no hi ha adreça per defecte, retornar la primera disponible
    for ($i = 1; $i <= 5; $i++) {
        $address = get_user_meta($user_id, "malet_{$type}_address_{$i}", true);
        if (!empty($address)) {
            $address['slot'] = $i;
            return $address;
        }
    }

    return null;
}

/**
 * Obtenir una adreça específica
 */
function malet_get_user_address($user_id, $type, $slot)
{
    if ($slot < 1 || $slot > 5) {
        return null;
    }

    if (!in_array($type, ['billing', 'shipping'])) {
        return null;
    }

    $address = get_user_meta($user_id, "malet_{$type}_address_{$slot}", true);
    if (!empty($address)) {
        $address['slot'] = $slot;
        return $address;
    }

    return null;
}

/**
 * Endpoints API per gestionar múltiples adreces
 */

/**
 * Registrar endpoints per adreces múltiples
 */
function malet_register_addresses_endpoints()
{
    // GET /wp-json/malet-torrent/v1/addresses - Obtenir totes les adreces de l'usuari
    register_rest_route('malet-torrent/v1', '/addresses', array(
        'methods' => 'GET',
        'callback' => 'malet_get_user_addresses_endpoint',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'type' => array(
                'description' => 'Tipus d\'adreça (billing, shipping, both)',
                'type' => 'string',
                'enum' => array('billing', 'shipping', 'both'),
                'default' => 'both'
            )
        )
    ));

    // POST /wp-json/malet-torrent/v1/addresses - Crear nova adreça
    register_rest_route('malet-torrent/v1', '/addresses', array(
        'methods' => 'POST',
        'callback' => 'malet_create_user_address_endpoint',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'type' => array(
                'required' => true,
                'type' => 'string',
                'enum' => array('billing', 'shipping', 'both')
            ),
            'slot' => array(
                'required' => false,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5
            ),
            'address' => array(
                'required' => true,
                'type' => 'object'
            )
        )
    ));

    // PUT /wp-json/malet-torrent/v1/addresses/{type}/{slot} - Actualitzar adreça
    register_rest_route('malet-torrent/v1', '/addresses/(?P<type>billing|shipping)/(?P<slot>[1-5])', array(
        'methods' => 'PUT',
        'callback' => 'malet_update_user_address_endpoint',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'type' => array(
                'required' => true,
                'type' => 'string'
            ),
            'slot' => array(
                'required' => true,
                'type' => 'integer'
            ),
            'address' => array(
                'required' => true,
                'type' => 'object'
            ),
            'new_type' => array(
                'description' => 'Nou tipus d\'adreça per canviar-la',
                'type' => 'string',
                'enum' => array('billing', 'shipping', 'both')
            )
        )
    ));

    // DELETE /wp-json/malet-torrent/v1/addresses/{type}/{slot} - Eliminar adreça
    register_rest_route('malet-torrent/v1', '/addresses/(?P<type>billing|shipping)/(?P<slot>[1-5])', array(
        'methods' => 'DELETE',
        'callback' => 'malet_delete_user_address_endpoint',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'type' => array(
                'required' => true,
                'type' => 'string'
            ),
            'slot' => array(
                'required' => true,
                'type' => 'integer'
            )
        )
    ));

    // GET /wp-json/malet-torrent/v1/addresses/{type}/default - Obtenir adreça per defecte
    register_rest_route('malet-torrent/v1', '/addresses/(?P<type>billing|shipping)/default', array(
        'methods' => 'GET',
        'callback' => 'malet_get_default_address_endpoint',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'type' => array(
                'required' => true,
                'type' => 'string'
            )
        )
    ));
}
add_action('rest_api_init', 'malet_register_addresses_endpoints');

/**
 * Endpoint: Obtenir totes les adreces de l'usuari
 */
function malet_get_user_addresses_endpoint($request)
{
    $user_id = get_current_user_id();
    $type = $request->get_param('type') ?: 'both';

    $addresses = malet_get_user_addresses($user_id, $type);

    return rest_ensure_response(array(
        'success' => true,
        'data' => $addresses
    ));
}

/**
 * Endpoint: Crear nova adreça (suporta type='both')
 */
function malet_create_user_address_endpoint($request)
{
    $user_id = get_current_user_id();
    $type = $request->get_param('type');
    $address_data = $request->get_param('address');
    $slot = $request->get_param('slot');

    // Si type='both', no validar slot únic ja que es crearan dues adreces
    if ($type !== 'both') {
        // Si no s'especifica slot, trobar el primer slot lliure
        if (!$slot) {
            for ($i = 1; $i <= 5; $i++) {
                $existing = get_user_meta($user_id, "malet_{$type}_address_{$i}", true);
                if (empty($existing)) {
                    $slot = $i;
                    break;
                }
            }

            if (!$slot) {
                return new WP_Error('no_slots', 'No hi ha slots disponibles (màxim 5 adreces)', array('status' => 400));
            }
        }

        // Verificar que el slot no estigui ocupat
        $existing = get_user_meta($user_id, "malet_{$type}_address_{$slot}", true);
        if (!empty($existing)) {
            return new WP_Error('slot_occupied', 'Aquest slot ja està ocupat', array('status' => 409));
        }
    } else {
        // Per type='both', usar slot preferit o 1 per defecte
        if (!$slot) {
            $slot = 1;
        }
    }

    $result = malet_save_user_address($user_id, $address_data, $type, $slot);

    if (is_wp_error($result)) {
        return $result;
    }

    // Si type='both', result conté ambdues adreces
    if ($type === 'both') {
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Adreces creades per facturació i enviament',
            'data' => $result
        ));
    } else {
        return rest_ensure_response(array(
            'success' => true,
            'data' => $result,
            'slot' => $slot
        ));
    }
}

/**
 * Endpoint: Actualitzar adreça existent (permet canviar tipus)
 */
function malet_update_user_address_endpoint($request)
{
    $user_id = get_current_user_id();
    $current_type = $request->get_param('type');
    $slot = (int) $request->get_param('slot');
    $address_data = $request->get_param('address');
    $new_type = $request->get_param('new_type') ?: $current_type; // Nou paràmetre per canviar tipus

    // Verificar que l'adreça existeix
    $existing = get_user_meta($user_id, "malet_{$current_type}_address_{$slot}", true);
    if (empty($existing)) {
        return new WP_Error('address_not_found', 'Adreça no trobada', array('status' => 404));
    }

    // Conservar dades d'audit
    $address_data['created_at'] = $existing['created_at'] ?? current_time('mysql');

    // Si es canvia el tipus
    if ($new_type !== $current_type) {
        if ($new_type === 'both') {
            // Convertir a ambdós tipus
            // 1. Eliminar l'adreça actual
            malet_delete_user_address($user_id, $current_type, $slot);

            // 2. Crear dues adreces noves
            $result = malet_save_user_address($user_id, $address_data, 'both', $slot);

            if (is_wp_error($result)) {
                return $result;
            }

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Adreça convertida a facturació i enviament',
                'data' => $result
            ));
        } elseif (in_array($new_type, ['billing', 'shipping'])) {
            // Canviar a un tipus específic
            // 1. Trobar slot lliure en el nou tipus
            $new_slot = malet_find_free_slot($user_id, $new_type, $slot);
            if (!$new_slot) {
                return new WP_Error('no_slots', "No hi ha slots disponibles per {$new_type}", array('status' => 400));
            }

            // 2. Guardar en el nou tipus
            $result = malet_save_single_address($user_id, $address_data, $new_type, $new_slot);
            if (is_wp_error($result)) {
                return $result;
            }

            // 3. Eliminar l'adreça del tipus anterior
            malet_delete_user_address($user_id, $current_type, $slot);

            return rest_ensure_response(array(
                'success' => true,
                'message' => "Adreça canviada de {$current_type} a {$new_type}",
                'data' => $result,
                'new_slot' => $new_slot
            ));
        } else {
            return new WP_Error('invalid_new_type', 'Nou tipus d\'adreça no vàlid', array('status' => 400));
        }
    } else {
        // Actualitzar en el mateix tipus
        $result = malet_save_single_address($user_id, $address_data, $current_type, $slot);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(array(
            'success' => true,
            'data' => $result
        ));
    }
}

/**
 * Endpoint: Eliminar adreça
 */
function malet_delete_user_address_endpoint($request)
{
    $user_id = get_current_user_id();
    $type = $request->get_param('type');
    $slot = (int) $request->get_param('slot');

    // Verificar que l'adreça existeix
    $existing = get_user_meta($user_id, "malet_{$type}_address_{$slot}", true);
    if (empty($existing)) {
        return new WP_Error('address_not_found', 'Adreça no trobada', array('status' => 404));
    }

    $result = malet_delete_user_address($user_id, $type, $slot);

    if ($result === false) {
        return new WP_Error('delete_failed', 'Error eliminant l\'adreça', array('status' => 500));
    }

    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Adreça eliminada correctament'
    ));
}

/**
 * Endpoint: Obtenir adreça per defecte
 */
function malet_get_default_address_endpoint($request)
{
    $user_id = get_current_user_id();
    $type = $request->get_param('type');

    $address = malet_get_default_user_address($user_id, $type);

    if (!$address) {
        return new WP_Error('no_default_address', 'No hi ha adreça per defecte', array('status' => 404));
    }

    return rest_ensure_response(array(
        'success' => true,
        'data' => $address
    ));
}

/**
 * Modificar checkout per suportar múltiples adreces
 */

/**
 * Modificar validació del checkout per acceptar address_slot
 */
function malet_validate_checkout_with_address_slots($data)
{
    $errors = array();
    $user_id = get_current_user_id();

    // Si s'especifica address_slot per billing
    if (!empty($data['billing_address_slot'])) {
        $slot = (int) $data['billing_address_slot'];
        $saved_address = malet_get_user_address($user_id, 'billing', $slot);

        if (!$saved_address) {
            $errors['billing_address_slot'] = 'Adreça de facturació seleccionada no vàlida';
        } else {
            // Usar l'adreça guardada en lloc de la enviada
            $data['billing_address'] = $saved_address;
        }
    }

    // Si s'especifica address_slot per shipping
    if (!empty($data['shipping_address_slot'])) {
        $slot = (int) $data['shipping_address_slot'];
        $saved_address = malet_get_user_address($user_id, 'shipping', $slot);

        if (!$saved_address) {
            $errors['shipping_address_slot'] = 'Adreça d\'enviament seleccionada no vàlida';
        } else {
            // Usar l'adreça guardada en lloc de la enviada
            $data['shipping_address'] = $saved_address;
        }
    }

    return array('data' => $data, 'errors' => $errors);
}

/**
 * Endpoint millorat per checkout amb suport per múltiples adreces
 */
function malet_checkout_with_address_support($request)
{
    try {
        $data = $request->get_json_params();

        // Validació bàsica
        $validation = malet_validate_checkout_data($data);
        if (!empty($validation['errors'])) {
            return new WP_Error('validation_failed', 'Dades de checkout no vàlides', array(
                'status' => 400,
                'errors' => $validation['errors']
            ));
        }

        // Processar address slots si s'especifiquen
        $address_validation = malet_validate_checkout_with_address_slots($data);
        if (!empty($address_validation['errors'])) {
            return new WP_Error('address_validation_failed', 'Adreces seleccionades no vàlides', array(
                'status' => 400,
                'errors' => $address_validation['errors']
            ));
        }

        $data = $address_validation['data'];

        // Processar checkout normalment amb les adreces resoltes
        return malet_process_checkout($data);
    } catch (Exception $e) {
        return new WP_Error('checkout_error', $e->getMessage(), array('status' => 500));
    }
}

/**
 * Registrar endpoint millorat de checkout
 */
function malet_register_improved_checkout_endpoint()
{
    register_rest_route('malet-torrent/v1', '/checkout-v2', array(
        'methods' => 'POST',
        'callback' => 'malet_checkout_with_address_support',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'billing_address_slot' => array(
                'description' => 'Slot d\'adreça de facturació (1-5)',
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5
            ),
            'shipping_address_slot' => array(
                'description' => 'Slot d\'adreça d\'enviament (1-5)',
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5
            ),
            'billing_address' => array(
                'description' => 'Adreça de facturació (requerida si no s\'usa billing_address_slot)',
                'type' => 'object'
            ),
            'shipping_address' => array(
                'description' => 'Adreça d\'enviament (opcional)',
                'type' => 'object'
            ),
            'save_billing_address' => array(
                'description' => 'Guardar adreça de facturació per ús futur',
                'type' => 'boolean',
                'default' => false
            ),
            'save_shipping_address' => array(
                'description' => 'Guardar adreça d\'enviament per ús futur',
                'type' => 'boolean',
                'default' => false
            )
        )
    ));
}
add_action('rest_api_init', 'malet_register_improved_checkout_endpoint');

