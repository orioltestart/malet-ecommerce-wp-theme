<?php
/**
 * Single post template
 * 
 * @package Malet Torrent
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div style="max-width: 800px; margin: 50px auto; padding: 40px; background: #f8f9fa; border-radius: 8px;">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article>
            <h1 style="color: #333; margin-bottom: 20px;"><?php the_title(); ?></h1>
            <div style="color: #666; margin-bottom: 30px;">
                Publicat el <?php the_date(); ?> per <?php the_author(); ?>
            </div>
            <div style="line-height: 1.6;">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; endif; ?>
    
    <div style="margin-top: 40px; text-align: center;">
        <a href="<?php echo home_url(); ?>" style="
            background: #0073aa;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            display: inline-block;
        ">← Tornar a l'inici</a>
    </div>
</div>

<?php get_footer(); ?>