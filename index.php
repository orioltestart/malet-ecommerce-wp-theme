<?php
/**
 * Index template for Malet Torrent
 * 
 * Aquest tema funciona principalment com a backend API.
 * La interfície visual es gestiona a través de Next.js.
 * 
 * @package Malet Torrent
 * @since 1.0.0
 */

// Evitar accés directe
if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div id="malet-torrent-headless-notice" style="
    max-width: 800px;
    margin: 50px auto;
    padding: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
    text-align: center;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
">
    <h1 style="margin: 0 0 20px 0; font-size: 2.5em; font-weight: 300;">
        🥨 Malet Torrent
    </h1>
    <h2 style="margin: 0 0 30px 0; font-size: 1.5em; font-weight: 400; opacity: 0.9;">
        Pastisseria Tradicional Catalana
    </h2>
    
    <div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 30px; margin: 30px 0;">
        <h3 style="margin: 0 0 15px 0; font-size: 1.2em;">
            Aquest és un lloc headless
        </h3>
        <p style="margin: 0 0 20px 0; line-height: 1.6; opacity: 0.9;">
            La web principal està disponible a:<br>
            <strong style="font-size: 1.1em;">malet.testart.cat</strong>
        </p>
        <p style="margin: 0; line-height: 1.6; opacity: 0.8; font-size: 0.9em;">
            Aquest WordPress funciona com a backend API per gestionar productes, comandes i contingut.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
        <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 20px;">
            <h4 style="margin: 0 0 10px 0;">🛍️ E-commerce</h4>
            <p style="margin: 0; font-size: 0.9em; opacity: 0.8;">
                WooCommerce amb melindros artesans
            </p>
        </div>
        <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 20px;">
            <h4 style="margin: 0 0 10px 0;">🌐 Multiidioma</h4>
            <p style="margin: 0; font-size: 0.9em; opacity: 0.8;">
                Català, Espanyol i Anglès
            </p>
        </div>
        <div style="background: rgba(255,255,255,0.1); border-radius: 6px; padding: 20px;">
            <h4 style="margin: 0 0 10px 0;">⚡ API REST</h4>
            <p style="margin: 0; font-size: 0.9em; opacity: 0.8;">
                Optimitzada per Next.js
            </p>
        </div>
    </div>

    <?php if (is_user_logged_in()): ?>
    <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.2);">
        <p style="margin: 0 0 15px 0; opacity: 0.8;">Gestió del lloc:</p>
        <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
            <a href="<?php echo admin_url(); ?>" style="
                background: rgba(255,255,255,0.2);
                color: white;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 5px;
                transition: background 0.3s;
            ">📊 Admin</a>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" style="
                background: rgba(255,255,255,0.2);
                color: white;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 5px;
                transition: background 0.3s;
            ">🥨 Productes</a>
            <a href="<?php echo admin_url('edit.php'); ?>" style="
                background: rgba(255,255,255,0.2);
                color: white;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 5px;
                transition: background 0.3s;
            ">📝 Blog</a>
            <a href="<?php echo admin_url('admin.php?page=malet-torrent-settings'); ?>" style="
                background: rgba(255,255,255,0.2);
                color: white;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 5px;
                transition: background 0.3s;
            ">⚙️ Configuració</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>