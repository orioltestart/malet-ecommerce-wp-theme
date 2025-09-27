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

/**
 * Configuració inicial del tema
 */
function malet_torrent_setup() {
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
function malet_torrent_load_textdomain() {
    // Carregar traduccions del tema
    load_theme_textdomain('malet-torrent', MALETNEXT_THEME_DIR . '/languages');
    
    // Forçar càrrega de traduccions WooCommerce si està actiu
    if (class_exists('WooCommerce')) {
        load_plugin_textdomain('woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}

/**
 * Registrar endpoint simple per debug
 */
function malet_debug_endpoint() {
    register_rest_route('malet-torrent/v1', '/debug', array(
        'methods' => 'GET',
        'callback' => function() { return array('debug' => 'working'); },
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'malet_debug_endpoint');

/**
 * Configuració específica per headless
 */
function malet_torrent_headless_setup() {
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

// Hooks per control d'indexació SEO
add_action('init', 'malet_torrent_control_search_indexing', 1);
add_action('template_redirect', 'malet_torrent_add_robots_header', 1);
add_action('admin_bar_menu', 'malet_torrent_add_environment_indicator', 999);
add_filter('option_blog_public', 'malet_torrent_override_indexing_settings', 10, 2);
add_action('admin_notices', 'malet_torrent_indexing_admin_notice');

// Incloure sistema d'instal·lació de plugins
require_once get_template_directory() . '/inc/class-plugin-installer.php';

require_once get_template_directory() . '/inc/admin-notices.php';

// Incloure sistema JWT Auth
require_once get_template_directory() . '/inc/class-jwt-auth.php';

// Incloure API de formularis
require_once get_template_directory() . '/inc/forms-api.php';

// Incloure gestió d'usuaris
require_once get_template_directory() . '/inc/user-management.php';

// Incloure plantilles d'email personalitzades
require_once get_template_directory() . '/inc/email-templates.php';
require_once get_template_directory() . '/inc/woocommerce-email-templates.php';

// Inicialitzar sistema de plugins
add_action('after_setup_theme', 'malet_torrent_init_plugin_system');
add_action('after_switch_theme', 'malet_torrent_reset_plugin_notices');

/**
 * Inicialitzar sistema de plugins
 */
function malet_torrent_init_plugin_system() {
    if (is_admin()) {
        new Malet_Torrent_Admin_Notices();
    }
}

/**
 * Reset avisos de plugins quan s'activa el tema
 */
function malet_torrent_reset_plugin_notices() {
    $admin_notices = new Malet_Torrent_Admin_Notices();
    $admin_notices->reset_dismissed_notices();
    $admin_notices->reset_dismissed_updates();
}

/**
 * Configuració CORS millorada per la API REST
 * Basat en mu-plugins/cors.php amb millores de seguretat
 */
function malet_torrent_add_cors_support() {
    // Verificar que no s'han enviat headers ja
    if (headers_sent()) {
        return;
    }
    
    // Orígens permesos
    $allowed_origins = array(
        'http://localhost:3000',
        'http://localhost:8080',
        'https://malet.testart.cat',
        'https://wp.malet.testart.cat'
    );
    
    // Variables d'entorn adicionals
    if (defined('NEXT_PUBLIC_SITE_URL') && NEXT_PUBLIC_SITE_URL) {
        $allowed_origins[] = NEXT_PUBLIC_SITE_URL;
    }
    
    // Obtenir l'origen de la petició
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    // Verificar si l'origen està permès
    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    
    // Headers CORS
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce, X-HTTP-Method-Override, Cart-Token, Nonce');
    
    // Headers exposats per WooCommerce
    header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Cart-Token, X-WC-Store-API-Nonce');
    
    // Gestionar peticions preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Max-Age: 86400'); // Cache 24h
        exit();
    }
    
    // Log de debugging
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        error_log("CORS Request: Origin: $origin, Method: $method, URI: $uri");
    }
}

/**
 * CORS per peticions wp-json
 */
function malet_torrent_wp_json_cors() {
    if (strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
        malet_torrent_add_cors_support();
        
        // Headers específics per WooCommerce Store API
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/wc/store/') !== false) {
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce, Cart-Token, Nonce');
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Cart-Token, X-WC-Store-API-Nonce');
        }
        
        // Headers per WooCommerce REST API v3
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/wc/v3/') !== false) {
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, X-WC-Store-API-Nonce');
            
            // Headers específics per customers endpoint
            if (strpos($_SERVER['REQUEST_URI'], '/wp-json/wc/v3/customers') !== false) {
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce');
            }
        }
        
        // Headers per endpoints personalitzats
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/malet-torrent/') !== false) {
            // Headers adicionals específics per Malet Torrent
        }
        
        // Headers per endpoints JWT Auth
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/maletnext/v1/auth/') !== false) {
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Expose-Headers: Authorization');
        }
    }
}

/**
 * Control d'indexació SEO basat en entorn
 * Basat en mu-plugins/seo-indexing.php
 */
function malet_torrent_control_search_indexing() {
    // Detectar entorn
    $is_production = defined('WP_ENV') && WP_ENV === 'production';
    $is_development = defined('WP_DEBUG') && WP_DEBUG;
    
    // Verificar domini
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false;
    $is_staging = strpos($domain, 'staging') !== false || strpos($domain, 'dev') !== false;
    
    // Desactivar indexació per entorns no-producció
    if ($is_development || $is_local || $is_staging || !$is_production) {
        // Desactivar indexació WordPress
        update_option('blog_public', 0);
        
        // Afegir meta tag noindex
        add_action('wp_head', 'malet_torrent_add_noindex_meta');
        
        // Bloquejar robots.txt
        add_action('do_robots', 'malet_torrent_block_robots');
        
        // Desactivar sitemaps XML
        remove_action('init', 'wp_sitemaps_get_server');
        add_filter('wpseo_enable_xml_sitemap', '__return_false');
        add_filter('rank_math/sitemap/enable', '__return_false');
        
        // Log de depuració
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Malet Torrent: Indexació DESACTIVADA per entorn: ' . $domain);
        }
    } else {
        // Entorn de producció - permetre indexació
        update_option('blog_public', 1);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Malet Torrent: Indexació ACTIVADA per producció: ' . $domain);
        }
    }
}

/**
 * Afegir meta tag noindex
 */
function malet_torrent_add_noindex_meta() {
    echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
    echo '<!-- Malet Torrent: Desenvolupament/Staging - Motors de cerca bloquejats -->' . "\n";
}

/**
 * Bloquejar robots.txt per no-producció
 */
function malet_torrent_block_robots() {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    echo "\n";
    echo "# Malet Torrent Entorn Desenvolupament/Staging\n";
    echo "# Indexació de motors de cerca desactivada\n";
}

/**
 * Afegir header X-Robots-Tag
 */
function malet_torrent_add_robots_header() {
    // Verificar que no s'han enviat headers ja
    if (headers_sent()) {
        return;
    }
    
    $is_production = defined('WP_ENV') && WP_ENV === 'production';
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = strpos($domain, 'localhost') !== false;
    $is_staging = strpos($domain, 'staging') !== false || strpos($domain, 'dev') !== false;
    
    if (!$is_production || $is_local || $is_staging) {
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
    }
}

/**
 * Indicador d'entorn a la barra d'admin
 */
function malet_torrent_add_environment_indicator($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = strpos($domain, 'localhost') !== false;
    $is_staging = strpos($domain, 'staging') !== false || strpos($domain, 'dev') !== false;
    $is_production = defined('WP_ENV') && WP_ENV === 'production';
    
    if ($is_local) {
        $env_text = 'LOCAL';
        $env_color = '#ff6b6b';
    } elseif ($is_staging) {
        $env_text = 'STAGING';
        $env_color = '#ffa726';
    } elseif (!$is_production) {
        $env_text = 'DEV';
        $env_color = '#66bb6a';
    } else {
        return; // No mostrar indicador en producció
    }
    
    $wp_admin_bar->add_node([
        'id'    => 'malet-torrent-environment',
        'title' => '<span style="background-color: ' . $env_color . '; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">' . $env_text . '</span>',
        'meta'  => [
            'title' => 'Malet Torrent Entorn: ' . $env_text . ' (Indexació Desactivada)',
        ],
    ]);
}

/**
 * =============================================================================
 * FUNCIONS PER CREAR COMANDES DESDE EL CHECKOUT
 * =============================================================================
 */

/**
 * Crear comanda des del checkout del frontend
 */
function malet_create_order_from_checkout($request) {
    $data = $request->get_json_params();

    // Log per debug
    malet_log_checkout_event('checkout_start', array('data_received' => !empty($data)));

    // Validar dades bàsiques
    $validation = malet_validate_checkout_order_data($data);
    if (!$validation['valid']) {
        malet_log_checkout_event('validation_failed', $validation['errors']);
        return new WP_REST_Response(array(
            'success' => false,
            'error_code' => 'validation_failed',
            'message' => 'Dades del checkout no vàlides',
            'errors' => $validation['errors']
        ), 400);
    }

    try {
        // Assegurar que WooCommerce està inicialitzat
        if (!malet_ensure_woocommerce_ready()) {
            throw new Exception('WooCommerce no està disponible');
        }

        // 1. Crear o obtenir customer
        $customer_id = malet_get_or_create_customer($data['customer']);

        // 2. Crear la comanda
        $order = malet_create_woocommerce_order($data, $customer_id);

        if (!$order || is_wp_error($order)) {
            throw new Exception('No s\'ha pogut crear la comanda');
        }

        // 3. Configurar adreces
        malet_set_order_addresses($order, $data);

        // 4. Afegir productes
        malet_add_order_line_items($order, $data['line_items']);

        // 5. Configurar enviament
        malet_set_order_shipping($order, $data['shipping']);

        // 6. Aplicar cupons si n'hi ha
        if (!empty($data['coupons'])) {
            malet_apply_order_coupons($order, $data['coupons']);
        }

        // 7. Configurar mètode de pagament
        malet_set_order_payment_method($order, $data['payment']);

        // 8. Afegir metadata
        if (!empty($data['meta_data'])) {
            malet_add_order_metadata($order, $data['meta_data']);
        }

        // 9. Recalcular totals i finalitzar comanda
        $order->calculate_totals();
        $order->save();

        // 10. Finalitzar comanda (convertir de placeholder a comanda real)
        malet_finalize_order($order);

        // 11. Enviar email de confirmació
        malet_send_order_confirmation_email($order);

        // 12. Generar resposta
        $response = malet_generate_order_response($order, $data);

        // Log d'èxit
        malet_log_checkout_event('order_created', array(
            'order_id' => $order->get_id(),
            'total' => $order->get_total()
        ));

        return new WP_REST_Response($response, 201);

    } catch (Exception $e) {
        malet_log_checkout_event('order_creation_failed', array(
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ));

        return new WP_REST_Response(array(
            'success' => false,
            'error_code' => 'order_creation_failed',
            'message' => 'Error creant la comanda: ' . $e->getMessage()
        ), 500);
    }
}

