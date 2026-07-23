<?php
/**
 * Generic page template.
 */

$context = Timber::get_context();
$context['post'] = new Timber\Post();

Timber::render('page.twig', $context);
