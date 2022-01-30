<?php

class TP_ThemeParks_Cron
{
    public static function run_every_five_minutes(float $max_run_time = 5.0)
    {
        $parks = TP_ThemeParks::get_parks(true, 'last_sync_date');
        if (empty($parks)) {
            return;
        }

        require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks-api.php';
        $api_url = TP_ThemeParks::option_get_api_url();
        if (empty($api_url)) {
            return;
        }

        $api = new TP_ThemeParks_Api($api_url);

        $start = microtime(true);
        $db = TP_ThemeParks::db();

        foreach ($parks as $park) {
            static::sync_wait_times($api, $park);
            static::sync_opening_times($api, $park);

            $db->update(
                $db->prefix . 'tp_parks',
                [
                    'last_sync_date' => time()
                ],
                ['park_id' => $park->park_id]
            );

            if ($max_run_time > 0 && microtime(true) - $start >= $max_run_time) {
                break;
            }
        }
    }

    protected static function sync_wait_times(TP_ThemeParks_Api $api, $park)
    {
        TP_ThemeParks::log('<---- sync_wait_times(' . $park->park_id . ') ---->');
        $start = microtime(true);
        $db = TP_ThemeParks::db();
        $extra_data = json_decode($park->extra_data, true);
        $wait_times = $api->get_wait_times($extra_data['id']);
        TP_ThemeParks::log('Total ' . count($wait_times) . ' records');
        if (empty($wait_times)) {
            return;
        }

        foreach ($wait_times as $wait_entry) {
            if (!is_int($wait_entry['waitTime'])) {
                continue;
            }

            $type = $wait_entry['meta']['type'] ?? '';
            $type_id = $wait_entry['meta']['entityId'] ?? '';
            if (empty($type) || empty($type_id)) {
                continue;
            }

            $attraction_data = [
                'name' => $wait_entry['name'],
                'active' => $wait_entry['active'] ? 1 : 0,
                'status' => empty($wait_entry['lastUpdate']) ? '' : strtolower($wait_entry['status']),
                'attraction_type' => strtolower($type),
                'entity_id' => $type_id,
                'latitude' => floatval($wait_entry['meta']['latitude'] ?? 0),
                'longitude' => floatval($wait_entry['meta']['longitude'] ?? 0),
                'updated_date' => time(),
                'park_id' => $park->park_id,
            ];
            $attraction_id = static::get_attraction_id($attraction_data);
            $db->update(
                $db->prefix . 'tp_park_attraction',
                $attraction_data,
                [
                    'attraction_id' => $attraction_id
                ]
            );

            $db->insert($db->prefix . 'tp_park_wait', [
                'active' => $attraction_data['active'],
                'park_id' => $park->park_id,
                'attraction_id' => $attraction_id,
                'status' => $attraction_data['status'],
                'wait_time' => $wait_entry['waitTime'],
                'fast_pass' => $wait_entry['fastPass'] ? 1 : 0,
                'extra_data' => json_encode($wait_entry),
                'created_date' => time()
            ]);
        }

        $timeElapsed = microtime(true) - $start;
        TP_ThemeParks::log('Time elapsed: ' . $timeElapsed . ' seconds');
        TP_ThemeParks::log('<---- END ---->');
    }

    protected static function get_attraction_id(array $info)
    {
        $entity_id = $info['entity_id'];
        $db = TP_ThemeParks::db();

        $row = $db->get_row($db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_park_attraction`
            WHERE `entity_id` = %s
        ", $entity_id));
        if (!empty($row)) {
            return $row->attraction_id;
        }

        $rows_count = $db->insert($db->prefix . 'tp_park_attraction', $info);
        if ($rows_count === false) {
            wp_die("Failed to insert attraction.");
        }

        return static::get_attraction_id($info);
    }

    protected static function sync_opening_times(TP_ThemeParks_Api $api, $park)
    {
        $extra_data = json_decode($park->extra_data, true);
        $opening_times = $api->get_opening_times($extra_data['id']);
        if (empty($opening_times)) {
            return;
        }

        $db = TP_ThemeParks::db();

        foreach ($opening_times as $opening_time) {
            try {
                $open_time_dt = DateTime::createFromFormat(DateTime::ISO8601, $opening_time['openingTime']);
                $open_time_dt->setTimezone(new DateTimeZone('UTC'));

                $close_time_dt = DateTime::createFromFormat(DateTime::ISO8601, $opening_time['closingTime']);
                $close_time_dt->setTimezone(new DateTimeZone('UTC'));
            } catch (Throwable $e) {
                continue;
            }

            $opening_record = $db->get_row($db->prepare("
                SELECT *
                FROM `{$db->prefix}tp_park_opening`
                WHERE `park_id` = %s AND open_date = %s
            ", $park->park_id, $opening_time['date']));
            if (!empty($opening_record)) {
                $db->update(
                    $db->prefix . 'tp_park_opening',
                    [
                        'open_time' => $open_time_dt->getTimestamp(),
                        'close_time' => $close_time_dt->getTimestamp(),
                        'type' => strtolower($opening_time['type']),
                        'extra_data' => json_encode($opening_time)
                    ],
                    [
                        'id' => $opening_record->id
                    ]
                );
            } else {
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
                    $park->park_id,
                    $opening_time['date'],
                    $open_time_dt->getTimestamp(),
                    $close_time_dt->getTimestamp(),
                    strtolower($opening_time['type']),
                    json_encode($opening_time)
                );

                $db->query($query);
            }
        }
    }
}
