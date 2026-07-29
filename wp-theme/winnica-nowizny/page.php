<?php
/**
 * Generic page template.
 */

defined('ABSPATH') || exit;
winnica_require_timber();

$context = Timber::context();
$context['post'] = Timber::get_post();

Timber::render('page.twig', $context);
