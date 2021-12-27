<?php

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';

class TP_ThemeParks_Park {
    /**
     * @var stdClass
     */
    protected $park;
    /**
     * @var DateTime
     */
    protected $date_dt;
    /**
     * @var stdClass|null|false
     */
    protected $opening_record;
    /**
     * @var array|null
     */
    protected $wait_records;

    public function __construct($park) {
        $this->park = $park;
        $this->setDate();
    }

    public function setDate(?string $date = null) {
        if ($date === null) {
            $dt = new DateTime('now', wp_timezone());
        } else {
            $dt = DateTime::createFromFormat('Y-m-d', $date, wp_timezone());
        }

        $this->date_dt = $dt;

        // reset cache
        $this->opening_record = null;
        $this->wait_records = null;
    }

    public function get_wait_date() {
        return $this->date_dt->format(get_option('date_format'));
    }

    public function get_attractions() {
        $grouped = [];
        $wait_data = $this->get_wait_data();

        foreach ($wait_data as $record) {
            if (empty($record['extra_data']) || !isset($record['extra_data']['type'])) {
                continue;
            }

            if ($record['extra_data']['type'] === 'ATTRACTION') {
                $grouped[$record['extra_data']['entityId']][] = $record;
            }
        }

        $attractions = [];
        foreach ($grouped as $id => $_records) {
            $wait_total = 0;
            foreach ($_records as $_record) {
                $wait_total += $_record['wait_time'];
            }

            $attractions[$id] = [
                'name' => $_records[0]['name'],
                'wait_average' => round($wait_total / count($_records), 1),
                'status' => ucfirst($_records[0]['status']),
                'total_records' => count($_records),
                'wait_total' => $wait_total,
            ];
        }

        uasort($attractions, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $attractions;
    }

    public function get_wait_data_chart() {
        $wait_data = $this->get_wait_data();
        $info = [];
        foreach ($wait_data as $record) {
            $time = TP_ThemeParks::date_time($record['last_update'], get_option('time_format'));
            if (!isset($info[$time])) {
                $info[$time] = [
                    $time,
                    []
                ];
            }
            $info[$time][1][] = (int) $record['wait_time'];
        }

        $data = [];
        foreach ($info as $pair) {
            list($time, $wait_times) = $pair;

            $data[] = [$time, ceil(array_sum($wait_times) / count($wait_times))];
        }

        return $data;
    }

    public function get_open_time() {
        $record = $this->get_opening_record();

        return $record === false ? '' : sprintf(
            '<span class="park-hours park-open-time">%s</span>',
            esc_html(TP_ThemeParks::date_time($record->open_time))
        );
    }

    public function get_close_time() {
        $record = $this->get_opening_record();

        return $record === false ? '' : sprintf(
            '<span class="park-hours park-open-time">%s</span>',
            esc_html(TP_ThemeParks::date_time($record->open_time))
        );
    }

    public function get_status() {
        $record = $this->get_opening_record();

        return $record === false ? '' : sprintf(
            '<span class="park-hours park-open-status">%s</span>',
            esc_html(ucfirst($record->type))
        );
    }

    public function get_wait_data(?DateTime $dt = null) {
        $allow_cache = $dt === null;
        if ($this->wait_records !== null && $allow_cache) {
            return $this->wait_records;
        }

        $db = TP_ThemeParks::db();
        $dt = $dt ?: $this->date_dt;

        $start_of_day = (clone $dt)->setTime(0, 0)->getTimestamp();
        $end_of_day = (clone $dt)-> setTime(23, 59, 59)->getTimestamp();

        $query = $db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_park_wait`
            WHERE `last_update` BETWEEN %d AND %d
                AND `park_id` = %s
            ORDER BY `last_update`
        ", $start_of_day, $end_of_day, $this->park->park_id);
        $records = (array) $db->get_results($query, ARRAY_A);
        foreach ($records as &$record) {
            $record['extra_data'] = (array) json_decode($record['extra_data'], true);
        }

        if ($allow_cache) {
            $this->wait_records = $records;
        }

        return $records;
    }

    public function get_opening_record() {
        if ($this->opening_record !== null) {
            return $this->opening_record;
        }

        $db = TP_ThemeParks::db();
        $query = $db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_park_opening`
            WHERE `park_id` = %s AND `open_date` = %s
        ", $this->park->park_id, $this->date_dt->format('Y-m-d'));

        $record = $db->get_row($query);
        $this->opening_record = is_object($record) ? $record : false;

        return $this->opening_record;
    }
}
