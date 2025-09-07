<form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <label for="search-field" class="screen-reader-text">Cercar:</label>
    <input type="search" id="search-field" name="s" value="<?php echo get_search_query(); ?>" 
           placeholder="Cercar..." style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
    <button type="submit" style="padding: 8px 16px; background: #0073aa; color: white; border: none; border-radius: 4px; margin-left: 5px;">
        Cercar
    </button>
</form>