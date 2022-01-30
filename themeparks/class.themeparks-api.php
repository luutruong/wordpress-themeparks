<?php

class TP_ThemeParks_Api {
    protected $apiUrls = [];
    public function __construct(string $apiUrl) {
        if (strpos($apiUrl, ',') === false) {
            $this->apiUrls = [$apiUrl];
        } else {
            $urls = preg_split('/\,/', $apiUrl, -1, PREG_SPLIT_NO_EMPTY);
            $urls = array_diff($urls, ['']);

            $this->apiUrls = $urls;
        }
    }

    public function get_parks() {
        $response = $this->do_request('GET', 'parks');

        return $response['parks'] ?? [];
    }

    public function get_wait_times(string $park_id) {
        $response = $this->do_request('GET', 'parks/' . urlencode($park_id) . '/wait-times');

        return $response['results'] ?? [];
    }

    public function get_opening_times(string $park_id) {
        $response = $this->do_request('GET', 'parks/' . urlencode($park_id) . '/opening-times');

        return $response['results'] ?? [];
    }

    protected function do_request(string $method, string $path, array $params = []) {
        $apiUrls = $this->apiUrls;
        while (count($apiUrls) > 0) {
            $apiUrl = array_shift($apiUrls);
            $url = rtrim($apiUrl, '/') . '/' . $path;

            $method = strtoupper($method);

            $formData = null;
            if ($method === 'POST' || $method === 'PUT') {
                $formData = http_build_query($params, '', '&');
            } elseif (count($params) > 0) {
                $url .= '?' . http_build_query($params, '', '&');
            }

            $ch = curl_init($url);
            $start = microtime(true);

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5.0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10.0);

            if ($formData !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $formData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Length: ' . strlen($formData),
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
            }

            $response = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);
            $errCode = curl_errno($ch);
            $err = curl_error($ch);
            curl_close($ch);

            $timeElapsed = microtime(true) - $start;
            TP_ThemeParks::log(sprintf(
                'Api request $url=%s $timeElapsed=%f $responseCode=%d',
                $url,
                $timeElapsed,
                $curlInfo['http_code']
            ));
            if (empty($curlInfo['http_code'])) {
                TP_ThemeParks::log(sprintf(
                    '  -> CURL Error $errCode=%d $err=%s',
                    $errCode,
                    $err
                ));
            }

            $json = json_decode($response, true);
            if (empty($json)) {
                // fall back to other API
                continue;
            }

            return $json;
        }

        return [];
    }
}
