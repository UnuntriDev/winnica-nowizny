<?php
/**
 * Generic page template.
 */

defined('ABSPATH') || exit;
winnica_require_timber();

$context = \Timber\Timber::context();
$context['post'] = \Timber\Timber::get_post();

\Timber\Timber::render('page.twig', $context);
