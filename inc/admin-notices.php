<?php
/**
 * Admin Notices for Plugin Installation
 * Handles display of plugin installation notices in WordPress admin
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Malet_Torrent_Admin_Notices {
    
    /**
     * Plugin installer instance
     */
    private $installer;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->installer = Malet_Torrent_Plugin_Installer::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_notices', [$this, 'display_plugin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_malet_torrent_dismiss_notice', [$this, 'ajax_dismiss_notice']);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        $current_screen = get_current_screen();
        
        // Only load on specific admin pages
        if (!$current_screen || !in_array($current_screen->id, [
            'dashboard', 
            'themes', 
            'plugins', 
            'appearance_page_malet-torrent-settings'
        ])) {
            return;
        }
        
        wp_enqueue_script(
            'malet-torrent-plugin-installer',
            get_template_directory_uri() . '/assets/js/plugin-installer.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_enqueue_style(
            'malet-torrent-admin-notices',
            get_template_directory_uri() . '/assets/css/admin-notices.css',
            [],
            '1.0.0'
        );
        
        // Localize script
        wp_localize_script('malet-torrent-plugin-installer', 'maletTorrentInstaller', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonces' => [
                'install' => wp_create_nonce('malet_torrent_install_plugin'),
                'activate' => wp_create_nonce('malet_torrent_activate_plugin'),
                'bulk' => wp_create_nonce('malet_torrent_install_bulk'),
                'dismiss' => wp_create_nonce('malet_torrent_dismiss_notice'),
                'check_updates' => wp_create_nonce('malet_torrent_check_updates'),
                'install_update' => wp_create_nonce('malet_torrent_install_update'),
                'dismiss_update' => wp_create_nonce('malet_torrent_dismiss_update_notice')
            ],
            'strings' => [
                'installing' => __('Instal·lant...', 'malet-torrent'),
                'activating' => __('Activant...', 'malet-torrent'),
                'installed' => __('Instal·lat', 'malet-torrent'),
                'activated' => __('Activat', 'malet-torrent'),
                'error' => __('Error', 'malet-torrent'),
                'installing_bulk' => __('Instal·lant plugins...', 'malet-torrent'),
                'progress' => __('Progrés', 'malet-torrent'),
                'completed' => __('Completat', 'malet-torrent'),
                'failed' => __('Ha fallat', 'malet-torrent'),
                'checking_updates' => __('Comprovant actualitzacions...', 'malet-torrent'),
                'updating_theme' => __('Actualitzant tema...', 'malet-torrent'),
                'update_available' => __('Actualització disponible', 'malet-torrent'),
                'up_to_date' => __('Actualitzat', 'malet-torrent'),
                'backup_warning' => __('Es recomana fer una còpia de seguretat abans d\'actualitzar. Continuar?', 'malet-torrent'),
                'update_success' => __('Tema actualitzat correctament', 'malet-torrent'),
                'update_failed' => __('Error en actualitzar el tema', 'malet-torrent')
            ]
        ]);
    }
    
    /**
     * Display plugin installation notices
     */
    public function display_plugin_notices() {
        // Check if user can install plugins
        if (!current_user_can('install_plugins')) {
            return;
        }
        
        $status = $this->installer->get_installation_status();
        
        // Show required plugins notice
        if (!$this->is_notice_dismissed('required') && 
            (!empty($status['required']['missing']) || !empty($status['required']['inactive']))) {
            $this->display_required_plugins_notice($status['required']);
        }
        
        // Show recommended plugins notice (only if required are done)
        if (empty($status['required']['missing']) && empty($status['required']['inactive'])) {
            if (!$this->is_notice_dismissed('recommended') && 
                (!empty($status['recommended']['missing']) || !empty($status['recommended']['inactive']))) {
                $this->display_recommended_plugins_notice($status['recommended']);
            }
            
            // Show optional plugins notice (only if required and recommended are done)
            // DESACTIVAT: No mostrem notificacions de plugins opcionals
            /*
            if (empty($status['recommended']['missing']) && empty($status['recommended']['inactive'])) {
                if (!$this->is_notice_dismissed('optional') && !empty($status['optional']['missing'])) {
                    $this->display_optional_plugins_notice($status['optional']);
                }
            }
            */
        }
    }
    
    /**
     * Display required plugins notice
     */
    private function display_required_plugins_notice($status) {
        $missing_plugins = array_merge($status['missing'], $status['inactive']);
        
        if (empty($missing_plugins)) {
            return;
        }
        
        ?>
        <div class="notice notice-error malet-torrent-notice" data-priority="required">
            <div class="malet-torrent-notice-header">
                <h3>
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('Plugins Requerits - Malet Torrent', 'malet-torrent'); ?>
                </h3>
                <button type="button" class="notice-dismiss malet-torrent-dismiss" data-priority="required">
                    <span class="screen-reader-text"><?php _e('Descartar aquest avís', 'malet-torrent'); ?></span>
                </button>
            </div>
            
            <div class="malet-torrent-notice-content">
                <p>
                    <strong><?php _e('El tema Malet Torrent requereix alguns plugins per funcionar correctament.', 'malet-torrent'); ?></strong>
                    <?php _e('Aquests plugins són essencials per al funcionament de la botiga de melindros.', 'malet-torrent'); ?>
                </p>
                
                <div class="malet-torrent-plugins-list">
                    <?php foreach ($missing_plugins as $slug => $plugin): ?>
                        <div class="malet-torrent-plugin-item" data-slug="<?php echo esc_attr($slug); ?>">
                            <div class="plugin-info">
                                <h4><?php echo esc_html($plugin['name']); ?></h4>
                                <p><?php echo esc_html($plugin['description']); ?></p>
                                <div class="plugin-features">
                                    <?php if (!empty($plugin['features'])): ?>
                                        <ul>
                                            <?php foreach ($plugin['features'] as $feature): ?>
                                                <li><?php echo esc_html($feature); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="plugin-actions">
                                <?php if (in_array($slug, array_keys($status['missing']))): ?>
                                    <button type="button" class="button button-primary install-plugin" data-slug="<?php echo esc_attr($slug); ?>">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php _e('Instal·lar', 'malet-torrent'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="button button-secondary activate-plugin" data-slug="<?php echo esc_attr($slug); ?>">
                                        <span class="dashicons dashicons-admin-plugins"></span>
                                        <?php _e('Activar', 'malet-torrent'); ?>
                                    </button>
                                <?php endif; ?>
                                <div class="plugin-status">
                                    <span class="status-text"></span>
                                    <span class="spinner"></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="malet-torrent-bulk-actions">
                    <button type="button" class="button button-primary button-large install-all-required">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Instal·lar Tots els Plugins Requerits', 'malet-torrent'); ?>
                    </button>
                    <div class="bulk-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display recommended plugins notice
     */
    private function display_recommended_plugins_notice($status) {
        $missing_plugins = array_merge($status['missing'], $status['inactive']);
        
        if (empty($missing_plugins)) {
            return;
        }
        
        ?>
        <div class="notice notice-warning malet-torrent-notice" data-priority="recommended">
            <div class="malet-torrent-notice-header">
                <h3>
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php _e('Plugins Recomanats - Malet Torrent', 'malet-torrent'); ?>
                </h3>
                <button type="button" class="notice-dismiss malet-torrent-dismiss" data-priority="recommended">
                    <span class="screen-reader-text"><?php _e('Descartar aquest avís', 'malet-torrent'); ?></span>
                </button>
            </div>
            
            <div class="malet-torrent-notice-content">
                <p>
                    <?php _e('Els següents plugins milloraran significativament la seguretat i el rendiment del teu lloc web.', 'malet-torrent'); ?>
                </p>
                
                <div class="malet-torrent-plugins-list recommended">
                    <?php foreach ($missing_plugins as $slug => $plugin): ?>
                        <div class="malet-torrent-plugin-item" data-slug="<?php echo esc_attr($slug); ?>">
                            <div class="plugin-info">
                                <h4><?php echo esc_html($plugin['name']); ?></h4>
                                <p><?php echo esc_html($plugin['description']); ?></p>
                            </div>
                            <div class="plugin-actions">
                                <?php if (in_array($slug, array_keys($status['missing']))): ?>
                                    <button type="button" class="button button-secondary install-plugin" data-slug="<?php echo esc_attr($slug); ?>">
                                        <?php _e('Instal·lar', 'malet-torrent'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="button button-secondary activate-plugin" data-slug="<?php echo esc_attr($slug); ?>">
                                        <?php _e('Activar', 'malet-torrent'); ?>
                                    </button>
                                <?php endif; ?>
                                <div class="plugin-status">
                                    <span class="status-text"></span>
                                    <span class="spinner"></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="malet-torrent-bulk-actions">
                    <button type="button" class="button button-secondary install-all-recommended">
                        <?php _e('Instal·lar Tots els Recomanats', 'malet-torrent'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display optional plugins notice
     * DESACTIVAT: No mostrem notificacions de plugins opcionals
     */
    private function display_optional_plugins_notice($status) {
        // Funció desactivada - no mostrem notificacions de plugins opcionals
        return;

        if (empty($status['missing'])) {
            return;
        }
        
        ?>
        <div class="notice notice-info malet-torrent-notice" data-priority="optional">
            <div class="malet-torrent-notice-header">
                <h3>
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Plugins Opcionals - Malet Torrent', 'malet-torrent'); ?>
                </h3>
                <button type="button" class="notice-dismiss malet-torrent-dismiss" data-priority="optional">
                    <span class="screen-reader-text"><?php _e('Descartar aquest avís', 'malet-torrent'); ?></span>
                </button>
            </div>
            
            <div class="malet-torrent-notice-content">
                <p>
                    <?php _e('Aquests plugins opcionals poden afegir funcionalitats adicionals al teu lloc web.', 'malet-torrent'); ?>
                </p>
                
                <div class="malet-torrent-plugins-list optional collapsed">
                    <?php foreach ($status['missing'] as $slug => $plugin): ?>
                        <div class="malet-torrent-plugin-item" data-slug="<?php echo esc_attr($slug); ?>">
                            <div class="plugin-info">
                                <h4><?php echo esc_html($plugin['name']); ?></h4>
                                <p><?php echo esc_html($plugin['description']); ?></p>
                            </div>
                            <div class="plugin-actions">
                                <button type="button" class="button button-secondary install-plugin" data-slug="<?php echo esc_attr($slug); ?>">
                                    <?php _e('Instal·lar', 'malet-torrent'); ?>
                                </button>
                                <div class="plugin-status">
                                    <span class="status-text"></span>
                                    <span class="spinner"></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="malet-torrent-toggle">
                    <button type="button" class="button button-link toggle-optional-plugins">
                        <?php _e('Mostrar/Ocultar Plugins Opcionals', 'malet-torrent'); ?>
                        <span class="dashicons dashicons-arrow-down"></span>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for dismissing notices
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer('malet_torrent_dismiss_notice', 'nonce');
        
        $priority = sanitize_text_field($_POST['priority']);
        $current_dismissed = get_option('malet_torrent_notices_dismissed', []);
        
        if (!is_array($current_dismissed)) {
            $current_dismissed = [];
        }
        
        $current_dismissed[$priority] = true;
        update_option('malet_torrent_notices_dismissed', $current_dismissed);
        
        wp_send_json_success();
    }
    
    /**
     * Check if specific notice is dismissed
     */
    public function is_notice_dismissed($priority) {
        $dismissed = get_option('malet_torrent_notices_dismissed', []);
        return isset($dismissed[$priority]) && $dismissed[$priority];
    }
    
    /**
     * Check if specific update version is dismissed
     */
    public function is_update_version_dismissed($version) {
        $dismissed_updates = get_option('malet_torrent_dismissed_updates', []);
        return isset($dismissed_updates[$version]) && $dismissed_updates[$version];
    }
    
    /**
     * Mark specific update version as dismissed
     */
    public function dismiss_update_version($version) {
        $dismissed_updates = get_option('malet_torrent_dismissed_updates', []);
        $dismissed_updates[$version] = true;
        update_option('malet_torrent_dismissed_updates', $dismissed_updates);
    }
    
    /**
     * Reset dismissed notices (for testing or when theme is reactivated)
     */
    public function reset_dismissed_notices() {
        delete_option('malet_torrent_notices_dismissed');
    }
    
    /**
     * Reset dismissed update versions
     */
    public function reset_dismissed_updates() {
        delete_option('malet_torrent_dismissed_updates');
    }
    
    /**
     * Get summary of plugin installation status for dashboard
     */
    public static function get_status_summary() {
        $installer = Malet_Torrent_Plugin_Installer::get_instance();
        $status = $installer->get_installation_status();
        
        $summary = [
            'required' => [
                'total' => $status['required']['total'],
                'completed' => $status['required']['active'],
                'percentage' => $status['required']['total'] > 0 ? 
                    round(($status['required']['active'] / $status['required']['total']) * 100) : 100
            ],
            'recommended' => [
                'total' => $status['recommended']['total'],
                'completed' => $status['recommended']['active'],
                'percentage' => $status['recommended']['total'] > 0 ? 
                    round(($status['recommended']['active'] / $status['recommended']['total']) * 100) : 100
            ],
            'optional' => [
                'total' => $status['optional']['total'],
                'completed' => $status['optional']['active'],
                'percentage' => $status['optional']['total'] > 0 ? 
                    round(($status['optional']['active'] / $status['optional']['total']) * 100) : 100
            ]
        ];
        
        return $summary;
    }
}