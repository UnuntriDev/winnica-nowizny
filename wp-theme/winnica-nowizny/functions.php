<?php
/**
 * Winnica Nowizny — functions.php
 */

defined('ABSPATH') || exit;

$winnica_theme_version = wp_get_theme()->get('Version');
define('WINNICA_VERSION', $winnica_theme_version !== '' ? $winnica_theme_version : '1.5.0');
unset($winnica_theme_version);
define('WINNICA_DIR', get_template_directory());
define('WINNICA_URI', get_template_directory_uri());

// ── Timber ──
require_once WINNICA_DIR . '/inc/timber-setup.php';

// ── Theme support & menus ──
require_once WINNICA_DIR . '/inc/theme-setup.php';

// ── ACF configuration (free ACF — JSON sync) ──
require_once WINNICA_DIR . '/inc/acf-setup.php';

// ── Customizer (global settings) ──
require_once WINNICA_DIR . '/inc/customizer.php';

// ── Assets (CSS/JS) ──
require_once WINNICA_DIR . '/inc/assets.php';

// ── Custom Post Types ──
require_once WINNICA_DIR . '/inc/cpt-wino.php';

// Canonical redirects for legacy one-page routes
require_once WINNICA_DIR . '/inc/redirects.php';

// Native contact form and messages stored in wp-admin
require_once WINNICA_DIR . '/inc/contact-form.php';

// Configurable SMTP transport
require_once WINNICA_DIR . '/inc/smtp.php';

// Consent-based analytics
require_once WINNICA_DIR . '/inc/analytics.php';

// ── SEO (Schema.org JSON-LD) ──
require_once WINNICA_DIR . '/inc/seo.php';

// ── Performance ──
require_once WINNICA_DIR . '/inc/performance.php';

// Security, health checks and uptime endpoint
require_once WINNICA_DIR . '/inc/security.php';
require_once WINNICA_DIR . '/inc/monitoring.php';
