<?php
/**
 * Front page template — renders homepage sections from individual ACF groups.
 */

$context = Timber::get_context();
$post = new Timber\Post();
$context['post'] = $post;

$context['menu'] = new Timber\Menu('primary');

// Wines for the Wina section
$wines_count = get_field('wines_count', $post->ID) ?: 3;
$context['wines'] = Timber::get_posts([
    'post_type'      => 'wino',
    'posts_per_page' => $wines_count,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);

// Customizer globals
$context['site_phone']       = get_theme_mod('winnica_phone', '607 578 156');
$context['site_email']       = get_theme_mod('winnica_email', 'winnicanowizny@op.pl');
$context['site_address']     = get_theme_mod('winnica_address', 'Połom Mały 60, 32-862 Porąbka Iwkowska');
$context['site_facebook']    = get_theme_mod('winnica_facebook', 'https://www.facebook.com/winnicanowizny');
$context['site_instagram']   = get_theme_mod('winnica_instagram', 'https://www.instagram.com/winnicanowizny/');
$context['footer_desc']      = get_theme_mod('winnica_footer_desc', 'Rodzinna winnica na Pogórzu Rożnowskim. Tradycja, pasja i smak od 2005 roku.');

$contact_status = isset($_GET['contact'])
    ? sanitize_key(wp_unslash($_GET['contact']))
    : '';
$context['contact_status'] = in_array(
    $contact_status,
    ['success', 'validation', 'security', 'rate_limit', 'error'],
    true
) ? $contact_status : '';

Timber::render('front-page.twig', $context);
