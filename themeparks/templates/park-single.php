<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';
require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks-api.php';
require_once TP_THEMEPARKS__PLUGIN_DIR . 'includes/class-park.php';

$__tp_slug = get_query_var(TP_ThemeParks::QUERY_VAR_PARK_SLUG);

$__tp_park = TP_ThemeParks::get_park_by_slug($__tp_slug);
if (empty($__tp_park) || empty($__tp_park->active)) {
    return;
}

add_filter('document_title_parts', function ($parts) use ($__tp_park) {
    $parts['title'] = sprintf('%s %s',
        esc_html(__theme_parks_trans('Wait Times at')),
        esc_html($__tp_park->name)
    );

    return $parts;
});

add_action('wp_head', function () {
   echo "<script type=\"text/javascript\" src=\"https://www.gstatic.com/charts/loader.js\"></script>";
});

get_header();

$__tp_park_info = new TP_ThemeParks_Park($__tp_park);

?>

<div <?php generate_do_attr('content'); ?>>
    <main <?php generate_do_attr( 'main' ); ?>>
        <article id="post-0" class="post-0 post type-post status-publish format-standard hentry category-uncategorized entry">
            <div class="inside-article">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo esc_url(site_url()); ?>"><?php echo esc_html(get_option('blogname')) ?></a></li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo esc_url(TP_ThemeParks::get_park_list_url()); ?>"><?php echo esc_html(__theme_parks_trans('All Parks')); ?></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo esc_html($__tp_park->name); ?></li>
                    </ol>
                </nav>

                <header class="entry-header alignwide">
                    <h1 class="entry-title">
                        <?php echo sprintf('%s %s',
                            esc_html(__theme_parks_trans('Wait Times at')),
                            esc_html($__tp_park->name)
                        ); ?>
                    </h1>
                </header>
                <div class="entry-content">
                    <p style="margin:0"><?php echo sprintf('%s: %s %s %s',
                            '<strong>' . esc_html(__theme_parks_trans('Park Hours')) . '</strong>',
                            $__tp_park_info->get_open_time(),
                            esc_html(__theme_parks_trans('to')),
                            $__tp_park_info->get_close_time()
                        ); ?></p>
                    <p style="margin:0"><?php echo sprintf('%s: %s',
                            '<strong>' . esc_html(__theme_parks_trans('Park Status')) . '</strong>',
                            $__tp_park_info->get_status()); ?></p>

                    <?php require_once TP_THEMEPARKS__PLUGIN_DIR . 'templates/charts.php'; ?>

                    <h3><strong><?php echo esc_html(__theme_parks_trans('Park Insights')); ?></strong></h3>
                    <ul>
                        <li><?php echo sprintf(
                                '<strong>%s</strong>: %d',
                                __theme_parks_trans('Total Attractions'),
                                $__tp_park_info->get_total_attractions()
                            ); ?></li>
                        <?php foreach($__tp_park_info->get_park_insights() as $__insight): ?>
                            <li>
                                <div><strong><?php echo sprintf('%s (%s)', esc_html($__insight['title']), esc_html($__insight['data']['date_range'])); ?></strong></div>
                                <ul>
                                    <?php foreach($__insight['data']['attractions'] as $__attraction): ?>
                                        <li>
                                            <ul class="list-inline list--bullet">
                                                <li><a href="<?php echo esc_url($__attraction['view_url']); ?>"
                                                       class="tp-park--attraction"><?php echo esc_html($__attraction['name']); ?></a></li>
                                                <li><small><?php echo esc_html(sprintf('%s: %s %s',
                                                        __theme_parks_trans('Average wait time'),
                                                        $__attraction['avg_wait_time'],
                                                        __theme_parks_trans('minutes')
                                                    )); ?></small></li>
                                            </ul>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php foreach($__tp_park_info->get_attractions_overview() as $__item): ?>
                        <?php if(!empty($__item['attractions'])): ?>
                        <h3><strong><?php echo esc_html($__item['title']); ?></strong></h3>
                        <ul>
                            <?php if(isset($__item['with_wait_times'])): ?>
                                <?php foreach($__item['attractions'] as $__attraction): ?>
                                    <li>
                                        <ul class="list-inline list--bullet">
                                            <li><a href="<?php echo esc_url($__attraction['view_url']); ?>"
                                                   class="tp-park--attraction"><?php echo esc_html($__attraction['name']); ?></a></li>
                                            <li>
                                                <small>
                                                    <?php if($__attraction['avg_wait_time'] > 0): ?>
                                                        <?php echo esc_html(sprintf(
                                                            '%s: %s %s',
                                                            __theme_parks_trans('Average wait time'),
                                                            $__attraction['avg_wait_time'],
                                                            __theme_parks_trans('minutes')
                                                        )); ?>
                                                    <?php else: ?>
                                                        <?php echo esc_html(sprintf('%s: %s', __theme_parks_trans('Status'), $__attraction['status'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </li>
                                        </ul>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach($__item['attractions'] as $__attraction): ?>
                                    <li data-type="<?php echo esc_attr($__attraction['attraction_type']); ?>"
                                        data-id="<?php echo esc_attr($__attraction['attraction_id']); ?>">
                                        <a href="<?php echo esc_url($__attraction['view_url']); ?>"
                                           class="tp-park--attraction"><?php echo esc_html($__attraction['name']); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>
    </main>
</div>

<?php

get_footer();

?>
