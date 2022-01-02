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
     * @var array
     */
    protected $attractions;

    public function __construct($park)
    {
        $this->park = $park;
        $this->setDate();
    }

    public function setDate(?string $date = null)
    {
        if ($date === null) {
            $dt = new DateTime('now', wp_timezone());
        } else {
            $dt = DateTime::createFromFormat('Y-m-d', $date, wp_timezone());
        }

        $this->date_dt = $dt;

        // reset cache
        $this->opening_record = null;
    }

    public function get_total_attractions()
    {
        return count($this->get_attractions());
    }

    public function get_attractions()
    {
        if ($this->attractions !== null) {
            return $this->attractions;
        }

        $db = TP_ThemeParks::db();
        $results = $db->get_results($db->prepare("
            SELECT *
            FROM `{$db->prefix}tp_park_attraction`
            WHERE `park_id` = %s AND `attraction_type` = %s
            ORDER BY `name`
        ", $this->park->park_id, 'attraction'), ARRAY_A);
        $attractions = [];
        foreach ($results as $result) {
            $attractions[$result['attraction_id']] = array_merge($result, [
                'view_url' => TP_ThemeParks::get_attraction_view_url($result),
            ]);
        }
        $this->attractions = $attractions;

        return $this->attractions;
    }

    public function get_attractions_overview()
    {
        return [
            [
                'title' => __theme_parks_trans('Attractions with Wait Times'),
                'attractions' => $this->get_attractions_operating(),
                'with_wait_times' => true,
            ],
            [
                'title' => __theme_parks_trans('Attractions Closed'),
                'attractions' => $this->get_attractions_closed()
            ],
            [
                'title' => __theme_parks_trans('Attractions Refurbishment'),
                'attractions' => $this->get_attractions_refurbishment(),
            ],
            [
                'title' => __theme_parks_trans('Attractions Not Reporting'),
                'attractions' => $this->get_attractions_not_reporting()
            ]
        ];
    }

    public function get_attractions_operating()
    {
        $start_of_day = (clone $this->date_dt)->setTime(0, 0)->getTimestamp();
        $end_of_day = (clone $this->date_dt)->setTime(23, 59, 59)->getTimestamp();

        $attractions = $this->get_attractions();
        $attraction_ids = array_keys($attractions);

        $db = TP_ThemeParks::db();
        $query = $db->prepare("
                SELECT `attraction_id`, AVG(`wait_time`) AS `avg_wait_time`
                FROM `{$db->prefix}tp_park_wait`
                WHERE `park_id` = %s 
                    AND `created_date` BETWEEN %d AND %d
                    AND `status` = %s
                GROUP BY `attraction_id`
                ORDER BY `avg_wait_time`
            ",
            $this->park->park_id,
            $start_of_day,
            $end_of_day,
            'operating'
        );

        $results = [];
        foreach ($db->get_results($query) as $record) {
            if (!isset($attractions[$record->attraction_id])) {
                continue;
            }

            $results[$record->attraction_id] = array_replace($attractions[$record->attraction_id], [
                'avg_wait_time' => ceil($record->avg_wait_time)
            ]);
        }

        uasort($results, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $results;
    }

    public function get_attractions_closed()
    {
        return array_filter($this->get_attractions(), function ($attraction) {
            return $attraction['status'] === 'closed';
        });
    }

    public function get_attractions_refurbishment()
    {
        return array_filter($this->get_attractions(), function ($attraction) {
            return $attraction['status'] === 'refurbishment';
        });
    }

    public function get_attractions_not_reporting()
    {
        return array_filter($this->get_attractions(), function ($attraction) {
            return empty($attraction['status']);
        });
    }

    public function get_park_insights()
    {
        return [
            [
                'title' => 'Lowest wait times today',
                'data' => $this->get_lowest_wait_time_attractions('today')
            ],
            [
                'title' => 'Lowest wait times this week',
                'data' => $this->get_lowest_wait_time_attractions('this_week')
            ],
            [
                'title' => 'Lowest wait times this month',
                'data' => $this->get_lowest_wait_time_attractions('this_month')
            ],
            [
                'title' => 'Lowest wait times this year',
                'data' => $this->get_lowest_wait_time_attractions('this_year')
            ],
        ];
    }

    protected function get_lowest_wait_time_attractions(string $type)
    {
        $start_date = new DateTime('now', wp_timezone());
        $end_date = clone $start_date;

        if ($type === 'today') {
            $start_date->setTime(0, 0);
            $end_date->setTime(23, 59, 59);
        } elseif ($type === 'this_week') {
            $week_days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $start_of_week = get_option('start_of_week');
            $start_date->modify($week_days[$start_of_week] . ' this week');
            $start_date->setTime(0, 0);
            $end_date = (clone $start_date)->modify('+7 days')->setTime(23, 59, 59);
        } elseif ($type === 'this_month') {
            $start_date->modify('first day of this month')->setTime(0, 0);
            $end_date = (clone $start_date)->modify('last day of this month')->setTime(23, 59, 59);
        } elseif ($type === 'this_year') {
            $current_year = (int) $start_date->format('Y');
            $start_date->setDate($current_year, 1, 1)->setTime(0, 0);
            if ($current_year % 4 === 0) {
                $end_date = (clone $start_date)->modify('+366 days')->setTime(23, 59, 59);
            } else {
                $end_date = (clone $start_date)->modify('+365 days')->setTime(23, 59, 59);
            }
        }

        $start_timestamp = $start_date->getTimestamp();
        $end_timestamp = $end_date->getTimestamp();

        $db = TP_ThemeParks::db();
        $query = $db->prepare("
                SELECT `attraction_id`, AVG(`wait_time`) AS `avg_wait_time`
                FROM `{$db->prefix}tp_park_wait`
                WHERE `park_id` = %s 
                    AND `created_date` BETWEEN %d AND %d
                    AND `status` = %s
                GROUP BY `attraction_id`
                ORDER BY `avg_wait_time`
            ", $this->park->park_id, $start_timestamp, $end_timestamp, 'operating');

        $results = $db->get_results($query, ARRAY_A);

        $lowest_wait_time = -1.0;
        foreach ($results as $result) {
            if (floatval($result['avg_wait_time']) < $lowest_wait_time
                || $lowest_wait_time === -1.0
            ) {
                $lowest_wait_time = floatval($result['avg_wait_time']);
            }
        }

        // get all attractions with same avg wait time.
        $all_attractions = $this->get_attractions();
        $attractions = [];
        foreach ($results as $result) {
            if (!isset($all_attractions[$result['attraction_id']])) {
                continue;
            }

            if ($result['avg_wait_time'] == $lowest_wait_time) {
                $attractions[$result['attraction_id']] = array_replace($all_attractions[$result['attraction_id']], [
                    'avg_wait_time' => ceil($result['avg_wait_time'])
                ]);
            }
        }

        $date_format = get_option('date_format');
        uasort($attractions, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'attractions' => $attractions,
            'date_range' => $type === 'today'
                ? $start_date->format($date_format)
                : sprintf(
                    '%s - %s',
                    $start_date->format($date_format),
                    $end_date->format($date_format)
                ),
        ];
    }

    public function get_wait_data_charts(array $attraction = [])
    {
        $data = [];

        $segments = [
            [
                'sub_days' => 0,
                'title' => __theme_parks_trans('Wait Times Today'),
                'data' => [],
                'vaxis-title' => __theme_parks_trans('Wait Time (minutes)'),
            ],
            [
                'sub_days' => 1,
                'title' => __theme_parks_trans('Wait Times Yesterday'),
                'data' => [],
                'vaxis-title' => __theme_parks_trans('Wait Time (minutes)'),
            ],
            [
                'sub_days' => 7,
                'title' => __theme_parks_trans('Wait Times Last 7 Days'),
                'data' => [],
                'vaxis-title' => __theme_parks_trans('Wait Time (minutes)'),
            ],
            [
                'sub_days' => 30,
                'title' => __theme_parks_trans('Wait Times Last 30 Days'),
                'data' => [],
                'vaxis-title' => __theme_parks_trans('Wait Time (minutes)'),
            ]
        ];
        $date_format = get_option('date_format');
        foreach ($segments as $segment) {
            $suffix = $segment['sub_days'] > 1 ? 'days' : 'day';
            $start_date = (clone $this->date_dt)
                ->modify('-' . $segment['sub_days'] . ' ' . $suffix)
                ->setTime(0, 0)
                ->getTimestamp();
            $end_date = (clone $this->date_dt)->setTime(23, 59, 59)->getTimestamp();

            $data[] = [
                'title' => $segment['title'],
                'data' => $this->get_wait_data_chart([
                    'attraction_id' => $attraction['attraction_id'] ?? null,
                    'date_range' => [$start_date, $end_date, wp_timezone()],
                    'group_by' => $segment['sub_days'] > 1 ? 'daily' : 'hourly',
                ]),
                'date' => $segment['sub_days'] > 1
                    ? sprintf('%s - %s', TP_ThemeParks::date_time($start_date, $date_format), TP_ThemeParks::date_time($end_date, $date_format))
                    : TP_ThemeParks::date_time($start_date, $date_format),
                'vaxis-title' => $segment['vaxis-title']
            ];
        }

        return $data;
    }

    public function get_wait_data_chart(array $options = [])
    {
        if (isset($options['date_range'])) {
            list($start_of_day, $end_of_day, $time_zone) = $options['date_range'];
        } else {
            $start_of_day = (clone $this->date_dt)->setTime(0, 0)->getTimestamp();
            $end_of_day = (clone $this->date_dt)->setTime(23, 59, 59)->getTimestamp();
            $time_zone = $this->date_dt->getTimezone();
        }

        $whereClause = '';
        if (!empty($options['attraction_id'])) {
            $whereClause = sprintf('AND `attraction_id` = %d', intval($options['attraction_id']));
        }

        $groupType = $options['group_by'] ?? null;

        $db = TP_ThemeParks::db();
        if ($groupType === 'daily') {
            $query = $db->prepare("
                SELECT FROM_UNIXTIME(`created_date`, '{mysql_date_format}') as `_date`, AVG(`wait_time`) AS `_avg_wait_time`
                FROM `{$db->prefix}tp_park_wait`
                WHERE `park_id` = %s
                    AND `created_date` BETWEEN %d AND %d
                    AND `status` = %s
                    {$whereClause}
                GROUP BY `_date`
                ORDER BY `_date`
            ",
                $this->park->park_id,
                $start_of_day,
                $end_of_day,
                'operating'
            );
            $query = str_replace('{mysql_date_format}', '%Y-%m-%d', $query);
        } else {
           $query = $db->prepare("
                SELECT *
                FROM `{$db->prefix}tp_park_wait`
                WHERE `park_id` = %s AND `created_date` BETWEEN %d AND %d
                    AND `status` = %s
                    {$whereClause}
                ORDER BY `created_date`
            ", $this->park->park_id, $start_of_day, $end_of_day, 'operating');
        }

        $records = $db->get_results($query, ARRAY_A);
        $data = [];

        if ($groupType === 'daily') {
            $date_pairs = [];
            foreach ($records as $record) {
                $date_pairs[$record['_date']] = ceil(floatval($record['_avg_wait_time']));
            }
            $start_date = $start_of_day;
            while ($start_date <= $end_of_day) {
                $_date_dt = new DateTime('@' . $start_date, $time_zone);
                $_date = $_date_dt->format('Y-m-d');
                $data[] = [
                    $_date_dt->format(get_option('date_format')),
                    $date_pairs[$_date] ?? 0
                ];

                $start_date += 86400;
            }
        } else {
            $info = [];
            foreach ($records as $record) {
                $time = TP_ThemeParks::date_time($record['created_date'], 'g:00 A');
                if (!isset($info[$time])) {
                    $info[$time] = [
                        $time,
                        []
                    ];
                }
                $info[$time][1][] = (int) $record['wait_time'];
            }

            foreach ($info as $pair) {
                list($time, $wait_times) = $pair;

                $data[] = [$time, ceil(array_sum($wait_times) / count($wait_times))];
            }
        }

        return $data;
    }

    public function get_open_time()
    {
        $record = $this->get_opening_record();

        return $record === false ? '' : sprintf(
            '<span class="park-hours park-open-time">%s</span>',
            esc_html(TP_ThemeParks::date_time($record->open_time))
        );
    }

    public function get_close_time()
    {
        $record = $this->get_opening_record();

        return $record === false ? '' : sprintf(
            '<span class="park-hours park-open-time">%s</span>',
            esc_html(TP_ThemeParks::date_time($record->open_time))
        );
    }

    public function get_status()
    {
        $record = $this->get_opening_record();

        return $record === false ? '' : sprintf(
            '<span class="park-hours park-open-status">%s</span>',
            esc_html(ucfirst($record->type))
        );
    }

    public function get_opening_record()
    {
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
