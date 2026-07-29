<?php
/**
 * 404 template.
 */

defined('ABSPATH') || exit;
winnica_require_timber();

$context = \Timber\Timber::context();
\Timber\Timber::render('404.twig', $context);
