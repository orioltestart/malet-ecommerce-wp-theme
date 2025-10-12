<?php
/**
 * Millores de l'API de WooCommerce
 *
 * Afegeix camps adicionals i funcionalitats a l'API REST de WooCommerce
 *
 * @package Malet_Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Millorar l'API de WooCommerce per headless
 */
function malet_torrent_enhance_woocommerce_api()
{
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
function malet_torrent_get_acf_fields($object, $field_name, $request)
{
    if (function_exists('get_fields')) {
        return get_fields($object['id']);
    }
    return null;
}

/**
 * Obtenir informació detallada de stock
 */
function malet_torrent_get_stock_info($object, $field_name, $request)
{
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
function malet_torrent_get_detailed_categories($object, $field_name, $request)
{
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
function malet_torrent_get_category_image($term_id)
{
    $image_id = get_term_meta($term_id, 'thumbnail_id', true);
    if ($image_id) {
        return wp_get_attachment_image_url($image_id, 'full');
    }
    return null;
}
