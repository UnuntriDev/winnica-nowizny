<?php
/**
 * Consent-based analytics configuration. No tracking script is emitted before consent.
 */

defined('ABSPATH') || exit;

function winnica_analytics_id(): string
{
    $environment_id = function_exists('winnica_config_value')
        ? winnica_config_value('WINNICA_GA_ID')
        : '';
    $id = $environment_id ?: (string) get_theme_mod('winnica_ga_id', '');
    return preg_match('/^G-[A-Z0-9]{6,16}$/', $id) ? $id : '';
}

add_filter('timber_context', function (array $context): array {
    $context['analytics_id'] = winnica_analytics_id();
    return $context;
});

add_action('admin_notices', function (): void {
    if (!current_user_can('manage_options') || winnica_analytics_id() !== '') {
        return;
    }
    echo '<div class="notice notice-info"><p><strong>Winnica Nowizny:</strong> analityka jest przygotowana, ale pozostaje wyłączona do czasu podania prawidłowego identyfikatora GA4 w zmiennej <code>WINNICA_GA_ID</code> lub w Personalizacji motywu.</p></div>';
});
