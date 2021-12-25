<?php

class TP_ThemeParks {
    const QUERY_VAR_PARK_ID = '__park_id';
    const PAGE_NAME_PARKS = '__parks';

    private static $initialized = false;

    public static function initialize(): void {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        ob_start();
        self::rewrites_init();

        add_filter('query_vars', [__CLASS__, 'filter_query_vars']);
        add_filter('template_include', [__CLASS__, 'filter_template_include']);

        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_menu', [__CLASS__, 'action_admin_menu']);
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

    public static function hook_deactivation() {}

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

    public static function update_park_status(int $id, bool $active) {
        $db = self::db();
        return self::db()->update(
            $db->prefix . 'tp_parks',
            [
                'active' => $active ? 1 : 0
            ],
            ['id' => $id]
        );
    }

    public static function get_parks(bool $activeOnly = false) {
        $db = self::db();
        $conditionSql = '1=1';
        if ($activeOnly) {
            $conditionSql .= ' AND active = \'1\'';
        }

        return $db->get_results("
            SELECT *
            FROM {$db->prefix}tp_parks
            WHERE {$conditionSql}
        ");
    }

    public static function insert_parks(array $parks) {
        $db = self::db();
        foreach ($parks as $park) {
            $query = $db->prepare("
                INSERT IGNORE INTO {$db->prefix}tp_parks
                    (park_id, name, location, latitude, longitude, timezone, map_url, active, last_sync_date)
                VALUES
                    (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    location = VALUES(location),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    timezone = VALUES(timezone),
                    map_url = VALUES(map_url)
            ",
                $park['id'],
                $park['name'],
                $park['location'],
                $park['latitude'],
                $park['longitude'],
                $park['timeZone'],
                $park['mapUrl'],
                0,
                time()
            );

            $db->query($query);
        }
    }

    protected static function init_tables(): void {
        // check tables.
        $db = self::db();
        if (!$db->query( "SHOW TABLES LIKE '{$db->prefix}tp_parks'")) {
            $sqlTableParks = "
                CREATE TABLE IF NOT EXISTS {$db->prefix}tp_parks (
                    id INT UNSIGNED AUTO_INCREMENT,
                    park_id VARCHAR(75) NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    location VARCHAR(50) NOT NULL,
                    latitude VARCHAR(25) NOT NULL,
                    longitude VARCHAR(25) NOT NULL,
                    timezone VARCHAR(32) NOT NULL,
                    map_url VARCHAR(255) NOT NULL,
                    active TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    last_sync_date INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY id (id),
                    UNIQUE KEY park_id (park_id),
                    KEY active (active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
            ";
            $rows_count = $db->query($sqlTableParks);
            if ($rows_count === false) {
                echo "<pre>";
                die('Failed to create table: ' . $sqlTableParks);
            }
        }

        if (!$db->query("SHOW TABLES LIKE '{$db->prefix}tp_park_records'")) {
            $sqlTableRecords = "
                CREATE TABLE IF NOT EXISTS {$db->prefix}tp_park_records (
                    record_id INT UNSIGNED AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    wait_id VARCHAR(75) NOT NULL,
                    active TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    status VARCHAR(25) NOT NULL,
                    wait_time INT UNSIGNED NOT NULL DEFAULT '0',
                    fast_past TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    extra_data TEXT NOT NULL,
                    last_update INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY record_id (record_id),
                    UNIQUE KEY wait_id (wait_id),
                    KEY last_update (last_update)
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
