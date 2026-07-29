<?php
/**
 * Canonical redirects for legacy URLs replaced by homepage sections.
 */

defined('ABSPATH') || exit;

add_action('template_redirect', function (): void {
    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

    if ($home_path !== '' && str_starts_with($path, $home_path . '/')) {
        $path = substr($path, strlen($home_path) + 1);
    }

    $targets = [
        'o-nas'   => '#historia',
        'kontakt' => '#wizyta',
    ];

    if (isset($targets[$path])) {
        $page = get_page_by_path($path, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status === 'publish') {
            return;
        }

        wp_safe_redirect(home_url('/' . $targets[$path]), 301);
        exit;
    }
}, 1);
