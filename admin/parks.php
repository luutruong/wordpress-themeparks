<?php
    require_once ABSPATH . 'wp-admin/admin.php';
    require_once TP_THEMEPARKS__PLUGIN_DIR . 'includes/class-parks-list-table.php';
    require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks-api.php';
    require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';

    $__tp_parks_table = new TP_ThemeParks_Parks_List_Table();
    $__tp_api_url = get_option('tp_themeparks_api_url');

    $__tp_menu_slug = plugin_basename(TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php');
    $__tp_sync_url = admin_url('admin.php?page=' . urlencode($__tp_menu_slug) . '&sync=1');

    if (isset($_GET['sync']) && $_GET['sync'] === '1') {
        if (empty($__tp_api_url)) {
            die(__('You may enter themeparks API url.'));
        }

        $__tp_api = new TP_ThemeParks_Api($__tp_api_url);
        TP_ThemeParks::insert_parks($__tp_api->parks());
    }

    $__tp_park_item_toggle = function () use ($__tp_menu_slug) {
        $action = $_GET['action'] ?? '';
        $nonce = $_GET['nonce'] ?? '';
        $id = intval($_GET['id'] ?? 0);

        if (!wp_verify_nonce($nonce, 'tp_themeparks_park_toggle')) {
            return null;
        }

        if (!in_array($action, ['activate', 'deactivate'], true)
            || $id <= 0
        ) {
            return null;
        }

        TP_ThemeParks::update_park_status($id, $action === 'activate');

        $redirect_url = admin_url('admin.php?' . http_build_query([
            'page' => $__tp_menu_slug,
            'status' => $_GET['status'] ?? 'all'
        ]));

        wp_redirect($redirect_url);
        exit;
    };
    call_user_func($__tp_park_item_toggle);

    $__tp_handle_bulk_update = function () use ($__tp_menu_slug) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['action'] ?? '-1';
        check_admin_referer('bulk-' . TP_ThemeParks_Parks_List_Table::TP_PARK_PLURAL);

        $checked = $_POST['checked'] ?? [];
        $checked = array_map('intval', $checked);
        $checked = array_unique($checked);

        if (count($checked) > 0 && in_array($action, ['activate-selected', 'deactivate-selected'], true)) {
            TP_ThemeParks::bulk_update_parks_status($checked, $action === 'activate-selected');

            $redirect_url = admin_url('admin.php?' . http_build_query([
                    'page' => $__tp_menu_slug,
                    'status' => $_GET['status'] ?? 'all'
                ]));

            wp_redirect($redirect_url);
            exit;
        }
    };
    call_user_func($__tp_handle_bulk_update);

    $__tp_parks_table->items = TP_ThemeParks::get_parks();
    $__tp_parks_table->prepare_items();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <a href="<?php echo esc_url($__tp_sync_url); ?>"
       class="page-title-action"><?php echo esc_html_x('Sync', 'themeparks'); ?></a>

    <hr class="wp-header-end">

    <?php $__tp_parks_table->views(); ?>

    <form method="post" id="bulk-action-form">
        <?php $__tp_parks_table->display();  ?>
    </form>

    <span class="spinner"></span>
</div>