/**
 * Validar dades del checkout abans de crear la comanda
 */
function malet_validate_checkout_order_data($data) {
    $errors = array();

    // Validar customer
    if (empty($data['customer']['email']) || !is_email($data['customer']['email'])) {
        $errors['customer.email'] = 'Email obligatori i vàlid';
    }

    if (empty($data['customer']['first_name'])) {
        $errors['customer.first_name'] = 'Nom obligatori';
    }

    if (empty($data['customer']['last_name'])) {
        $errors['customer.last_name'] = 'Cognoms obligatoris';
    }

    // Validar billing address
    $required_billing_fields = ['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country'];
    foreach ($required_billing_fields as $field) {
        if (empty($data['billing_address'][$field])) {
            $errors["billing_address.{$field}"] = "Camp obligatori: {$field}";
        }
    }

    // Validar codi postal espanyol
    if (!empty($data['billing_address']['postcode']) &&
        $data['billing_address']['country'] === 'ES' &&
        !preg_match('/^[0-9]{5}$/', $data['billing_address']['postcode'])) {
        $errors['billing_address.postcode'] = 'Format de codi postal no vàlid';
    }

    // Validar line items
    if (empty($data['line_items']) || !is_array($data['line_items'])) {
        $errors['line_items'] = 'Almenys un producte és obligatori';
    } else {
        foreach ($data['line_items'] as $index => $item) {
            if (empty($item['product_id']) || !is_numeric($item['product_id'])) {
                $errors["line_items.{$index}.product_id"] = 'ID de producte obligatori';
            }
            if (empty($item['quantity']) || $item['quantity'] < 1) {
                $errors["line_items.{$index}.quantity"] = 'Quantitat mínima 1';
            }
            if (!isset($item['price']) || $item['price'] < 0) {
                $errors["line_items.{$index}.price"] = 'Preu vàlid obligatori';
            }
        }
    }

    // Validar shipping
    if (empty($data['shipping']['method_id'])) {
        $errors['shipping.method_id'] = 'Mètode d\'enviament obligatori';
    }

    // Validar payment
    if (empty($data['payment']['method'])) {
        $errors['payment.method'] = 'Mètode de pagament obligatori';
    }

    return array(
        'valid' => empty($errors),
        'errors' => $errors
    );
}

/**
 * Assegurar que WooCommerce està preparat
 */
function malet_ensure_woocommerce_ready() {
    if (!class_exists('WooCommerce')) {
        return false;
    }

    // Inicialitzar WooCommerce si no està fet
    if (!did_action('woocommerce_loaded')) {
        WC();
    }

    // Assegurar que tenim una sessió
    if (!WC()->session) {
        WC()->initialize_session();
    }

    return true;
}

/**
 * Crear o obtenir customer
 */
function malet_get_or_create_customer($customer_data) {
    $email = sanitize_email($customer_data['email']);

    // Buscar usuari existent
    $existing_user = get_user_by('email', $email);
    if ($existing_user) {
        return $existing_user->ID;
    }

    // Crear nou usuari/customer SENSE contrasenya
    $username = malet_generate_unique_username($email);

    // Crear usuari amb dades bàsiques
    $user_data = array(
        'user_login' => $username,
        'user_email' => $email,
        'first_name' => sanitize_text_field($customer_data['first_name']),
        'last_name' => sanitize_text_field($customer_data['last_name']),
        'display_name' => sanitize_text_field($customer_data['first_name'] . ' ' . $customer_data['last_name']),
        'role' => 'customer'
        // NO password - l'usuari haurà d'establir-la més tard
    );

    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        throw new Exception('No s\'ha pogut crear l\'usuari: ' . $user_id->get_error_message());
    }

    // Actualitzar phone si està disponible
    if (!empty($customer_data['phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($customer_data['phone']));
    }

    // Marcar que és un usuari creat via checkout
    update_user_meta($user_id, '_created_via_checkout', current_time('mysql'));
    update_user_meta($user_id, '_password_nag', true); // Forçar canvi de contrasenya

    return $user_id;
}

/**
 * Generar username únic
 */
function malet_generate_unique_username($email) {
    $base_username = sanitize_user(substr($email, 0, strpos($email, '@')));
    $username = $base_username;
    $counter = 1;

    while (username_exists($username)) {
        $username = $base_username . $counter;
        $counter++;
    }

    return $username;
}

/**
 * Crear comanda WooCommerce
 */
function malet_create_woocommerce_order($data, $customer_id) {
    // Crear comanda buit
    $order = wc_create_order(array(
        'customer_id' => $customer_id
    ));

    if (is_wp_error($order)) {
        throw new Exception('Error creant la comanda base: ' . $order->get_error_message());
    }

    // Assegurar que és del tipus correcte
    $order->set_status('pending');

    return $order;
}

/**
 * Configurar adreces de la comanda
 */
function malet_set_order_addresses($order, $data) {
    // Adreça de facturació
    $billing = $data['billing_address'];
    $order->set_billing_first_name(sanitize_text_field($billing['first_name']));
    $order->set_billing_last_name(sanitize_text_field($billing['last_name']));
    $order->set_billing_company(sanitize_text_field($billing['company'] ?? ''));
    $order->set_billing_address_1(sanitize_text_field($billing['address_1']));
    $order->set_billing_address_2(sanitize_text_field($billing['address_2'] ?? ''));
    $order->set_billing_city(sanitize_text_field($billing['city']));
    $order->set_billing_state(sanitize_text_field($billing['state'] ?? ''));
    $order->set_billing_postcode(sanitize_text_field($billing['postcode']));
    $order->set_billing_country(sanitize_text_field($billing['country']));
    $order->set_billing_email(sanitize_email($billing['email']));
    $order->set_billing_phone(sanitize_text_field($billing['phone'] ?? ''));

    // Adreça d'enviament
    $use_billing = !empty($data['same_billing_address']) && $data['same_billing_address'] === true;

    if ($use_billing || empty($data['shipping_address'])) {
        // Usar adreça de facturació per enviament
        $order->set_shipping_first_name($order->get_billing_first_name());
        $order->set_shipping_last_name($order->get_billing_last_name());
        $order->set_shipping_company($order->get_billing_company());
        $order->set_shipping_address_1($order->get_billing_address_1());
        $order->set_shipping_address_2($order->get_billing_address_2());
        $order->set_shipping_city($order->get_billing_city());
        $order->set_shipping_state($order->get_billing_state());
        $order->set_shipping_postcode($order->get_billing_postcode());
        $order->set_shipping_country($order->get_billing_country());
    } else {
        // Usar adreça d'enviament diferent
        $shipping = $data['shipping_address'];
        $order->set_shipping_first_name(sanitize_text_field($shipping['first_name']));
        $order->set_shipping_last_name(sanitize_text_field($shipping['last_name']));
        $order->set_shipping_company(sanitize_text_field($shipping['company'] ?? ''));
        $order->set_shipping_address_1(sanitize_text_field($shipping['address_1']));
        $order->set_shipping_address_2(sanitize_text_field($shipping['address_2'] ?? ''));
        $order->set_shipping_city(sanitize_text_field($shipping['city']));
        $order->set_shipping_state(sanitize_text_field($shipping['state'] ?? ''));
        $order->set_shipping_postcode(sanitize_text_field($shipping['postcode']));
        $order->set_shipping_country(sanitize_text_field($shipping['country']));
    }
}

/**
 * Afegir productes a la comanda
 */
function malet_add_order_line_items($order, $line_items) {
    foreach ($line_items as $item) {
        $product_id = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        $variation_id = !empty($item['variation_id']) ? intval($item['variation_id']) : 0;

        // Obtenir el producte
        $product = wc_get_product($variation_id ?: $product_id);

        if (!$product) {
            throw new Exception("Producte amb ID {$product_id} no trobat");
        }

        // Verificar stock
        if (!$product->is_in_stock()) {
            throw new Exception("Producte {$product->get_name()} fora de stock");
        }

        if ($product->managing_stock() && $product->get_stock_quantity() < $quantity) {
            throw new Exception("Stock insuficient per {$product->get_name()}");
        }

        // Afegir item a la comanda
        $order_item_id = $order->add_product($product, $quantity, array(
            'variation' => $variation_id ? wc_get_product_variation_attributes($variation_id) : array(),
            'totals' => array(
                'subtotal' => $item['price'] * $quantity,
                'total' => $item['price'] * $quantity,
            )
        ));

        if (!$order_item_id) {
            throw new Exception("No s'ha pogut afegir el producte {$product->get_name()} a la comanda");
        }
    }
}

/**
 * Configurar enviament de la comanda
 */
function malet_set_order_shipping($order, $shipping_data) {
    $method_id = sanitize_text_field($shipping_data['method_id']);
    $method_title = sanitize_text_field($shipping_data['method_title'] ?? $method_id);
    $cost = floatval($shipping_data['cost'] ?? 0);
    $instance_id = !empty($shipping_data['instance_id']) ? intval($shipping_data['instance_id']) : 0;

    // Crear shipping item
    $shipping_item = new WC_Order_Item_Shipping();
    $shipping_item->set_method_title($method_title);
    $shipping_item->set_method_id($method_id);
    $shipping_item->set_instance_id($instance_id);
    $shipping_item->set_total($cost);

    // Afegir metadata adicional si està disponible
    if (!empty($shipping_data['meta_data'])) {
        foreach ($shipping_data['meta_data'] as $key => $value) {
            $shipping_item->add_meta_data($key, $value, true);
        }
    }

    $order->add_item($shipping_item);
}

/**
 * Aplicar cupons a la comanda
 */
function malet_apply_order_coupons($order, $coupons_data) {
    foreach ($coupons_data as $coupon_data) {
        $coupon_code = sanitize_text_field($coupon_data['code']);
        $coupon = new WC_Coupon($coupon_code);

        if (!$coupon->get_id()) {
            throw new Exception("Cupó {$coupon_code} no vàlid");
        }

        if (!$coupon->is_valid()) {
            throw new Exception("Cupó {$coupon_code} caducat o no aplicable");
        }

        // Aplicar cupó
        $discount_amount = floatval($coupon_data['discount_amount'] ?? 0);

        $coupon_item = new WC_Order_Item_Coupon();
        $coupon_item->set_name($coupon_code);
        $coupon_item->set_code($coupon_code);
        $coupon_item->set_discount($discount_amount);

        $order->add_item($coupon_item);
    }
}

/**
 * Configurar mètode de pagament
 */
function malet_set_order_payment_method($order, $payment_data) {
    $method = sanitize_text_field($payment_data['method']);
    $method_title = sanitize_text_field($payment_data['method_title'] ?? $method);

    $order->set_payment_method($method);
    $order->set_payment_method_title($method_title);

    // Afegir metadata adicional del pagament
    if (!empty($payment_data['meta_data'])) {
        foreach ($payment_data['meta_data'] as $key => $value) {
            $order->add_meta_data('_payment_' . $key, sanitize_text_field($value), true);
        }
    }
}

/**
 * Afegir metadata a la comanda
 */
function malet_add_order_metadata($order, $meta_data) {
    // Metadata estàndard
    if (!empty($meta_data['locale'])) {
        $order->add_meta_data('_checkout_locale', sanitize_text_field($meta_data['locale']), true);
    }

    if (!empty($meta_data['source'])) {
        $order->add_meta_data('_order_source', sanitize_text_field($meta_data['source']), true);
    }

    if (!empty($meta_data['user_agent'])) {
        $order->add_meta_data('_customer_user_agent', sanitize_text_field($meta_data['user_agent']), true);
    }

    if (!empty($meta_data['ip_address'])) {
        $order->add_meta_data('_customer_ip_address', sanitize_text_field($meta_data['ip_address']), true);
    }

    // Timestamps
    $order->add_meta_data('_checkout_completed_at', current_time('mysql'), true);
    $order->add_meta_data('_order_version', MALETNEXT_VERSION, true);
}

/**
 * Generar resposta per la comanda creada
 */
function malet_generate_order_response($order, $original_data) {
    $order_id = $order->get_id();
    $order_number = $order->get_order_number();

    $response = array(
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'status' => $order->get_status(),
        'total' => floatval($order->get_total()),
        'currency' => $order->get_currency(),
        'date_created' => $order->get_date_created()->date('c'),
        'customer_id' => $order->get_customer_id(),
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'message' => 'Comanda creada correctament',
        'urls' => array(
            'order_received' => wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order->get_order_key(),
            'order_pay' => $order->get_checkout_payment_url(),
            'my_account' => wc_get_page_permalink('myaccount')
        )
    );

    // URL de pagament específica segons el mètode
    $payment_method = $order->get_payment_method();

    switch ($payment_method) {
        case 'redsys':
            // Per Redsys generarem la URL de pagament més tard
            $response['payment_url'] = $order->get_checkout_payment_url();
            $response['redirect_url'] = '/checkout/success?order=' . $order_id;
            break;

        case 'cod': // Cash on delivery
            $response['payment_url'] = null;
            $response['redirect_url'] = '/checkout/success?order=' . $order_id;
            break;

        case 'bacs': // Bank transfer
            $response['payment_url'] = null;
            $response['redirect_url'] = '/checkout/success?order=' . $order_id;
            $response['bank_details'] = malet_get_bank_transfer_details();
            break;

        default:
            $response['payment_url'] = $order->get_checkout_payment_url();
            $response['redirect_url'] = '/checkout/success?order=' . $order_id;
            break;
    }

    // Informació adicional per al frontend
    $response['line_items'] = array();
    foreach ($order->get_items() as $item) {
        $response['line_items'][] = array(
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'total' => floatval($item->get_total())
        );
    }

    // Informació d'enviament
    $shipping_methods = $order->get_shipping_methods();
    if (!empty($shipping_methods)) {
        $shipping_method = reset($shipping_methods);
        $response['shipping'] = array(
            'method_title' => $shipping_method->get_method_title(),
            'total' => floatval($shipping_method->get_total())
        );
    }

    return $response;
}

/**
 * Finalitzar comanda (convertir de placeholder a comanda real)
 */
function malet_finalize_order($order) {
    // Assegurar que la comanda té el post_type correcte
    wp_update_post(array(
        'ID' => $order->get_id(),
        'post_type' => 'shop_order',
        'post_status' => 'wc-pending'
    ));

    // Actualitzar status de la comanda
    $order->set_status('pending', 'Comanda creada via checkout personalitzat.');
    $order->save();

    // Trigger hooks de WooCommerce
    do_action('woocommerce_new_order', $order->get_id(), $order);
    do_action('woocommerce_checkout_order_processed', $order->get_id(), array(), $order);
}

/**
 * Enviar email de confirmació de comanda
 */
function malet_send_order_confirmation_email($order) {
    try {
        // Dades de la comanda
        $order_id = $order->get_id();
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        // Subject de l'email
        $subject = sprintf('[Malet Torrent] Confirmació de la teva comanda #%s', $order->get_order_number());

        // Contingut de l'email
        $message = malet_get_order_confirmation_email_content($order);

        // Headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Malet Torrent <pastisseria@malet.testart.cat>',
            'Reply-To: Malet Torrent <pastisseria@malet.testart.cat>'
        );

        // Enviar email
        $sent = wp_mail($customer_email, $subject, $message, $headers);

        if ($sent) {
            $order->add_order_note('Email de confirmació enviat a: ' . $customer_email);
        } else {
            $order->add_order_note('Error enviant email de confirmació a: ' . $customer_email);
        }

        // Log per debug
        malet_log_checkout_event('email_sent', array(
            'order_id' => $order_id,
            'customer_email' => $customer_email,
            'sent' => $sent
        ));

    } catch (Exception $e) {
        $order->add_order_note('Error enviant email de confirmació: ' . $e->getMessage());

        malet_log_checkout_event('email_error', array(
            'order_id' => $order->get_id(),
            'error' => $e->getMessage()
        ));
    }
}

