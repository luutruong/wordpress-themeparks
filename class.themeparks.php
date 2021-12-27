<?php

class TP_ThemeParks {
    const QUERY_VAR_PARK_SLUG = '__park_slug';
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

        add_action(self::CRON_HOOK_NAME, [__CLASS__, 'cron_run']);
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_menu', [__CLASS__, 'action_admin_menu']);
        }

        add_filter('cron_schedules', [__CLASS__, 'filter_cron_schedules']);
        add_filter('pre_handle_404', [__CLASS__, 'filter_pre_handle_404']);

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
    }

    public static function uninstall() {
        $db = self::db();

        $db->query("DROP TABLE IF EXIST `{$db->prefix}`tp_parks");
        $db->query("DROP TABLE IF EXIST `{$db->prefix}`tp_park_opening");
        $db->query("DROP TABLE IF EXIST `{$db->prefix}`tp_park_wait");

        delete_option('tp_themeparks_api_url');
        delete_option('tp_themeparks_park_route');
    }

    public static function cron_run() {
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

        $sync_wait_times = function ($park_id) use ($db, $api) {
            $wait_times = $api->get_wait_times($park_id);
            if (empty($wait_times)) {
                return;
            }

            foreach ($wait_times as $wait_entry) {
                if (!is_int($wait_entry['waitTime'])) {
                    continue;
                }

                $sql = $db->prepare("
                    INSERT IGNORE INTO {$db->prefix}tp_park_wait
                        (wait_id, name, active, status, wait_time, fast_pass, extra_data, last_update, park_id)
                    VALUES
                        (%s, %s, %d, %s, %d, %d, %s, %d, %s)
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
                    $park_id
                );
                $db->query($sql);
            }
        };
        $sync_opening_times = function ($park_id) use ($db, $api) {
            $opening_times = $api->get_opening_times($park_id);
            if (empty($opening_times)) {
                return;
            }

            foreach ($opening_times as $opening_time) {
                try {
                    $open_time_dt = DateTime::createFromFormat(DateTime::ISO8601, $opening_time['openingTime']);
                    $open_time_dt->setTimezone(new DateTimeZone('UTC'));

                    $close_time_dt = DateTime::createFromFormat(DateTime::ISO8601, $opening_time['closingTime']);
                    $close_time_dt->setTimezone(new DateTimeZone('UTC'));
                } catch (Throwable $e) {
                    continue;
                }

                $query = $db->prepare("
                    INSERT IGNORE INTO {$db->prefix}tp_park_opening
                        (park_id, open_date, open_time, close_time, type, extra_data)
                    VALUES
                        (%s, %s, %d, %d, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        open_time = VALUES(open_time),
                        close_time = VALUES(close_time),
                        type = VALUES(type),
                        extra_data = VALUES(extra_data)
                ",
                    $park_id,
                    $opening_time['date'],
                    $open_time_dt->getTimestamp(),
                    $close_time_dt->getTimestamp(),
                    strtolower($opening_time['type']),
                    json_encode($opening_time)
                );

                $db->query($query);
            }
        };

        foreach ($parks as $park) {
            call_user_func($sync_wait_times, $park->park_id);
            call_user_func($sync_opening_times, $park->park_id);

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

    public static function filter_pre_handle_404($is_404) {
        $pageName = get_query_var('page' . 'name');
        if ($pageName === self::PAGE_NAME_PARKS) {
            // prevent return 404 status in our pages.
            return true;
        }

        return $is_404;
    }

    public static function filter_cron_schedules($schedules) {
        $schedules[self::CRON_RECURRENCE] = [
            'interval' => 300,
            'display' => esc_html__('Every Five Minutes')
        ];

        return $schedules;
    }

    public static function filter_template_include($template) {
        $pageName = get_query_var('page' . 'name');
        if ($pageName === self::PAGE_NAME_PARKS) {
            $parkId = get_query_var(self::QUERY_VAR_PARK_SLUG, '');
            if ($parkId !== '') {
                return TP_THEMEPARKS__PLUGIN_DIR . 'templates/park-single.php';
            }

            return TP_THEMEPARKS__PLUGIN_DIR . 'templates/park-index.php';
        }

        return $template;
    }

    public static function filter_query_vars($query_vars) {
        $query_vars[] = self::QUERY_VAR_PARK_SLUG;

        return $query_vars;
    }

    public static function rewrites_init(): void {
        $route_name = self::option_get_parks_route();
        add_rewrite_rule(
            '^(' . $route_name . ')(\/)?([0-9a-zA-Z\-]+)?\/?$',
            'index.php?page' . 'name=' . self::PAGE_NAME_PARKS . '&' . self::QUERY_VAR_PARK_SLUG . '=$matches[3]',
            'top'
        );

        flush_rewrite_rules();
    }

    public static function date_time(int $timestamp, ?string $format = null) {
        if ($format === null || $format === 'absolute') {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        $dt = new DateTime('@' . $timestamp, new DateTimeZone('UTC'));
        $dt->setTimezone(wp_timezone());

        return $dt->format($format);
    }

    /** LINKS */
    public static function get_park_list_url() {
        return site_url(self::option_get_parks_route() . '/');
    }
    public static function get_park_item_url($park) {
        return site_url(self::option_get_parks_route() . '/' . urlencode($park->slug) . '/');
    }

    /** OPTIONS */
    public static function option_update_api_url(string $api_url) {
        update_option('tp_themeparks_api_url', $api_url);
    }
    public static function option_get_api_url() {
        return get_option('tp_themeparks_api_url');
    }
    public static function option_update_parks_route(string $route) {
        if (empty($route) || preg_match('#[^a-zA-Z0-9\-]+#', $route)) {
            wp_die(__('Please enter valid route name'));
        }

        update_option('tp_themeparks_park_route', $route);
    }
    public static function option_get_parks_route() {
        return get_option('tp_themeparks_park_route');
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
            $condition_sql .= ' AND `active` = \'1\'';
        }

        $order_clause = '';
        if (!empty($order)) {
            $order_clause = 'ORDER BY ' . $order;
        }

        return $db->get_results("
            SELECT *
            FROM `{$db->prefix}tp_parks`
            WHERE {$condition_sql}
            {$order_clause}
        ");
    }

    public static function get_park(string $park_id) {
        $db = self::db();
        $query = $db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_parks`
            WHERE `park_id` = %s
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
                'map_url' => $park['mapUrl'],
                'slug' => strtolower(sanitize_title_with_dashes($park['name'])),
            ];
            if (self::get_park($park['id'])) {
                $result = $db->update(
                    $db->prefix . 'tp_parks',
                    $park_data,
                    [
                        'park_id' => $park['id']
                    ]
                );
            } else {
                $result = $db->insert(
                    $db->prefix . 'tp_parks',
                    $park_data + [
                        'park_id' => $park['id'],
                        'active' => 0,
                        'last_sync_date' => 0
                    ]
                );
            }

            if ($result === false) {
                wp_die($db->last_error);
            }
        }
    }

    public static function get_park_by_slug(string $slug) {
        $db = self::db();
        $query = $db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_parks`
            WHERE `slug` = %s
        ", $slug);

        return $db->get_row($query);
    }

    protected static function init_tables(): void {
        // check tables.
        $db = self::db();
        if (!$db->query( "SHOW TABLES LIKE '{$db->prefix}tp_parks'")) {
            $sqlTable = "
                CREATE TABLE IF NOT EXISTS `{$db->prefix}tp_parks` (
                    `park_id` VARBINARY(32) NOT NULL,
                    `name` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(100) DEFAULT NULL,
                    `location` VARCHAR(50) NOT NULL,
                    `latitude` VARCHAR(25) NOT NULL,
                    `longitude` VARCHAR(25) NOT NULL,
                    `timezone` VARCHAR(32) NOT NULL,
                    `map_url` VARCHAR(255) NOT NULL,
                    `active` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    `last_sync_date` INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY `park_id` (`park_id`),
                    UNIQUE KEY `slug` (`slug`),
                    KEY `active` (`active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
            ";
            $rows_count = $db->query($sqlTable);
            if ($rows_count === false) {
                echo "<pre>";
                die('Failed to create table: ' . $sqlTable);
            }
        }
        // migrate 1.0.1

        if (!$db->query("SHOW COLUMNS FROM `{$db->prefix}tp_parks` LIKE 'slug'")) {
            $addColumn = "ALTER TABLE `{$db->prefix}tp_parks` ADD COLUMN `slug` VARCHAR(100) DEFAULT NULL";
            $result = $db->query($addColumn);
            if ($result === false) {
                wp_die($db->last_error);
            }

            $records = $db->get_results("
                SELECT *
                FROM `{$db->prefix}tp_parks`
            ");
            foreach ($records as $record) {
                $slug = strtolower(sanitize_title_with_dashes($record->name));
                $_dupe = self::get_park_by_slug($slug);
                if (!empty($_dupe)) {
                    $slug .= '-' . substr($record->park_id, 0, 4);
                }

                $db->update(
                    "{$db->prefix}tp_parks",
                    [
                        'slug' => $slug
                    ],
                    [
                        'park_id' => $record->park_id
                    ]
                );
            }
        }

        if (!$db->query("SHOW TABLES LIKE '{$db->prefix}tp_park_wait'")) {
            $sqlTable = "
                CREATE TABLE IF NOT EXISTS `{$db->prefix}tp_park_wait` (
                    `wait_id` VARBINARY(32) NOT NULL,
                    `name` VARCHAR(100) NOT NULL,
                    `active` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    `park_id` VARBINARY(32) NOT NULL,
                    `status` VARCHAR(25) NOT NULL,
                    `wait_time` INT UNSIGNED NOT NULL DEFAULT '0',
                    `fast_pass` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    `extra_data` TEXT NOT NULL,
                    `last_update` INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY `wait_id` (`wait_id`),
                    KEY `last_update` (`last_update`),
                    KEY `park_id` (`park_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
            ";
            $rows_count = $db->query($sqlTable);
            if ($rows_count === false) {
                echo "<pre>";
                die('Failed to create table: ' . $sqlTable);
            }
        }

        if (!$db->query("SHOW TABLES LIKE '{$db->prefix}tp_park_opening'")) {
            $sqlTable = "
                CREATE TABLE IF NOT EXISTS `{$db->prefix}tp_park_opening` (
                    `id` INT UNSIGNED AUTO_INCREMENT,
                    `park_id` VARBINARY(32) NOT NULL,
                    `open_date` VARCHAR(16) NOT NULL,
                    `open_time` INT UNSIGNED NOT NULL,
                    `close_time` INT UNSIGNED NOT NULL,
                    `type` VARCHAR(32) NOT NULL,
                    `extra_data` TEXT NOT NULL,
                    PRIMARY KEY `id` (`id`),
                    UNIQUE KEY `park_id_date` (`park_id`, `open_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
            ";
            $rows_count = $db->query($sqlTable);
            if ($rows_count === false) {
                echo "<pre>";
                die('Failed to create table: ' . $sqlTable);
            }
        }
    }

    public static function db(): \wpdb {
        global $wpdb;

        return $wpdb;
    }
}
