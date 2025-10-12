<?php
/**
 * Funcions per crear comandes des del checkout
 *
 * Gestiona la creació de comandes WooCommerce des del frontend headless
 *
 * @package Malet_Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log events del checkout per debug
 */
function malet_log_checkout_event($event, $data = array())
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('MALET CHECKOUT [' . $event . ']: ' . json_encode($data));
    }
}

/**
 * Crear comanda des del checkout del frontend
 */
function malet_create_order_from_checkout($request)
{
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

        // 10. Processament d'emails ara gestionat pel sistema d'emails simplificat

        // 11. Generar resposta
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
function malet_validate_checkout_order_data($data)
{
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
    if (
        !empty($data['billing_address']['postcode']) &&
        $data['billing_address']['country'] === 'ES' &&
        !preg_match('/^[0-9]{5}$/', $data['billing_address']['postcode'])
    ) {
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
function malet_ensure_woocommerce_ready()
{
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
function malet_get_or_create_customer($customer_data)
{
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
function malet_generate_unique_username($email)
{
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
function malet_create_woocommerce_order($data, $customer_id)
{
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
function malet_set_order_addresses($order, $data)
{
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
function malet_add_order_line_items($order, $line_items)
{
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
function malet_set_order_shipping($order, $shipping_data)
{
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
function malet_apply_order_coupons($order, $coupons_data)
{
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
function malet_set_order_payment_method($order, $payment_data)
{
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
function malet_add_order_metadata($order, $meta_data)
{
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
function malet_generate_order_response($order, $original_data)
{
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
 * Obtenir detalls de transferència bancària
 */
function malet_get_bank_transfer_details()
{
    return array(
        'account_name' => get_option('woocommerce_bacs_account_name', 'Malet Torrent'),
        'account_number' => get_option('woocommerce_bacs_account_number', ''),
        'sort_code' => get_option('woocommerce_bacs_sort_code', ''),
        'iban' => get_option('woocommerce_bacs_iban', ''),
        'bic' => get_option('woocommerce_bacs_bic', ''),
        'instructions' => get_option('woocommerce_bacs_instructions', '')
    );
}
