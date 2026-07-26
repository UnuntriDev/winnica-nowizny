<?php
/**
 * Performance: preload, defer, resource hints.
 */

defined('ABSPATH') || exit;

add_filter('script_loader_tag', function (string $tag, string $handle) {
    if (str_starts_with($handle, 'winnica-')) {
        return str_replace(' src=', ' defer src=', $tag);
    }
    return $tag;
}, 10, 2);

add_action('after_setup_theme', function () {
    add_theme_support('wp-block-styles');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
});

add_action('init', function (): void {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('template_redirect', 'wp_shortlink_header', 11);
});

add_action('wp_enqueue_scripts', function (): void {
    if (!is_user_logged_in()) {
        wp_dequeue_style('dashicons');
    }
}, 100);

add_action('send_headers', function (): void {
    if (is_admin() || is_user_logged_in() || is_404() || is_search() || headers_sent()) {
        return;
    }

    if (!empty($_GET)) {
        header('Cache-Control: private, no-store, max-age=0');
    } else {
        header('Cache-Control: public, max-age=300, stale-while-revalidate=60');
    }
});

add_filter('image_editor_output_format', function (array $formats): array {
    $target = function_exists('wp_image_editor_supports')
        && wp_image_editor_supports(['mime_type' => 'image/avif'])
        ? 'image/avif'
        : 'image/webp';
    $formats['image/jpeg'] = $target;
    $formats['image/png']  = $target;
    return $formats;
});

add_filter('wp_editor_set_quality', function (int $quality, string $mime_type): int {
    if ($mime_type === 'image/avif') {
        return 52;
    }
    if ($mime_type === 'image/webp') {
        return 82;
    }
    return $quality;
}, 10, 2);

add_filter('wp_get_attachment_image_attributes', function (array $attr): array {
    $attr['decoding'] = 'async';
    if (empty($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    return $attr;
});

add_filter('heartbeat_settings', function (array $settings): array {
    $settings['interval'] = 60;
    return $settings;
});

function winnica_page_cache_key(): string
{
    $analytics_id = function_exists('winnica_analytics_id') ? winnica_analytics_id() : '';
    $manifest_path = WINNICA_DIR . '/assets/dist/.vite/manifest.json';
    $asset_version = is_readable($manifest_path) ? md5_file($manifest_path) : 'development';

    return 'winnica_front_' . md5(
        home_url('/')
        . '|' . determine_locale()
        . '|' . WINNICA_VERSION
        . '|' . $asset_version
        . '|' . $analytics_id
    );
}

function winnica_page_cache_allowed(): bool
{
    return !is_admin()
        && !is_user_logged_in()
        && !is_preview()
        && is_front_page()
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && empty($_GET);
}

add_action('template_redirect', function (): void {
    if (!winnica_page_cache_allowed()) {
        return;
    }

    $cached = get_transient(winnica_page_cache_key());
    if (is_string($cached) && $cached !== '') {
        header('X-Winnica-Cache: HIT');
        echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    header('X-Winnica-Cache: MISS');
    ob_start(function (string $html): string {
        if (http_response_code() === 200 && $html !== '') {
            // A cold render costs ~1.9s against ~0.6s warm, and every expiry made one
            // visitor pay it. Editing flushes the cache anyway (save_post,
            // customize_save_after), so the TTL is only a backstop. Keep it below the
            // 2-hour form token validity window in winnica_contact_token_is_valid().
            set_transient(winnica_page_cache_key(), $html, HOUR_IN_SECONDS);
        }
        return $html;
    });
}, 99);

function winnica_flush_page_cache(): void
{
    delete_transient(winnica_page_cache_key());
}

add_action('save_post', 'winnica_flush_page_cache');
add_action('customize_save_after', 'winnica_flush_page_cache');
add_action('switch_theme', 'winnica_flush_page_cache');
// The nav renders a real menu (Timber\Menu('primary')), so a menu edit changes
// the cached HTML. Terms stay unhooked: rodzaj-wina is registered but nothing
// in the templates prints it.
add_action('wp_update_nav_menu', 'winnica_flush_page_cache');

add_filter('big_image_size_threshold', function () {
    return 2560;
});
