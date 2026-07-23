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
