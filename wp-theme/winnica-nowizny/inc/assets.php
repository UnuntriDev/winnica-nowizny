<?php
/**
 * Enqueue CSS/JS assets built by Vite.
 */

defined('ABSPATH') || exit;

function winnica_asset_manifest(): array
{
    static $manifest;

    if (is_array($manifest)) {
        return $manifest;
    }

    $path = WINNICA_DIR . '/assets/dist/.vite/manifest.json';
    if (!is_readable($path)) {
        return $manifest = [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return $manifest = is_array($decoded) ? $decoded : [];
}

function winnica_asset_build_is_valid(): bool
{
    $manifest = winnica_asset_manifest();
    $entries = [
        $manifest['src/js/main.js']['file'] ?? '',
        $manifest['src/css/main.css']['file'] ?? '',
    ];

    foreach ($entries as $file) {
        if ($file === '' || !is_readable(WINNICA_DIR . '/assets/dist/' . $file)) {
            return false;
        }
    }

    return true;
}

add_action('wp_enqueue_scripts', function (): void {
    $manifest = winnica_asset_manifest();

    if (winnica_asset_build_is_valid()) {
        $script = $manifest['src/js/main.js'];
        $styles = $manifest['src/css/main.css'];

        wp_enqueue_style(
            'winnica-main',
            WINNICA_URI . '/assets/dist/' . $styles['file'],
            [],
            WINNICA_VERSION
        );

        wp_enqueue_script(
            'winnica-main',
            WINNICA_URI . '/assets/dist/' . $script['file'],
            [],
            WINNICA_VERSION,
            true
        );

        // Fonts ship with the theme. Preload only the faces needed above the fold.
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
                    printf(
                        '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
                        esc_url($href)
                    );
                }
            }, 2);
        }

        return;
    }

    // A readable source fallback is useful locally. It is deliberately not used
    // in production, where a missing build means an incomplete release.
    if (wp_get_environment_type() === 'local' || wp_get_environment_type() === 'development') {
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

add_filter('script_loader_tag', function (string $tag, string $handle): string {
    if ($handle === 'winnica-main-dev' && !str_contains($tag, 'type=')) {
        return str_replace('<script ', '<script type="module" ', $tag);
    }

    return $tag;
}, 20, 2);

add_action('template_redirect', function (): void {
    if (
        wp_get_environment_type() !== 'production'
        || is_admin()
        || winnica_asset_build_is_valid()
    ) {
        return;
    }

    status_header(503);
    nocache_headers();
    wp_die(
        esc_html__('Strona jest chwilowo niedostępna. Spróbuj ponownie za kilka minut.', 'winnica-nowizny'),
        esc_html__('Przerwa techniczna', 'winnica-nowizny'),
        ['response' => 503]
    );
}, 0);

add_action('admin_notices', function (): void {
    if (winnica_asset_build_is_valid() || !current_user_can('manage_options')) {
        return;
    }

    echo '<div class="notice notice-error"><p><strong>Winnica Nowizny:</strong> brak kompletnego buildu Vite. Uruchom <code>npm run build</code> przed wdrożeniem.</p></div>';
});
