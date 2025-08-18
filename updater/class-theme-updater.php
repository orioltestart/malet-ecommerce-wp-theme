<?php
/**
 * Theme Updater for Malet Torrent
 * Handles automatic theme updates via GitHub API
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Malet_Torrent_Theme_Updater {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * GitHub API base URL
     */
    private $github_api_url = 'https://api.github.com';
    
    /**
     * Theme data
     */
    private $theme_data;
    
    /**
     * GitHub repository information
     */
    private $github_user;
    private $github_repo;
    
    /**
     * Transient keys
     */
    private $transient_key = 'malet_torrent_update_check';
    private $version_key = 'malet_torrent_latest_version';
    
    /**
     * Get singleton instance
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
        $this->theme_data = wp_get_theme();
        $this->github_user = defined('MALET_TORRENT_GITHUB_USER') ? MALET_TORRENT_GITHUB_USER : '';
        $this->github_repo = defined('MALET_TORRENT_GITHUB_REPO') ? MALET_TORRENT_GITHUB_REPO : '';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // WordPress update system integration
        add_filter('pre_set_site_transient_update_themes', [$this, 'check_for_updates']);
        add_filter('themes_api', [$this, 'themes_api_handler'], 10, 3);
        add_filter('upgrader_process_complete', [$this, 'after_update'], 10, 2);
        
        // Admin AJAX handlers
        add_action('wp_ajax_malet_torrent_check_updates', [$this, 'ajax_check_updates']);
        add_action('wp_ajax_malet_torrent_install_update', [$this, 'ajax_install_update']);
        
        // Cleanup on theme switch
        add_action('switch_theme', [$this, 'cleanup_transients']);
    }
    
    /**
     * Check for theme updates
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $theme_slug = wp_get_theme()->get_stylesheet();
        $current_version = $this->theme_data->get('Version');
        
        // Get cached version data first
        $latest_version = get_site_transient($this->version_key);
        
        // Only fetch from GitHub if we should check for updates
        if ($this->should_check_for_updates()) {
            $latest_version = $this->get_latest_version();
        }
        
        if ($latest_version && version_compare($current_version, $latest_version['version'], '<')) {
            $update_data = [
                'theme' => $theme_slug,
                'new_version' => $latest_version['version'],
                'url' => $latest_version['details_url']
            ];
            
            // Only include package if valid download URL exists
            if ($latest_version['download_url']) {
                $update_data['package'] = $latest_version['download_url'];
            }
            
            $transient->response[$theme_slug] = $update_data;
        }
        
        return $transient;
    }
    
    /**
     * Handle themes API requests
     */
    public function themes_api_handler($result, $action, $args) {
        if ($action !== 'theme_information') {
            return $result;
        }
        
        if ($args->slug !== wp_get_theme()->get_stylesheet()) {
            return $result;
        }
        
        $latest_version = $this->get_latest_version();
        
        if (!$latest_version) {
            return $result;
        }
        
        return (object) [
            'name' => $this->theme_data->get('Name'),
            'slug' => wp_get_theme()->get_stylesheet(),
            'version' => $latest_version['version'],
            'author' => $this->theme_data->get('Author'),
            'homepage' => $this->theme_data->get('ThemeURI'),
            'description' => $this->theme_data->get('Description'),
            'sections' => [
                'description' => $this->theme_data->get('Description'),
                'changelog' => $latest_version['changelog']
            ],
            'download_link' => $latest_version['download_url'],
            'last_updated' => $latest_version['published_at'],
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4'
        ];
    }
    
    /**
     * Check if we should perform update check
     */
    private function should_check_for_updates() {
        $last_check = get_site_transient($this->transient_key);
        $check_interval = defined('MALET_TORRENT_UPDATE_CHECK_INTERVAL') ? 
            MALET_TORRENT_UPDATE_CHECK_INTERVAL : 12 * HOUR_IN_SECONDS;
        
        return false === $last_check || (time() - $last_check) > $check_interval;
    }
    
    /**
     * Get latest version from GitHub
     */
    public function get_latest_version($force_check = false) {
        if (!$this->github_user || !$this->github_repo) {
            return false;
        }
        
        if (!$force_check) {
            $cached_version = get_site_transient($this->version_key);
            if ($cached_version !== false) {
                return $cached_version;
            }
        }
        
        // Check if prereleases are allowed
        $allow_prereleases = defined('MALET_TORRENT_ALLOW_PRERELEASES') && MALET_TORRENT_ALLOW_PRERELEASES;
        
        if ($allow_prereleases) {
            // Fetch all releases and filter for latest (including prereleases)
            $api_url = sprintf(
                '%s/repos/%s/%s/releases',
                $this->github_api_url,
                $this->github_user,
                $this->github_repo
            );
        } else {
            // Fetch only latest stable release
            $api_url = sprintf(
                '%s/repos/%s/%s/releases/latest',
                $this->github_api_url,
                $this->github_user,
                $this->github_repo
            );
        }
        
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Malet-Torrent-Theme-Updater'
        ];
        
        // Add authorization header if token is defined
        if (defined('MALET_TORRENT_GITHUB_TOKEN') && MALET_TORRENT_GITHUB_TOKEN) {
            $headers['Authorization'] = 'Bearer ' . MALET_TORRENT_GITHUB_TOKEN;
        }
        
        $response = wp_remote_get($api_url, [
            'timeout' => 30,
            'headers' => $headers
        ]);
        
        if (is_wp_error($response)) {
            $error_message = 'Failed to fetch latest version: ' . $response->get_error_message();
            $this->log_error($error_message);
            set_site_transient($this->transient_key, time(), 15 * MINUTE_IN_SECONDS);
            set_transient('malet_torrent_update_last_error', $error_message, 15 * MINUTE_IN_SECONDS);
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body_snippet = substr(wp_remote_retrieve_body($response), 0, 200);
            $error_message = "GitHub API returned HTTP {$code}: {$body_snippet}";
            $this->log_error($error_message);
            set_site_transient($this->transient_key, time(), 15 * MINUTE_IN_SECONDS);
            set_transient('malet_torrent_update_last_error', $error_message, 15 * MINUTE_IN_SECONDS);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $releases_data = json_decode($body, true);
        
        if (!$releases_data) {
            $error_message = 'Invalid response from GitHub API';
            $this->log_error($error_message);
            set_site_transient($this->transient_key, time(), 15 * MINUTE_IN_SECONDS);
            set_transient('malet_torrent_update_last_error', $error_message, 15 * MINUTE_IN_SECONDS);
            return false;
        }
        
        // Handle different response formats
        if ($allow_prereleases) {
            // Filter releases array to get the latest one
            if (empty($releases_data) || !is_array($releases_data)) {
                $error_message = 'No releases found in GitHub API response';
                $this->log_error($error_message);
                set_site_transient($this->transient_key, time(), 15 * MINUTE_IN_SECONDS);
                set_transient('malet_torrent_update_last_error', $error_message, 15 * MINUTE_IN_SECONDS);
                return false;
            }
            
            // Filter out draft releases
            $releases_data = array_filter($releases_data, function($release) {
                return empty($release['draft']);
            });
            
            if (empty($releases_data)) {
                $error_message = 'No valid (non-draft) releases found';
                $this->log_error($error_message);
                set_site_transient($this->transient_key, time(), 15 * MINUTE_IN_SECONDS);
                set_transient('malet_torrent_update_last_error', $error_message, 15 * MINUTE_IN_SECONDS);
                return false;
            }
            
            // Sort by semantic version comparison, then by published_at
            usort($releases_data, function($a, $b) {
                $version_a = ltrim($a['tag_name'], 'v');
                $version_b = ltrim($b['tag_name'], 'v');
                
                // Compare versions semantically
                $version_comparison = version_compare($version_b, $version_a);
                
                // If versions are equal, sort by published_at descending
                if ($version_comparison === 0) {
                    return strtotime($b['published_at']) - strtotime($a['published_at']);
                }
                
                return $version_comparison;
            });
            
            // Get the first release (latest)
            $release_data = $releases_data[0];
        } else {
            // Single release object from /releases/latest
            $release_data = $releases_data;
        }
        
        if (!isset($release_data['tag_name'])) {
            $error_message = 'Invalid release data from GitHub API';
            $this->log_error($error_message);
            set_site_transient($this->transient_key, time(), 15 * MINUTE_IN_SECONDS);
            set_transient('malet_torrent_update_last_error', $error_message, 15 * MINUTE_IN_SECONDS);
            return false;
        }
        
        // Skip prereleases if not allowed
        if (!$allow_prereleases && !empty($release_data['prerelease'])) {
            // This shouldn't happen with /releases/latest, but handle it
            return false;
        }
        
        $version_data = [
            'version' => ltrim($release_data['tag_name'], 'v'),
            'download_url' => $this->get_download_url($release_data),
            'details_url' => $release_data['html_url'],
            'changelog' => $this->format_changelog($release_data['body']),
            'published_at' => $release_data['published_at'],
            'prerelease' => $release_data['prerelease']
        ];
        
        // Cache for configured interval
        $check_interval = defined('MALET_TORRENT_UPDATE_CHECK_INTERVAL') ? 
            MALET_TORRENT_UPDATE_CHECK_INTERVAL : 12 * HOUR_IN_SECONDS;
        
        set_site_transient($this->version_key, $version_data, $check_interval);
        set_site_transient($this->transient_key, time(), $check_interval);
        
        return $version_data;
    }
    
    /**
     * Get download URL from release data
     */
    private function get_download_url($release_data) {
        $theme_slug = wp_get_theme()->get_stylesheet();
        
        // Look for theme zip in assets that matches theme slug
        if (!empty($release_data['assets'])) {
            foreach ($release_data['assets'] as $asset) {
                if (strpos($asset['name'], '.zip') !== false && 
                    strpos($asset['name'], $theme_slug) !== false) {
                    return $asset['browser_download_url'];
                }
            }
        }
        
        // No valid asset found
        return null;
    }
    
    /**
     * Format changelog from release notes
     */
    private function format_changelog($body) {
        if (empty($body)) {
            return 'No changelog available.';
        }
        
        // Escape HTML first
        $changelog = esc_html($body);
        
        // Convert markdown headers (only at line start)
        $changelog = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $changelog);
        $changelog = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $changelog);
        $changelog = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $changelog);
        
        // Convert markdown lists (only at line start)
        $changelog = preg_replace('/^\- (.+)$/m', '• $1', $changelog);
        $changelog = preg_replace('/^\* (.+)$/m', '• $1', $changelog);
        
        // Convert bold text
        $changelog = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $changelog);
        
        // Convert italic text
        $changelog = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $changelog);
        
        // Convert code blocks
        $changelog = preg_replace('/`(.+?)`/', '<code>$1</code>', $changelog);
        
        // Convert line breaks to paragraphs
        $changelog = wpautop($changelog);
        
        return $changelog;
    }
    
    /**
     * Get update status
     */
    public function get_update_status($latest_version = null) {
        $current_version = $this->theme_data->get('Version');
        
        // Use provided version or fetch if not provided
        if ($latest_version === null) {
            $latest_version = $this->get_latest_version();
        }
        
        $status = [
            'current_version' => $current_version,
            'latest_version' => $latest_version ? $latest_version['version'] : null,
            'update_available' => false,
            'last_checked' => get_site_transient($this->transient_key),
            'error' => null
        ];
        
        if ($latest_version && version_compare($current_version, $latest_version['version'], '<')) {
            $status['update_available'] = true;
            $status['download_url'] = $latest_version['download_url'];
            $status['changelog'] = $latest_version['changelog'];
            $status['published_at'] = $latest_version['published_at'];
        }
        
        return $status;
    }
    
    /**
     * AJAX handler for checking updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('malet_torrent_check_updates', 'nonce');
        
        if (!current_user_can('update_themes')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Fetch latest version once and pass to get_update_status
        $latest_version = $this->get_latest_version(true);
        $status = $this->get_update_status($latest_version);
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX handler for installing updates
     */
    public function ajax_install_update() {
        check_ajax_referer('malet_torrent_install_update', 'nonce');
        
        if (!current_user_can('update_themes')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // TODO: Phase 2 - Improve backup system with cloud storage options
        // Try to create backup before updating (non-blocking)
        $backup_created = $this->create_backup();
        
        if (!$backup_created) {
            $this->log_error('Backup creation failed, proceeding with update');
            // Note: Continue with update even if backup fails
        }
        
        // Force update check and get latest version
        $latest_version = $this->force_update_check();
        
        if (!$latest_version) {
            wp_send_json_error('No update available');
        }
        
        if (empty($latest_version['download_url'])) {
            wp_send_json_error(__('No update package available for automatic installation.', 'malet-torrent'));
        }
        
        // Perform update
        $result = $this->install_update();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Install theme update
     */
    private function install_update() {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        
        // Force update check to populate transient
        wp_update_themes();
        
        $upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->upgrade(wp_get_theme()->get_stylesheet());
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => $result->get_error_message()
            ];
        }
        
        if ($result === false || $result === null) {
            return [
                'success' => false,
                'message' => 'Theme upgrade failed - no response from upgrader'
            ];
        }
        
        // Clear update transients
        $this->cleanup_transients();
        
        return [
            'success' => true,
            'message' => 'Theme updated successfully'
        ];
    }
    
    /**
     * Create backup before update
     * TODO: Phase 2 - Add cloud storage backup options (S3, Google Drive, etc.)
     */
    private function create_backup() {
        $backup_dir = wp_upload_dir()['basedir'] . '/malet-torrent-backups';
        
        if (!wp_mkdir_p($backup_dir)) {
            return false;
        }
        
        $theme_dir = get_template_directory();
        $backup_file = $backup_dir . '/theme-backup-' . date('Y-m-d-H-i-s') . '.zip';
        
        // Create zip backup
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            
            if ($zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $this->add_directory_to_zip($zip, $theme_dir, basename($theme_dir));
                $zip->close();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add directory to zip archive
     */
    private function add_directory_to_zip($zip, $dir, $zip_dir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = $zip_dir . '/' . substr($file_path, strlen($dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    /**
     * Handle post-update actions
     */
    public function after_update($upgrader, $hook_extra) {
        if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'theme') {
            return;
        }
        
        if (!isset($hook_extra['theme']) || $hook_extra['theme'] !== wp_get_theme()->get_stylesheet()) {
            return;
        }
        
        // Clear all update-related transients
        $this->cleanup_transients();
        
        // Log successful update
        $this->log_message('Theme updated successfully');
        
        // Set update notice
        set_transient('malet_torrent_update_success', true, MINUTE_IN_SECONDS * 5);
    }
    
    /**
     * Cleanup transients
     */
    public function cleanup_transients() {
        delete_site_transient($this->transient_key);
        delete_site_transient($this->version_key);
        delete_site_transient('update_themes');
    }
    
    /**
     * Log error message
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Malet Torrent Updater Error: ' . $message);
        }
    }
    
    /**
     * Log general message
     */
    private function log_message($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Malet Torrent Updater: ' . $message);
        }
    }
    
    /**
     * Get GitHub repository URL
     */
    public function get_repository_url() {
        if (!$this->github_user || !$this->github_repo) {
            return false;
        }
        
        return sprintf('https://github.com/%s/%s', $this->github_user, $this->github_repo);
    }
    
    /**
     * Force update check
     */
    public function force_update_check() {
        $this->cleanup_transients();
        return $this->get_latest_version(true);
    }
}