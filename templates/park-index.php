<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';
add_filter('document_title_parts', function ($parts) {
    $parts['title'] = __('Wait Times for All Parks');

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
                        <?php echo __('Wait Times for All Parks'); ?>
                    </h1>
                </header>
                <div class="entry-content">
                    <ul style="margin-top:0;margin-bottom:0">
                        <?php foreach (TP_ThemeParks::get_parks(true) as $__park): ?>
                            <li>
                                <a href="<?php echo esc_url(TP_ThemeParks::get_link_park_item($__park)); ?>"
                                   class="themeparks-park--item"><?php echo esc_html($__park->name); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </article>
    </main>
</div>

<?php

generate_construct_sidebars();
get_footer();

?>