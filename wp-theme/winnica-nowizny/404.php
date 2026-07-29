<?php
/**
 * 404 template.
 */

defined('ABSPATH') || exit;
winnica_require_timber();

$context = Timber::context();
Timber::render('404.twig', $context);
