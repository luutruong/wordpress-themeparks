<?php

class TP_ThemeParks_FileCache
{
    public static function get(string $id)
    {
        $cacheDir = static::getCacheDir();
        if (file_exists($cacheDir . '/' . $id)) {
            $contents = file_get_contents($cacheDir . '/' . $id);
            list($timestamp, $data) = explode(PHP_EOL, $contents, 2);
            if ($timestamp > time()) {
                return json_decode($data, true);
            }
        }

        return null;
    }

    public static function save(string $id, $data, int $minutes = 0): void
    {
        $cacheDir = static::getCacheDir();
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }

        $expires = $minutes > 0 ? (time() + $minutes * 60) : 0;

        file_put_contents(
            $cacheDir . '/' . $id,
            $expires . PHP_EOL . json_encode($data),
            LOCK_EX
        );
    }

    protected static function getCacheDir(): string
    {
        return TP_THEMEPARKS__PLUGIN_DIR . 'cache';
    }
}
