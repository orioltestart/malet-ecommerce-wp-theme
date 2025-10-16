<?php
/**
 * Next.js Cache Revalidation Webhooks
 *
 * Sistema automàtic per invalidar la caché de Next.js quan hi ha canvis
 * en productes, categories o posts de WordPress.
 *
 * @package Malet_Torrent
 * @version 1.0.0
 */

// Exit si s'accedeix directament
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Envia un webhook a Next.js per invalidar la caché
 *
 * @param string $type Tipus de recurs ('product', 'category', 'post')
 * @param string $action Acció realitzada ('created', 'updated', 'deleted', 'published')
 * @param int $id ID del recurs
 * @return bool|WP_Error True si s'ha enviat correctament, WP_Error si hi ha hagut error
 */
function malet_send_revalidation_webhook($type, $action, $id) {
    // Verificar que tenim les constants configurades
    if (!defined('REVALIDATE_SECRET')) {
        error_log('❌ REVALIDATE_SECRET not defined. Cannot send revalidation webhook.');
        return new WP_Error('missing_config', 'REVALIDATE_SECRET not defined');
    }

    // URL de revalidació (amb fallback per defecte)
    $revalidate_url = defined('NEXTJS_REVALIDATE_URL')
        ? NEXTJS_REVALIDATE_URL
        : 'https://malet.cat/api/revalidate';

    // Preparar payload
    $payload = array(
        'secret' => REVALIDATE_SECRET,
        'type' => $type,
        'action' => $action,
        'id' => $id,
        'timestamp' => current_time('mysql'),
    );

    // Log del webhook que s'envia
    error_log(sprintf('📤 Sending revalidation webhook: %s %s (ID: %d)', $type, $action, $id));

    // Enviar webhook via wp_remote_post
    $response = wp_remote_post($revalidate_url, array(
        'method'      => 'POST',
        'timeout'     => 10,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(
            'Content-Type' => 'application/json',
        ),
        'body'        => json_encode($payload),
        'cookies'     => array(),
    ));

    // Gestionar resposta
    if (is_wp_error($response)) {
        error_log(sprintf('❌ Revalidation webhook failed: %s', $response->get_error_message()));
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($status_code >= 200 && $status_code < 300) {
        error_log(sprintf('✅ Revalidation webhook sent successfully for %s (ID: %d)', $type, $id));
        return true;
    } else {
        error_log(sprintf('❌ Revalidation webhook failed with status %d: %s', $status_code, $response_body));
        return new WP_Error('webhook_failed', "Webhook failed with status $status_code", array('response' => $response_body));
    }
}

/**
 * ==========================================
 * WEBHOOKS PER PRODUCTES WOOCOMMERCE
 * ==========================================
 */

// Producte creat
add_action('woocommerce_new_product', function($product_id) {
    malet_send_revalidation_webhook('product', 'created', $product_id);
}, 10, 1);

// Producte actualitzat
add_action('woocommerce_update_product', function($product_id) {
    malet_send_revalidation_webhook('product', 'updated', $product_id);
}, 10, 1);

// Producte eliminat
add_action('woocommerce_delete_product', function($product_id) {
    malet_send_revalidation_webhook('product', 'deleted', $product_id);
}, 10, 1);

// Canvi d'estat d'stock
add_action('woocommerce_product_set_stock_status', function($product_id) {
    malet_send_revalidation_webhook('product', 'updated', $product_id);
}, 10, 1);

/**
 * ==========================================
 * WEBHOOKS PER CATEGORIES DE PRODUCTES
 * ==========================================
 */

// Categoria creada
add_action('created_product_cat', function($term_id, $tt_id, $taxonomy) {
    malet_send_revalidation_webhook('category', 'created', $term_id);
}, 10, 3);

// Categoria editada
add_action('edited_product_cat', function($term_id, $tt_id, $taxonomy) {
    malet_send_revalidation_webhook('category', 'updated', $term_id);
}, 10, 3);

// Categoria eliminada
add_action('delete_product_cat', function($term_id, $tt_id, $taxonomy) {
    malet_send_revalidation_webhook('category', 'deleted', $term_id);
}, 10, 3);

/**
 * ==========================================
 * WEBHOOKS PER BLOG POSTS
 * ==========================================
 */

// Post publicat
add_action('publish_post', function($post_id, $post) {
    // Evitar webhooks per auto-drafts i revisions
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    malet_send_revalidation_webhook('post', 'published', $post_id);
}, 10, 2);

// Post actualitzat
add_action('post_updated', function($post_id, $post_after, $post_before) {
    // Només enviar webhook si el post està publicat
    if ($post_after->post_status !== 'publish') {
        return;
    }
    // Evitar webhooks per revisions
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    malet_send_revalidation_webhook('post', 'updated', $post_id);
}, 10, 3);

// Post eliminat
add_action('before_delete_post', function($post_id, $post) {
    // Només enviar webhook per posts publicats
    if ($post->post_type !== 'post' || $post->post_status !== 'publish') {
        return;
    }
    malet_send_revalidation_webhook('post', 'deleted', $post_id);
}, 10, 2);

/**
 * ==========================================
 * DASHBOARD WIDGET
 * ==========================================
 */

/**
 * Registra el dashboard widget per mostrar l'estat de configuració
 */
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'malet_revalidation_status',
        '🔄 Next.js Cache Revalidation Status',
        'malet_revalidation_dashboard_widget'
    );
});

