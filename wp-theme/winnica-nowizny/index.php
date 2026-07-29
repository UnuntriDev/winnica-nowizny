<?php
/**
 * Default template — fallback for all pages.
 */

defined('ABSPATH') || exit;
winnica_require_timber();

$context = Timber::context();
$context['posts'] = Timber::get_posts();

Timber::render('index.twig', $context);
