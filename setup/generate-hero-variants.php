<?php
/**
 * Generate responsive WebP/AVIF variants for the static hero fallback.
 *
 * Run with:
 * wp eval-file /setup/generate-hero-variants.php --allow-root
 */

defined('ABSPATH') || exit;

$theme_dir = get_theme_root() . '/winnica-nowizny';
$source = $theme_dir . '/assets/images/hero-winnica.webp';

if (!is_readable($source)) {
    WP_CLI::error('Static hero source is not readable.');
}

foreach ([768, 1280] as $width) {
    foreach (['image/webp' => 'webp', 'image/avif' => 'avif'] as $mime => $extension) {
        if (!wp_image_editor_supports(['mime_type' => $mime])) {
            WP_CLI::warning(sprintf('%s is not supported; skipped.', $mime));
            continue;
        }

        $editor = wp_get_image_editor($source);
        if (is_wp_error($editor)) {
            WP_CLI::error($editor->get_error_message());
        }

        $editor->set_quality($mime === 'image/avif' ? 52 : 82);
        $result = $editor->resize($width, null, false);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        $target = sprintf(
            '%s/assets/images/hero-winnica-%d.%s',
            $theme_dir,
            $width,
            $extension
        );
        $saved = $editor->save($target, $mime);
        if (is_wp_error($saved)) {
            WP_CLI::error($saved->get_error_message());
        }

        WP_CLI::success(sprintf('Created %s', basename($target)));
    }
}
