<?php
/**
 * Control d'indexació SEO basat en entorn
 *
 * @package MaletTorrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

// Hooks per control d'indexació SEO
add_action('init', 'malet_torrent_control_search_indexing', 1);
add_action('template_redirect', 'malet_torrent_add_robots_header', 1);
add_action('admin_bar_menu', 'malet_torrent_add_environment_indicator', 999);
add_filter('option_blog_public', 'malet_torrent_override_indexing_settings', 10, 2);
add_action('admin_notices', 'malet_torrent_indexing_admin_notice');

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