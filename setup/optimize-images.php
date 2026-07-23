<?php
/**
 * Generate modern variants for static theme assets.
 * Run: docker compose run --rm --entrypoint wp wpcli eval-file /setup/optimize-images.php --allow-root
 */

$directory = get_template_directory() . '/assets/images';
$files = array_merge(glob($directory . '/*.png') ?: [], glob($directory . '/*.webp') ?: []);
$avif_supported = wp_image_editor_supports(['mime_type' => 'image/avif']);

foreach ($files as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $basename = substr($file, 0, -strlen($extension) - 1);
    $targets = [];

    if ($extension === 'png') {
        $targets['image/webp'] = $basename . '.webp';
    }
    if ($avif_supported) {
        $targets['image/avif'] = $basename . '.avif';
    }

    foreach ($targets as $mime => $target) {
        if (is_file($target) && filemtime($target) >= filemtime($file)) {
            continue;
        }
        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            WP_CLI::warning($editor->get_error_message());
            continue;
        }
        $editor->set_quality($mime === 'image/avif' ? 52 : 82);
        $result = $editor->save($target, $mime);
        if (is_wp_error($result)) {
            WP_CLI::warning($result->get_error_message());
        } else {
            WP_CLI::log('Utworzono: ' . basename($target));
        }
    }
}

if (!$avif_supported) {
    WP_CLI::warning('AVIF nie jest obsługiwany przez bieżącą bibliotekę obrazu; WebP pozostaje aktywnym formatem zapasowym.');
}
