<?php
/**
 * @package ThemeParks
 */
/*
Plugin Name: Theme Parks
Plugin URI: https://nobita.me
Description: Display waiting times and opening times each parks.
Version: 1.0.7
Author: Truong Luu
Text Domain: themeparks
*/

if (!function_exists( 'add_action' )) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define('TP_THEMEPARKS__PLUGIN_DIR', plugin_dir_path(__FILE__));

if (class_exists('TP_ThemeParks')) {
    echo 'Class TP_ThemeParks already declared.';
    exit;
}

if (!function_exists('__theme_parks_trans')) {
    function __theme_parks_trans(string $text) {
        return __($text, 'themeparks');
    }
}

require_once TP_THEMEPARKS__PLUGIN_DIR . 'class.themeparks.php';

add_action('init', ['TP_ThemeParks', 'initialize']);

register_activation_hook(__FILE__, ['TP_ThemeParks', 'hook_activation']);

register_deactivation_hook(__FILE__, ['TP_ThemeParks', 'hook_deactivation']);

register_uninstall_hook(__FILE__, ['TP_ThemeParks', 'uninstall']);