/**
 * Generar contingut HTML per l'email de confirmació
 */
function malet_get_order_confirmation_email_content($order) {
    $order_id = $order->get_id();
    $order_number = $order->get_order_number();
    $customer_name = $order->get_billing_first_name();
    $order_total = $order->get_formatted_order_total();
    $order_date = $order->get_date_created()->format('d/m/Y H:i');

    // URL per veure la comanda
    $order_url = wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'));

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Confirmació de comanda</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <header style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #8B4513;">🥨 Malet Torrent</h1>
                <h2 style="color: #666;">Confirmació de la teva comanda</h2>
            </header>

            <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>Hola ' . esc_html($customer_name) . ',</h3>
                <p>Gràcies per la teva comanda! Hem rebut correctament la teva sol·licitud i estem preparant els teus melindros artesans.</p>
            </div>

            <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>Detalls de la comanda</h3>
                <p><strong>Número de comanda:</strong> #' . esc_html($order_number) . '</p>
                <p><strong>Data:</strong> ' . esc_html($order_date) . '</p>
                <p><strong>Total:</strong> ' . $order_total . '</p>
                <p><strong>Estat:</strong> Pendent de processament</p>
            </div>

            <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>Productes</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Producte</th>
                            <th style="text-align: center; padding: 10px; border-bottom: 1px solid #ddd;">Quantitat</th>
                            <th style="text-align: right; padding: 10px; border-bottom: 1px solid #ddd;">Preu</th>
                        </tr>
                    </thead>
                    <tbody>';

    // Afegir productes
    foreach ($order->get_items() as $item) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $total = wc_price($item->get_total());

        $html .= '
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html($product_name) . '</td>
                            <td style="text-align: center; padding: 10px; border-bottom: 1px solid #eee;">' . $quantity . '</td>
                            <td style="text-align: right; padding: 10px; border-bottom: 1px solid #eee;">' . $total . '</td>
                        </tr>';
    }

    $html .= '
                    </tbody>
                </table>
            </div>';

    // Informació d'enviament
    $shipping_methods = $order->get_shipping_methods();
    if (!empty($shipping_methods)) {
        $shipping_method = reset($shipping_methods);
        $html .= '
            <div style="background: #e8f4fd; border: 1px solid #bee5eb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>📦 Informació d\'enviament</h3>
                <p><strong>Mètode:</strong> ' . esc_html($shipping_method->get_method_title()) . '</p>';

        if ($shipping_method->get_method_id() === 'malet_recollida_botiga') {
            $html .= '
                <p><strong>📍 Adreça de recollida:</strong><br>
                Carrer Major, 123<br>
                46900 Torrent, València<br>
                Tel: 96 123 45 67</p>
                <p><strong>⏰ Horari:</strong><br>
                Dimarts a Dissabte: 9:00 - 13:00 i 17:00 - 20:00<br>
                Diumenge: 9:00 - 14:00</p>
                <p><em>La teva comanda estarà llesta per recollir en 24 hores.</em></p>';
        } else {
            $shipping_address = $order->get_formatted_shipping_address();
            if ($shipping_address) {
                $html .= '<p><strong>📍 Adreça d\'enviament:</strong><br>' . $shipping_address . '</p>';
            }
        }

        $html .= '</div>';
    }

    $html .= '
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>ℹ️ Informació important</h3>
                <ul>
                    <li>Rebràs un altre email quan la comanda estigui preparada</li>
                    <li>Si tens qualsevol dubte, contacta\'ns a pastisseria@malet.testart.cat</li>
                    <li>Pots consultar l\'estat de la teva comanda al teu compte</li>
                </ul>
            </div>

            <footer style="text-align: center; margin-top: 30px; padding: 20px; border-top: 1px solid #ddd; color: #666;">
                <p><strong>Malet Torrent - Pastisseria Artesana</strong></p>
                <p>Carrer Major, 123 - 46900 Torrent, València</p>
                <p>Tel: 96 123 45 67 | Email: pastisseria@malet.testart.cat</p>
                <p style="font-size: 0.9em; color: #999;">
                    Aquest email s\'ha generat automàticament, si us plau no responguis directament.
                </p>
            </footer>
        </div>
    </body>
    </html>';

    return $html;
}

/**
 * Obtenir detalls de transferència bancària
 */
function malet_get_bank_transfer_details() {
    return array(
        'account_name' => get_option('woocommerce_bacs_account_name', 'Malet Torrent'),
        'account_number' => get_option('woocommerce_bacs_account_number', ''),
        'sort_code' => get_option('woocommerce_bacs_sort_code', ''),
        'iban' => get_option('woocommerce_bacs_iban', ''),
        'bic' => get_option('woocommerce_bacs_bic', ''),
        'instructions' => get_option('woocommerce_bacs_instructions', '')
    );
}

/**
 * Sobreescriure configuració d'indexació per no-producció
 */
function malet_torrent_override_indexing_settings($value, $option) {
    if ($option === 'blog_public') {
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $is_local = strpos($domain, 'localhost') !== false;
        $is_staging = strpos($domain, 'staging') !== false || strpos($domain, 'dev') !== false;
        $is_production = defined('WP_ENV') && WP_ENV === 'production';
        
        if (!$is_production || $is_local || $is_staging) {
            return 0; // Forçar desactivar indexació
        }
    }
    
    return $value;
}

/**
 * Avís d'admin sobre estat d'indexació
 */
function malet_torrent_indexing_admin_notice() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'options-reading') {
        return;
    }
    
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = strpos($domain, 'localhost') !== false;
    $is_production = defined('WP_ENV') && WP_ENV === 'production';
    
    if (!$is_production || $is_local) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>Malet Torrent:</strong> La indexació de motors de cerca està deshabilitada automàticament en aquest entorn de desenvolupament/staging.';
        echo '</p></div>';
    }
}

/**
 * Millorar l'API de WooCommerce per headless
 */
