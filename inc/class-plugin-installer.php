<?php
/**
 * Plugin Installer Class for Malet Torrent Theme
 * Handles automatic installation and activation of required plugins
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Malet_Torrent_Plugin_Installer {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin configurations
     */
    private $plugins = [];
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_plugin_configs();
        $this->init_hooks();
    }
    
    /**
     * Load plugin configurations
     */
    private function load_plugin_configs() {
        if (file_exists(get_template_directory() . '/inc/required-plugins-config.php')) {
            $this->plugins = include get_template_directory() . '/inc/required-plugins-config.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_malet_torrent_install_plugin', [$this, 'ajax_install_plugin']);
        add_action('wp_ajax_malet_torrent_activate_plugin', [$this, 'ajax_activate_plugin']);
        add_action('wp_ajax_malet_torrent_install_bulk_plugins', [$this, 'ajax_install_bulk_plugins']);
    }
    
    /**
     * Get all plugin configurations
     */
    public function get_plugins() {
        return $this->plugins;
    }
    
    /**
     * Get plugins by priority
     */
    public function get_plugins_by_priority($priority = 'required') {
        return array_filter($this->plugins, function($plugin) use ($priority) {
            return isset($plugin['priority']) && $plugin['priority'] === $priority;
        });
    }
    
    /**
     * Check if plugin is installed
     */
    public function is_plugin_installed($plugin_slug) {
        $plugins = get_plugins();
        foreach ($plugins as $plugin_path => $plugin_data) {
            if (strpos($plugin_path, $plugin_slug . '/') === 0 || 
                strpos($plugin_path, $plugin_slug . '.php') !== false) {
                return $plugin_path;
            }
        }
        return false;
    }
    
    /**
     * Check if plugin is active
     */
    public function is_plugin_active($plugin_slug) {
        $plugin_path = $this->is_plugin_installed($plugin_slug);
        if ($plugin_path) {
            return is_plugin_active($plugin_path);
        }
        return false;
    }
    
    /**
     * Get missing plugins
     */
    public function get_missing_plugins($priority = null) {
        $missing = [];
        $plugins_to_check = $priority ? $this->get_plugins_by_priority($priority) : $this->plugins;
        
        foreach ($plugins_to_check as $slug => $plugin) {
            if (!$this->is_plugin_installed($slug)) {
                $missing[$slug] = $plugin;
            }
        }
        
        return $missing;
    }
    
    /**
     * Get inactive plugins
     */
    public function get_inactive_plugins($priority = null) {
        $inactive = [];
        $plugins_to_check = $priority ? $this->get_plugins_by_priority($priority) : $this->plugins;
        
        foreach ($plugins_to_check as $slug => $plugin) {
            if ($this->is_plugin_installed($slug) && !$this->is_plugin_active($slug)) {
                $inactive[$slug] = $plugin;
            }
        }
        
        return $inactive;
    }
    
    /**
     * Install plugin from WordPress.org
     */
    public function install_plugin($plugin_slug, $source_url = null) {
        if (!current_user_can('install_plugins')) {
            return new WP_Error('insufficient_permissions', __('No tens permisos per instal·lar plugins.', 'malet-torrent'));
        }
        
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        
        // Get plugin info from WordPress.org
        if (!$source_url) {
            $api = plugins_api('plugin_information', [
                'slug' => $plugin_slug,
                'fields' => [
                    'short_description' => false,
                    'sections' => false,
                    'requires' => false,
                    'rating' => false,
                    'ratings' => false,
                    'downloaded' => false,
                    'last_updated' => false,
                    'added' => false,
                    'tags' => false,
                    'compatibility' => false,
                    'homepage' => false,
                    'donate_link' => false,
                ]
            ]);
            
            if (is_wp_error($api)) {
                return $api;
            }
            
            $source_url = $api->download_link;
        }
        
        // Install the plugin
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($source_url);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }
    
    /**
     * Activate plugin
     */
    public function activate_plugin($plugin_slug) {
        if (!current_user_can('activate_plugins')) {
            return new WP_Error('insufficient_permissions', __('No tens permisos per activar plugins.', 'malet-torrent'));
        }
        
        $plugin_path = $this->is_plugin_installed($plugin_slug);
        if (!$plugin_path) {
            return new WP_Error('plugin_not_found', __('Plugin no trobat.', 'malet-torrent'));
        }
        
        $result = activate_plugin($plugin_path);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Run post-activation configuration
        $this->configure_plugin_after_activation($plugin_slug);
        
        return true;
    }
    
    /**
     * Configure plugin after activation
     */
    private function configure_plugin_after_activation($plugin_slug) {
        switch ($plugin_slug) {
            case 'wordfence':
                $this->configure_wordfence();
                break;
            case 'contact-form-7':
                $this->configure_contact_form_7();
                break;
            case 'redis-cache':
                $this->configure_redis_cache();
                break;
            case 'seo-by-rank-math':
                $this->configure_rank_math();
                break;
        }
    }
    
    /**
     * Configure Wordfence
     */
    private function configure_wordfence() {
        // Basic Wordfence configuration
        if (class_exists('wfConfig')) {
            wfConfig::set('autoUpdate', 1);
            wfConfig::set('emailSummaryEnabled', 1);
            wfConfig::set('alertOn_loginLockout', 1);
        }
    }
    
    /**
     * Configure Contact Form 7
     */
    private function configure_contact_form_7() {
        // Create default contact form if none exists
        if (function_exists('wpcf7_get_contact_forms')) {
            $forms = wpcf7_get_contact_forms();
            if (empty($forms)) {
                $this->create_default_contact_form();
            }
        }
    }
    
    /**
     * Create default contact form
     */
    private function create_default_contact_form() {
        $form_content = '[text* your-name placeholder "El teu nom"]
[email* your-email placeholder "El teu email"]
[text your-subject placeholder "Assumpte"]
[textarea your-message placeholder "El teu missatge"]
[submit "Enviar"]';
        
        $mail_content = 'De: [your-name] <[your-email]>
Assumpte: [your-subject]
Cos del missatge:
[your-message]

--
Aquest email s\'ha enviat des del formulari de contacte de ' . get_bloginfo('name');
        
        if (function_exists('wpcf7_save_contact_form')) {
            $contact_form = wpcf7_save_contact_form([
                'title' => 'Formulari de Contacte',
                'form' => $form_content,
                'mail' => [
                    'subject' => '[your-subject]',
                    'sender' => '[your-name] <[your-email]>',
                    'body' => $mail_content,
                    'recipient' => get_option('admin_email'),
                ],
            ]);
        }
    }
    
    /**
     * Configure Redis Cache
     */
    private function configure_redis_cache() {
        // Enable Redis cache if Redis is available
        if (class_exists('RedisObjectCache') && function_exists('wp_redis_get_info')) {
            $redis_info = wp_redis_get_info();
            if ($redis_info && isset($redis_info['status']) && $redis_info['status'] === 'connected') {
                wp_cache_flush();
            }
        }
    }
    
    /**
     * Configure Rank Math
     */
    private function configure_rank_math() {
        // Basic Rank Math configuration for pastry shop
        if (function_exists('rank_math_get_option')) {
            update_option('rank_math_general_options', [
                'setup_mode' => 'advanced',
                'country' => 'ES',
                'search_console_profile' => '',
            ]);
        }
    }
    
    /**
     * AJAX handler for plugin installation
     */
    public function ajax_install_plugin() {
        check_ajax_referer('malet_torrent_install_plugin', 'nonce');
        
        if (!current_user_can('install_plugins')) {
            wp_die(json_encode(['success' => false, 'message' => 'Permisos insuficients']));
        }
        
        $plugin_slug = sanitize_text_field($_POST['plugin_slug']);
        $source_url = isset($_POST['source_url']) ? esc_url_raw($_POST['source_url']) : null;
        
        $result = $this->install_plugin($plugin_slug, $source_url);
        
        if (is_wp_error($result)) {
            wp_die(json_encode(['success' => false, 'message' => $result->get_error_message()]));
        }
        
        wp_die(json_encode(['success' => true, 'message' => 'Plugin instal·lat correctament']));
    }
    
    /**
     * AJAX handler for plugin activation
     */
    public function ajax_activate_plugin() {
        check_ajax_referer('malet_torrent_activate_plugin', 'nonce');
        
        if (!current_user_can('activate_plugins')) {
            wp_die(json_encode(['success' => false, 'message' => 'Permisos insuficients']));
        }
        
        $plugin_slug = sanitize_text_field($_POST['plugin_slug']);
        
        $result = $this->activate_plugin($plugin_slug);
        
        if (is_wp_error($result)) {
            wp_die(json_encode(['success' => false, 'message' => $result->get_error_message()]));
        }
        
        wp_die(json_encode(['success' => true, 'message' => 'Plugin activat correctament']));
    }
    
    /**
     * AJAX handler for bulk plugin installation
     */
    public function ajax_install_bulk_plugins() {
        check_ajax_referer('malet_torrent_install_bulk', 'nonce');
        
        if (!current_user_can('install_plugins')) {
            wp_die(json_encode(['success' => false, 'message' => 'Permisos insuficients']));
        }
        
        $plugin_slugs = array_map('sanitize_text_field', $_POST['plugin_slugs']);
        $results = [];
        
        foreach ($plugin_slugs as $slug) {
            if (isset($this->plugins[$slug])) {
                $plugin_config = $this->plugins[$slug];
                $source_url = isset($plugin_config['source']) ? $plugin_config['source'] : null;
                
                // Install plugin
                $install_result = $this->install_plugin($slug, $source_url);
                if (is_wp_error($install_result)) {
                    $results[$slug] = [
                        'success' => false,
                        'action' => 'install',
                        'message' => $install_result->get_error_message()
                    ];
                    continue;
                }
                
                // Activate plugin if auto-activation is enabled
                if (isset($plugin_config['auto_activate']) && $plugin_config['auto_activate']) {
                    $activate_result = $this->activate_plugin($slug);
                    if (is_wp_error($activate_result)) {
                        $results[$slug] = [
                            'success' => false,
                            'action' => 'activate',
                            'message' => $activate_result->get_error_message()
                        ];
                        continue;
                    }
                }
                
                $results[$slug] = [
                    'success' => true,
                    'action' => 'complete',
                    'message' => 'Instal·lat i activat correctament'
                ];
            }
        }
        
        wp_die(json_encode(['success' => true, 'results' => $results]));
    }
    
    /**
     * Check if all required plugins are installed and active
     */
    public function are_required_plugins_ready() {
        $required_plugins = $this->get_plugins_by_priority('required');
        
        foreach ($required_plugins as $slug => $plugin) {
            if (!$this->is_plugin_active($slug)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get installation status summary
     */
    public function get_installation_status() {
        $status = [
            'required' => [
                'total' => 0,
                'installed' => 0,
                'active' => 0,
                'missing' => [],
                'inactive' => []
            ],
            'recommended' => [
                'total' => 0,
                'installed' => 0,
                'active' => 0,
                'missing' => [],
                'inactive' => []
            ],
            'optional' => [
                'total' => 0,
                'installed' => 0,
                'active' => 0,
                'missing' => [],
                'inactive' => []
            ]
        ];
        
        foreach (['required', 'recommended', 'optional'] as $priority) {
            $plugins = $this->get_plugins_by_priority($priority);
            $status[$priority]['total'] = count($plugins);
            
            foreach ($plugins as $slug => $plugin) {
                if ($this->is_plugin_installed($slug)) {
                    $status[$priority]['installed']++;
                    
                    if ($this->is_plugin_active($slug)) {
                        $status[$priority]['active']++;
                    } else {
                        $status[$priority]['inactive'][$slug] = $plugin;
                    }
                } else {
                    $status[$priority]['missing'][$slug] = $plugin;
                }
            }
        }
        
        return $status;
    }
}