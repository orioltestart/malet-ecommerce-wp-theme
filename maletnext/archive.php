<?php
/**
 * Archive template
 * 
 * @package Malet Torrent
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div style="max-width: 800px; margin: 50px auto; padding: 40px; background: #f8f9fa; border-radius: 8px;">
    <h1 style="color: #333; margin-bottom: 30px;">
        <?php
        if (is_category()) {
            single_cat_title('Categoria: ');
        } elseif (is_tag()) {
            single_tag_title('Etiqueta: ');
        } elseif (is_author()) {
            echo 'Autor: ' . get_the_author();
        } elseif (is_date()) {
            echo 'Arxiu per data';
        } else {
            echo 'Arxiu';
        }
        ?>
    </h1>
    
    <?php if (have_posts()) : ?>
        <div style="display: grid; gap: 20px;">
            <?php while (have_posts()) : the_post(); ?>
                <article style="background: white; padding: 20px; border-radius: 4px; border-left: 4px solid #0073aa;">
                    <h2 style="margin: 0 0 10px 0;">
                        <a href="<?php the_permalink(); ?>" style="color: #0073aa; text-decoration: none;">
                            <?php the_title(); ?>
                        </a>
                    </h2>
                    <div style="color: #666; font-size: 14px; margin-bottom: 15px;">
                        <?php the_date(); ?> per <?php the_author(); ?>
                    </div>
                    <div>
                        <?php the_excerpt(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        
        <div style="margin-top: 40px; text-align: center;">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p>No s'han trobat articles en aquesta categoria.</p>
    <?php endif; ?>
    
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