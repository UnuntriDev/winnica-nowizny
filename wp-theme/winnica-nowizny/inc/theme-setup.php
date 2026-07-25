<?php
/**
 * Theme support, menus, image sizes.
 */

defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);

    register_nav_menus([
        'primary' => __('Menu główne', 'winnica-nowizny'),
        'footer'  => __('Menu stopka', 'winnica-nowizny'),
    ]);

    // Cut-out bottle canvas is 520×1400 with transparency — resize proportionally,
    // never hard-crop (that would clip the bottle). Small variant feeds srcset.
    add_image_size('bottle', 520, 1400, false);
    add_image_size('bottle-sm', 320, 9999, false);
    add_image_size('gallery-square', 600, 600, true);
    add_image_size('gallery-tall', 600, 1200, true);
    add_image_size('gallery-wide', 1200, 600, true);
    add_image_size('hero-bg', 1920, 1080, true);
    add_image_size('section-photo', 800, 1067, true);
});