/**
 * Mostra el contingut del dashboard widget
 */
function malet_revalidation_dashboard_widget() {
    $secret_configured = defined('REVALIDATE_SECRET');
    $url = defined('NEXTJS_REVALIDATE_URL') ? NEXTJS_REVALIDATE_URL : 'https://malet.cat/api/revalidate';

    ?>
    <div style="padding: 10px;">
        <h4 style="margin-top: 0;">Configuració</h4>
        <table style="width: 100%;">
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <?php if ($secret_configured): ?>
                        <span style="color: green;">✅ Configured</span>
                    <?php else: ?>
                        <span style="color: red;">❌ Not Configured</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>URL:</strong></td>
                <td><code><?php echo esc_html($url); ?></code></td>
            </tr>
        </table>

        <?php if (!$secret_configured): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 15px;">
                <strong>⚠️ Atenció:</strong> Cal definir <code>REVALIDATE_SECRET</code> al <code>wp-config.php</code>
                <p style="margin-bottom: 0; font-size: 12px;">
                    <a href="https://github.com/orioltestart/malet-ecommerce-wp-theme/blob/main/WEBHOOKS_CONFIGURATION.md" target="_blank">
                        📖 Veure documentació completa
                    </a>
                </p>
            </div>
        <?php endif; ?>

        <h4 style="margin-top: 20px;">Webhooks Automàtics Actius</h4>
        <ul style="margin: 0; padding-left: 20px; font-size: 13px;">
            <li>✅ Productes WooCommerce (crear, actualitzar, eliminar, stock)</li>
            <li>✅ Categories de productes (crear, editar, eliminar)</li>
            <li>✅ Blog posts (publicar, actualitzar, eliminar)</li>
        </ul>
    </div>
    <?php
}

/**
 * ==========================================
 * BOTÓ DE TEST AL ADMIN BAR
 * ==========================================
 */

/**
 * Afegeix botó de test al Admin Bar
 */
add_action('admin_bar_menu', function($wp_admin_bar) {
    // Només mostrar per administradors
    if (!current_user_can('manage_options')) {
        return;
    }

    // Comprovar que tenim configuració
    if (!defined('REVALIDATE_SECRET')) {
        return;
    }

    $wp_admin_bar->add_menu(array(
        'id'    => 'malet_test_revalidation',
        'title' => '🔄 Test Cache Revalidation',
        'href'  => add_query_arg('malet_test_webhook', '1', admin_url()),
    ));
}, 999);

/**
 * Gestiona el test de webhook des del Admin Bar
 */
add_action('admin_init', function() {
    // Verificar que s'ha fet clic al botó de test
    if (!isset($_GET['malet_test_webhook'])) {
        return;
    }

    // Verificar permisos
    if (!current_user_can('manage_options')) {
        return;
    }

    // Verificar nonce (si n'hi ha)
    if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'])) {
        wp_die('Security check failed');
    }

    // Enviar webhook de prova amb un producte aleatori
    $products = wc_get_products(array('limit' => 1));
    $product_id = !empty($products) ? $products[0]->get_id() : 999;

    $result = malet_send_revalidation_webhook('product', 'updated', $product_id);

    // Mostrar missatge de confirmació
    if (is_wp_error($result)) {
        add_action('admin_notices', function() use ($result) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>❌ Test Webhook Failed:</strong> <?php echo esc_html($result->get_error_message()); ?></p>
            </div>
            <?php
        });
    } else {
        add_action('admin_notices', function() use ($product_id) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Test Webhook Sent!</strong> Revalidation webhook enviado para producto ID: <?php echo esc_html($product_id); ?></p>
            </div>
            <?php
        });
    }
});

/**
 * ==========================================
 * FILTRES PER PERSONALITZACIÓ
 * ==========================================
 */

/**
 * Filtre per modificar la URL de revalidació
 *
 * @param string $url URL de revalidació per defecte
 * @return string URL modificada
 */
apply_filters('malet_revalidation_url', function($url) {
    return defined('NEXTJS_REVALIDATE_URL') ? NEXTJS_REVALIDATE_URL : $url;
});

/**
 * Filtre per modificar el timeout dels webhooks
 *
 * @param int $timeout Timeout en segons (per defecte: 10)
 * @return int Timeout modificat
 */
apply_filters('malet_revalidation_timeout', 10);

/**
 * Filtre per desactivar webhooks en entorns específics
 *
 * @param bool $enabled Si els webhooks estan activats (per defecte: true)
 * @return bool
 */
apply_filters('malet_revalidation_enabled', true);
