<?php
/**
 * Default template — fallback for all pages.
 */

defined('ABSPATH') || exit;
winnica_require_timber();

$context = \Timber\Timber::context();
$context['posts'] = \Timber\Timber::get_posts();

\Timber\Timber::render('index.twig', $context);
