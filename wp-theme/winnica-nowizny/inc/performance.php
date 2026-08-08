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

function winnica_dequeue_unused_front_page_styles(): void
{
    // The custom front-page templates do not render Gutenberg blocks. Core
    // nevertheless enqueues three inline styles there, adding about 13 kB to
    // every HTML response. Keep them available everywhere else so a future
    // block-based page or post continues to render correctly.
    if (is_front_page()) {
        foreach ([
            'wp-block-library',
            'wp-block-library-theme',
            'classic-theme-styles',
            'global-styles',
        ] as $handle) {
            wp_dequeue_style($handle);
        }
    }
}

/**
 * WordPress registers global styles both in wp_enqueue_scripts and wp_footer.
 * Remove those callbacks only for the custom front page, which does not render
 * blocks. The editor and all other templates keep the core behaviour.
 */
function winnica_disable_front_page_global_styles(): void
{
    if (!is_front_page()) {
        return;
    }

    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
    remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
}
add_action('wp', 'winnica_disable_front_page_global_styles');

add_action('wp_enqueue_scripts', function (): void {
    if (!is_user_logged_in()) {
        wp_dequeue_style('dashicons');
    }

    winnica_dequeue_unused_front_page_styles();
}, 100);

// Keep a final, front-page-only safeguard for styles added by extensions after
// wp_enqueue_scripts, without affecting other templates or the block editor.
add_action('wp_print_styles', 'winnica_dequeue_unused_front_page_styles', 100);

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
    $manifest_path = WINNICA_DIR . '/assets/dist/.vite/manifest.json';
    $asset_version = is_readable($manifest_path) ? md5_file($manifest_path) : 'development';
    $release = defined('WINNICA_RELEASE')
        ? (string) WINNICA_RELEASE
        : (string) (getenv('WINNICA_RELEASE') ?: WINNICA_VERSION);

    return 'winnica_front_' . md5(
        home_url('/')
        . '|' . determine_locale()
        . '|' . $release
        . '|' . $asset_version
    );
}

function winnica_register_page_cache_key(string $key): void
{
    $keys = get_option('winnica_page_cache_keys', []);
    $keys = is_array($keys) ? $keys : [];

    if (!in_array($key, $keys, true)) {
        $keys[] = $key;
        update_option('winnica_page_cache_keys', array_slice($keys, -20), false);
    }
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

/**
 * The cache header is a debugging aid for us, not information visitors need.
 * On production it stays off; everywhere else it is what lets the smoke tests
 * and a manual curl tell a warm response from a cold one.
 */
function winnica_page_cache_header(string $state): void
{
    if (wp_get_environment_type() !== 'production') {
        header('X-Winnica-Cache: ' . $state);
    }
}

add_action('template_redirect', function (): void {
    if (!winnica_page_cache_allowed()) {
        return;
    }

    $cached = get_transient(winnica_page_cache_key());
    if (is_string($cached) && $cached !== '') {
        winnica_page_cache_header('HIT');
        echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    winnica_page_cache_header('MISS');
    ob_start(function (string $html): string {
        if (http_response_code() === 200 && $html !== '') {
            // A cold render costs ~1.9s against ~0.6s warm, and every expiry made one
            // visitor pay it. Editing flushes the cache anyway (save_post,
            // customize_save_after), so the TTL is only a backstop. Keep it below the
            // 2-hour form token validity window in winnica_contact_token_is_valid().
            $key = winnica_page_cache_key();
            set_transient($key, $html, HOUR_IN_SECONDS);
            winnica_register_page_cache_key($key);
        }
        return $html;
    });
}, 99);

function winnica_flush_page_cache(): void
{
    $keys = get_option('winnica_page_cache_keys', []);
    if (is_array($keys)) {
        foreach ($keys as $key) {
            if (is_string($key) && str_starts_with($key, 'winnica_front_')) {
                delete_transient($key);
            }
        }
    }

    delete_option('winnica_page_cache_keys');
    delete_transient(winnica_page_cache_key());
}

/**
 * Whether a save actually changes what the front page renders.
 *
 * A cold render costs ~1.9s against ~0.6s warm, and that bill lands on whoever
 * visits next. Before this guard every contact form submission sent it: the
 * form stores the message with wp_insert_post(), save_post fired, the cache
 * went. A visitor's message does not appear anywhere on the public page.
 *
 * Revisions and autosaves are snapshots of a post whose real save fires this
 * hook again a moment later, and an auto-draft is a row WordPress created for
 * an editor screen nobody has saved yet.
 *
 * What is left is the short list of types the front page reads: its own page
 * with the ACF fields, the wines, and the images those point at. Menu changes
 * arrive through wp_update_nav_menu instead, once per menu rather than once
 * per item.
 */
function winnica_save_affects_front_page(int $post_id, WP_Post $post): bool
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return false;
    }

    if ($post->post_status === 'auto-draft') {
        return false;
    }

    return in_array($post->post_type, ['page', 'wino', 'attachment'], true);
}

add_action('save_post', function (int $post_id, WP_Post $post): void {
    if (winnica_save_affects_front_page($post_id, $post)) {
        winnica_flush_page_cache();
    }
}, 10, 2);
add_action('customize_save_after', 'winnica_flush_page_cache');
add_action('switch_theme', 'winnica_flush_page_cache');
// The nav renders a real menu (Timber::get_menu('primary')), so a menu edit changes
// the cached HTML. Terms stay unhooked: rodzaj-wina is registered but nothing
// in the templates prints it.
add_action('wp_update_nav_menu', 'winnica_flush_page_cache');

add_filter('big_image_size_threshold', function () {
    return 2560;
});
