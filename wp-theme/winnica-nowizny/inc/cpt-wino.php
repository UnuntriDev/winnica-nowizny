<?php
/**
 * Custom Post Type: Wino
 */

defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('wino', [
        'labels' => [
            'name'               => 'Wina',
            'singular_name'      => 'Wino',
            'add_new'            => 'Dodaj wino',
            'add_new_item'       => 'Dodaj nowe wino',
            'edit_item'          => 'Edytuj wino',
            'view_item'          => 'Zobacz wino',
            'search_items'       => 'Szukaj win',
            'not_found'          => 'Nie znaleziono win',
            'not_found_in_trash' => 'Nie znaleziono win w koszu',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'menu_icon'           => 'dashicons-carrot',
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest'        => false,
    ]);

    register_taxonomy('rodzaj-wina', 'wino', [
        'labels' => [
            'name'          => 'Rodzaje win',
            'singular_name' => 'Rodzaj wina',
            'add_new_item'  => 'Dodaj rodzaj',
        ],
        'public'       => false,
        'show_ui'      => true,
        'hierarchical' => true,
        'rewrite'      => false,
        'show_in_rest' => false,
    ]);
});

add_action('template_redirect', function () {
    $request_path = '/' . ltrim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $home_path = '/' . trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

    if ($home_path !== '/') {
        if ($request_path !== $home_path && !str_starts_with($request_path, $home_path . '/')) {
            return;
        }
        $request_path = (string) substr($request_path, strlen($home_path));
    }

    $path = trim($request_path, '/');

    if ($path === 'wina' || str_starts_with($path, 'wina/')) {
        wp_safe_redirect(home_url('/#wina'), 301);
        exit;
    }
});

add_action('after_switch_theme', 'flush_rewrite_rules');