function malet_torrent_enhance_woocommerce_api() {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Afegir camps adicionals als productes
    register_rest_field('product', 'acf_fields', array(
        'get_callback' => 'malet_torrent_get_acf_fields',
        'update_callback' => null,
        'schema' => null,
    ));
    
    // Afegir informació de stock
    register_rest_field('product', 'stock_info', array(
        'get_callback' => 'malet_torrent_get_stock_info',
        'update_callback' => null,
        'schema' => null,
    ));
    
    // Afegir categories amb informació adicional
    register_rest_field('product', 'categories_detailed', array(
        'get_callback' => 'malet_torrent_get_detailed_categories',
        'update_callback' => null,
        'schema' => null,
    ));
}

/**
 * Obtenir camps ACF per la API
 */
function malet_torrent_get_acf_fields($object, $field_name, $request) {
    if (function_exists('get_fields')) {
        return get_fields($object['id']);
    }
    return null;
}

/**
 * Obtenir informació detallada de stock
 */
function malet_torrent_get_stock_info($object, $field_name, $request) {
    $product = wc_get_product($object['id']);
    if (!$product) {
        return null;
    }
    
    return array(
        'stock_quantity' => $product->get_stock_quantity(),
        'stock_status' => $product->get_stock_status(),
        'backorders' => $product->get_backorders(),
        'low_stock_amount' => $product->get_low_stock_amount(),
        'sold_individually' => $product->get_sold_individually(),
    );
}

/**
 * Obtenir categories amb informació detallada
 */
function malet_torrent_get_detailed_categories($object, $field_name, $request) {
    $categories = get_the_terms($object['id'], 'product_cat');
    if (!$categories || is_wp_error($categories)) {
        return array();
    }
    
    $detailed_categories = array();
    foreach ($categories as $category) {
        $detailed_categories[] = array(
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => malet_torrent_get_category_image($category->term_id),
            'count' => $category->count,
        );
    }
    
    return $detailed_categories;
}

/**
 * Obtenir imatge de categoria
 */
function malet_torrent_get_category_image($term_id) {
    $image_id = get_term_meta($term_id, 'thumbnail_id', true);
    if ($image_id) {
        return wp_get_attachment_image_url($image_id, 'full');
    }
    return null;
}

/**
 * Registrar endpoints personalitzats de la API
 */
function malet_torrent_register_custom_endpoints() {
    // Log per debug - temporary
    error_log('MALET DEBUG: Registrant endpoints personalitzats');
    // Endpoint per obtenir configuració del lloc
    register_rest_route('malet-torrent/v1', '/config', array(
        'methods' => 'GET',
        'callback' => 'malet_torrent_get_site_config',
        'permission_callback' => '__return_true',
    ));
    
    // Endpoint per obtenir menús
    register_rest_route('malet-torrent/v1', '/menus/(?P<location>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'malet_torrent_get_menu',
        'permission_callback' => '__return_true',
    ));
    
    // Endpoint per productes destacats
    register_rest_route('malet-torrent/v1', '/products/featured', array(
        'methods' => 'GET',
        'callback' => 'malet_torrent_get_featured_products',
        'permission_callback' => '__return_true',
    ));
    
    // Endpoint per configuració de WooCommerce
    register_rest_route('malet-torrent/v1', '/woocommerce/config', array(
        'methods' => 'GET',
        'callback' => 'malet_torrent_get_woocommerce_config',
        'permission_callback' => '__return_true',
    ));
    
    // Endpoint de test simple
    register_rest_route('malet-torrent/v1', '/test', array(
        'methods' => 'GET',
        'callback' => function() { return array('status' => 'working', 'time' => current_time('mysql')); },
        'permission_callback' => '__return_true',
    ));
    
    // Endpoint per categories de productes WooCommerce
    register_rest_route('malet-torrent/v1', '/products/categories', array(
        'methods' => 'GET',
        'callback' => 'malet_torrent_get_product_categories',
        'permission_callback' => '__return_true',
        'args' => array(
            'hide_empty' => array(
                'description' => 'Ocultar categories sense productes',
                'type' => 'boolean',
                'default' => false,
            ),
            'orderby' => array(
                'description' => 'Ordenar per: name, slug, count, term_id',
                'type' => 'string',
                'default' => 'name',
                'enum' => array('name', 'slug', 'count', 'term_id'),
            ),
            'order' => array(
                'description' => 'Ordre: asc o desc',
                'type' => 'string',
                'default' => 'asc',
                'enum' => array('asc', 'desc'),
            ),
        ),
    ));
    
    // Endpoint per customers (proxy per facilitar frontend)
    register_rest_route('malet-torrent/v1', '/customers', array(
        'methods' => 'GET',
        'callback' => 'malet_torrent_get_customers',
        'permission_callback' => '__return_true',
    ));
    
    // Endpoint per orders (proxy per facilitar frontend)
    register_rest_route('malet-torrent/v1', '/orders', array(
        'methods' => 'GET',
        'callback' => 'malet_torrent_get_orders',
        'permission_callback' => '__return_true',
    ));

    // ==========================================================================
    // CHECKOUT ENDPOINTS
    // ==========================================================================

    // Endpoint per validar dades de checkout
    register_rest_route('malet/v1', '/checkout/validate', array(
        'methods' => 'POST',
        'callback' => 'malet_validate_checkout_data',
        'permission_callback' => '__return_true',
        'args' => array(
            'email' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_email($param);
                }
            ),
            'billing_address' => array(
                'required' => true,
                'type' => 'object'
            )
        )
    ));

    // Endpoint per calcular enviament en temps real
    register_rest_route('malet/v1', '/shipping/calculate', array(
        'methods' => 'POST',
        'callback' => 'malet_calculate_shipping_realtime',
        'permission_callback' => '__return_true'
    ));

    // Endpoint per aplicar cupons
    register_rest_route('malet/v1', '/coupon/apply', array(
        'methods' => 'POST',
        'callback' => 'malet_apply_coupon_code',
        'permission_callback' => '__return_true'
    ));

    // Endpoint per obtenir mètodes de pagament
    register_rest_route('malet/v1', '/payment-methods', array(
        'methods' => 'GET',
        'callback' => 'malet_get_available_payment_methods',
        'permission_callback' => '__return_true'
    ));

    // Endpoint per obtenir zones i mètodes d'enviament
    register_rest_route('malet/v1', '/shipping/zones', array(
        'methods' => 'GET',
        'callback' => 'malet_get_shipping_zones',
        'permission_callback' => '__return_true',
        'args' => array(
            'country' => array(
                'description' => 'Codi de país (ES, FR, etc)',
                'type' => 'string',
                'default' => 'ES'
            ),
            'state' => array(
                'description' => 'Codi d\'estat/província',
                'type' => 'string',
                'default' => ''
            ),
            'postcode' => array(
                'description' => 'Codi postal',
                'type' => 'string',
                'default' => ''
            )
        )
    ));

    // ENDPOINT PRINCIPAL PER CREAR COMANDES
    register_rest_route('malet/v1', '/checkout/create-order', array(
        'methods' => 'POST',
        'callback' => 'malet_create_order_from_checkout',
        'permission_callback' => '__return_true',
        'args' => array(
            'customer' => array(
                'required' => true,
                'type' => 'object',
                'properties' => array(
                    'email' => array('type' => 'string', 'format' => 'email'),
                    'first_name' => array('type' => 'string'),
                    'last_name' => array('type' => 'string'),
                    'phone' => array('type' => 'string')
                )
            ),
            'billing_address' => array(
                'required' => true,
                'type' => 'object'
            ),
            'line_items' => array(
                'required' => true,
                'type' => 'array',
                'minItems' => 1
            ),
            'shipping' => array(
                'required' => true,
                'type' => 'object'
            ),
            'payment' => array(
                'required' => true,
                'type' => 'object'
            )
        )
    ));
}

/**
 * Obtenir configuració del lloc
 */
function malet_torrent_get_site_config($request) {
    return array(
        'name' => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'url' => get_bloginfo('url'),
        'admin_email' => get_bloginfo('admin_email'),
        'language' => get_bloginfo('language'),
        'timezone' => get_option('timezone_string'),
        'date_format' => get_option('date_format'),
        'time_format' => get_option('time_format'),
        'start_of_week' => get_option('start_of_week'),
        'currency' => (class_exists('WooCommerce') && function_exists('get_woocommerce_currency') && did_action('woocommerce_init')) ? get_woocommerce_currency() : 'EUR',
        'logo' => malet_torrent_get_custom_logo(),
        'theme_version' => MALETNEXT_VERSION,
    );
}

/**
 * Obtenir logo personalitzat
 */
function malet_torrent_get_custom_logo() {
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        return wp_get_attachment_image_url($custom_logo_id, 'full');
    }
    return null;
}

/**
 * Obtenir menú per ubicació
 */
function malet_torrent_get_menu($request) {
    $location = $request['location'];
    $locations = get_nav_menu_locations();
    
    if (!isset($locations[$location])) {
        return new WP_Error('no_menu', 'No menu found for this location', array('status' => 404));
    }
    
    $menu = wp_get_nav_menu_object($locations[$location]);
    $menu_items = wp_get_nav_menu_items($menu->term_id);
    
    $items = array();
    foreach ($menu_items as $item) {
        $items[] = array(
            'id' => $item->ID,
            'title' => $item->title,
            'url' => $item->url,
            'target' => $item->target,
            'parent' => $item->menu_item_parent,
            'order' => $item->menu_order,
            'classes' => $item->classes,
            'description' => $item->description,
        );
    }
    
    return array(
        'menu' => array(
            'id' => $menu->term_id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'description' => $menu->description,
        ),
        'items' => $items,
    );
}

/**
 * Obtenir productes destacats
 */
function malet_torrent_get_featured_products($request) {
    if (!class_exists('WooCommerce') || !did_action('woocommerce_init')) {
        return new WP_Error('woocommerce_not_ready', 'WooCommerce is not ready', array('status' => 500));
    }
    
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $request->get_param('per_page') ?: 12,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_featured',
                'value' => 'yes',
                'compare' => '='
            )
        )
    );
    
    $featured_products = get_posts($args);
    $products = array();
    
    foreach ($featured_products as $product_post) {
        $product = wc_get_product($product_post->ID);
        if ($product) {
            $products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'slug' => $product->get_slug(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'on_sale' => $product->is_on_sale(),
                'stock_status' => $product->get_stock_status(),
                'featured' => $product->is_featured(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail'),
                'gallery' => array_map(function($id) {
                    return wp_get_attachment_image_url($id, 'woocommerce_thumbnail');
                }, $product->get_gallery_image_ids()),
                'short_description' => $product->get_short_description(),
                'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
            );
        }
    }
    
    return $products;
}

/**
 * Obtenir configuració de WooCommerce
 */
