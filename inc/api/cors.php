<?php
/**
 * Configuració CORS per la API REST
 *
 * @package MaletTorrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
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

    // Primer, intentar carregar origins des de variable d'entorn
    $cors_origins_env = getenv('CORS_ALLOWED_ORIGINS');
    $allowed_origins = array();

    if ($cors_origins_env !== false && !empty($cors_origins_env)) {
        // Variable d'entorn definida: split per comes
        $allowed_origins = array_map('trim', explode(',', $cors_origins_env));
    } else {
        // Fallback: origins per defecte
        $allowed_origins = array(
            'http://localhost:3000',
            'http://localhost:8080',
            'https://malet.testart.cat',
            'https://wp.malet.testart.cat',
            'https://wp2.malet.testart.cat'
        );

        // Variables d'entorn adicionals
        if (defined('NEXT_PUBLIC_SITE_URL') && NEXT_PUBLIC_SITE_URL) {
            $allowed_origins[] = NEXT_PUBLIC_SITE_URL;
        }
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
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/malet-torrent/v1/auth/') !== false) {
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Expose-Headers: Authorization');
        }
    }
}