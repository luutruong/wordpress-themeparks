<?php

if (!current_user_can('manage_options')) {
    wp_die('You do not have permissions to view this page.');
    exit;
}

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';
$__tp_park_id = intval($_REQUEST['park_id'] ?? 0);

$__tp_park = TP_ThemeParks::get_park($__tp_park_id);
if (empty($__tp_park)) {
    wp_die('Requested park could not be found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $__tp_park_name = sanitize_text_field($_REQUEST['name'] ?? '');
    $__tp_park_image_url = sanitize_text_field($_REQUEST['image_url'] ?? '');

    if (empty($__tp_park_name)) {
        wp_die('Please enter valid park name.');
    }

    TP_ThemeParks::db()->update(
        TP_ThemeParks::db()->prefix . 'tp_parks',
        [
            'name' => $__tp_park_name,
            'image_url' => $__tp_park_image_url
        ],
        [
            'park_id' => $__tp_park_id
        ]
    );

    wp_redirect(admin_url('admin.php?' . http_build_query([
        'page' => 'themeparks/admin/parks.php',
        'status' => 'all',
    ])));
    exit;
}

?>

<div class="wrap">
    <h1><?php echo sprintf('%s: %s',
            __theme_parks_trans('Edit Park'),
            $__tp_park->name
        ); ?></h1>
    <form class="tp-park--form" method="post">
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="park-name"><?php echo esc_html(__theme_parks_trans('Park Name')); ?></label></th>
                <td>
                    <input name="name" type="text" id="park-name"
                           value="<?php echo esc_attr($__tp_park->name); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="park-image-url"><?php echo esc_html(__theme_parks_trans('Park Image URL')); ?></label></th>
                <td>
                    <input name="image_url" id="park-image-url"
                           type="url" value="<?php echo esc_attr($__tp_park->image_url); ?>" class="regular-text" />
                </td>
            </tr>
            </tbody>
        </table>

        <?php
            // output save settings button
            submit_button(__( 'Save Settings', 'default' ));
        ?>
    </form>
</div>