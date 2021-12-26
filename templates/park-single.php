<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';
require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks-api.php';

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
});

get_header();

$__api_url = TP_ThemeParks::option_get_api_url();
$__park_info = TP_ThemeParks::get_park_open_info($__park->park_id);
$__js_data = [];
foreach ($__park_info['wait_data'] as $__item) {
    $__js_data[] = [$__item['time'], $__item['total']];
}

?>

<div <?php generate_do_attr( 'content' ); ?>>
    <main <?php generate_do_attr( 'main' ); ?>>
        <article id="post-0" class="post-0 post type-post status-publish format-standard hentry category-uncategorized entry">
            <div class="inside-article">
                <header class="entry-header alignwide">
                    <h1 class="entry-title">
                        <?php echo __('Wait Times at') . ' ' . esc_html($__park->name); ?>
                    </h1>
                </header>
                <div class="entry-content">
                    <p style="margin:0"><?php echo __('Park Hours'); ?>:&nbsp;<?php echo $__park_info['open']; ?>&nbsp;to&nbsp;<?php echo $__park_info['close']; ?></p>
                    <p style="margin:0"><?php echo __('Park Status'); ?>:&nbsp;<?php echo esc_html(ucfirst($__park_info['status'])); ?></p>

                    <div id="park-wait--times--chart" data-wait="<?php echo esc_attr(json_encode($__js_data)); ?>"
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
                                title: '<?php echo esc_js(__('Data for ' . $__park_info['wait_data_date'])); ?>'
                            };

                            var chart = new google.visualization.LineChart(chart_element);
                            chart.draw(data, options);
                        }
                    </script>
                </div>
            </div>
        </article>
    </main>
</div>

<?php

generate_construct_sidebars();
get_footer();

?>
