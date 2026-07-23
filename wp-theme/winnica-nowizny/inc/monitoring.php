<?php
/**
 * Public uptime endpoint and WordPress Site Health checks.
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function (): void {
    register_rest_route('winnica/v1', '/health', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function (): WP_REST_Response {
            global $wpdb;
            $database_ok = $wpdb->get_var('SELECT 1') === '1';
            $status = $database_ok ? 200 : 503;

            return new WP_REST_Response([
                'status'  => $database_ok ? 'ok' : 'degraded',
                'service' => 'winnica-nowizny',
                'time'    => gmdate('c'),
            ], $status);
        },
    ]);
});

add_filter('site_status_tests', function (array $tests): array {
    $tests['direct']['winnica_smtp'] = [
        'label' => 'Konfiguracja SMTP Winnicy',
        'test'  => function (): array {
            $configured = function_exists('winnica_smtp_is_configured') && winnica_smtp_is_configured();
            return [
                'label'       => $configured ? 'SMTP jest skonfigurowane' : 'SMTP wymaga konfiguracji',
                'status'      => $configured ? 'good' : 'recommended',
                'badge'       => ['label' => 'Winnica Nowizny', 'color' => 'blue'],
                'description' => '<p>' . ($configured
                    ? 'Formularz korzysta ze skonfigurowanego transportu SMTP.'
                    : 'Uzupełnij zmienne WINNICA_SMTP_* w pliku .env środowiska produkcyjnego.') . '</p>',
                'actions'     => '',
                'test'        => 'winnica_smtp',
            ];
        },
    ];
    return $tests;
});
