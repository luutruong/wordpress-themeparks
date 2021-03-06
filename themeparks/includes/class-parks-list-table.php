<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';

class TP_ThemeParks_Parks_List_Table extends WP_List_Table {
    const TP_SCREEN_ID = 'tp-themeparks-parks-list';
    const TP_PARK_PLURAL = 'tp-themeparks-parks';
    const TP_PARK_SINGULAR = 'tp-themeparks-park';

    protected $parks_total = 0;
    protected $parks_active = 0;
    protected $parks_inactive = 0;

    public function __construct($args = array())
    {
        parent::__construct([
            'screen' => self::TP_SCREEN_ID,
            'plural' => self::TP_PARK_PLURAL,
            'singular' => self::TP_PARK_SINGULAR
        ]);
    }

    public function get_columns()
    {
        return array(
            'cb' => '<input type="checkbox" />',
            'name' => __theme_parks_trans('Park'),
            'description' => __theme_parks_trans('Description'),
        );
    }

    protected function column_cb($item)
    {
        return '<input type="checkbox" name="checked[]"'
            . ' value="' . esc_attr($item->park_id) . '"'
            . ' id="checkbox_park_' . esc_attr($item->park_id) . '" />';
    }

    protected function column_default($item, $column_name)
    {
        $menu_slug = plugin_basename(TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php');

        if ($column_name === 'description') {
            $desc_html =
                '<div class="plugin-description"><p>' . esc_html(sprintf(
                    'Time zone: %s',
                    $this->column_default($item, 'timezone')
                )) . '</p></div>';
            $desc_html .= '<div class="inactive second plugin-version-author-uri">
                <a href="' . esc_url(admin_url('admin.php?' . http_build_query([
                    'page' => plugin_basename(TP_THEMEPARKS__PLUGIN_DIR . 'admin/park_edit.php'),
                    'park_id' => $item->park_id,
                ]))) . '">' . esc_html(__theme_parks_trans('Edit')) .  '</a>
                <a href="' . esc_url(TP_ThemeParks::get_park_item_url($item)) . '" target="_blank">' . esc_html(__theme_parks_trans('View park')) . '</a>
            </div>';

            return $desc_html;
        } elseif ($column_name === 'name') {
            $name_html = '<strong>' . esc_html($item->name) . '</strong>';
            $link_url = admin_url('admin.php?' . http_build_query([
                'page' => $menu_slug,
                'action' => $item->active ? 'deactivate' : 'activate',
                'park_id' => $item->park_id,
            ]));

            $name_html .= '<div class="row-actions visible">
                <span class="' . ($item->active ? 'activate' : 'deactivate')  . '">
                    <a href="' . esc_url($link_url) . '" class="edit">' . ($item->active ? __theme_parks_trans('Deactivate') : __theme_parks_trans('Activate')) . '</a>
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
        $this->parks_inactive = $this->parks_total - $this->parks_active;

        $items = array_filter($items, [$this, '_search_callback']);

        $filter_status = sanitize_text_field($_GET['status'] ?? '');
        if ($filter_status === 'active') {
            $items = array_filter($items, function ($item) {
                return $item->active > 0;
            });
        } elseif ($filter_status === 'inactive') {
            $items = array_filter($items, function ($item) {
                return $item->active <= 0;
            });
        }

        $this->items = $items;
    }

    protected function get_views()
    {
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
            $actions['activate-selected'] = __theme_parks_trans('Activate');
        }

        if ($status !== 'inactive') {
            $actions['deactivate-selected'] = __theme_parks_trans('Deactivate');
        }

        return $actions;
    }

    public function search_box($text, $input_id)
    {
        $input_id = $input_id . '-search-input';

        return '
            <p class="search-box">
                <label class="screen-reader-text" for="' . esc_attr($input_id) . '">' . esc_html($text) . ':</label>
                <input type="search" id="' . esc_attr($input_id) . '"
                    class="wp-filter-search" name="s"
                    value="' . esc_attr(wp_unslash($_GET['s'] ?? '')) . '"
                    style="width:280px"
                    placeholder="' . esc_attr('Search parks...') . '" />
                ' . get_submit_button($text, 'hide-if-js', '', false, ['id' => 'search-submit']) . '
            </p>
        ';
    }

    public function _search_callback($park)
    {
        $search_text = sanitize_text_field($_GET['s'] ?? '');
        if (empty($search_text)) {
            return true;
        }

        if (preg_match('#(' . preg_quote($search_text, '#') . ')#iu', $park->name)) {
            return true;
        }

        return false;
    }
}
