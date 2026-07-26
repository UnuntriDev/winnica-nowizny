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

            // Fonts now ship with the theme, so the browser only finds them after it
            // has parsed the stylesheet. Preload the faces that paint the first
            // screen; the italic and any later face can wait for the cascade.
            $first_paint = [
                'assets/fonts/source-sans-3-normal-latin.woff2',
                'assets/fonts/source-sans-3-normal-latin-ext.woff2',
                'assets/fonts/cormorant-garamond-normal-latin.woff2',
                'assets/fonts/cormorant-garamond-normal-latin-ext.woff2',
            ];

            $preloads = [];
            foreach ($first_paint as $src) {
                if (!empty($manifest[$src]['file'])) {
                    $preloads[] = WINNICA_URI . '/assets/dist/' . $manifest[$src]['file'];
                }
            }

            if ($preloads) {
                add_action('wp_head', function () use ($preloads): void {
                    foreach ($preloads as $href) {
                        // crossorigin is required even same-origin: fonts fetch anonymously.
                        printf(
                            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
                            esc_url($href)
                        );
                    }
                }, 2);
            }
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
});
