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

/**
 * Compare an IP address with an IPv4 or IPv6 CIDR range.
 */
function winnica_ip_in_cidr(string $ip, string $cidr): bool
{
    [$network, $prefix] = array_pad(explode('/', trim($cidr), 2), 2, null);
    $ip_binary = @inet_pton($ip);
    $network_binary = @inet_pton($network);

    if ($ip_binary === false || $network_binary === false || strlen($ip_binary) !== strlen($network_binary)) {
        return false;
    }

    $bits = $prefix === null ? strlen($ip_binary) * 8 : (int) $prefix;
    if ($bits < 0 || $bits > strlen($ip_binary) * 8) {
        return false;
    }

    $full_bytes = intdiv($bits, 8);
    $remaining_bits = $bits % 8;

    if ($full_bytes > 0 && substr($ip_binary, 0, $full_bytes) !== substr($network_binary, 0, $full_bytes)) {
        return false;
    }

    if ($remaining_bits === 0) {
        return true;
    }

    $mask = (0xff << (8 - $remaining_bits)) & 0xff;
    return (ord($ip_binary[$full_bytes]) & $mask) === (ord($network_binary[$full_bytes]) & $mask);
}

/**
 * Return the visitor IP without trusting spoofable forwarding headers.
 *
 * WINNICA_TRUSTED_PROXY_CIDRS is a comma-separated allow-list of the reverse
 * proxy addresses that are permitted to supply CF-Connecting-IP,
 * X-Forwarded-For or X-Real-IP. With no allow-list REMOTE_ADDR is used.
 */
function winnica_client_ip(): string
{
    $remote = filter_var(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP);
    if ($remote === false) {
        return 'unknown';
    }

    $configured = defined('WINNICA_TRUSTED_PROXY_CIDRS')
        ? (string) WINNICA_TRUSTED_PROXY_CIDRS
        : (string) (getenv('WINNICA_TRUSTED_PROXY_CIDRS') ?: '');
    $trusted = array_filter(array_map('trim', explode(',', $configured)));

    $remote_is_trusted = false;
    foreach ($trusted as $cidr) {
        if (winnica_ip_in_cidr($remote, $cidr)) {
            $remote_is_trusted = true;
            break;
        }
    }

    if (!$remote_is_trusted) {
        return $remote;
    }

    $forwarded = [
        wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        trim(explode(',', wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''), 2)[0]),
        wp_unslash($_SERVER['HTTP_X_REAL_IP'] ?? ''),
    ];

    foreach ($forwarded as $candidate) {
        $valid = filter_var(trim((string) $candidate), FILTER_VALIDATE_IP);
        if ($valid !== false) {
            return $valid;
        }
    }

    return $remote;
}

function winnica_login_key(string $username): string
{
    $ip = winnica_client_ip();
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
 * The one ACF field wp_kses_post cannot handle: the map iframe. It used to
 * reach the page through a bare | raw, which meant any account that can edit
 * the front page could store a script there. This keeps the field usable while
 * allowing only what the field exists to hold.
 *
 * There was a second one here, winnica_kses_svg(), for the inline icons in the
 * terroir section. The section is gone and so is its last caller.
 */
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
