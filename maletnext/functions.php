<?php
/**
 * Functions and definitions for Malet Torrent Headless Theme
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

// Constants del tema
define('MALETNEXT_VERSION', '1.0.0');
define('MALETNEXT_THEME_DIR', get_template_directory());
define('MALETNEXT_THEME_URL', get_template_directory_uri());

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
    
    // Localització
    load_theme_textdomain('malet-torrent', MALETNEXT_THEME_DIR . '/languages');
}
add_action('after_setup_theme', 'malet_torrent_setup');

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
    
    // Millorar la API REST per WooCommerce
    add_action('rest_api_init', 'malet_torrent_enhance_woocommerce_api');
    
    // Afegir endpoints personalitzats
    add_action('rest_api_init', 'malet_torrent_register_custom_endpoints');
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
}

/**
 * Configuració CORS millorada per la API REST
 * Basat en mu-plugins/cors.php amb millores de seguretat
 */
function malet_torrent_add_cors_support() {
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
        }
        
        // Headers per endpoints personalitzats
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/malet-torrent/') !== false) {
            // Headers adicionals específics per Malet Torrent
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
        'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
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
    if (!class_exists('WooCommerce')) {
        return new WP_Error('woocommerce_not_active', 'WooCommerce is not active', array('status' => 500));
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
    if (!class_exists('WooCommerce')) {
        return new WP_Error('woocommerce_not_active', 'WooCommerce is not active', array('status' => 500));
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
    $admin_notices = new Malet_Torrent_Admin_Notices();
    $status = $installer->get_installation_status();
    $summary = $admin_notices->get_status_summary();
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
                    <li><?php echo (class_exists('autoptimizeMain')) ? '✅' : '❌'; ?> Autoptimize</li>
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
}
add_action('after_switch_theme', 'malet_torrent_set_default_settings');

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
    if (get_template() === 'malet-torrent-headless-theme-enhanced') {
        $actions[] = '<a href="' . admin_url('themes.php?page=malet-torrent-settings') . '">Configuració</a>';
    }
    return $actions;
}
add_filter('theme_action_links', 'malet_torrent_theme_action_links', 10, 2);