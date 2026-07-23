<?php
/**
 * Default template — fallback for all pages.
 */

$context = Timber::get_context();
$context['posts'] = Timber::get_posts();

Timber::render('index.twig', $context);
