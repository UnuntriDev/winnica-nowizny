<?php
/**
 * Timber initialization and Twig template paths (Timber 1.x API).
 */

defined('ABSPATH') || exit;

if (!class_exists('Timber')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>Timber nie jest zainstalowany. Zainstaluj plugin Timber.</p></div>';
    });
    return;
}

Timber::$dirname = ['templates', 'templates/partials'];

add_filter('timber_context', function (array $context): array {
    $context['site_phone']     = get_theme_mod('winnica_phone', '607 578 156');
    $context['site_email']     = get_theme_mod('winnica_email', 'winnicanowizny@op.pl');
    $context['site_address']   = get_theme_mod('winnica_address', 'Połom Mały 60, 32-862 Porąbka Iwkowska');
    $context['site_facebook']  = get_theme_mod('winnica_facebook', 'https://www.facebook.com/winnicanowizny');
    $context['site_instagram'] = get_theme_mod('winnica_instagram', 'https://www.instagram.com/winnicanowizny/');
    $context['footer_desc']    = get_theme_mod('winnica_footer_desc', 'Rodzinna winnica na Pogórzu Rożnowskim. Tradycja, pasja i smak od 2005 roku.');
    return $context;
});