function malet_torrent_get_woocommerce_config($request) {
    if (!class_exists('WooCommerce') || !did_action('woocommerce_init')) {
        return new WP_Error('woocommerce_not_ready', 'WooCommerce is not ready', array('status' => 500));
    }
    
    return array(
        'currency' => get_woocommerce_currency(),
        'currency_symbol' => get_woocommerce_currency_symbol(),
        'currency_position' => get_option('woocommerce_currency_pos'),
        'thousand_separator' => wc_get_price_thousand_separator(),
        'decimal_separator' => wc_get_price_decimal_separator(),
        'decimals' => wc_get_price_decimals(),
        'shop_url' => wc_get_page_permalink('shop'),
        'cart_url' => wc_get_cart_url(),
        'checkout_url' => wc_get_checkout_url(),
        'myaccount_url' => wc_get_page_permalink('myaccount'),
        'terms_url' => wc_get_page_permalink('terms'),
        'shipping_enabled' => wc_shipping_enabled(),
        'taxes_enabled' => wc_tax_enabled(),
        'coupons_enabled' => wc_coupons_enabled(),
        'guest_checkout' => get_option('woocommerce_enable_guest_checkout') === 'yes',
        'reviews_enabled' => get_option('woocommerce_enable_reviews') === 'yes',
    );
}

/**
 * Obtenir categories de productes WooCommerce
 */
function malet_torrent_get_product_categories($request) {
    if (!class_exists('WooCommerce') || !did_action('woocommerce_init')) {
        return new WP_Error('woocommerce_not_ready', 'WooCommerce is not ready', array('status' => 500));
    }
    
    // Obtenir paràmetres de la request
    $hide_empty = $request->get_param('hide_empty');
    $orderby = $request->get_param('orderby');
    $order = $request->get_param('order');
    
    // Arguments per obtenir les categories
    $args = array(
        'taxonomy' => 'product_cat',
        'orderby' => $orderby,
        'order' => $order,
        'hide_empty' => $hide_empty,
    );
    
    // Obtenir totes les categories
    $categories = get_terms($args);
    
    if (is_wp_error($categories)) {
        return new WP_Error('categories_error', 'Error retrieving categories: ' . $categories->get_error_message(), array('status' => 500));
    }
    
    $formatted_categories = array();
    
    foreach ($categories as $category) {
        // Obtenir informació detallada de la categoria
        $image_id = get_term_meta($category->term_id, 'thumbnail_id', true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : null;
        
        // Obtenir categories fills
        $children = get_term_children($category->term_id, 'product_cat');
        $children_formatted = array();
        
        if (!is_wp_error($children) && !empty($children)) {
            foreach ($children as $child_id) {
                $child_term = get_term($child_id, 'product_cat');
                if (!is_wp_error($child_term)) {
                    $child_image_id = get_term_meta($child_term->term_id, 'thumbnail_id', true);
                    $children_formatted[] = array(
                        'id' => $child_term->term_id,
                        'name' => $child_term->name,
                        'slug' => $child_term->slug,
                        'description' => $child_term->description,
                        'count' => (int) $child_term->count,
                        'parent' => $child_term->parent,
                        'image' => $child_image_id ? wp_get_attachment_image_url($child_image_id, 'full') : null,
                        'link' => get_term_link($child_term->term_id, 'product_cat'),
                    );
                }
            }
        }
        
        $formatted_categories[] = array(
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'count' => (int) $category->count,
            'parent' => $category->parent,
            'image' => $image_url,
            'link' => get_term_link($category->term_id, 'product_cat'),
            'children' => $children_formatted,
            'meta' => array(
                'display_type' => get_term_meta($category->term_id, 'display_type', true),
                'order' => get_term_meta($category->term_id, 'order', true),
            ),
        );
    }
    
    // Estadístiques adicionals
    $response = array(
        'categories' => $formatted_categories,
        'total' => count($formatted_categories),
        'parameters' => array(
            'hide_empty' => $hide_empty,
            'orderby' => $orderby,
            'order' => $order,
        ),
        'generated_at' => current_time('mysql'),
    );
    
    return rest_ensure_response($response);
}

/**
 * Obtenir customers (proxy per facilitar frontend)
 */
function malet_torrent_get_customers($request) {
    if (!class_exists('WooCommerce') || !did_action('woocommerce_init')) {
        return new WP_Error('woocommerce_not_ready', 'WooCommerce is not ready', array('status' => 500));
    }
    
    $per_page = $request->get_param('per_page') ?: 10;
    $page = $request->get_param('page') ?: 1;
    
    $args = array(
        'role' => 'customer',
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'orderby' => 'registered',
        'order' => 'DESC'
    );
    
    $user_query = new WP_User_Query($args);
    $customers = array();
    
    foreach ($user_query->get_results() as $user) {
        $customer = new WC_Customer($user->ID);
        $customers[] = array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'username' => $user->user_login,
            'date_created' => $user->user_registered,
            'orders_count' => wc_get_customer_order_count($user->ID),
            'total_spent' => wc_get_customer_total_spent($user->ID),
            'avatar_url' => get_avatar_url($user->ID),
        );
    }
    
    return array(
        'customers' => $customers,
        'total' => $user_query->get_total(),
        'page' => $page,
        'per_page' => $per_page,
        'pages' => ceil($user_query->get_total() / $per_page)
    );
}

/**
 * Obtenir orders (proxy per facilitar frontend)
 */
function malet_torrent_get_orders($request) {
    if (!class_exists('WooCommerce') || !did_action('woocommerce_init')) {
        return new WP_Error('woocommerce_not_ready', 'WooCommerce is not ready', array('status' => 500));
    }
    
    $per_page = $request->get_param('per_page') ?: 10;
    $page = $request->get_param('page') ?: 1;
    $status = $request->get_param('status') ?: 'any';
    $customer_id = $request->get_param('customer_id');
    
    $args = array(
        'limit' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    if ($status !== 'any') {
        $args['status'] = $status;
    }
    
    if ($customer_id) {
        $args['customer_id'] = $customer_id;
    }
    
    $orders_query = wc_get_orders($args);
    $orders = array();
    
    foreach ($orders_query as $order) {
        $customer = $order->get_user();
        $line_items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $line_items[] = array(
                'product_id' => $item->get_product_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : '',
            );
        }
        
        $orders[] = array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'customer_id' => $order->get_customer_id(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'customer_note' => $order->get_customer_note(),
            'line_items' => $line_items,
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'address_1' => $order->get_billing_address_1(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
            ),
        );
    }
    
    // Contar total de orders
    $total_args = $args;
    unset($total_args['limit']);
    unset($total_args['offset']);
    $total_orders = count(wc_get_orders($total_args));
    
    return array(
        'orders' => $orders,
        'total' => $total_orders,
        'page' => $page,
        'per_page' => $per_page,
        'pages' => ceil($total_orders / $per_page)
    );
}

/**
 * =============================================================================
 * FUNCIONS DELS ENDPOINTS DE CHECKOUT
 * =============================================================================
 */

/**
 * Validar dades de checkout abans del submit final
 */
function malet_validate_checkout_data($request) {
    $data = $request->get_json_params();
    $errors = [];

    // Validar email
    if (!is_email($data['email'])) {
        $errors[] = 'Email no és vàlid';
    }

    // Validar direcció de facturació
    $required_fields = ['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country'];
    foreach ($required_fields as $field) {
        if (empty($data['billing_address'][$field])) {
            $errors[] = "Camp obligatori: " . $field;
        }
    }

    // Validar codi postal espanyol
    if (!empty($data['billing_address']['postcode']) &&
        $data['billing_address']['country'] === 'ES' &&
        !preg_match('/^[0-9]{5}$/', $data['billing_address']['postcode'])) {
        $errors[] = 'Codi postal no és vàlid';
    }

    // Validar stock dels productes
    if (!empty($data['line_items'])) {
        foreach ($data['line_items'] as $item) {
            $product = wc_get_product($item['product_id']);
            if (!$product || !$product->is_in_stock()) {
                $errors[] = "Producte {$product->get_name()} no disponible";
            }

            if ($product->managing_stock() && $product->get_stock_quantity() < $item['quantity']) {
                $errors[] = "Stock insuficient per {$product->get_name()}";
            }
        }
    }

    if (empty($errors)) {
        return new WP_REST_Response([
            'valid' => true,
            'message' => 'Dades vàlides'
        ], 200);
    } else {
        return new WP_REST_Response([
            'valid' => false,
            'errors' => $errors
        ], 400);
    }
}

/**
 * Calcular cost d'enviament en temps real
 */
function malet_calculate_shipping_realtime($request) {
    $data = $request->get_json_params();

    // Assegurar que WooCommerce està carregat
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'WooCommerce no està disponible'
        ], 500);
    }

    // Inicialitzar WooCommerce si no està fet
    if (!did_action('woocommerce_loaded')) {
        WC();
    }

    // Assegurar que tenim una sessió
    if (!WC()->session) {
        WC()->initialize_session();
    }

    // Inicialitzar cart i customer si no existeixen
    if (!WC()->cart) {
        WC()->initialize_cart();
    }

    if (!WC()->customer) {
        WC()->initialize_customer();
    }

    // Verificar que el cart està disponible ara
    if (!WC()->cart) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No es pot inicialitzar el carret de WooCommerce'
        ], 500);
    }

    // Buidar carret existent
    WC()->cart->empty_cart();

    // Afegir productes al carret
    if (!empty($data['line_items'])) {
        foreach ($data['line_items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                continue;
            }

            $product_id = intval($item['product_id']);
            $quantity = intval($item['quantity']);

            if ($product_id > 0 && $quantity > 0) {
                WC()->cart->add_to_cart($product_id, $quantity);
            }
        }
    }

    // Establir direcció d'enviament
    if (!empty($data['shipping_address'])) {
        $address = $data['shipping_address'];

        if (isset($address['address_1'])) {
            WC()->customer->set_shipping_address_1($address['address_1']);
        }
        if (isset($address['city'])) {
            WC()->customer->set_shipping_city($address['city']);
        }
        if (isset($address['postcode'])) {
            WC()->customer->set_shipping_postcode($address['postcode']);
        }
        if (isset($address['country'])) {
            WC()->customer->set_shipping_country($address['country']);
        }
        if (isset($address['state'])) {
            WC()->customer->set_shipping_state($address['state']);
        }
    }

    // Forçar recalculació d'enviament
    WC()->shipping()->reset_shipping();
    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();

    $shipping_packages = WC()->shipping()->get_packages();
    $shipping_methods = [];

    foreach ($shipping_packages as $package) {
        if (!empty($package['rates'])) {
            foreach ($package['rates'] as $rate) {
                $shipping_methods[] = [
                    'id' => $rate->get_id(),
                    'label' => $rate->get_label(),
                    'cost' => floatval($rate->get_cost()),
                    'formatted_cost' => wc_price($rate->get_cost()),
                    'tax' => floatval($rate->get_shipping_tax()),
                    'delivery_time' => $rate->get_meta_data()['delivery_time'] ?? null
                ];
            }
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'shipping_methods' => $shipping_methods,
        'cart_total' => floatval(WC()->cart->get_total('edit'))
    ], 200);
}

/**
 * Aplicar cupó de descompte
 */
