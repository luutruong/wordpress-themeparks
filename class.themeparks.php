<?php

class TP_ThemeParks {
    const QUERY_VAR_PARK_ID = '__park_id';
    const PAGE_NAME_PARKS = '__parks';

    const CRON_HOOK_NAME = 'tp_themeparks_cron';
    const CRON_RECURRENCE = 'tp_themeparks_five_minutes';

    private static $initialized = false;

    public static function initialize(): void {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        ob_start();
        self::rewrites_init();

        add_action(self::CRON_HOOK_NAME, [__CLASS__, 'cronRun']);
        add_filter('cron_schedules', [__CLASS__, 'filter_cron_schedules']);

        $timestamp = wp_next_scheduled(self::CRON_HOOK_NAME);
        if (!$timestamp) {
            $result = wp_schedule_event(time(), self::CRON_RECURRENCE, self::CRON_HOOK_NAME, [], true);

            if ($result instanceof WP_Error
                && $result->has_errors()
            ) {
                echo "<pre>";
                die($result->get_error_message());
            }
        }

        add_filter('query_vars', [__CLASS__, 'filter_query_vars']);
        add_filter('template_include', [__CLASS__, 'filter_template_include']);

        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_menu', [__CLASS__, 'action_admin_menu']);
        }
    }

    public static function cronRun() {
        $parks = self::get_parks(true, 'last_sync_date');
        if (empty($parks)) {
            return;
        }

        require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks-api.php';
        if (empty(self::option_get_api_url())) {
            return;
        }

        $api = new TP_ThemeParks_Api(self::option_get_api_url());

        $max_time = 5.0;
        $start = microtime(true);
        $db = self::db();

        foreach ($parks as $park) {
            $wait_times = $api->get_wait_times($park->park_id);
            if (empty($wait_times)) {
                continue;
            }

            foreach ($wait_times as $wait_entry) {
                if (!is_int($wait_entry['waitTime'])) {
                    continue;
                }

                $sql = $db->prepare("
                    INSERT IGNORE INTO {$db->prefix}tp_park_wait
                        (wait_id, name, active, status, wait_time, fast_pass, extra_data, last_update, park_id)
                    VALUES
                        (%s, %s, %d, %s, %d, %d, %s, %d)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        active = VALUES(active),
                        status = VALUES(status),
                        wait_time = VALUES(wait_time),
                        fast_pass = VALUES(fast_pass),
                        extra_data = VALUES(extra_data),
                        last_update = VALUES(last_update),
                        park_id = VALUES(park_id)
                ",
                    $wait_entry['id'],
                    $wait_entry['name'],
                    $wait_entry['active'] ? 1 : 0,
                    strtolower($wait_entry['status']),
                    $wait_entry['waitTime'],
                    $wait_entry['fastPass'] ? 1 : 0,
                    json_encode($wait_entry['meta']),
                    max(0, strtotime($wait_entry['lastUpdate'], time())),
                    $park->park_id
                );
                $db->query($sql);
            }

            $db->update(
                $db->prefix . 'tp_parks',
                [
                    'last_sync_date' => time()
                ],
                ['park_id' => $park->park_id]
            );

            if (microtime(true) - $start >= $max_time) {
                break;
            }
        }
    }

    public static function action_admin_menu() {
        add_menu_page(
            'Theme Parks',
            'Theme Parks',
            'manage_options',
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php',
            null,
            '',
            9999
        );

        add_submenu_page(
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php',
            'All Parks',
            'All Parks',
            'manage_options',
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php',
            null,
            9999
        );

        add_submenu_page(
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php',
            'Options',
            'Options',
            'manage_options',
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/options.php',
            null,
            9999
        );
    }

    public static function hook_activation() {
        self::init_tables();
    }

    public static function hook_deactivation() {
        $cron_timestamp = wp_next_scheduled(self::CRON_HOOK_NAME);
        wp_unschedule_event($cron_timestamp, self::CRON_HOOK_NAME);
    }

    public static function filter_cron_schedules(array $schedules) {
        $schedules[self::CRON_RECURRENCE] = [
            'interval' => 300,
            'display' => esc_html__('Every Five Minutes')
        ];

        return $schedules;
    }

    public static function filter_template_include($template) {
        $pageName = get_query_var('page' . 'name');
        if ($pageName === self::PAGE_NAME_PARKS) {
            $parkId = (int) get_query_var(self::QUERY_VAR_PARK_ID, 0);
            if ($parkId > 0) {
                return TP_THEMEPARKS__PLUGIN_DIR . 'templates/park-single.php';
            }

            return TP_THEMEPARKS__PLUGIN_DIR . 'templates/park-index.php';
        }

        return $template;
    }

    public static function filter_query_vars($query_vars) {
        $query_vars[] = self::QUERY_VAR_PARK_ID;

        return $query_vars;
    }

    public static function rewrites_init(): void {
        add_rewrite_rule(
            '^parks(\/)?([0-9]+)?\/?$',
            'index.php?page' . 'name=' . self::PAGE_NAME_PARKS . '&' . self::QUERY_VAR_PARK_ID . '=$matches[2]',
            'top'
        );

        flush_rewrite_rules();
    }

    /** OPTIONS */
    public static function option_update_api_url(string $api_url) {
        update_option('tp_themeparks_api_url', $api_url);
    }
    public static function option_get_api_url() {
        return get_option('tp_themeparks_api_url');
    }

    public static function bulk_update_parks_status(array $ids, bool $active) {
        if (empty($ids)) {
            return 0;
        }

        $count = 0;
        foreach ($ids as $id) {
            if (self::update_park_status($id, $active) > 0) {
                $count++;
            }
        }

        return $count;
    }

    public static function update_park_status(string $id, bool $active) {
        $db = self::db();
        return self::db()->update(
            $db->prefix . 'tp_parks',
            [
                'active' => $active ? 1 : 0
            ],
            ['park_id' => $id]
        );
    }

    public static function get_parks(bool $active_only = false, string $order = 'name') {
        $db = self::db();
        $condition_sql = '1=1';
        if ($active_only) {
            $condition_sql .= ' AND active = \'1\'';
        }

        $order_clause = '';
        if (!empty($order)) {
            $order_clause = 'ORDER BY ' . $order;
        }

        return $db->get_results("
            SELECT *
            FROM {$db->prefix}tp_parks
            WHERE {$condition_sql}
            {$order_clause}
        ");
    }

    public static function get_park(string $park_id) {
        $db = self::db();
        $query = $db->prepare("
            SELECT *
            FROM {$db->prefix}tp_parks
            WHERE park_id = %s
        ", $park_id);

        return $db->get_row($query);
    }

    public static function insert_parks(array $parks) {
        $db = self::db();
        foreach ($parks as $park) {
            $park_data = [
                'name' => $park['name'],
                'location' => $park['location'],
                'latitude' => $park['latitude'],
                'longitude' => $park['longitude'],
                'timezone' => $park['timeZone'],
                'map_url' => $park['mapUrl']
            ];
            if (self::get_park($park['id'])) {
                $db->update(
                    $db->prefix . 'tp_parks',
                    $park_data,
                    [
                        'park_id' => $park['id']
                    ]
                );
            } else {
                $db->insert(
                    $db->prefix . 'tp_parks',
                    $park_data + [
                        'park_id' => $park['id'],
                        'active' => 0,
                        'last_sync_date' => 0
                    ]
                );
            }
        }
    }

    protected static function init_tables(): void {
        // check tables.
        $db = self::db();
        if (!$db->query( "SHOW TABLES LIKE '{$db->prefix}tp_parks'")) {
            $sqlTableParks = "
                CREATE TABLE IF NOT EXISTS {$db->prefix}tp_parks (
                    park_id VARBINARY(32) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    location VARCHAR(50) NOT NULL,
                    latitude VARCHAR(25) NOT NULL,
                    longitude VARCHAR(25) NOT NULL,
                    timezone VARCHAR(32) NOT NULL,
                    map_url VARCHAR(255) NOT NULL,
                    active TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    last_sync_date INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY park_id (park_id),
                    KEY active (active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
            ";
            $rows_count = $db->query($sqlTableParks);
            if ($rows_count === false) {
                echo "<pre>";
                die('Failed to create table: ' . $sqlTableParks);
            }
        }

        if (!$db->query("SHOW TABLES LIKE '{$db->prefix}tp_park_wait'")) {
            $sqlTableRecords = "
                CREATE TABLE IF NOT EXISTS {$db->prefix}tp_park_wait (
                    wait_id VARBINARY(32) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    active TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    park_id VARBINARY(32) NOT NULL,
                    status VARCHAR(25) NOT NULL,
                    wait_time INT UNSIGNED NOT NULL DEFAULT '0',
                    fast_pass TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    extra_data TEXT NOT NULL,
                    last_update INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY wait_id (wait_id),
                    KEY last_update (last_update),
                    KEY park_id (park_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
            ";
            $rows_count = $db->query($sqlTableRecords);
            if ($rows_count === false) {
                echo "<pre>";
                die('Failed to create table: ' . $sqlTableRecords);
            }
        }
    }

    protected static function db(): \wpdb {
        global $wpdb;

        return $wpdb;
    }
}
