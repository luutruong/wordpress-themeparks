<?php

class TP_ThemeParks_Parks_List_Table extends WP_List_Table {
    const TP_SCREEN_ID = 'tp-themeparks-parks-list';
    const TP_PARK_PLURAL = 'tp-themeparks-parks';
    const TP_PARK_SINGULAR = 'tp-themeparks-park';

    protected $parks_total = 0;
    protected $parks_active = 0;
    protected $parks_inactive = 0;

    public function __construct($args = array()){
        parent::__construct([
            'screen' => self::TP_SCREEN_ID,
            'plural' => self::TP_PARK_PLURAL,
            'singular' => self::TP_PARK_SINGULAR
        ]);
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'name' => __( 'Park' ),
            'description' => __( 'Description' ),
        );
    }

    protected function column_cb($item) {
        return '<input type="checkbox" name="checked[]"'
            . ' value="' . esc_attr($item->id) . '"'
            . ' id="checkbox_park_' . esc_attr($item->id) . '" />';
    }

    protected function column_default($item, $column_name) {
        if ($column_name === 'description') {
            $desc_html =
                '<div class="plugin-description"><p>' . esc_html(sprintf(
                    'Location: %s, Time zone: %s',
                    $this->column_default($item, 'location'),
                    $this->column_default($item, 'timezone')
                )) . '</p></div>';
            $desc_html .= '<div class="inactive second plugin-version-author-uri">
                <a href="' . esc_attr($item->map_url) . '" target="_blank">View maps</a>
            </div>';

            return $desc_html;
        } elseif ($column_name === 'name') {
            $name_html = '<strong>' . esc_html($item->name) . '</strong>';
            $menu_slug = plugin_basename(TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php');
            $link_url = admin_url('admin.php?' . http_build_query([
                'page' => $menu_slug,
                'action' => $item->active ? 'deactivate' : 'activate',
                'nonce' => wp_create_nonce('tp_themeparks_park_toggle'),
                'id' => $item->id,
            ], '', '&'));

            $name_html .= '<div class="row-actions visible">
                <span class="' . ($item->active ? 'activate' : 'deactivate')  . '">
                    <a href="' . esc_attr($link_url) . '" class="edit">' . ($item->active ? __('Deactivate') : __('Activate')) . '</a>
                </span>
            </div>';

            return $name_html;
        }

        return $item->{ $column_name };
    }

    public function prepare_items()
    {
        $items = $this->items;
        $this->parks_total = count($items);
        $this->parks_active = count(array_filter($items, function ($item) {
            return $item->active > 0;
        }));
        $this->parks_inactive = $this->parks_total - $this->parks_inactive;

        $filter_status = sanitize_text_field($_GET['status'] ?? '');
        if ($filter_status === 'active') {
            $this->items = array_filter($items, function ($item) {
                return $item->active > 0;
            });
        } elseif ($filter_status === 'inactive') {
            $this->items = array_filter($items, function ($item) {
                return $item->active <= 0;
            });
        }
    }

    protected function get_views() {
        $menu_slug = plugin_basename(TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php');

        $status_links = [];
        $types = ['all' => $this->parks_total, 'active' => $this->parks_active, 'inactive' => $this->parks_inactive];
        $filter_status = sanitize_text_field($_GET['status'] ?? '');
        if (!isset($types[$filter_status])) {
            $filter_status = 'all';
        }

        foreach ($types as $type => $total) {
            $text = _nx(
                ucfirst($type) . ' <span class="count">(%s)</span>',
                ucfirst($type) . ' <span class="count">(%s)</span>',
                $total,
                'themeparks'
            );
            $text = sprintf(
                "<a href='%s'%s>%s</a>",
                admin_url('admin.php?' . http_build_query([
                    'page' => $menu_slug,
                    'status' => $type
                ])),
                ($filter_status === $type) ? 'class="current"' : '',
                sprintf($text, number_format_i18n($total))
            );

            $status_links[$type] = $text;
        }

        return $status_links;
    }

    protected function get_bulk_actions()
    {
        $status = sanitize_text_field($_GET['status'] ?? '');
        $actions = array();

        if ($status !== 'active') {
            $actions['activate-selected'] = __('Activate');
        }

        if ($status !== 'inactive') {
            $actions['deactivate-selected'] = __('Deactivate');
        }

        return $actions;
    }
}
