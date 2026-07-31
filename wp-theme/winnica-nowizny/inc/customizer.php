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
        'winnica_email'   => ['label' => 'Email', 'default' => 'kontakt@winnicanowizny.pl'],
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

    // Godzin otwarcia tu nie ma celowo: widoczne godziny edytuje się na stronie
    // głównej (grupa Wizyta, pole "Godziny otwarcia"), a ich wersję dla Google
    // trzyma inc/seo.php, bo sezonowego grafiku nie da się zapisać w jednym polu
    // tekstowym.

    // ── Section: Social Media ──
    $wp_customize->add_section('winnica_social', [
        'title' => 'Social Media',
        'panel' => 'winnica_panel',
    ]);

    // Only the networks the templates actually render; adding a field here without
    // a matching link in nav.twig and footer.twig just creates a dead control.
    $social_fields = [
        'winnica_facebook'  => 'Facebook URL',
        'winnica_instagram' => 'Instagram URL',
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
});
