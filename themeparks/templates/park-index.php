<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';
add_filter('document_title_parts', function ($parts) {
    $parts['title'] = __theme_parks_trans('Wait Times for All Parks');

    return $parts;
});

get_header();

?>

<div <?php generate_do_attr( 'content' ); ?>>
    <main <?php generate_do_attr( 'main' ); ?>>
        <article id="post-0" class="post-0 post type-post status-publish format-standard hentry category-uncategorized entry">
            <div class="inside-article">
                <header class="entry-header alignwide">
                    <h1 class="entry-title">
                        <?php echo esc_html(__theme_parks_trans('Wait Times for All Parks')); ?>
                    </h1>
                </header>
                <div class="entry-content" style="margin-top: 0">
                    <div class="tp-parks tp-parks-columns">
                        <?php foreach (TP_ThemeParks::get_parks(true) as $__park): ?>
                            <div class="tp-parks--item">
                                <div class="tp-parkItem--inner">
                                    <div class="tp-parkItem--image">
                                        <a href="<?php echo esc_url(TP_ThemeParks::get_park_item_url($__park)); ?>"
                                           style="background-image:url(<?php echo esc_url($__park->image_url); ?>)"
                                           title="<?php echo esc_attr($__park->name); ?>"></a>
                                    </div>
                                    <header class="tp-parkItem--header">
                                        <h4 class="tp-parkItem--title">
                                            <a href="<?php echo esc_url(TP_ThemeParks::get_park_item_url($__park)); ?>"
                                               title="<?php echo esc_attr($__park->name); ?>">
                                                <?php echo esc_html($__park->name); ?>
                                            </a>
                                        </h4>
                                        <div class="tp-parkItem--meta">
                                            <span><?php echo esc_html(__theme_parks_trans('Last updated')); ?></span>
                                            <span><?php echo esc_html(wp_date(get_option('links_updated_date_format'), $__park->last_sync_date)); ?></span>
                                        </div>
                                    </header>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </article>
    </main>
</div>

<?php

generate_construct_sidebars();
get_footer();

?>