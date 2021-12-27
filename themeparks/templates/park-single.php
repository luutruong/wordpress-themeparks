<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';
require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks-api.php';
require_once TP_THEMEPARKS__PLUGIN_DIR . 'includes/class-park.php';

ob_start();
$__slug = get_query_var(TP_ThemeParks::QUERY_VAR_PARK_SLUG);

$__park = TP_ThemeParks::get_park_by_slug($__slug);
if (empty($__park) || empty($__park->active)) {
    return;
}

add_filter('document_title_parts', function ($parts) use ($__park) {
    $parts['title'] = __('Wait Times at') . ' ' . esc_html($__park->name);

    return $parts;
});

add_action('wp_head', function () {
   echo "<script type=\"text/javascript\" src=\"https://www.gstatic.com/charts/loader.js\"></script>";
   echo "<style>
.park-hours {
    display: inline-block;
    padding: .35em .65em;
    font-size: .75em;
    font-weight: 700;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: .25rem;
    background-color: rgb(13,110,253);
}
.breadcrumb {
    display: flex;
    flex-wrap: wrap;
    padding: 0;
    margin: 0;
    list-style: none;
}
.breadcrumb-item+.breadcrumb-item::before {
    float: left;
    padding-right: .5rem;
    color: #6c757d;
    content: \"/\";
}
.breadcrumb-item+.breadcrumb-item {
    padding-left: .5rem;
}
.breadcrumb-item.active {
    color: #6c757d;
}
.site-content .content-area {
    width: 100%;
    box-sizing: border-box;
}
.list-inline {
    list-style: none;
    margin: 0;
    padding: 0;
}
.list-inline li {
    display: inline;
}
.list--bullet li + li:before {
    content: \"\\00B7\\20\";
}
   </style>";
});

get_header();

$__api_url = TP_ThemeParks::option_get_api_url();
$__park_info = new TP_ThemeParks_Park($__park);

?>

<div <?php generate_do_attr( 'content' ); ?>>
    <main <?php generate_do_attr( 'main' ); ?>>
        <article id="post-0" class="post-0 post type-post status-publish format-standard hentry category-uncategorized entry">
            <div class="inside-article">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo esc_url(site_url()); ?>"><?php echo esc_html(get_option('blogname')) ?></a></li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo esc_url(TP_ThemeParks::get_park_list_url()); ?>"><?php echo esc_html(__('All Parks')); ?></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo __('Wait Times at') . ' ' . esc_html($__park->name); ?></li>
                    </ol>
                </nav>

                <header class="entry-header alignwide">
                    <h1 class="entry-title">
                        <?php echo __('Wait Times at') . ' ' . esc_html($__park->name); ?>
                    </h1>
                </header>
                <div class="entry-content">
                    <p style="margin:0"><?php echo sprintf('%s: %s %s %s',
                            '<strong>' . esc_html(__('Park Hours')) . '</strong>',
                            $__park_info->get_open_time(),
                            esc_html(__('to')),
                            $__park_info->get_close_time()
                        ); ?></p>
                    <p style="margin:0"><?php echo sprintf('%s: %s',
                            '<strong>' . esc_html(__('Park Status')) . '</strong>',
                            $__park_info->get_status()); ?></p>

                    <div id="park-wait--times--chart" data-wait="<?php echo esc_attr(json_encode($__park_info->get_wait_data_chart())); ?>"
                         style="width: 100%;height: 500px"></div>
                    <script type="text/javascript">
                        google.charts.load('current', {packages: ['corechart', 'line']});
                        google.charts.setOnLoadCallback(__drawBasic);
                        var chart_element = document.getElementById('park-wait--times--chart');
                        function __drawBasic() {
                            var data = new google.visualization.DataTable();
                            data.addColumn('string', 'X');
                            data.addColumn('number', '<?php echo esc_js(__('Minutes')); ?>');
                            data.addRows(JSON.parse(chart_element.getAttribute('data-wait')));

                            var options = {
                                hAxis: {
                                    title: '<?php echo esc_js(__('Time of Day')); ?>',
                                },
                                vAxis: {
                                    title: '<?php echo esc_js(__('Wait Time (minutes)')); ?>'
                                },
                                legend: {position: 'none'},
                                theme: {
                                    chartArea: {width: '80%', height: '80%'}
                                },
                                title: '<?php echo esc_js(sprintf('%s %s', __('Data for'), $__park_info->get_wait_date())); ?>'
                            };

                            var chart = new google.visualization.LineChart(chart_element);
                            chart.draw(data, options);
                        }
                    </script>

                    <h3><strong><?php echo esc_html(__('Park Insights')); ?></strong></h3>
                    <ul>
                        <li><?php echo esc_html(sprintf(
                                '%s: %d',
                                __('Total Attractions'),
                                count($__park_info->get_attractions())
                            )); ?></li>
                    </ul>

                    <h3><strong><?php echo esc_html(__('Attractions with Wait Times')); ?></strong></h3>
                    <ul>
                        <?php foreach($__park_info->get_attractions('operating') as $__attraction): ?>
                            <li>
                                <ul class="list-inline list--bullet">
                                    <li><strong><?php echo esc_html($__attraction['name']); ?></strong></li>
                                    <li>
                                        <small>
                                            <?php if($__attraction['wait_average'] > 0): ?>
                                                <?php echo esc_html(sprintf(
                                                    '%s: %s %s',
                                                    __('Average Wait Time'),
                                                    $__attraction['wait_average'],
                                                    __('minutes')
                                                )); ?>
                                            <?php else: ?>
                                                <?php echo esc_html(sprintf('%s: %s', __('Status'), $__attraction['status'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </li>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3><strong><?php echo esc_html(__('Attractions Closed')); ?></strong></h3>
                    <ul>
                        <?php foreach($__park_info->get_attractions('closed') as $__attraction): ?>
                            <li><strong><?php echo esc_html($__attraction['name']); ?></strong></li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if($__park_info->get_attractions('refurbishment')): ?>
                    <h3><strong><?php echo esc_html(__('Attractions Refurbishment')); ?></strong></h3>
                    <ul>
                        <?php foreach($__park_info->get_attractions('refurbishment') as $__attraction): ?>
                            <li><strong><?php echo esc_html($__attraction['name']); ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if($__park_info->get_attractions('unknown')): ?>
                    <h3><strong><?php echo esc_html(__('Attractions Not Reporting')); ?></strong></h3>
                    <ul>
                        <?php foreach($__park_info->get_attractions('unknown') as $__attraction): ?>
                            <li><strong><?php echo esc_html($__attraction['name']); ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </main>
</div>

<?php

get_footer();

?>
