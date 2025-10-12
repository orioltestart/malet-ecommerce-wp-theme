<?php
/**
 * Endpoints personalitzats de la REST API
 *
 * Gestiona el registre i implementació d'endpoints personalitzats
 *
 * @package Malet_Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar endpoints personalitzats de la API
 */
function malet_torrent_register_custom_endpoints()
{
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
        'callback' => function () {
            return array('status' => 'working', 'time' => current_time('mysql'));
        },
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
                'validate_callback' => function ($param) {
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
function malet_torrent_get_site_config($request)
{
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
function malet_torrent_get_custom_logo()
{
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        return wp_get_attachment_image_url($custom_logo_id, 'full');
    }
    return null;
}

/**
 * Obtenir menú per ubicació
 */
function malet_torrent_get_menu($request)
{
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
function malet_torrent_get_featured_products($request)
{
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
                'gallery' => array_map(function ($id) {
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
function malet_torrent_get_woocommerce_config($request)
{
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
function malet_torrent_get_product_categories($request)
{
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
function malet_torrent_get_customers($request)
{
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
function malet_torrent_get_orders($request)
{
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
 * Validar dades de checkout abans del submit final
 */
function malet_validate_checkout_data($request)
{
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
    if (
        !empty($data['billing_address']['postcode']) &&
        $data['billing_address']['country'] === 'ES' &&
        !preg_match('/^[0-9]{5}$/', $data['billing_address']['postcode'])
    ) {
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
function malet_calculate_shipping_realtime($request)
{
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
                // Obtenir metadata del rate
                $meta_data = $rate->get_meta_data();

                $shipping_methods[] = [
                    'id' => $rate->get_id(),
                    'label' => $rate->get_label(),
                    'cost' => floatval($rate->get_cost()),
                    'formatted_cost' => wc_price($rate->get_cost()),
                    'tax' => floatval($rate->get_shipping_tax()),
                    'delivery_time' => isset($meta_data['delivery_time']) ? $meta_data['delivery_time'] : null,
                    'meta_data' => $meta_data // Incloure tota la metadata per debug
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
function malet_apply_coupon_code($request)
{
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
function malet_get_available_payment_methods()
{
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
function malet_get_shipping_zones($request)
{
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
                        'method_id' => isset($method->method_id) ? $method->method_id : $method->id,
                        'method_title' => $method->method_title ?? $method->title,
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
                    'locations' => array_map(function ($loc) {
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
 * Configurar ubicacions de zones d'enviament
 */
function malet_setup_shipping_zones($request)
{
    if (!class_exists('WC_Shipping_Zones')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'WooCommerce Shipping Zones no disponible'
        ], 500);
    }

    // Obtenir zona 1 (Torrent i Rodalies)
    $zone_1 = WC_Shipping_Zones::get_zone(1);

    // Afegir ubicació: Codi postal 17401-17403 (Arbúcies i rodalies)
    $zone_1->clear_locations();
    $zone_1->add_location('17401', 'postcode');
    $zone_1->add_location('17402', 'postcode');
    $zone_1->add_location('17403', 'postcode');
    $zone_1->add_location('ES:08', 'state'); // Catalunya (Barcelona province)
    $zone_1->add_location('ES:17', 'state'); // Girona
    $zone_1->save();

    // Obtenir zona 2 (Espanya)
    $zone_2 = WC_Shipping_Zones::get_zone(2);

    // Afegir ubicació: Tot Espanya
    $zone_2->clear_locations();
    $zone_2->add_location('ES', 'country');
    $zone_2->save();

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Zones d\'enviament configurades correctament',
        'zones' => [
            'zone_1' => [
                'id' => 1,
                'name' => $zone_1->get_zone_name(),
                'locations' => $zone_1->get_zone_locations()
            ],
            'zone_2' => [
                'id' => 2,
                'name' => $zone_2->get_zone_name(),
                'locations' => $zone_2->get_zone_locations()
            ]
        ]
    ], 200);
}
