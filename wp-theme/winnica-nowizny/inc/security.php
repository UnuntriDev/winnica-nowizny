<?php
/**
 * Lightweight WordPress hardening and login throttling.
 */

defined('ABSPATH') || exit;

if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

add_filter('xmlrpc_enabled', '__return_false');
add_filter('auto_update_core', function ($update, $item) {
    return isset($item->response) && $item->response === 'autoupdate' ? true : $update;
}, 10, 2);
add_filter('auto_update_plugin', '__return_true');
add_filter('auto_update_theme', '__return_true');

add_filter('rest_endpoints', function (array $endpoints): array {
    if (!is_user_logged_in()) {
        unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

add_filter('login_errors', function (): string {
    return 'Nieprawidłowe dane logowania.';
});

function winnica_login_key(string $username): string
{
    $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return 'winnica_login_' . substr(hash_hmac('sha256', strtolower($username) . '|' . $ip, wp_salt('auth')), 0, 36);
}

add_filter('authenticate', function ($user, string $username) {
    if ($username === '') {
        return $user;
    }

    $state = get_transient(winnica_login_key($username));
    if (is_array($state) && !empty($state['locked_until']) && (int) $state['locked_until'] > time()) {
        $minutes = max(1, (int) ceil(((int) $state['locked_until'] - time()) / 60));
        return new WP_Error('winnica_login_locked', sprintf('Zbyt wiele prób logowania. Spróbuj ponownie za %d min.', $minutes));
    }

    return $user;
}, 5, 2);

add_action('wp_login_failed', function (string $username): void {
    $key = winnica_login_key($username);
    $state = get_transient($key);
    $attempts = is_array($state) ? (int) ($state['attempts'] ?? 0) + 1 : 1;
    $locked_until = $attempts >= 5 ? time() + 30 * MINUTE_IN_SECONDS : 0;
    set_transient($key, [
        'attempts'     => $attempts,
        'locked_until' => $locked_until,
    ], 30 * MINUTE_IN_SECONDS);
});

add_action('wp_login', function (string $user_login): void {
    delete_transient(winnica_login_key($user_login));
});

add_action('send_headers', function (): void {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
});

/**
 * The two ACF fields wp_kses_post cannot handle: inline SVG icons and the map
 * iframe. Both used to reach the page through a bare | raw, which meant any
 * account that can edit the front page could store a script there. These keep
 * the fields usable while allowing only what the fields exist to hold.
 */
function winnica_kses_svg(string $svg): string
{
    $shape = ['fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-dasharray' => true, 'opacity' => true, 'transform' => true, 'class' => true];

    return wp_kses($svg, [
        'svg'      => ['xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'aria-hidden' => true, 'focusable' => true, 'role' => true, 'class' => true],
        'g'        => $shape,
        'path'     => $shape + ['d' => true],
        'circle'   => $shape + ['cx' => true, 'cy' => true, 'r' => true],
        'ellipse'  => $shape + ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true],
        'rect'     => $shape + ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true],
        'line'     => $shape + ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true],
        'polyline' => $shape + ['points' => true],
        'polygon'  => $shape + ['points' => true],
        'title'    => [],
    ]);
}

function winnica_kses_map_embed(string $html): string
{
    $filtered = wp_kses($html, [
        'iframe' => [
            'src'             => true,
            'title'           => true,
            'width'           => true,
            'height'          => true,
            'loading'         => true,
            'referrerpolicy'  => true,
            'allowfullscreen' => true,
            'style'           => true,
        ],
    ]);

    // An iframe is only as safe as where it points. Anything that is not a
    // Google Maps embed renders as nothing rather than as a surprise.
    if (!preg_match('/\ssrc=["\']([^"\']+)["\']/i', $filtered, $m)) {
        return '';
    }
    $host = strtolower((string) wp_parse_url($m[1], PHP_URL_HOST));
    $allowed = $host === 'www.google.com' || $host === 'google.com' || $host === 'maps.google.com'
        || str_ends_with($host, '.google.com');

    if (!$allowed) {
        return '';
    }

    // ACF may contain an embed copied from Google without modern loading or
    // referrer attributes. Normalise them here so every accepted iframe has the
    // same privacy and performance baseline as the theme fallback.
    if (class_exists('WP_HTML_Tag_Processor')) {
        $processor = new WP_HTML_Tag_Processor($filtered);
        if ($processor->next_tag('iframe')) {
            $processor->set_attribute('loading', 'lazy');
            $processor->set_attribute('referrerpolicy', 'strict-origin-when-cross-origin');
            if (!$processor->get_attribute('title')) {
                $processor->set_attribute('title', 'Winnica Nowizny, Połom Mały 60');
            }
            return $processor->get_updated_html();
        }
    }

    return $filtered;
}
