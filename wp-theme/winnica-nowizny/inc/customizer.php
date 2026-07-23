<?php
/**
 * WordPress Customizer — global settings (replaces ACF PRO Options Pages).
 */

defined('ABSPATH') || exit;

add_action('customize_register', function (WP_Customize_Manager $wp_customize) {

    // ── Panel: Winnica ──
    $wp_customize->add_panel('winnica_panel', [
        'title'    => 'Winnica Nowizny',
        'priority' => 30,
    ]);

    // ── Section: Kontakt ──
    $wp_customize->add_section('winnica_contact', [
        'title' => 'Dane kontaktowe',
        'panel' => 'winnica_panel',
    ]);

    $contact_fields = [
        'winnica_phone'   => ['label' => 'Telefon', 'default' => '607 578 156'],
        'winnica_email'   => ['label' => 'Email', 'default' => 'winnicanowizny@op.pl'],
        'winnica_address' => ['label' => 'Adres', 'default' => 'Połom Mały 60, 32-862 Porąbka Iwkowska'],
    ];

    foreach ($contact_fields as $id => $field) {
        $wp_customize->add_setting($id, [
            'default'           => $field['default'],
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control($id, [
            'label'   => $field['label'],
            'section' => 'winnica_contact',
            'type'    => 'text',
        ]);
    }

    // GPS
    $wp_customize->add_setting('winnica_gps_lat', ['default' => '', 'sanitize_callback' => 'sanitize_text_field']);
    $wp_customize->add_control('winnica_gps_lat', ['label' => 'GPS Latitude', 'section' => 'winnica_contact', 'type' => 'text']);
    $wp_customize->add_setting('winnica_gps_lng', ['default' => '', 'sanitize_callback' => 'sanitize_text_field']);
    $wp_customize->add_control('winnica_gps_lng', ['label' => 'GPS Longitude', 'section' => 'winnica_contact', 'type' => 'text']);

    // Godziny
    $wp_customize->add_setting('winnica_hours', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
    ]);
    $wp_customize->add_control('winnica_hours', [
        'label'   => 'Godziny otwarcia',
        'section' => 'winnica_contact',
        'type'    => 'textarea',
    ]);

    $wp_customize->add_setting('winnica_season_notice', [
        'default'           => 'Wizyty grupowe po wcześniejszej rezerwacji',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('winnica_season_notice', [
        'label'   => 'Uwaga sezonowa',
        'section' => 'winnica_contact',
        'type'    => 'text',
    ]);

    // ── Section: Social Media ──
    $wp_customize->add_section('winnica_social', [
        'title' => 'Social Media',
        'panel' => 'winnica_panel',
    ]);

    $social_fields = [
        'winnica_facebook'  => 'Facebook URL',
        'winnica_instagram' => 'Instagram URL',
        'winnica_tiktok'    => 'TikTok URL',
        'winnica_youtube'   => 'YouTube URL',
    ];

    foreach ($social_fields as $id => $label) {
        $defaults = [
            'winnica_facebook'  => 'https://www.facebook.com/winnicanowizny',
            'winnica_instagram' => 'https://www.instagram.com/winnicanowizny/',
        ];
        $wp_customize->add_setting($id, ['default' => $defaults[$id] ?? '', 'sanitize_callback' => 'esc_url_raw']);
        $wp_customize->add_control($id, ['label' => $label, 'section' => 'winnica_social', 'type' => 'url']);
    }

    // ── Section: Stopka ──
    $wp_customize->add_section('winnica_footer', [
        'title' => 'Stopka',
        'panel' => 'winnica_panel',
    ]);

    $wp_customize->add_setting('winnica_footer_desc', [
        'default'           => 'Rodzinna winnica na Pogórzu Rożnowskim. Tradycja, pasja i smak od 2005 roku.',
        'sanitize_callback' => 'sanitize_textarea_field',
    ]);
    $wp_customize->add_control('winnica_footer_desc', [
        'label'   => 'Opis w stopce',
        'section' => 'winnica_footer',
        'type'    => 'textarea',
    ]);

    // ── Section: Analytics ──
    $wp_customize->add_section('winnica_analytics', [
        'title' => 'Analityka i zgody',
        'panel' => 'winnica_panel',
    ]);
    $wp_customize->add_setting('winnica_ga_id', [
        'default' => '',
        'sanitize_callback' => function (string $value): string {
            $value = strtoupper(trim($value));
            return preg_match('/^G-[A-Z0-9]{6,16}$/', $value) ? $value : '';
        },
    ]);
    $wp_customize->add_control('winnica_ga_id', [
        'label'       => 'Identyfikator Google Analytics 4',
        'description' => 'Format G-XXXXXXXXXX. Skrypt uruchomi się wyłącznie po zgodzie użytkownika.',
        'section'     => 'winnica_analytics',
        'type'        => 'text',
    ]);
});