function malet_apply_coupon_code($request) {
    $data = $request->get_json_params();
    $coupon_code = sanitize_text_field($data['coupon_code']);

    if (empty($coupon_code)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Codi de cupó requerit'
        ], 400);
    }

    $coupon = new WC_Coupon($coupon_code);

    if (!$coupon->get_id() || !$coupon->is_valid()) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Cupó no vàlid o caducat'
        ], 400);
    }

    // Aplicar cupó al carret (simulat)
    $discount_amount = 0;
    $cart_total = floatval($data['cart_subtotal'] ?? 0);

    if ($coupon->get_discount_type() === 'percent') {
        $discount_amount = ($cart_total * $coupon->get_amount()) / 100;
    } elseif ($coupon->get_discount_type() === 'fixed_cart') {
        $discount_amount = floatval($coupon->get_amount());
    }

    return new WP_REST_Response([
        'success' => true,
        'coupon' => [
            'code' => $coupon_code,
            'type' => $coupon->get_discount_type(),
            'amount' => $coupon->get_amount(),
            'discount_amount' => $discount_amount,
            'formatted_discount' => wc_price($discount_amount),
            'description' => $coupon->get_description(),
            'minimum_amount' => $coupon->get_minimum_amount(),
            'expiry_date' => $coupon->get_date_expires() ? $coupon->get_date_expires()->date('Y-m-d') : null
        ]
    ], 200);
}

/**
 * Obtenir mètodes de pagament disponibles
 */
function malet_get_available_payment_methods() {
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'WooCommerce no està disponible'
        ], 500);
    }

    // Inicialitzar WooCommerce si no està fet
    if (!did_action('woocommerce_loaded')) {
        WC();
    }

    // Assegurar que payment_gateways està disponible
    if (!WC()->payment_gateways) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Payment gateways no disponibles'
        ], 500);
    }

    try {
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $methods = [];

        foreach ($payment_gateways as $gateway) {
            if ($gateway->enabled === 'yes') {
                $methods[] = [
                    'id' => $gateway->id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->get_description(),
                    'icon' => $gateway->get_icon(),
                    'supports' => array_keys($gateway->supports ?? []),
                    'needs_setup' => !$gateway->is_available()
                ];
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'payment_methods' => $methods
        ], 200);

    } catch (Exception $e) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Error obtenint mètodes de pagament: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Obtenir zones i mètodes d'enviament disponibles
 */
function malet_get_shipping_zones($request) {
    if (!class_exists('WooCommerce') || !did_action('woocommerce_init')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'WooCommerce no està disponible'
        ], 500);
    }

    $country = $request->get_param('country');
    $state = $request->get_param('state');
    $postcode = $request->get_param('postcode');

    // Obtenir totes les zones d'enviament
    $shipping_zones = WC_Shipping_Zones::get_zones();
    $zone_0 = WC_Shipping_Zones::get_zone(0); // Zona "Rest of the World"

    $formatted_zones = [];

    // Processar cada zona
    foreach ($shipping_zones as $zone_id => $zone_data) {
        $zone = WC_Shipping_Zones::get_zone($zone_id);

        // Comprovar si la zona aplica a la ubicació especificada
        $zone_locations = $zone->get_zone_locations();
        $applies = false;

        foreach ($zone_locations as $location) {
            if ($location->type === 'country' && $location->code === $country) {
                $applies = true;
                break;
            }
            if ($location->type === 'state' && $location->code === $country . ':' . $state) {
                $applies = true;
                break;
            }
            if ($location->type === 'postcode' && !empty($postcode)) {
                // Comprovar si el codi postal està en el rang
                $postcodes = explode('...', $location->code);
                if (count($postcodes) == 2) {
                    if ($postcode >= $postcodes[0] && $postcode <= $postcodes[1]) {
                        $applies = true;
                        break;
                    }
                } elseif ($location->code === $postcode) {
                    $applies = true;
                    break;
                }
            }
        }

        if ($applies || empty($zone_locations)) {
            $shipping_methods = $zone->get_shipping_methods(true);
            $methods = [];

            foreach ($shipping_methods as $method) {
                if ($method->enabled === 'yes') {
                    $methods[] = [
                        'id' => $method->id,
                        'instance_id' => $method->instance_id,
                        'title' => $method->title,
                        'method_id' => $method->method_id,
                        'method_title' => $method->method_title,
                        'cost' => $method->cost ?? null,
                        'min_amount' => $method->min_amount ?? null,
                        'requires' => $method->requires ?? null,
                        'settings' => [
                            'cost' => $method->get_option('cost'),
                            'min_amount' => $method->get_option('min_amount'),
                            'requires' => $method->get_option('requires')
                        ]
                    ];
                }
            }

            if (!empty($methods)) {
                $formatted_zones[] = [
                    'id' => $zone_id,
                    'name' => $zone->get_zone_name(),
                    'order' => $zone->get_zone_order(),
                    'locations' => array_map(function($loc) {
                        return [
                            'code' => $loc->code,
                            'type' => $loc->type
                        ];
                    }, $zone_locations),
                    'shipping_methods' => $methods
                ];
            }
        }
    }

    // Afegir zona "Rest of the World" si no hi ha cap zona específica
    if (empty($formatted_zones)) {
        $shipping_methods = $zone_0->get_shipping_methods(true);
        $methods = [];

        foreach ($shipping_methods as $method) {
            if ($method->enabled === 'yes') {
                $methods[] = [
                    'id' => $method->id,
                    'instance_id' => $method->instance_id,
                    'title' => $method->title,
                    'method_id' => $method->method_id,
                    'method_title' => $method->method_title,
                    'cost' => $method->cost ?? null
                ];
            }
        }

        if (!empty($methods)) {
            $formatted_zones[] = [
                'id' => 0,
                'name' => 'Rest of the World',
                'order' => 999,
                'locations' => [],
                'shipping_methods' => $methods
            ];
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'zones' => $formatted_zones,
        'request_location' => [
            'country' => $country,
            'state' => $state,
            'postcode' => $postcode
        ]
    ], 200);
}

/**
 * =============================================================================
 * GOOGLE ANALYTICS 4 ENHANCED ECOMMERCE
 * =============================================================================
 */

/**
 * Enviar event 'purchase' a GA4 quan es completa una comanda
 */
add_action('woocommerce_order_status_completed', 'malet_send_ga4_purchase_event', 10, 1);
add_action('woocommerce_payment_complete', 'malet_send_ga4_purchase_event', 10, 1);

function malet_send_ga4_purchase_event($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $measurement_id = get_option('ga4_measurement_id', 'G-XXXXXXXXXX');
    $api_secret = get_option('ga4_api_secret', 'xxxxxxxxx');

    // Client ID from order meta (saved during checkout)
    $client_id = $order->get_meta('_ga4_client_id') ?: malet_generate_uuid();

    $items = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $items[] = [
            'item_id' => $product->get_sku() ?: $product->get_id(),
            'item_name' => $product->get_name(),
            'category' => malet_get_product_primary_category($product->get_id()),
            'quantity' => $item->get_quantity(),
            'price' => floatval($product->get_price())
        ];
    }

    $event_data = [
        'client_id' => $client_id,
        'events' => [[
            'name' => 'purchase',
            'parameters' => [
                'transaction_id' => $order->get_order_number(),
                'value' => floatval($order->get_total()),
                'tax' => floatval($order->get_total_tax()),
                'shipping' => floatval($order->get_shipping_total()),
                'currency' => $order->get_currency(),
                'coupon' => implode(',', $order->get_coupon_codes()),
                'items' => $items
            ]
        ]]
    ];

    // Enviar a GA4 Measurement Protocol
    malet_send_ga4_event($measurement_id, $api_secret, $event_data);

    // Log per debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('GA4 Purchase Event sent for order: ' . $order_id);
    }
}

/**
 * Enviar event a GA4 Measurement Protocol
 */
function malet_send_ga4_event($measurement_id, $api_secret, $event_data) {
    $url = "https://www.google-analytics.com/mp/collect?measurement_id={$measurement_id}&api_secret={$api_secret}";

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($event_data),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        error_log('GA4 API Error: ' . $response->get_error_message());
    }

    return $response;
}

/**
 * Obtenir categoria principal d'un producte
 */
function malet_get_product_primary_category($product_id) {
    $categories = get_the_terms($product_id, 'product_cat');
    if ($categories && !is_wp_error($categories)) {
        return $categories[0]->name;
    }
    return 'Melindros';
}

/**
 * Generar UUID per client_id
 */
function malet_generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * =============================================================================
 * CUSTOMITZACIÓ REDSYS GATEWAY
 * =============================================================================
 */

/**
 * Personalitzar missatges de resposta Redsys
 */
add_filter('woocommerce_redsys_payment_complete_message', 'malet_custom_redsys_success_message', 10, 2);

function malet_custom_redsys_success_message($message, $order) {
    return sprintf(
        '✅ Pagament processat correctament. Núm. comanda: %s. Rebràs un email de confirmació en breus.',
        $order->get_order_number()
    );
}

/**
 * Afegir dades personalitzades a la comanda durant checkout
 */
add_action('woocommerce_checkout_create_order', 'malet_add_custom_checkout_data', 20, 2);

function malet_add_custom_checkout_data($order, $data) {
    // Guardar Client ID de GA4 si ve del frontend
    if (!empty($_POST['ga4_client_id'])) {
        $order->update_meta_data('_ga4_client_id', sanitize_text_field($_POST['ga4_client_id']));
    }

    // Guardar source de tràfic
    if (!empty($_POST['traffic_source'])) {
        $order->update_meta_data('_traffic_source', sanitize_text_field($_POST['traffic_source']));
    }

    // Timestamp del checkout per analytics
    $order->update_meta_data('_checkout_started_at', current_time('mysql'));

    $order->save();
}

/**
 * Hook després del pagament Redsys exitós
 */
add_action('woocommerce_redsys_payment_complete', 'malet_after_redsys_payment_complete', 10, 1);

function malet_after_redsys_payment_complete($order_id) {
    $order = wc_get_order($order_id);

    // Enviar email personalitzat de confirmació
    malet_send_custom_order_confirmation($order);

    // Registrar en log personalitzat
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Redsys payment completed for order: {$order_id}, amount: {$order->get_total()}");
    }

    // Trigger GA4 purchase event (ja es fa automàticament amb l'hook anterior)
}

/**
 * Email personalitzat de confirmació
 */
function malet_send_custom_order_confirmation($order) {
    $to = $order->get_billing_email();
    $subject = 'Confirmació de la teva comanda - Malet Torrent';

    $message = sprintf(
        'Hola %s,\n\nGràcies per la teva comanda #%s!\n\nImport total: %s\n\nL\'enviarem en 24-48 hores.\n\nSalutacions,\nEquip Malet Torrent',
        $order->get_billing_first_name(),
        $order->get_order_number(),
        $order->get_formatted_order_total()
    );

    wp_mail($to, $subject, $message);
}

/**
 * =============================================================================
 * OPTIMITZACIONS I SEGURETAT PER CHECKOUT
 * =============================================================================
 */

/**
 * Rate limiting per API endpoints de checkout
 */
