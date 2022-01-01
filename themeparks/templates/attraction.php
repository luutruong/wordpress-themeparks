<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';
require_once TP_THEMEPARKS__PLUGIN_DIR . 'includes/class-park.php';

$__tp_attraction_id = (int) get_query_var(TP_ThemeParks::QUERY_VAR_ATTRACTION_ID);
$__tp_attraction = TP_ThemeParks::get_attraction($__tp_attraction_id);

if (!empty($__tp_attraction)) {
    add_filter('document_title_parts', function ($parts) use ($__tp_attraction) {
        $parts['title'] = esc_html($__tp_attraction['name']);

        return $parts;
    });
}

add_action('wp_head', function () {
    echo "<script type=\"text/javascript\" src=\"https://www.gstatic.com/charts/loader.js\"></script>";
});

get_header();

if (empty($__tp_attraction)) {
    wp_die('Requested attraction could not be found.');
}

$__tp_park_info = new TP_ThemeParks_Park($__tp_attraction['park']);
$__tp_params = $__tp_park_info->get_attraction_view_params($__tp_attraction);

?>

<div <?php generate_do_attr('content'); ?>>
    <main <?php generate_do_attr('main'); ?>>
        <article id="post-0" class="post-0 post type-post status-publish format-standard hentry category-uncategorized entry">
            <div class="inside-article">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo esc_url(site_url()); ?>"><?php echo esc_html(get_option('blogname')) ?></a></li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo esc_url(TP_ThemeParks::get_park_list_url()); ?>"><?php echo esc_html(__theme_parks_trans('All Parks')); ?></a>
                        </li>
                        <li class="breadcrumb-item" aria-current="page">
                            <a href="<?php echo esc_url(TP_ThemeParks::get_park_item_url($__tp_attraction['park'])); ?>">
                                <?php echo esc_html($__tp_attraction['park']->name); ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo esc_html($__tp_attraction['name']); ?></li>
                    </ol>
                </nav>

                <header class="entry-header alignwide">
                    <h1 class="entry-title">
                        <?php echo esc_html($__tp_attraction['name']); ?>
                    </h1>
                </header>

                <div class="entry-content">
                    <h3><?php echo esc_html(__theme_parks_trans('Wait Times Today')); ?></h3>
                    <div class="js-chart-element"
                         data-wait-date="<?php echo esc_attr($__tp_park_info->get_wait_date()); ?>"
                         data-haxis-title="<?php echo esc_attr(__theme_parks_trans('Time of Day')); ?>"
                         data-wait="<?php echo esc_attr(json_encode($__tp_params['chart_data'])); ?>"
                         style="width: 100%;height: 500px"></div>

                    <h3><?php echo esc_html(__theme_parks_trans('Wait Times Yesterday')); ?></h3>
                    <div class="js-chart-element"
                         data-wait-date="<?php echo esc_attr($__tp_params['chart_data_yesterday_date']); ?>"
                         data-haxis-title="<?php echo esc_attr(__theme_parks_trans('Time of Day')); ?>"
                         data-wait="<?php echo esc_attr(json_encode($__tp_params['chart_data_yesterday'])); ?>"
                         style="width: 100%;height: 500px"></div>

                    <h3><?php echo esc_html(__theme_parks_trans('Wait Times in 7 Days')); ?></h3>
                    <div class="js-chart-element"
                         data-wait-date="<?php echo esc_attr($__tp_params['chart_data_7_days']['start_date'] . ' - ' . $__tp_params['chart_data_7_days']['end_date']); ?>"
                         data-haxis-title="<?php echo esc_attr(__theme_parks_trans('Day')); ?>"
                         data-wait="<?php echo esc_attr(json_encode($__tp_params['chart_data_7_days']['data'])); ?>"
                         style="width: 100%;height: 500px"></div>
                </div>
            </div>
        </article>
    </main>
</div>

<?php

get_footer();

?>
