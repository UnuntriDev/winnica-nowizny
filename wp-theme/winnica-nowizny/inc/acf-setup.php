<?php
/**
 * ACF configuration — JSON sync only (free ACF, no Options Pages).
 */

defined('ABSPATH') || exit;

add_filter('acf/settings/save_json', function () {
    return WINNICA_DIR . '/acf-json';
});

add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = WINNICA_DIR . '/acf-json';
    return $paths;
});
