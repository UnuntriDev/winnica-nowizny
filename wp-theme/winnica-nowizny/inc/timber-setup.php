<?php
/**
 * Timber 2 initialization, Twig security defaults and global context.
 */

defined('ABSPATH') || exit;

/**
 * Build a dialable tel: target from a free-text Customizer phone number.
 * Accepts "607 578 156", "+48 607 578 156" and "0048 607 578 156" alike,
 * so the country prefix is never doubled. Returns '' when there is no number.
 */
function winnica_tel_href(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === '') {
        return '';
    }

    if (strpos($digits, '0048') === 0) {
        $digits = substr($digits, 4);
    } elseif (strpos($digits, '48') === 0 && strlen($digits) > 9) {
        $digits = substr($digits, 2);
    }

    return 'tel:+48' . $digits;
}

/**
 * Shorten a review signature to a first name and a surname initial.
 *
 * Google publishes full names, this page does not need them. The shortening runs
 * on output so that a signature typed in full in the admin panel still renders
 * short; the seed script stores the short form from the start, because the
 * repository has no business carrying full names of real people. A one-word
 * signature has no surname to shorten and passes through untouched.
 */
function winnica_short_author(string $name): string
{
    $name  = trim($name);
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if (count($parts) < 2) {
        return $name;
    }

    $surname = array_pop($parts);

    return implode(' ', $parts) . ' ' . mb_strtoupper(mb_substr($surname, 0, 1)) . '.';
}

function winnica_require_timber(): void
{
    if (class_exists(\Timber\Timber::class)) {
        return;
    }

    status_header(503);
    nocache_headers();
    wp_die(
        esc_html__('Strona jest chwilowo niedostępna. Spróbuj ponownie za kilka minut.', 'winnica-nowizny'),
        esc_html__('Przerwa techniczna', 'winnica-nowizny'),
        ['response' => 503]
    );
}

if (!class_exists(\Timber\Timber::class)) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Winnica Nowizny:</strong> '
            . 'brak zależności PHP. Uruchom <code>composer install --no-dev</code> w katalogu motywu.</p></div>';
    });
    return;
}

\Timber\Timber::init();
\Timber\Timber::$dirname = ['templates', 'templates/partials'];

// Keep Twig safe by default and opt into |raw only for markup already produced
// or sanitised by WordPress.
add_filter('timber/twig/environment/options', function (array $options): array {
    $options['autoescape'] = 'html';
    return $options;
});

add_filter('timber/context', function (array $context): array {
    $context['menu']           = \Timber\Timber::get_menu('primary');
    $context['site_phone']      = get_theme_mod('winnica_phone', '607 578 156');
    $context['site_phone_href'] = winnica_tel_href((string) $context['site_phone']);
    $context['site_email']     = get_theme_mod('winnica_email', 'kontakt@winnicanowizny.pl');
    $context['site_address']   = get_theme_mod('winnica_address', 'Połom Mały 60, 32-862 Porąbka Iwkowska');
    $context['site_facebook']  = get_theme_mod('winnica_facebook', 'https://www.facebook.com/winnicanowizny');
    $context['site_instagram'] = get_theme_mod('winnica_instagram', 'https://www.instagram.com/winnicanowizny/');
    $context['footer_desc']    = get_theme_mod('winnica_footer_desc', 'Rodzinna winnica na Pogórzu Rożnowskim. Tradycja, pasja i smak od 2005 roku.');
    return $context;
});
