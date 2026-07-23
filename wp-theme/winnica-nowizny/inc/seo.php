<?php
/**
 * SEO: Schema.org JSON-LD structured data.
 * Uses Customizer (get_theme_mod) for global settings.
 */

defined('ABSPATH') || exit;

add_action('wp_head', 'winnica_schema_organization');
add_action('wp_head', 'winnica_schema_breadcrumbs');

function winnica_schema_organization(): void {
    if (!is_front_page()) {
        return;
    }

    $phone   = get_theme_mod('winnica_phone', '');
    $email   = get_theme_mod('winnica_email', '');
    $address = get_theme_mod('winnica_address', '');
    $lat     = get_theme_mod('winnica_gps_lat', '');
    $lng     = get_theme_mod('winnica_gps_lng', '');
    $fb      = get_theme_mod('winnica_facebook', '');
    $ig      = get_theme_mod('winnica_instagram', '');
    $hours   = get_theme_mod('winnica_hours', '');

    $schema = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'       => ['LocalBusiness', 'Winery'],
                '@id'         => home_url('/#winery'),
                'name'        => 'Winnica Nowizny',
                'description' => 'Rodzinna winnica na Pogórzu Rożnowskim. Degustacje, zwiedzanie, enoturystyka w Małopolsce.',
                'url'         => home_url('/'),
                'telephone'   => $phone,
                'email'       => $email,
                'image'       => get_theme_file_uri('assets/images/hero-winnica.webp'),
                'logo'        => get_theme_file_uri('assets/images/logo-nowizny.webp'),
                'foundingDate' => '2005',
                'hasMap'      => 'https://www.google.com/maps/search/?api=1&query=Po%C5%82om+Ma%C5%82y+60%2C+32-862+Por%C4%85bka+Iwkowska',
                'areaServed'  => [
                    '@type' => 'AdministrativeArea',
                    'name'  => 'Małopolska',
                ],
            ],
        ],
    ];

    if ($address) {
        $schema['@graph'][0]['address'] = [
            '@type'           => 'PostalAddress',
            'addressLocality' => 'Połom Mały',
            'postalCode'      => '32-862',
            'addressRegion'   => 'Małopolska',
            'addressCountry'  => 'PL',
            'streetAddress'   => $address,
        ];
    }

    if ($phone || $email) {
        $schema['@graph'][0]['contactPoint'] = [
            '@type'       => 'ContactPoint',
            'telephone'   => $phone,
            'email'       => $email,
            'contactType' => 'reservations',
            'availableLanguage' => ['pl'],
        ];
    }

    if ($lat && $lng) {
        $schema['@graph'][0]['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $lat,
            'longitude' => (float) $lng,
        ];
    }

    $same_as = array_filter([$fb, $ig]);
    if ($same_as) {
        $schema['@graph'][0]['sameAs'] = array_values($same_as);
    }

    if ($hours) {
        $schema['@graph'][0]['openingHours'] = array_filter(
            array_map('trim', explode("\n", $hours))
        );
    }

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function winnica_schema_breadcrumbs(): void {
    if (is_front_page()) {
        return;
    }

    $items = [
        [
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Strona główna',
            'item'     => home_url('/'),
        ],
    ];

    $position = 2;

    if (is_page()) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => get_the_title(),
            'item'     => get_permalink(),
        ];
    }

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}
