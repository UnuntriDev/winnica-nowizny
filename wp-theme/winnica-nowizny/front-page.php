<?php
/**
 * Front page template — renders homepage sections from individual ACF groups.
 */

defined('ABSPATH') || exit;
winnica_require_timber();

$context = \Timber\Timber::context();
$post = \Timber\Timber::get_post();
$context['post'] = $post;

$wines_count = (int) (get_field('wines_count', $post?->ID) ?: 5);
$context['wines'] = \Timber\Timber::get_posts([
    'post_type'      => 'wino',
    'posts_per_page' => $wines_count,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);

$contact_status = isset($_GET['contact'])
    ? sanitize_key(wp_unslash($_GET['contact']))
    : '';
$context['contact_status'] = in_array(
    $contact_status,
    ['success', 'validation', 'security', 'rate_limit', 'error'],
    true
) ? $contact_status : '';

// Every section hangs off a *_show flag. On a fresh import those keys do not
// exist yet, and an unset key is not a decision to hide the section, so treat
// missing as visible and let only an explicit 0 switch a section off.
$context['show'] = [];
foreach (['hero', 'historia', 'exp', 'wines', 'cellar', 'galeria', 'opinie', 'terroir', 'wizyta'] as $section) {
    $flag = get_post_meta($post?->ID ?? 0, $section . '_show', true);
    $context['show'][$section] = $flag === '' ? true : (bool) $flag;
}

$contact_old = winnica_contact_old_input();
$context['contact_old']    = $contact_old['values'];
$context['contact_errors'] = $contact_old['errors'];

\Timber\Timber::render('front-page.twig', $context);
