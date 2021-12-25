<?php

class TP_ThemeParks_Api {
    protected $apiUrl;
    public function __construct(string $apiUrl) {
        $this->apiUrl = rtrim($apiUrl, '/');
    }

    public function parks() {
        $response = $this->request('GET', 'parks');

        return $response['parks'] ?? [];
    }

    protected function request(string $method, string $path, array $params = []) {
        $url = $this->apiUrl . '/' . $path;
        $method = strtoupper($method);

        $formData = null;
        if ($method === 'POST' || $method === 'PUT') {
            $formData = http_build_query($params, '', '&');
        } else {
            $url .= '?' . http_build_query($params, '', '&');
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3.0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10.0);

        if ($formData !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $formData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Length: ' . strlen($formData),
                'Content-Type: application/x-www-form-urlencoded'
            ]);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
