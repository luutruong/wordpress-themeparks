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

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && current_user_can('manage_options')
) {
    TP_ThemeParks::option_update_api_url(sanitize_text_field($_POST['api_url'] ?? ''));
    TP_ThemeParks::option_update_parks_route(sanitize_text_field($_POST['park_route'] ?? ''));
}

?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <form action="<?php menu_page_url(TP_THEMEPARKS__PLUGIN_DIR . 'admin/options.php') ?>" method="post">
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="apiurl"><?php echo esc_html(__theme_parks_trans('Api URL')); ?></label></th>
                    <td>
                        <input name="api_url" type="url" id="apiurl"
                               value="<?php echo esc_attr(TP_ThemeParks::option_get_api_url()) ?>" class="regular-text" />
                        <p class="description" id="api_url-description">
                            You can put multiple api URLs here by separate them by comma (,)
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="park-route-name"><?php echo esc_html(__theme_parks_trans('Parks Route Name')); ?></label></th>
                    <td>
                        <input name="park_route" type="text" id="park-route-name"
                               pattern="[a-zA-Z0-9\-]+" minlength="3"
                               value="<?php echo esc_attr(TP_ThemeParks::option_get_parks_route()); ?>" class="regular-text" />
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