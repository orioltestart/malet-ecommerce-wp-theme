<?php
/**
 * Google Analytics 4 Enhanced Ecommerce
 *
 * Gestiona l'enviament d'events de compra a GA4
 *
 * @package Malet_Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks per enviar events de compra a GA4
 */
add_action('woocommerce_order_status_completed', 'malet_send_ga4_purchase_event', 10, 1);
add_action('woocommerce_payment_complete', 'malet_send_ga4_purchase_event', 10, 1);

/**
 * Enviar event 'purchase' a GA4 quan es completa una comanda
 */
function malet_send_ga4_purchase_event($order_id)
{
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
function malet_send_ga4_event($measurement_id, $api_secret, $event_data)
{
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
function malet_get_product_primary_category($product_id)
{
    $categories = get_the_terms($product_id, 'product_cat');
    if ($categories && !is_wp_error($categories)) {
        return $categories[0]->name;
    }
    return 'Melindros';
}

/**
 * Generar UUID per client_id
 */
function malet_generate_uuid()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}