add_action('rest_api_init', 'malet_setup_checkout_rate_limiting');

function malet_setup_checkout_rate_limiting() {
    add_filter('rest_pre_dispatch', 'malet_check_checkout_rate_limit', 10, 3);
}

function malet_check_checkout_rate_limit($result, $server, $request) {
    $route = $request->get_route();

    // Aplicar rate limiting només als endpoints de checkout
    if (strpos($route, '/malet/v1/') === 0) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'api_rate_limit_' . md5($ip . $route);

        $requests = get_transient($key) ?: 0;

        if ($requests >= 60) { // 60 requests per minut
            return new WP_Error(
                'rate_limit_exceeded',
                'Massa sol·licituds. Prova més tard.',
                ['status' => 429]
            );
        }

        set_transient($key, $requests + 1, 60);
    }

    return $result;
}

/**
 * Logging personalitzat per checkout
 */
function malet_log_checkout_event($event, $data = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        error_log('CHECKOUT_LOG: ' . json_encode($log_entry));
    }
}

/**
 * Afegir pàgina de configuració al admin
 */
function malet_torrent_admin_menu() {
    add_theme_page(
        'Configuració Malet Torrent',
        'Malet Torrent',
        'manage_options',
        'malet-torrent-settings',
        'malet_torrent_settings_page'
    );
}
add_action('admin_menu', 'malet_torrent_admin_menu');

/**
 * Pàgina de configuració del tema
 */
function malet_torrent_settings_page() {
    $installer = Malet_Torrent_Plugin_Installer::get_instance();
    $status = $installer->get_installation_status();
    $summary = Malet_Torrent_Admin_Notices::get_status_summary();
    ?>
    <div class="wrap malet-torrent-settings-page">
        <h1>🥨 Configuració Malet Torrent</h1>
        
        <div class="malet-torrent-admin-notice">
            <h3>Tema Headless Actiu</h3>
            <p>Aquest tema està optimitzat per funcionar com a backend API amb Next.js a <strong>malet.testart.cat</strong></p>
        </div>
        
        <div class="malet-torrent-grid">
            <div class="malet-torrent-card">
                <h2>📊 Estat de l'API</h2>
                <div id="api-status">
                    <?php malet_torrent_display_api_status(); ?>
                </div>
            </div>
            
            <div class="malet-torrent-card">
                <h2>🔌 Estat dels Plugins</h2>
                <div class="plugins-status">
                    <div class="plugin-category">
                        <h4>Plugins Requerits</h4>
                        <div class="progress-indicator">
                            <div class="progress-bar-small">
                                <div class="progress-fill" style="width: <?php echo $summary['required']['percentage']; ?>%"></div>
                            </div>
                            <span><?php echo $summary['required']['completed']; ?>/<?php echo $summary['required']['total']; ?> (<?php echo $summary['required']['percentage']; ?>%)</span>
                        </div>
                    </div>
                    
                    <div class="plugin-category">
                        <h4>Plugins Recomanats</h4>
                        <div class="progress-indicator">
                            <div class="progress-bar-small">
                                <div class="progress-fill" style="width: <?php echo $summary['recommended']['percentage']; ?>%"></div>
                            </div>
                            <span><?php echo $summary['recommended']['completed']; ?>/<?php echo $summary['recommended']['total']; ?> (<?php echo $summary['recommended']['percentage']; ?>%)</span>
                        </div>
                    </div>
                    
                    <div class="plugin-category">
                        <h4>Plugins Opcionals</h4>
                        <div class="progress-indicator">
                            <div class="progress-bar-small">
                                <div class="progress-fill" style="width: <?php echo $summary['optional']['percentage']; ?>%"></div>
                            </div>
                            <span><?php echo $summary['optional']['completed']; ?>/<?php echo $summary['optional']['total']; ?> (<?php echo $summary['optional']['percentage']; ?>%)</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="malet-torrent-card">
                <h2>🛍️ WooCommerce</h2>
                <?php if (class_exists('WooCommerce')): ?>
                    <p class="api-status-ok">✅ WooCommerce actiu</p>
                    <p><strong>Versió:</strong> <?php echo WC()->version; ?></p>
                    <p><strong>Moneda:</strong> <?php echo get_woocommerce_currency(); ?></p>
                    <p><strong>Productes:</strong> <?php echo wp_count_posts('product')->publish; ?></p>
                <?php else: ?>
                    <p class="api-status-error">❌ WooCommerce no està instal·lat</p>
                    <p>Instal·la WooCommerce per gestionar els melindros</p>
                <?php endif; ?>
            </div>
            
            <div class="malet-torrent-card">
                <h2>🔗 Enlaces Útils</h2>
                <ul>
                    <?php if (class_exists('WooCommerce')): ?>
                        <li><a href="<?php echo admin_url('edit.php?post_type=product'); ?>">Gestionar Productes</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=api'); ?>">Configurar API WooCommerce</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo admin_url('edit.php'); ?>">Gestionar Blog</a></li>
                    <?php if (function_exists('wpcf7')): ?>
                        <li><a href="<?php echo admin_url('admin.php?page=wpcf7'); ?>">Gestionar Formularis</a></li>
                    <?php endif; ?>
                    <li><a href="https://malet.testart.cat" target="_blank">Veure Web Principal</a></li>
                </ul>
            </div>
            
            <div class="malet-torrent-card">
                <h2>🔐 Seguretat i Rendiment</h2>
                <ul>
                    <li><?php echo (class_exists('wordfence')) ? '✅' : '❌'; ?> Wordfence Security</li>
                    <li><?php echo (class_exists('LimitLoginAttempts')) ? '✅' : '❌'; ?> Limit Login Attempts</li>
                    <li><?php echo (class_exists('UpdraftPlus')) ? '✅' : '❌'; ?> UpdraftPlus Backup</li>
                    <li><?php echo (class_exists('RedisObjectCache')) ? '✅' : '❌'; ?> Redis Object Cache</li>
                    <!-- Autoptimize eliminat - 7 setembre 2025 -->
                </ul>
            </div>
            
            <div class="malet-torrent-card">
                <h2>⚙️ Configuració del Sistema</h2>
                <ul>
                    <li>✅ Permalinks activats</li>
                    <li>✅ API REST habilitada</li>
                    <li>✅ CORS configurat</li>
                    <li>✅ Control SEO automàtic</li>
                    <li>✅ SSL recomanat per producció</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Mostrar estat de l'API
 */
function malet_torrent_display_api_status() {
    $rest_url = get_rest_url();
    echo '<p><strong>Endpoint API:</strong> <code>' . esc_url($rest_url) . '</code></p>';
    
    // Comprovar si l'API està accessible
    $response = wp_remote_get($rest_url);
    if (!is_wp_error($response)) {
        echo '<p class="api-status-ok">✅ API REST accessible</p>';
    } else {
        echo '<p class="api-status-error">❌ Error accessing API: ' . $response->get_error_message() . '</p>';
    }
    
    // URLs específiques de l'API
    echo '<h4>Endpoints Disponibles:</h4>';
    echo '<ul>';
    echo '<li><code>/wp-json/wp/v2/</code> - WordPress API</li>';
    echo '<li><code>/wp-json/wc/v3/</code> - WooCommerce API</li>';
    echo '<li><code>/wp-json/wc/store/v1/</code> - Store API</li>';
    echo '<li><code>/wp-json/malet-torrent/v1/</code> - API Personalitzada</li>';
    echo '<li><code>/wp-json/malet-torrent/v1/products/categories</code> - Categories WooCommerce</li>';
    echo '</ul>';
}

/**
 * Afegir estils de l'admin
 */
function malet_torrent_admin_styles() {
    wp_enqueue_style('malet-torrent-admin', MALETNEXT_THEME_URL . '/style.css', array(), MALETNEXT_VERSION);
}
add_action('admin_enqueue_scripts', 'malet_torrent_admin_styles');

/**
 * Configuració per defecte del lloc
 */
function malet_torrent_set_default_settings() {
    // Configurar permalinks
    if (get_option('permalink_structure') == '') {
        update_option('permalink_structure', '/%postname%/');
        flush_rewrite_rules();
    }
    
    // Configurar timezone
    if (get_option('timezone_string') == '') {
        update_option('timezone_string', 'Europe/Madrid');
    }
    
    // Configurar idioma
    if (get_option('WPLANG') == '') {
        update_option('WPLANG', 'ca');
    }
    
    // Directoris per plugins eliminats - 7 setembre 2025
}
add_action('after_switch_theme', 'malet_torrent_set_default_settings');

// Codi d'Autoptimize eliminat - 7 setembre 2025

/**
 * Missatge d'activació del tema
 */
function malet_torrent_activation_notice() {
    if (get_option('malet_torrent_activation_notice', true)) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Malet Torrent Tema Activat!</strong> El teu lloc ja està configurat per funcionar com a backend headless.</p>';
        echo '<p><a href="' . admin_url('themes.php?page=malet-torrent-settings') . '">Configurar Malet Torrent</a></p>';
        echo '</div>';
        update_option('malet_torrent_activation_notice', false);
    }
}
add_action('admin_notices', 'malet_torrent_activation_notice');

/**
 * Afegir enllaços d'acció al tema
 */
function malet_torrent_theme_action_links($actions, $theme) {
    if (get_template() === 'malet-torrent') {
        $actions[] = '<a href="' . admin_url('themes.php?page=malet-torrent-settings') . '">Configuració</a>';
    }
    return $actions;
}
add_filter('theme_action_links', 'malet_torrent_theme_action_links', 10, 2);

/**
 * Saltar només els wizards de WooCommerce, però mantenir l'accés a WC Admin
 */
add_filter('woocommerce_enable_setup_wizard', '__return_false');
add_filter('woocommerce_admin_onboarding_profile_completed', '__return_true');
add_filter('woocommerce_show_admin_notice', function($show, $notice) {
    $notices_to_hide = ['install_notice', 'update_notice', 'template_check', 'theme_support'];
    if (in_array($notice, $notices_to_hide)) {
        return false;
    }
    return $show;
}, 10, 2);

// Marcar com completat l'onboarding i forçar càrrega de traduccions
add_action('init', function() {
    if (class_exists('WooCommerce')) {
        // Forçar càrrega de traduccions WooCommerce primer
        if (!is_textdomain_loaded('woocommerce')) {
            load_plugin_textdomain('woocommerce');
        }
        
        update_option('woocommerce_onboarding_profile', array(
            'completed' => true,
            'skipped' => true
        ));
        update_option('woocommerce_task_list_complete', 'yes');
        update_option('woocommerce_extended_task_list_complete', 'yes');
    }
}, 1);

/**
 * Permisos personalitzats per customers a l'API WooCommerce
 */
// Permetre als customers accedir a les seves pròpies dades
add_filter('woocommerce_rest_check_permissions', 'malet_torrent_customer_api_permissions', 10, 4);

