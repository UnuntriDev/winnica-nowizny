<?php
/**
 * Native SEO metadata and Schema.org JSON-LD.
 *
 * The theme intentionally owns this small SEO surface so a second plugin does
 * not emit duplicate canonical links or structured data.
 */

defined('ABSPATH') || exit;

remove_action('wp_head', 'rel_canonical');

add_filter('pre_get_document_title', 'winnica_seo_document_title');
add_action('wp_head', 'winnica_seo_meta', 5);
add_action('wp_head', 'winnica_schema_organization', 20);
add_action('wp_head', 'winnica_schema_breadcrumbs', 21);

function winnica_seo_document_title(string $title): string {
    if (is_front_page()) {
        return 'Winnica Nowizny | Rodzinna winnica na Pogórzu Rożnowskim';
    }

    if (is_404()) {
        return 'Strona nie istnieje | Winnica Nowizny';
    }

    return $title;
}

function winnica_seo_description(): string {
    if (is_front_page()) {
        return 'Rodzinna winnica w Połomiu Małym na Pogórzu Rożnowskim. Degustacje, regionalna kuchnia, ENO-Safari i wypoczynek w rytmie slow.';
    }

    if (is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $source = has_excerpt($post)
                ? get_the_excerpt($post)
                : strip_shortcodes((string) $post->post_content);
            $description = trim(wp_strip_all_tags($source, true));
            if ($description !== '') {
                return wp_html_excerpt($description, 155, '…');
            }
        }
    }

    return 'Winnica Nowizny — rodzinna winnica i enoturystyka na Pogórzu Rożnowskim.';
}

function winnica_seo_canonical_url(): string {
    if (is_front_page()) {
        return home_url('/');
    }

    if (is_singular()) {
        return (string) get_permalink();
    }

    return '';
}

/**
 * Sharing previews need the dimensions of whichever image we actually send, not
 * the theme fallback's, or the crop is wrong on every post with a thumbnail.
 *
 * @return array{url: string, width: int, height: int}
 */
