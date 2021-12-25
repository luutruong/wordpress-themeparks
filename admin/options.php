<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && current_user_can('manage_options')
) {
    $api_url = sanitize_text_field($_POST['api_url'] ?? '');
    if (add_option('tp_themeparks_api_url', $api_url) === false) {
        update_option('tp_themeparks_api_url', $api_url);
    }
}

?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <form action="<?php menu_page_url(TP_THEMEPARKS__PLUGIN_DIR . 'admin/options.php') ?>" method="post">
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="apiurl">Api URL</label>
                    </th>
                    <td>
                        <input name="api_url" type="url" id="apiurl"
                               value="<?php echo esc_attr(get_option('tp_themeparks_api_url')) ?>" class="regular-text" />
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