function malet_torrent_customer_api_permissions($permission, $context, $object_id, $post_type) {
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

function malet_torrent_filter_customer_orders($args, $request) {
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

function malet_torrent_filter_customer_data($args, $request) {
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

function malet_torrent_basic_auth_handler($user_id) {
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
add_filter('woocommerce_api_check_authentication', function($user, $consumer_key, $consumer_secret, $signature, $timestamp, $nonce) {
    return $user; // Deixar que WooCommerce gestioni l'autenticació
}, 10, 6);

// Forçar que WooCommerce accepti connexions HTTP (només desenvolupament)
add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $post_type) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return true; // Permetre accés en mode debug
    }
    return $permission;
}, 10, 4);

/**
 * Forçar HTTP per WooCommerce assets en desenvolupament local
 */
add_action('init', function() {
    if (defined('WP_DEBUG') && WP_DEBUG && 
        isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost:8080') {
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
function malet_torrent_force_http_assets($src) {
    if (defined('WP_DEBUG') && WP_DEBUG &&
        isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost:8080') {
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

function malet_torrent_shipping_methods_init() {
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

function malet_torrent_add_shipping_methods($methods) {
    $methods['malet_recollida_botiga'] = 'WC_Malet_Recollida_Botiga_Shipping';
    $methods['malet_enviament_local'] = 'WC_Malet_Enviament_Local_Shipping';
    $methods['malet_enviament_nacional'] = 'WC_Malet_Enviament_Nacional_Shipping';
    return $methods;
}

/**
 * Configurar zones d'enviament per defecte via WP-CLI
 */
add_action('init', 'malet_torrent_setup_default_shipping_zones');

function malet_torrent_setup_default_shipping_zones() {
    // Només executar una vegada i si WooCommerce està actiu
    if (get_option('malet_shipping_zones_setup') || !class_exists('WooCommerce')) {
        return;
    }

    // Configurar zones d'enviament per defecte
    malet_torrent_create_shipping_zones();

    // Marcar com configurat
    update_option('malet_shipping_zones_setup', true);
}

function malet_torrent_create_shipping_zones() {
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
function malet_add_custom_product_fields() {
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

    echo '</div>';
}

// Guardar els camps personalitzats
add_action('woocommerce_process_product_meta', 'malet_save_custom_product_fields');
function malet_save_custom_product_fields($post_id) {
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
}

// Mostrar camps personalitzats al frontend (single product)
add_action('woocommerce_single_product_summary', 'malet_display_custom_product_fields', 25);
function malet_display_custom_product_fields() {
    global $product;

    $weight_grams = get_post_meta($product->get_id(), '_malet_weight_grams', true);
    $ingredients = get_post_meta($product->get_id(), '_malet_ingredients', true);

    if ($weight_grams || $ingredients) {
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

        echo '</div>';
    }
}

// Afegir camps personalitzats a l'API REST de WooCommerce
add_action('rest_api_init', 'malet_add_custom_fields_to_api');
function malet_add_custom_fields_to_api() {
    // Camp de pes
    register_rest_field('product', 'weight_grams', array(
        'get_callback' => function($object) {
            return get_post_meta($object['id'], '_malet_weight_grams', true);
        },
        'update_callback' => function($value, $object) {
            return update_post_meta($object->ID, '_malet_weight_grams', $value);
        },
        'schema' => array(
            'description' => 'Pes del producte en grams',
            'type' => 'integer'
        )
    ));

    // Camp d'ingredients
    register_rest_field('product', 'ingredients', array(
        'get_callback' => function($object) {
            return get_post_meta($object['id'], '_malet_ingredients', true);
        },
        'update_callback' => function($value, $object) {
            return update_post_meta($object->ID, '_malet_ingredients', $value);
        },
        'schema' => array(
            'description' => 'Llista d\'ingredients del producte',
            'type' => 'string'
        )
    ));
}

/**
 * Registrar Custom Post Type de Receptes
 */
add_action('init', 'malet_register_recipe_post_type');
function malet_register_recipe_post_type() {
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
function malet_register_recipe_taxonomies() {
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
function malet_add_recipe_meta_boxes() {
    add_meta_box(
        'recipe_details',
        'Detalls de la Recepta',
        'malet_recipe_details_callback',
        'recipe',
        'normal',
        'high'
    );
}

function malet_recipe_details_callback($post) {
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
function malet_save_recipe_details($post_id) {
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
function malet_add_recipe_fields_to_api() {
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
            'get_callback' => function($object) use ($meta_key) {
                return get_post_meta($object['id'], $meta_key, true);
            },
            'update_callback' => function($value, $object) use ($meta_key) {
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
function malet_add_product_recipe_meta_box() {
    add_meta_box(
        'product_recipes',
        'Receptes Relacionades',
        'malet_product_recipes_callback',
        'product',
        'side',
        'default'
    );
}

function malet_product_recipes_callback($post) {
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
function malet_add_recipe_product_meta_box() {
    add_meta_box(
        'recipe_products',
        'Productes Relacionats',
        'malet_recipe_products_callback',
        'recipe',
        'side',
        'default'
    );
}

function malet_recipe_products_callback($post) {
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
function malet_save_product_recipe_relationships($post_id) {
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
function malet_add_relationship_fields_to_api() {
    // Camp de receptes vinculades als productes
    register_rest_field('product', 'linked_recipes', array(
        'get_callback' => function($object) {
            $linked_recipes = get_post_meta($object['id'], '_linked_recipes', true);
            return is_array($linked_recipes) ? $linked_recipes : array();
        },
        'update_callback' => function($value, $object) {
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
        'get_callback' => function($object) {
            $linked_products = get_post_meta($object['id'], '_linked_products', true);
            return is_array($linked_products) ? $linked_products : array();
        },
        'update_callback' => function($value, $object) {
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
function malet_gestio_torrent_redirect() {
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
 * Bloquejar accés directe a wp-login.php (OPCIONAL - comentat per debug)
 */
/*
function malet_block_direct_wp_login() {
    global $pagenow;

    if ($pagenow === 'wp-login.php' && !isset($_GET['gestio-torrent'])) {
        wp_redirect(home_url('/gestio-torrent'));
        exit;
    }
}
add_action('init', 'malet_block_direct_wp_login');
*/

/**
 * Sanititzar i validar reviews contra XSS i CSRF
 */
function malet_sanitize_review_content($comment_id) {
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
function malet_notify_admin_security_threat($comment_id, $malicious_content, $author) {
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
function malet_notify_admin_new_review($comment_id, $comment_approved) {
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
function malet_notify_admin_approved_review($comment_id) {
    $comment = get_comment($comment_id);

    // Verificar que és una review de WooCommerce
    if ($comment->comment_type !== 'review') {
        return;
    }

    // Cridar la funció de notificació
    malet_notify_admin_new_review($comment_id, 1);
}
add_action('wp_set_comment_status', function($comment_id, $status) {
    if ($status === 'approve') {
        malet_notify_admin_approved_review($comment_id);
    }
}, 10, 2);

/**
 * Hook per sanititzar reviews ABANS que es guardin (prevenció primària)
 */
function malet_sanitize_review_before_save($comment_data) {
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
function malet_verify_review_nonce() {
    // Només per requests de review via POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['comment']) &&
        isset($_POST['comment_post_ID'])) {

        // Verificar que és un producte (WooCommerce review)
        $post = get_post($_POST['comment_post_ID']);
        if ($post && $post->post_type === 'product') {

            // WordPress ja té protecció CSRF built-in, però afegim validació extra
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'unfiltered-html-comment_' . $_POST['comment_post_ID'])) {
                // Si no hi ha nonce vàlid, usar el nonce de comment estàndard
                if (!isset($_POST['_wp_http_referer']) ||
                    !wp_verify_nonce($_POST['_wpnonce'] ?? '', 'comment_' . $_POST['comment_post_ID'])) {

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
function malet_translate_auth_errors($errors, $redirect_to = '') {
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
function malet_translate_rest_auth_errors($error) {
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
function malet_custom_login_messages($message) {
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
function malet_customize_login_error_message($message) {
    // Si és la pàgina de login, personalitzar encara més
    if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false ||
        strpos($_SERVER['REQUEST_URI'], 'gestio-torrent') !== false) {

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
function malet_get_user_addresses($user_id, $type = 'both') {
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
function malet_save_user_address($user_id, $address_data, $type = 'billing', $slot = 1) {
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
function malet_find_free_slot($user_id, $type, $preferred_slot = 1) {
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
function malet_save_single_address($user_id, $sanitized_address, $type, $slot) {
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
function malet_delete_user_address($user_id, $type, $slot) {
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
function malet_get_default_user_address($user_id, $type) {
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
function malet_get_user_address($user_id, $type, $slot) {
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
function malet_register_addresses_endpoints() {
    // GET /wp-json/malet-torrent/v1/addresses - Obtenir totes les adreces de l'usuari
    register_rest_route('malet-torrent/v1', '/addresses', array(
        'methods' => 'GET',
        'callback' => 'malet_get_user_addresses_endpoint',
        'permission_callback' => function() {
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
        'permission_callback' => function() {
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
        'permission_callback' => function() {
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
        'permission_callback' => function() {
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
        'permission_callback' => function() {
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
function malet_get_user_addresses_endpoint($request) {
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
function malet_create_user_address_endpoint($request) {
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
function malet_update_user_address_endpoint($request) {
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
function malet_delete_user_address_endpoint($request) {
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
function malet_get_default_address_endpoint($request) {
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
function malet_validate_checkout_with_address_slots($data) {
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
function malet_checkout_with_address_support($request) {
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
function malet_register_improved_checkout_endpoint() {
    register_rest_route('malet-torrent/v1', '/checkout-v2', array(
        'methods' => 'POST',
        'callback' => 'malet_checkout_with_address_support',
        'permission_callback' => function() {
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

/**
 * Guardar adreces noves durant el checkout si es demana
 */
function malet_save_addresses_after_checkout($order_id, $data) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return; // No guardar si no hi ha usuari logat
    }

    // Guardar adreça de facturació si es demana
    if (!empty($data['save_billing_address']) && !empty($data['billing_address'])) {
        // Trobar primer slot lliure
        for ($i = 1; $i <= 5; $i++) {
            $existing = get_user_meta($user_id, "malet_billing_address_{$i}", true);
            if (empty($existing)) {
                $address_data = $data['billing_address'];
                $address_data['label'] = 'Adreça de facturació #' . $i;
                malet_save_user_address($user_id, $address_data, 'billing', $i);
                break;
            }
        }
    }

    // Guardar adreça d'enviament si es demana
    if (!empty($data['save_shipping_address']) && !empty($data['shipping_address'])) {
        // Trobar primer slot lliure
        for ($i = 1; $i <= 5; $i++) {
            $existing = get_user_meta($user_id, "malet_shipping_address_{$i}", true);
            if (empty($existing)) {
                $address_data = $data['shipping_address'];
                $address_data['label'] = 'Adreça d\'enviament #' . $i;
                malet_save_user_address($user_id, $address_data, 'shipping', $i);
                break;
            }
        }
    }
}