<?php

class TP_ThemeParks {
    const QUERY_VAR_PARK_SLUG = '__park_slug';
    const QUERY_VAR_ATTRACTION_ID = '__attraction_id';
    const PAGE_NAME_PARKS = '__parks';
    const PAGE_NAME_ATTRACTIONS = '__attractions';

    const CRON_HOOK_NAME = 'tp_themeparks_cron';
    const CRON_RECURRENCE = 'tp_themeparks_five_minutes';

    private static $initialized = false;

    public static function initialize(): void
    {
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

        add_action('check_admin_referer', [__CLASS__, 'action_check_admin_referer']);
        add_action('_admin_menu', function () {
           global $_registered_pages;
           $_registered_pages['admin_page_themeparks/admin/park_edit'] = true;
        });

        add_filter('cron_schedules', [__CLASS__, 'filter_cron_schedules']);
        add_filter('pre_handle_404', [__CLASS__, 'filter_pre_handle_404']);

        static::load_resources();

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

    public static function uninstall()
    {
        $db = self::db();

        $db->query("DROP TABLE IF EXISTS `{$db->prefix}tp_parks`");
        $db->query("DROP TABLE IF EXISTS `{$db->prefix}tp_park_opening`");
        $db->query("DROP TABLE IF EXISTS `{$db->prefix}tp_park_wait`");
        $db->query("DROP TABLE IF EXISTS `{$db->prefix}tp_park_attraction`");

        delete_option('tp_themeparks_api_url');
        delete_option('tp_themeparks_park_route');

        $timestamp = wp_next_scheduled(self::CRON_HOOK_NAME);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK_NAME);
        }
    }

    public static function log(string $message, bool $timestamp = true): void
    {
        $ymd = date('Ymd');
        $logPath = TP_THEMEPARKS__PLUGIN_DIR . '/log-' . $ymd . '.log';
        $fp = fopen($logPath, 'a+');

        if ($timestamp) {
            $logTime = date('Y-m-d H:i:s');
            fwrite($fp, "[$logTime] {$message}" . PHP_EOL);
        } else {
            fwrite($fp, $message . PHP_EOL);
        }

        fclose($fp);
    }

    public static function getLogFiles(): array
    {
        $files = [];

        foreach (glob(TP_THEMEPARKS__PLUGIN_DIR . 'log-*.log') as $path) {
            $name = basename($path);
            preg_match('/^log-(\d+)\.log$/', $name, $matches);
            $date = intval($matches[1]);

            $files[] = [
                'name' => $name,
                'path' => $path,
                'view_url' => admin_url('admin.php?' . http_build_query([
                    'page' => 'themeparks/admin/logs.php',
                    'file' => $name,
                    'noheader' => 1,
                ])),
                'date' => $date,
            ];
        }

        uasort($files, function ($a, $b) {
            return $b['date'] - $a['date'];
        });

        return $files;
    }

    public static function cron_run()
    {
        require_once TP_THEMEPARKS__PLUGIN_DIR . 'includes/class-cron.php';
        TP_ThemeParks_Cron::run_every_five_minutes();

        // clean up junk records
        $db = self::db();
        $sql = $db->prepare('
            DELETE FROM `wp_tp_park_wait` WHERE `status` = \'\'
        ');
        $db->query($sql);

        // remove old logs
        $logFiles = self::getLogFiles();
        while (count($logFiles) > 3) {
            $logFile = array_pop($logFiles);
            @unlink($logFile['path']);
        }
    }

    public static function load_resources()
    {
        $path = $_SERVER['REQUEST_URI'];
        if (stripos($path, '/' . self::option_get_parks_route()) === false) {
            return;
        }

        $version = substr(md5_file(TP_THEMEPARKS__PLUGIN_DIR . 'assets/css/park.css'), 0, 6);
        wp_register_style('theme' . 'parks.css', plugin_dir_url(__FILE__) . 'assets/css/park.css', [], $version);
        wp_enqueue_style('theme' . 'parks.css');

        $js_version = substr(md5_file(TP_THEMEPARKS__PLUGIN_DIR . 'assets/js/app.js'), 0, 6);
        wp_register_script('theme' . 'park.js', plugin_dir_url(__FILE__) . 'assets/js/app.js', [], $js_version);
        wp_enqueue_script('theme' . 'park.js');
    }

    public static function action_check_admin_referer($action)
    {
        if ($action === 'bulk-tp-themeparks-parks') {
            return true;
        }

        return false;
    }

    public static function action_admin_menu()
    {
        add_menu_page(
            'Theme Parks',
            'Theme Parks',
            'manage_options',
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php',
            null,
            '',
            9999
        );

        // sub menu.
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
        add_submenu_page(
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/parks.php',
            'Logs',
            'Logs',
            'manage_options',
            TP_THEMEPARKS__PLUGIN_DIR . 'admin/logs.php',
            null,
            9999
        );
    }

    public static function hook_activation()
    {
        self::init_tables();
    }

    public static function hook_deactivation()
    {
        $cron_timestamp = wp_next_scheduled(self::CRON_HOOK_NAME);
        if ($cron_timestamp) {
            wp_unschedule_event($cron_timestamp, self::CRON_HOOK_NAME);
        }
    }

    public static function filter_pre_handle_404($is_404)
    {
        $pageName = get_query_var('page' . 'name');
        if ($pageName === self::PAGE_NAME_PARKS
            || $pageName === self::PAGE_NAME_ATTRACTIONS
        ) {
            // prevent return 404 status in our pages.
            return true;
        }

        return $is_404;
    }

    public static function filter_cron_schedules($schedules)
    {
        $schedules[self::CRON_RECURRENCE] = [
            'interval' => 300,
            'display' => __theme_parks_trans('Every Five Minutes')
        ];

        return $schedules;
    }

    public static function filter_template_include($template)
    {
        $pageName = get_query_var('page' . 'name');
        if ($pageName === self::PAGE_NAME_PARKS) {
            $parkId = get_query_var(self::QUERY_VAR_PARK_SLUG, '');
            if ($parkId !== '') {
                return TP_THEMEPARKS__PLUGIN_DIR . 'templates/park-single.php';
            }

            return TP_THEMEPARKS__PLUGIN_DIR . 'templates/park-index.php';
        } elseif ($pageName === self::PAGE_NAME_ATTRACTIONS) {
            $attractionId = (int) get_query_var(self::QUERY_VAR_ATTRACTION_ID);
            if ($attractionId > 0) {
                return TP_THEMEPARKS__PLUGIN_DIR . 'templates/attraction.php';
            }
        }

        return $template;
    }

    public static function filter_query_vars($query_vars)
    {
        $query_vars[] = self::QUERY_VAR_PARK_SLUG;
        $query_vars[] = self::QUERY_VAR_ATTRACTION_ID;

        return $query_vars;
    }

    public static function rewrites_init(): void
    {
        $route_name = preg_quote(self::option_get_parks_route());

        add_rewrite_rule(
            '^(' . $route_name . ')(\/)?([0-9a-zA-Z\-]+)?\/?$',
            'index.php?page' . 'name=' . self::PAGE_NAME_PARKS . '&' . self::QUERY_VAR_PARK_SLUG . '=$matches[3]',
            'top'
        );
        add_rewrite_rule(
            '^(' . $route_name . ')\/attractions\/([0-9]+)\/?$',
            'index.php?page' . 'name=' . self::PAGE_NAME_ATTRACTIONS . '&' . self::QUERY_VAR_ATTRACTION_ID . '=$matches[2]',
            'top'
        );

        flush_rewrite_rules();
    }

    public static function date_time($timestamp, ?string $format = null, ?DateTimeZone $time_zone = null)
    {
        if ($format === null || $format === 'absolute') {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        if ($time_zone === null) {
            $time_zone = new DateTimeZone('UTC');
        }

        if ($timestamp instanceof DateTime) {
            $dt = clone $timestamp;
        } else {
            $dt = new DateTime('@' . $timestamp, $time_zone);
        }

        $wp_time_zone = wp_timezone();
        if ($dt->getTimezone() !== $wp_time_zone) {
            $dt->setTimezone(wp_timezone());
        }

        return $dt->format($format);
    }

    /** LINKS */
    public static function get_park_list_url()
    {
        return site_url(self::option_get_parks_route() . '/');
    }
    public static function get_park_item_url($park)
    {
        if (is_array($park)) {
            $slug = $park['slug'];
        } elseif (is_object($park)) {
            $slug = $park->slug;
        } else {
            throw new \InvalidArgumentException('Invalid argument.');
        }

        return site_url(self::option_get_parks_route() . '/' . urlencode($slug) . '/');
    }

    public static function get_attraction_view_url($attraction)
    {
        if (is_array($attraction)) {
            $attraction_id = $attraction['attraction_id'];
        } elseif (is_object($attraction)) {
            $attraction_id = $attraction->attraction_id;
        } else {
            throw new \InvalidArgumentException('Invalid argument.');
        }

        return site_url(self::option_get_parks_route() . '/attractions/' . $attraction_id . '/');
    }

    /** OPTIONS */
    public static function option_update_api_url(string $api_url)
    {
        update_option('tp_themeparks_api_url', $api_url);
    }
    public static function option_get_api_url()
    {
        return get_option('tp_themeparks_api_url');
    }
    public static function option_update_parks_route(string $route)
    {
        if (empty($route) || preg_match('#[^a-zA-Z0-9\-]+#', $route)) {
            wp_die(__theme_parks_trans('Please enter valid route name'));
        }

        update_option('tp_themeparks_park_route', $route);
    }
    public static function option_get_parks_route()
    {
        return get_option('tp_themeparks_park_route');
    }

    public static function bulk_update_parks_status(array $ids, bool $active)
    {
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

    public static function update_park_status(string $id, bool $active)
    {
        $db = self::db();
        return self::db()->update(
            $db->prefix . 'tp_parks',
            [
                'active' => $active ? 1 : 0
            ],
            ['park_id' => $id]
        );
    }

    public static function get_parks(bool $active_only = false, string $order = 'name')
    {
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

    public static function get_park(int $park_id)
    {
        $db = self::db();
        $query = $db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_parks`
            WHERE `park_id` = %d
        ", $park_id);

        return $db->get_row($query);
    }

    public static function get_attraction(int $attraction_id)
    {
        $db = self::db();
        $attraction = $db->get_row($db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_park_attraction`
            WHERE `attraction_id` = %d
        ", $attraction_id), ARRAY_A);
        if (empty($attraction)) {
            return null;
        }

        $attraction['park'] = self::get_park($attraction['park_id']);

        return $attraction;
    }

    public static function insert_parks(array $parks)
    {
        $db = self::db();
        $total = 0;
        foreach ($parks as $park) {
            $park_data = [
                'name' => $park['name'],
                'latitude' => $park['latitude'],
                'longitude' => $park['longitude'],
                'timezone' => $park['timeZone'],
                'slug' => strtolower(sanitize_title_with_dashes($park['name'])),
                'extra_data' => json_encode($park),
            ];
            if (!empty($park['park_id']) && self::get_park($park['park_id'])) {
                $result = $db->update(
                    $db->prefix . 'tp_parks',
                    $park_data,
                    [
                        'park_id' => $park['park_id']
                    ]
                );
            } else {
                $result = $db->insert(
                    $db->prefix . 'tp_parks',
                    $park_data + [
                        'active' => 0,
                        'last_sync_date' => 0
                    ]
                );
            }

            if ($result === false) {
                wp_die($db->last_error);
            }

            $total++;
        }

        return $total;
    }

    public static function get_park_by_slug(string $slug)
    {
        $db = self::db();
        $query = $db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_parks`
            WHERE `slug` = %s
        ", $slug);

        return $db->get_row($query);
    }

    protected static function init_tables(): void
    {
        // check tables.
        $db = self::db();
        if (!$db->query( "SHOW TABLES LIKE '{$db->prefix}tp_parks'")) {
            $sqlTable = "
                CREATE TABLE IF NOT EXISTS `{$db->prefix}tp_parks` (
                    `park_id` INT UNSIGNED AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(100) DEFAULT NULL,
                    `latitude` VARCHAR(25) NOT NULL,
                    `longitude` VARCHAR(25) NOT NULL,
                    `timezone` VARCHAR(32) NOT NULL,
                    `active` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    `last_sync_date` INT UNSIGNED NOT NULL DEFAULT '0',
                    `extra_data` TEXT NOT NULL,
                    `image_url` VARCHAR(255) NOT NULL DEFAULT '',
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

        if (!$db->query("SHOW TABLES LIKE '{$db->prefix}tp_park_wait'")) {
            $sqlTable = "
                CREATE TABLE IF NOT EXISTS `{$db->prefix}tp_park_wait` (
                    `wait_id` INT UNSIGNED AUTO_INCREMENT,
                    `active` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    `park_id` INT UNSIGNED NOT NULL,
                    `attraction_id` INT UNSIGNED NOT NULL,
                    `status` VARCHAR(25) NOT NULL,
                    `wait_time` INT UNSIGNED NOT NULL DEFAULT '0',
                    `fast_pass` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    `extra_data` TEXT NOT NULL,
                    `created_date` INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY `wait_id` (`wait_id`),
                    KEY `park_id_created_date` (`park_id`, `created_date`),
                    KEY `attraction_id` (`attraction_id`)
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
                    `park_id` INT UNSIGNED NOT NULL,
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

        if (!$db->query("SHOW TABLES LIKE '{$db->prefix}tp_park_attraction'")) {
            $db->query("
                CREATE TABLE IF NOT EXISTS `{$db->prefix}tp_park_attraction` (
                    `attraction_id` INT UNSIGNED AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `attraction_type` VARCHAR(25) NOT NULL,
                    `entity_id` VARCHAR(50) NOT NULL,
                    `park_id` INT UNSIGNED NOT NULL,
                    `active` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                    `status` VARCHAR(25) NOT NULL,
                    `latitude` FLOAT NOT NULL,
                    `longitude` FLOAT NOT NULL,
                    `updated_date` INT UNSIGNED NOT NULL DEFAULT '0',
                    PRIMARY KEY `attraction_id` (`attraction_id`),
                    UNIQUE KEY `entity_id` (`entity_id`),
                    KEY `park_id` (`park_id`),
                    KEY `park_id_type` (`park_id`, `attraction_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
            ");
        }

        if (!$db->query("SHOW COLUMNS FROM `{$db->prefix}tp_parks` LIKE 'image_url'")) {
            $db->query("ALTER TABLE `{$db->prefix}tp_parks` ADD COLUMN `image_url` VARCHAR(255) NOT NULL DEFAULT ''");
        }

        if (!$db->query("SHOW INDEXES FROM `{$db->prefix}tp_park_wait` LIKE `Key_name` = \'park_status_date\'")) {
            $db->query("
                ALTER TABLE `{$db->prefix}tp_park_wait` ADD KEY `park_status_date` (`park_id`, `status`, `created_date`)
            ");
        }
    }

    public static function db(): \wpdb
    {
        global $wpdb;

        return $wpdb;
    }
}
