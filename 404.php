<?php
/**
 * 404 template
 * 
 * @package Malet Torrent
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div style="max-width: 600px; margin: 100px auto; padding: 40px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; text-align: center;">
    <h1 style="color: #856404; margin-bottom: 20px; font-size: 3em;">🔍</h1>
    <h2 style="color: #856404; margin-bottom: 20px;">Pàgina no trobada</h2>
    <p style="color: #856404; margin-bottom: 30px; line-height: 1.6;">
        Ho sentim, la pàgina que cerques no existeix o ha estat moguda.<br>
        Aquest és un lloc headless - el contingut principal està a <strong>malet.testart.cat</strong>
    </p>
    
    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <a href="<?php echo home_url(); ?>" style="
            background: #0073aa;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            display: inline-block;
        ">🏠 Inici</a>
        
        <a href="https://malet.testart.cat" style="
            background: #28a745;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            display: inline-block;
        " target="_blank">🌐 Web Principal</a>
        
        <?php if (is_user_logged_in()): ?>
        <a href="<?php echo admin_url(); ?>" style="
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            display: inline-block;
        ">⚙️ Admin</a>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #ffeaa7;">
        <p style="color: #856404; font-size: 14px; margin: 0;">
            Si ets un desenvolupador, comprova els logs d'error o la configuració de l'API REST.
        </p>
    </div>
</div>

<?php get_footer(); ?>