<?php
/**
 * Enqueue CSS/JS assets — Vite integration for dev/prod.
 */

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    $manifest_path = WINNICA_DIR . '/assets/dist/.vite/manifest.json';

    if (file_exists($manifest_path)) {
        $manifest = json_decode(file_get_contents($manifest_path), true);
        $entry = $manifest['src/js/main.js'] ?? null;

        if ($entry) {
            if (!empty($entry['css'])) {
                foreach ($entry['css'] as $i => $css_file) {
                    wp_enqueue_style(
                        'winnica-main' . ($i > 0 ? "-$i" : ''),
                        WINNICA_URI . '/assets/dist/' . $css_file,
                        [],
                        WINNICA_VERSION
                    );
                }
            }

            wp_enqueue_script(
                'winnica-main',
                WINNICA_URI . '/assets/dist/' . $entry['file'],
                [],
                WINNICA_VERSION,
                true
            );
        }
    } else {
        wp_enqueue_style(
            'winnica-main-dev',
            WINNICA_URI . '/src/css/main.css',
            [],
            WINNICA_VERSION
        );
        wp_enqueue_script(
            'winnica-main-dev',
            WINNICA_URI . '/src/js/main.js',
            [],
            WINNICA_VERSION,
            true
        );
    }

    wp_enqueue_style(
        'winnica-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,400&family=Source+Sans+3:wght@400;500;600&display=swap',
        [],
        null
    );
});
