<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';

if (!current_user_can('manage_options')) {
    wp_die('You do not have permissions to view this page.');
    exit;
}

$__parks_route = TP_ThemeParks::option_get_parks_route();
if ($__parks_route === false) {
    // not exists
    TP_ThemeParks::option_update_parks_route('parks');
}

$__tp_logFiles = TP_ThemeParks::getLogFiles();

if (isset($_GET['file'])) {
    if (!preg_match('#^log-\d+\.log$#', $_GET['file'])) {
        wp_die('Invalid file.');
    }
    $__tp_filePath = TP_THEMEPARKS__PLUGIN_DIR  . $_GET['file'];
    if (!file_exists($__tp_filePath)) {
        wp_die('File not found.');
    }

    header('Content-Type: text/plain');
    header('Content-Length: ' . filesize($__tp_filePath));
    header('Content-Disposition: attachment; filename="' . basename($__tp_filePath) . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $__tp_fp = fopen($__tp_filePath, 'r');
    while (!feof($__tp_fp)) {
        $__buffer = fread($__tp_fp, 4 * 1024);
        echo $__buffer;
    }

    fclose($__tp_fp);
    exit;
}

?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <ol>
        <?php foreach($__tp_logFiles as $__tp_logFile): ?>
            <li>
                <a href="<?php echo esc_attr($__tp_logFile['view_url']); ?>" target="_blank"><?php echo esc_html($__tp_logFile['name']); ?></a>
            </li>
        <?php endforeach; ?>
    </ol>
</div>