function winnica_seo_image(): array {
    if (is_singular() && has_post_thumbnail()) {
        $src = wp_get_attachment_image_src(get_post_thumbnail_id(get_queried_object_id()), 'large');
        if (is_array($src) && $src[0]) {
            return ['url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2]];
        }
    }

    return [
        'url'    => get_theme_file_uri('assets/images/hero-winnica.webp'),
        'width'  => 1920,
        'height' => 1080,
    ];
}

function winnica_seo_meta(): void {
    $title       = wp_get_document_title();
    $description = winnica_seo_description();
    $canonical   = winnica_seo_canonical_url();
    $image       = winnica_seo_image();
    $url         = $canonical ?: home_url('/');

    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    if ($canonical !== '') {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }

    echo '<meta property="og:locale" content="pl_PL">' . "\n";
    // Pages (privacy policy) are not articles; only a real post would be.
    echo '<meta property="og:type" content="' . (is_singular('post') ? 'article' : 'website') . '">' . "\n";
    echo '<meta property="og:site_name" content="Winnica Nowizny">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($image['url']) . '">' . "\n";
    echo '<meta property="og:image:width" content="' . esc_attr((string) $image['width']) . '">' . "\n";
    echo '<meta property="og:image:height" content="' . esc_attr((string) $image['height']) . '">' . "\n";
    echo '<meta property="og:image:alt" content="Winnica Nowizny na Pogórzu Rożnowskim">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($image['url']) . '">' . "\n";
}

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
    $url     = home_url('/');
    $image   = get_theme_file_uri('assets/images/hero-winnica.webp');

    $winery = [
        '@type'        => ['LocalBusiness', 'Winery'],
        '@id'          => home_url('/#winery'),
        'name'         => 'Winnica Nowizny',
        'description'  => winnica_seo_description(),
        'url'          => $url,
        'telephone'    => $phone,
        'email'        => $email,
        'image'        => $image,
        'logo'         => get_theme_file_uri('assets/images/logo-nowizny.webp'),
        'foundingDate' => '2005',
        'hasMap'       => 'https://www.google.com/maps/search/?api=1&query=Po%C5%82om+Ma%C5%82y+60%2C+32-862+Por%C4%85bka+Iwkowska',
        'areaServed'   => [
            '@type' => 'AdministrativeArea',
            'name'  => 'Małopolska',
        ],
    ];

    if ($address) {
        // The Customizer holds the full postal line ("Połom Mały 60, 32-862 ...").
        // streetAddress wants only the street part, or the postal code shows up
        // twice; the segment before the first comma is exactly that.
        $winery['address'] = [
            '@type'           => 'PostalAddress',
            'addressLocality' => 'Połom Mały',
            'postalCode'      => '32-862',
            'addressRegion'   => 'Małopolska',
            'addressCountry'  => 'PL',
            'streetAddress'   => trim(explode(',', $address, 2)[0]),
        ];
    }

    if ($phone || $email) {
        $winery['contactPoint'] = [
            '@type'             => 'ContactPoint',
            'telephone'         => $phone,
            'email'             => $email,
            'contactType'       => 'reservations',
            'availableLanguage' => ['pl'],
        ];
    }

    if ($lat && $lng) {
        $winery['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $lat,
            'longitude' => (float) $lng,
        ];
    }

    $same_as = array_filter([$fb, $ig]);
    if ($same_as) {
        $winery['sameAs'] = array_values($same_as);
    }

    // Plain openingHours cannot say "weekends only, except daily in high summer",
    // and a search result promising Tuesday in April sends someone up the hill to
    // a closed gate. The seasons go in one by one instead.
    //
    // Same fact as the "Godziny otwarcia" block on the front page (ACF field
    // wizyta_hours), written for machines. Change one, change the other.
    $seasons = [
        ['04-01', '11-30', ['Saturday'], '11:00', '20:00'],
        ['04-01', '11-30', ['Sunday'], '14:00', '20:00'],
        ['07-01', '08-31', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], '11:00', '20:00'],
    ];

    $today = current_time('Y-m-d');
    $year  = (int) current_time('Y');

    $winery['openingHoursSpecification'] = array_map(
        static function (array $season) use ($today, $year): array {
            [$from, $through, $days, $opens, $closes] = $season;
            // Once this year's season is over the dates describe the past, so the
            // window rolls forward to the next one rather than going stale in
            // December and staying that way until somebody notices.
            $season_year = ($year . '-' . $through) < $today ? $year + 1 : $year;

            return [
                '@type'        => 'OpeningHoursSpecification',
                'dayOfWeek'    => $days,
                'opens'        => $opens,
                'closes'       => $closes,
                'validFrom'    => $season_year . '-' . $from,
                'validThrough' => $season_year . '-' . $through,
            ];
        },
        $seasons
    );

    $schema = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            $winery,
            [
                '@type'      => 'WebSite',
                '@id'        => home_url('/#website'),
                'url'        => $url,
                'name'       => 'Winnica Nowizny',
                'inLanguage' => 'pl-PL',
                'publisher'  => ['@id' => home_url('/#winery')],
            ],
            [
                '@type'               => 'WebPage',
                '@id'                 => home_url('/#webpage'),
                'url'                 => $url,
                'name'                => wp_get_document_title(),
                'description'         => winnica_seo_description(),
                'inLanguage'          => 'pl-PL',
                'isPartOf'            => ['@id' => home_url('/#website')],
                'about'               => ['@id' => home_url('/#winery')],
                'primaryImageOfPage'  => [
                    '@type'  => 'ImageObject',
                    '@id'    => home_url('/#primaryimage'),
                    'url'    => $image,
                    'width'  => 1920,
                    'height' => 1080,
                ],
            ],
        ],
    ];

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

function winnica_schema_breadcrumbs(): void {
    if (is_front_page() || !is_page()) {
        return;
    }

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'Strona główna',
                'item'     => home_url('/'),
            ],
            [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => get_the_title(),
                'item'     => get_permalink(),
            ],
        ],
    ];

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}
