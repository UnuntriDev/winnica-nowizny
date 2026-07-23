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
            $environment = wp_get_environment_type();
            $indexable = (bool) get_option('blog_public');
            $production_noindex = $environment === 'production' && !$indexable;
            $healthy = $database_ok && !$production_noindex;

            return new WP_REST_Response([
                'status'      => $healthy ? 'ok' : 'degraded',
                'service'     => 'winnica-nowizny',
                'environment' => $environment,
                'indexable'   => $indexable,
                'time'        => gmdate('c'),
            ], $healthy ? 200 : 503);
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

    $tests['direct']['winnica_search_visibility'] = [
        'label' => 'Widoczność Winnicy w wyszukiwarkach',
        'test'  => function (): array {
            $production = wp_get_environment_type() === 'production';
            $indexable = (bool) get_option('blog_public');
            $status = $production && !$indexable ? 'critical' : ($indexable ? 'good' : 'recommended');

            return [
                'label'       => $indexable
                    ? 'Wyszukiwarki mogą indeksować stronę'
                    : 'Indeksowanie strony jest wyłączone',
                'status'      => $status,
                'badge'       => ['label' => 'Winnica Nowizny', 'color' => 'blue'],
                'description' => '<p>' . ($production && !$indexable
                    ? 'Środowisko produkcyjne ma włączone noindex. Włącz widoczność w Ustawienia → Czytanie przed publikacją.'
                    : 'Na środowisku deweloperskim noindex jest prawidłowy. Przed publikacją sprawdź Ustawienia → Czytanie.') . '</p>',
                'actions'     => '',
                'test'        => 'winnica_search_visibility',
            ];
        },
    ];

    return $tests;
});

add_action('admin_notices', function (): void {
    if (
        wp_get_environment_type() !== 'production'
        || (bool) get_option('blog_public')
        || !current_user_can('manage_options')
    ) {
        return;
    }

    echo '<div class="notice notice-error"><p><strong>Winnica Nowizny:</strong> indeksowanie strony produkcyjnej jest wyłączone. Sprawdź Ustawienia → Czytanie.</p></div>';
});
