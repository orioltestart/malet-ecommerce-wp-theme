/**
 * Plugin Installer JavaScript for Malet Torrent Theme
 * Handles AJAX installation and activation of plugins
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Plugin Installer object
     */
    const MaletTorrentInstaller = {
        
        /**
         * Initialize the installer
         */
        init: function() {
            this.bindEvents();
            this.setupProgressBars();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Individual plugin actions
            $(document).on('click', '.install-plugin', this.installPlugin);
            $(document).on('click', '.activate-plugin', this.activatePlugin);
            
            // Bulk actions
            $(document).on('click', '.install-all-required', this.installBulkPlugins);
            $(document).on('click', '.install-all-recommended', this.installBulkPlugins);
            
            // Notice dismissal
            $(document).on('click', '.malet-torrent-dismiss', this.dismissNotice);
            
            // Toggle optional plugins
            $(document).on('click', '.toggle-optional-plugins', this.toggleOptionalPlugins);
        },
        
        /**
         * Setup progress bars
         */
        setupProgressBars: function() {
            $('.bulk-progress').each(function() {
                const $progress = $(this);
                const $fill = $progress.find('.progress-fill');
                const $text = $progress.find('.progress-text');
                
                $progress.data('progress', 0);
                $fill.css('width', '0%');
                $text.text('0%');
            });
        },
        
        /**
         * Install individual plugin
         */
        installPlugin: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $item = $button.closest('.malet-torrent-plugin-item');
            const $status = $item.find('.plugin-status');
            const slug = $item.data('slug');
            
            MaletTorrentInstaller.setPluginStatus($item, 'installing', maletTorrentInstaller.strings.installing);
            $button.prop('disabled', true);
            
            $.ajax({
                url: maletTorrentInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'malet_torrent_install_plugin',
                    plugin_slug: slug,
                    nonce: maletTorrentInstaller.nonces.install
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    
                    if (data.success) {
                        MaletTorrentInstaller.setPluginStatus($item, 'success', maletTorrentInstaller.strings.installed);
                        
                        // Change button to activate
                        $button.removeClass('install-plugin button-primary')
                               .addClass('activate-plugin button-secondary')
                               .html('<span class="dashicons dashicons-admin-plugins"></span> Activar')
                               .prop('disabled', false);
                    } else {
                        MaletTorrentInstaller.setPluginStatus($item, 'error', data.message || maletTorrentInstaller.strings.error);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    MaletTorrentInstaller.setPluginStatus($item, 'error', maletTorrentInstaller.strings.error);
                    $button.prop('disabled', false);
                }
            });
        },
        
        /**
         * Activate individual plugin
         */
        activatePlugin: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $item = $button.closest('.malet-torrent-plugin-item');
            const slug = $item.data('slug');
            
            MaletTorrentInstaller.setPluginStatus($item, 'activating', maletTorrentInstaller.strings.activating);
            $button.prop('disabled', true);
            
            $.ajax({
                url: maletTorrentInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'malet_torrent_activate_plugin',
                    plugin_slug: slug,
                    nonce: maletTorrentInstaller.nonces.activate
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    
                    if (data.success) {
                        MaletTorrentInstaller.setPluginStatus($item, 'success', maletTorrentInstaller.strings.activated);
                        $button.fadeOut(300, function() {
                            $item.addClass('plugin-activated');
                        });
                        
                        // Check if all required plugins are now installed
                        MaletTorrentInstaller.checkAllPluginsStatus();
                    } else {
                        MaletTorrentInstaller.setPluginStatus($item, 'error', data.message || maletTorrentInstaller.strings.error);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    MaletTorrentInstaller.setPluginStatus($item, 'error', maletTorrentInstaller.strings.error);
                    $button.prop('disabled', false);
                }
            });
        },
        
        /**
         * Install bulk plugins
         */
        installBulkPlugins: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $notice = $button.closest('.malet-torrent-notice');
            const priority = $notice.data('priority');
            const $progress = $button.siblings('.bulk-progress');
            const $items = $notice.find('.malet-torrent-plugin-item');
            
            // Get plugin slugs
            const pluginSlugs = [];
            $items.each(function() {
                const slug = $(this).data('slug');
                if (slug) {
                    pluginSlugs.push(slug);
                }
            });
            
            if (pluginSlugs.length === 0) {
                return;
            }
            
            // Disable button and show progress
            $button.prop('disabled', true).text(maletTorrentInstaller.strings.installing_bulk);
            $progress.show();
            
            // Reset all plugin statuses
            $items.each(function() {
                MaletTorrentInstaller.setPluginStatus($(this), 'waiting', maletTorrentInstaller.strings.installing);
            });
            
            // Install plugins one by one with progress updates
            MaletTorrentInstaller.installPluginsSequentially(pluginSlugs, 0, $progress, $items, function() {
                $button.prop('disabled', false).text(maletTorrentInstaller.strings.completed);
                
                setTimeout(function() {
                    $notice.fadeOut(500);
                }, 2000);
            });
        },
        
        /**
         * Install plugins sequentially
         */
        installPluginsSequentially: function(pluginSlugs, index, $progress, $items, callback) {
            if (index >= pluginSlugs.length) {
                callback();
                return;
            }
            
            const slug = pluginSlugs[index];
            const $currentItem = $items.filter('[data-slug="' + slug + '"]');
            const progress = Math.round(((index + 1) / pluginSlugs.length) * 100);
            
            // Update progress bar
            MaletTorrentInstaller.updateProgress($progress, progress);
            
            // Set current item as installing
            MaletTorrentInstaller.setPluginStatus($currentItem, 'installing', maletTorrentInstaller.strings.installing);
            
            $.ajax({
                url: maletTorrentInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'malet_torrent_install_plugin',
                    plugin_slug: slug,
                    nonce: maletTorrentInstaller.nonces.install
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    
                    if (data.success) {
                        MaletTorrentInstaller.setPluginStatus($currentItem, 'success', maletTorrentInstaller.strings.installed);
                        
                        // Try to activate the plugin
                        MaletTorrentInstaller.activatePluginInSequence($currentItem, slug, function() {
                            // Continue with next plugin
                            setTimeout(function() {
                                MaletTorrentInstaller.installPluginsSequentially(pluginSlugs, index + 1, $progress, $items, callback);
                            }, 500);
                        });
                    } else {
                        MaletTorrentInstaller.setPluginStatus($currentItem, 'error', data.message || maletTorrentInstaller.strings.failed);
                        
                        // Continue with next plugin even if this one failed
                        setTimeout(function() {
                            MaletTorrentInstaller.installPluginsSequentially(pluginSlugs, index + 1, $progress, $items, callback);
                        }, 500);
                    }
                },
                error: function() {
                    MaletTorrentInstaller.setPluginStatus($currentItem, 'error', maletTorrentInstaller.strings.failed);
                    
                    // Continue with next plugin even if this one failed
                    setTimeout(function() {
                        MaletTorrentInstaller.installPluginsSequentially(pluginSlugs, index + 1, $progress, $items, callback);
                    }, 500);
                }
            });
        },
        
        /**
         * Activate plugin in sequence
         */
        activatePluginInSequence: function($item, slug, callback) {
            MaletTorrentInstaller.setPluginStatus($item, 'activating', maletTorrentInstaller.strings.activating);
            
            $.ajax({
                url: maletTorrentInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'malet_torrent_activate_plugin',
                    plugin_slug: slug,
                    nonce: maletTorrentInstaller.nonces.activate
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    
                    if (data.success) {
                        MaletTorrentInstaller.setPluginStatus($item, 'success', maletTorrentInstaller.strings.activated);
                        $item.addClass('plugin-activated');
                    } else {
                        MaletTorrentInstaller.setPluginStatus($item, 'error', data.message || maletTorrentInstaller.strings.failed);
                    }
                    
                    callback();
                },
                error: function() {
                    MaletTorrentInstaller.setPluginStatus($item, 'error', maletTorrentInstaller.strings.failed);
                    callback();
                }
            });
        },
        
        /**
         * Set plugin status
         */
        setPluginStatus: function($item, status, message) {
            const $status = $item.find('.plugin-status');
            const $statusText = $status.find('.status-text');
            const $spinner = $status.find('.spinner');
            
            $item.removeClass('status-installing status-activating status-success status-error status-waiting')
                 .addClass('status-' + status);
            
            $statusText.text(message);
            
            if (status === 'installing' || status === 'activating') {
                $spinner.addClass('is-active');
            } else {
                $spinner.removeClass('is-active');
            }
        },
        
        /**
         * Update progress bar
         */
        updateProgress: function($progress, percentage) {
            const $fill = $progress.find('.progress-fill');
            const $text = $progress.find('.progress-text');
            
            $fill.css('width', percentage + '%');
            $text.text(percentage + '%');
        },
        
        /**
         * Check if all plugins are installed
         */
        checkAllPluginsStatus: function() {
            const $requiredNotice = $('.malet-torrent-notice[data-priority="required"]');
            const $requiredItems = $requiredNotice.find('.malet-torrent-plugin-item');
            let allActivated = true;
            
            $requiredItems.each(function() {
                if (!$(this).hasClass('plugin-activated')) {
                    allActivated = false;
                    return false;
                }
            });
            
            if (allActivated) {
                $requiredNotice.addClass('all-plugins-completed');
                
                setTimeout(function() {
                    $requiredNotice.fadeOut(500);
                }, 2000);
                
                // Show success message
                MaletTorrentInstaller.showSuccessMessage();
            }
        },
        
        /**
         * Show success message
         */
        showSuccessMessage: function() {
            const $successMessage = $('<div class="notice notice-success malet-torrent-success-notice">' +
                '<h3><span class="dashicons dashicons-yes-alt"></span> Plugins Requerits Instal·lats!</h3>' +
                '<p>Tots els plugins essencials s\'han instal·lat correctament. El teu lloc web està llest per funcionar.</p>' +
                '<p><a href="' + window.location.href + '" class="button button-primary">Actualitzar Pàgina</a></p>' +
                '</div>');
            
            $('.malet-torrent-notice').first().before($successMessage);
            
            // Auto refresh after 5 seconds
            setTimeout(function() {
                window.location.reload();
            }, 5000);
        },
        
        /**
         * Dismiss notice
         */
        dismissNotice: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $notice = $button.closest('.malet-torrent-notice');
            const priority = $notice.data('priority');
            
            $.ajax({
                url: maletTorrentInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'malet_torrent_dismiss_notice',
                    priority: priority,
                    nonce: maletTorrentInstaller.nonces.dismiss
                },
                success: function() {
                    $notice.fadeOut(300);
                }
            });
        },
        
        /**
         * Toggle optional plugins visibility
         */
        toggleOptionalPlugins: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $list = $button.closest('.malet-torrent-notice-content').find('.malet-torrent-plugins-list');
            const $icon = $button.find('.dashicons');
            
            if ($list.hasClass('collapsed')) {
                $list.removeClass('collapsed');
                $icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
            } else {
                $list.addClass('collapsed');
                $icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
            }
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof maletTorrentInstaller !== 'undefined') {
            MaletTorrentInstaller.init();
        }
    });
    
})(jQuery);