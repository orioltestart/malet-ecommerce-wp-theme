<?php
/**
 * Pàgina de configuració i administració del tema
 *
 * Gestiona la interfície d'administració del tema
 *
 * @package Malet_Torrent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks d'administració
 */
add_action('admin_menu', 'malet_torrent_admin_menu');
add_action('admin_enqueue_scripts', 'malet_torrent_admin_styles');
add_action('after_switch_theme', 'malet_torrent_set_default_settings');
add_action('admin_notices', 'malet_torrent_activation_notice');
add_filter('theme_action_links', 'malet_torrent_theme_action_links', 10, 2);

/**
 * Saltar wizards de WooCommerce
 */
add_filter('woocommerce_enable_setup_wizard', '__return_false');
add_filter('woocommerce_admin_onboarding_profile_completed', '__return_true');
add_filter('woocommerce_show_admin_notice', function ($show, $notice) {
    $notices_to_hide = ['install_notice', 'update_notice', 'template_check', 'theme_support'];
    if (in_array($notice, $notices_to_hide)) {
        return false;
    }
    return $show;
}, 10, 2);

/**
 * Marcar com completat l'onboarding i forçar càrrega de traduccions
 */
add_action('init', function () {
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
 * Afegir pàgina de configuració al admin
 */
function malet_torrent_admin_menu()
{
    add_theme_page(
        'Configuració Malet Torrent',
        'Malet Torrent',
        'manage_options',
        'malet-torrent-settings',
        'malet_torrent_settings_page'
    );
}

/**
 * Pàgina de configuració del tema
 */
function malet_torrent_settings_page()
{
    // Sistema de plugins requerits DESACTIVAT
    // $installer = Malet_Torrent_Plugin_Installer::get_instance();
    // $status = $installer->get_installation_status();
    // $summary = Malet_Torrent_Admin_Notices::get_status_summary();
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
function malet_torrent_display_api_status()
{
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
function malet_torrent_admin_styles()
{
    wp_enqueue_style('malet-torrent-admin', MALETNEXT_THEME_URL . '/style.css', array(), MALETNEXT_VERSION);
}

/**
 * Configuració per defecte del lloc
 */
function malet_torrent_set_default_settings()
{
    // Configurar permalinks
    if (get_option('permalink_structure') == '') {
        update_option('permalink_structure', '/%postname%/');
        flush_rewrite_rules();
    }

    // Configurar timezone
    if (get_option('timezone_string') == '') {
        update_option('timezone_string', 'Europe/Madrid');
    }

    // Configuració d'idioma eliminada - es gestiona des de wp-admin
}

/**
 * Missatge d'activació del tema
 */
function malet_torrent_activation_notice()
{
    if (get_option('malet_torrent_activation_notice', true)) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Malet Torrent Tema Activat!</strong> El teu lloc ja està configurat per funcionar com a backend headless.</p>';
        echo '<p><a href="' . admin_url('themes.php?page=malet-torrent-settings') . '">Configurar Malet Torrent</a></p>';
        echo '</div>';
        update_option('malet_torrent_activation_notice', false);
    }
}

/**
 * Afegir enllaços d'acció al tema
 */
function malet_torrent_theme_action_links($actions, $theme)
{
    if (get_template() === 'malet-torrent') {
        $actions[] = '<a href="' . admin_url('themes.php?page=malet-torrent-settings') . '">Configuració</a>';
    }
    return $actions;
}
