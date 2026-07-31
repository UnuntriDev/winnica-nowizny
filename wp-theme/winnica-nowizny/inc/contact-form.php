<?php
/**
 * Native contact form, anti-spam and reservation workflow.
 */

defined('ABSPATH') || exit;

const WINNICA_MESSAGE_STATUSES = [
    'new'       => 'Nowa',
    'contacted' => 'Skontaktowano się',
    'booked'    => 'Zarezerwowano',
    'closed'    => 'Zamknięta',
    'spam'      => 'Spam',
];

/**
 * Two ceilings, because they answer two different questions.
 *
 * The submission limit is the strict one. Four accepted reservations a quarter
 * is already more than a real visitor sends, and it counts only what passed
 * validation, so nobody locks themselves out while fixing their own typo.
 *
 * The request limit is the flood guard and counts every hit on the handler
 * whatever the outcome. A rejected submission is not free: it stores the typed
 * values in a ten-minute transient so the visitor gets the form back filled in.
 * With the strict limit alone that path was unbounded, and the pre-deployment
 * review reproduced it: five invalid posts against a limit of four all went
 * through and left ten rows in wp_options.
 */
const WINNICA_CONTACT_MAX_SUBMISSIONS = 4;
const WINNICA_CONTACT_MAX_REQUESTS    = 20;
const WINNICA_CONTACT_WINDOW          = 15 * MINUTE_IN_SECONDS;

function winnica_message_capabilities(): array
{
    return [
        'edit_winnica_message',
        'read_winnica_message',
        'delete_winnica_message',
        'edit_winnica_messages',
        'edit_others_winnica_messages',
        'publish_winnica_messages',
        'read_private_winnica_messages',
        'delete_winnica_messages',
        'delete_private_winnica_messages',
        'delete_published_winnica_messages',
        'delete_others_winnica_messages',
        'edit_private_winnica_messages',
        'edit_published_winnica_messages',
    ];
}

function winnica_install_message_capabilities(): void
{
    $role = get_role('administrator');
    if (!$role) {
        return;
    }

    foreach (winnica_message_capabilities() as $capability) {
        $role->add_cap($capability);
    }

    update_option('winnica_message_caps_version', '1', false);
}

add_action('after_switch_theme', 'winnica_install_message_capabilities');
add_action('admin_init', function (): void {
    if (get_option('winnica_message_caps_version') !== '1' && current_user_can('manage_options')) {
        winnica_install_message_capabilities();
    }
});

add_action('init', function (): void {
    register_post_type('winnica_message', [
        'labels' => [
            'name'          => 'Wiadomości i rezerwacje',
            'singular_name' => 'Wiadomość',
            'menu_name'     => 'Wiadomości',
            'all_items'     => 'Wszystkie wiadomości',
            'view_item'     => 'Zobacz wiadomość',
            'search_items'  => 'Szukaj wiadomości',
            'not_found'     => 'Brak wiadomości',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_icon'           => 'dashicons-calendar-alt',
        'supports'            => ['title', 'editor'],
        'capability_type'     => ['winnica_message', 'winnica_messages'],
        'map_meta_cap'        => true,
        'show_in_rest'        => false,
        'exclude_from_search' => true,
    ]);
});

function winnica_contact_token(): string
{
    $timestamp = time();
    $signature = hash_hmac('sha256', (string) $timestamp, wp_salt('nonce'));
    return $timestamp . '.' . $signature;
}

function winnica_contact_token_is_valid(string $token): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) {
        return false;
    }

    $timestamp = (int) $parts[0];
    $age = time() - $timestamp;
    $expected = hash_hmac('sha256', (string) $timestamp, wp_salt('nonce'));

    // No minimum age. The token is baked into the cached front page, so by the time
    // anyone submits it is already minutes old and the "filled in too fast" test
    // could never fire. What is left is a replay window plus the signature; the
    // honeypot, the nonce and the rate limit carry the anti-spam work.
    return $age >= 0 && $age <= 7200 && hash_equals($expected, $parts[1]);
}

function winnica_contact_fingerprint(): string
{
    $ip = function_exists('winnica_client_ip') ? winnica_client_ip() : 'unknown';
    return hash_hmac('sha256', $ip, wp_salt('auth'));
}

/**
 * Visitors type the date the Polish way ("15.08.2026"); everything downstream
 * wants one sortable shape. Returns '' when the string is not a real calendar
 * day, so 30.02 is rejected here instead of quietly becoming 2 March.
 */
function winnica_parse_visit_date(string $input): string
{
    $input = trim($input);

    if (preg_match('#^(\d{1,2})[./-](\d{1,2})[./-](\d{4})$#', $input, $parts)) {
        [, $day, $month, $year] = $parts;
    } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $input, $parts)) {
        // Still accept ISO: a browser that autofills a cached page, or an older
        // message being edited, should not read as a typo.
        [, $year, $month, $day] = $parts;
    } else {
        return '';
    }

    return checkdate((int) $month, (int) $day, (int) $year)
        ? sprintf('%04d-%02d-%02d', $year, $month, $day)
        : '';
}

function winnica_format_visit_date(string $iso): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    return $parsed ? $parsed->format('d.m.Y') : $iso;
}

function winnica_contact_redirect(string $status, array $payload = []): void
{
    $args = ['contact' => $status];

    if ($payload) {
        $key = bin2hex(random_bytes(12));
        set_transient('winnica_contact_old_' . $key, $payload, 10 * MINUTE_IN_SECONDS);
        $args['ct'] = $key;
    }

    wp_safe_redirect(add_query_arg($args, home_url('/')) . '#wizyta');
    exit;
}

/**
 * One-shot recovery of a rejected submission, so a visitor who mistyped one field
 * does not have to write the whole message again. The lookup key travels in the URL
 * and is burned on first read, so a shared link never replays somebody else's data.
 *
 * @return array{values: array<string, mixed>, errors: string[]}
 */
function winnica_contact_old_input(): array
{
    $empty = ['values' => [], 'errors' => []];

    $key = sanitize_key(wp_unslash($_GET['ct'] ?? ''));
    if ($key === '' || !ctype_xdigit($key)) {
        return $empty;
    }

    $stored = get_transient('winnica_contact_old_' . $key);
    delete_transient('winnica_contact_old_' . $key);

    if (!is_array($stored)) {
        return $empty;
    }

    return [
        'values' => is_array($stored['values'] ?? null) ? $stored['values'] : [],
        'errors' => is_array($stored['errors'] ?? null) ? $stored['errors'] : [],
    ];
}

function winnica_handle_contact_form(): void
{
    $fingerprint = winnica_contact_fingerprint();
    $suffix      = substr($fingerprint, 0, 32);
    $request_key = 'winnica_contact_req_' . $suffix;
    $rate_key    = 'winnica_contact_' . $suffix;

    // First thing in the handler, so a missing nonce, a tripped honeypot and a
    // failed validation all cost the sender exactly as much as a real message.
    $requests = (int) get_transient($request_key);
    if ($requests >= WINNICA_CONTACT_MAX_REQUESTS) {
        winnica_contact_redirect('rate_limit');
    }
    // Nothing is written once the ceiling is reached, so hammering the form
    // cannot keep pushing the expiry forward and lock an address out for good.
    set_transient($request_key, $requests + 1, WINNICA_CONTACT_WINDOW);

    if (
        !isset($_POST['winnica_contact_nonce'])
        || !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['winnica_contact_nonce'])),
            'winnica_contact'
        )
    ) {
        winnica_contact_redirect('security');
    }

    if (!empty($_POST['website'])) {
        winnica_contact_redirect('success');
    }

    $started = sanitize_text_field(wp_unslash($_POST['contact_started'] ?? ''));
    if (!winnica_contact_token_is_valid($started)) {
        winnica_contact_redirect('security');
    }

    $attempts = (int) get_transient($rate_key);
    if ($attempts >= WINNICA_CONTACT_MAX_SUBMISSIONS) {
        winnica_contact_redirect('rate_limit');
    }

    $name = sanitize_text_field(wp_unslash($_POST['contact_name'] ?? ''));
    // Keep the raw address alongside the sanitised one: sanitize_email() strips a
    // malformed value down to an empty string, and we still want to show it back.
    $email_input = sanitize_text_field(wp_unslash($_POST['contact_email'] ?? ''));
    $email       = sanitize_email($email_input);
    $phone       = sanitize_text_field(wp_unslash($_POST['contact_phone'] ?? ''));
    // Keep the typed date next to the normalised one: showing back "15.O8.2026"
    // is how somebody spots their own typo.
    $date_input  = sanitize_text_field(wp_unslash($_POST['contact_date'] ?? ''));
    $date        = winnica_parse_visit_date($date_input);
    $guests      = sanitize_text_field(wp_unslash($_POST['contact_guests'] ?? ''));
    $topic       = sanitize_key(wp_unslash($_POST['contact_topic'] ?? ''));
    $message     = sanitize_textarea_field(wp_unslash($_POST['contact_message'] ?? ''));
    $consent     = !empty($_POST['contact_consent']);

    $topics = [
        'degustacja' => 'Degustacja',
        'grupa'      => 'Oferta dla grupy',
        'szkola'     => 'Szkoła lub przedszkole',
        'wydarzenie' => 'Wydarzenie lub warsztaty',
        'inne'       => 'Inne pytanie',
    ];

    $url_count = preg_match_all('~https?://|www\.~iu', $message);

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'name';
    }
    if (!is_email($email)) {
        $errors[] = 'email';
    }
    if (!isset($topics[$topic])) {
        $errors[] = 'topic';
        $topic = '';
    }
    if (mb_strlen($message) < 10 || mb_strlen($message) > 4000 || $url_count > 2) {
        $errors[] = 'message';
    }
    if (!$consent) {
        $errors[] = 'consent';
    }
    // Both reservation helpers are optional; they only fail when filled in and
    // impossible. The date must parse as a real calendar day, not sit in the
    // past, and stay within two years, which catches year typos.
    if ($date_input !== '') {
        $parsed = $date !== ''
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone())
            : false;
        $tomorrow = new DateTimeImmutable('tomorrow', wp_timezone());
        if (!$parsed || $parsed < $tomorrow || $parsed > $tomorrow->modify('+2 years')) {
            $errors[] = 'date';
        }
    }
    // Eight is the smallest group we host, so a smaller number is a rejection
    // now rather than a disappointing reply later.
    if ($guests !== '' && (!ctype_digit($guests) || (int) $guests < 8 || (int) $guests > 60)) {
        $errors[] = 'guests';
    }

    if ($errors) {
        winnica_contact_redirect('validation', [
            'values' => [
                'name'    => $name,
                'email'   => $email_input,
                'phone'   => $phone,
                'date'    => $date_input,
                'guests'  => $guests,
                'topic'   => $topic,
                'message' => $message,
                'consent' => $consent,
                // Reuse the original timestamp token so correcting a typo does not
                // trip the "form filled in too fast" guard all over again.
                'started' => $started,
            ],
            'errors' => $errors,
        ]);
    }

    // Only submissions that get past validation count against this limit, so nobody
    // locks themselves out by fixing their own typos. The request counter above
    // already charged this attempt regardless of how it ended.
    set_transient($rate_key, $attempts + 1, WINNICA_CONTACT_WINDOW);

    $topic_label = $topics[$topic];
    $content = sprintf(
        "Imię i nazwisko: %s\nE-mail: %s\nTelefon: %s\nPreferowana data wizyty: %s\nLiczba osób: %s\nTemat: %s\n\nWiadomość:\n%s",
        $name,
        $email,
        $phone ?: 'nie podano',
        $date ? winnica_format_visit_date($date) : 'nie podano',
        $guests ?: 'nie podano',
        $topic_label,
        $message
    );

    $message_id = wp_insert_post([
        'post_type'    => 'winnica_message',
        'post_status'  => 'private',
        'post_title'   => sprintf('%s — %s', $topic_label, $name),
        'post_content' => $content,
    ], true);

    if (is_wp_error($message_id)) {
        winnica_contact_redirect('error');
    }

    update_post_meta($message_id, '_contact_name', $name);
    update_post_meta($message_id, '_contact_email', $email);
    update_post_meta($message_id, '_contact_phone', $phone);
    update_post_meta($message_id, '_contact_date', $date);
    update_post_meta($message_id, '_contact_guests', $guests);
    update_post_meta($message_id, '_contact_topic', $topic);
    update_post_meta($message_id, '_contact_status', 'new');
    update_post_meta($message_id, '_contact_fingerprint', $fingerprint);

    $recipient = sanitize_email(get_theme_mod('winnica_email', get_option('admin_email')));
    $sent = false;
    if ($recipient) {
        $sent = wp_mail(
            $recipient,
            sprintf('[Winnica Nowizny] %s — %s', $topic_label, $name),
            $content,
            [
                'Content-Type: text/plain; charset=UTF-8',
                sprintf('Reply-To: %s <%s>', $name, $email),
            ]
        );
    }
    update_post_meta($message_id, '_contact_email_sent', $sent ? '1' : '0');

    winnica_contact_redirect('success');
}

add_action('admin_post_nopriv_winnica_contact', 'winnica_handle_contact_form');
add_action('admin_post_winnica_contact', 'winnica_handle_contact_form');

add_action('add_meta_boxes_winnica_message', function (): void {
    add_meta_box('winnica_message_details', 'Obsługa rezerwacji', 'winnica_message_meta_box', 'winnica_message', 'side', 'high');
});

function winnica_message_meta_box(WP_Post $post): void
{
    if (!current_user_can('edit_post', $post->ID)) {
        return;
    }

    $status = get_post_meta($post->ID, '_contact_status', true) ?: 'new';
    $email  = get_post_meta($post->ID, '_contact_email', true);
    $phone  = get_post_meta($post->ID, '_contact_phone', true);
    $date   = get_post_meta($post->ID, '_contact_date', true);
    $guests = get_post_meta($post->ID, '_contact_guests', true);
    $sent   = get_post_meta($post->ID, '_contact_email_sent', true) === '1';
    wp_nonce_field('winnica_message_status', 'winnica_message_status_nonce');

    echo '<p><label for="winnica-message-status"><strong>Status</strong></label></p>';
    echo '<select id="winnica-message-status" name="winnica_message_status" style="width:100%">';
    foreach (WINNICA_MESSAGE_STATUSES as $value => $label) {
        printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($status, $value, false), esc_html($label));
    }
    echo '</select>';
    echo '<p><strong>E-mail:</strong><br><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
    if ($phone) {
        echo '<p><strong>Telefon:</strong><br><a href="tel:' . esc_attr(preg_replace('/\D+/', '', $phone)) . '">' . esc_html($phone) . '</a></p>';
    }
    if ($date) {
        echo '<p><strong>Preferowana data:</strong><br>' . esc_html(winnica_format_visit_date($date)) . '</p>';
    }
    if ($guests) {
        echo '<p><strong>Liczba osób:</strong><br>' . esc_html($guests) . '</p>';
    }
    echo '<p><strong>Powiadomienie e-mail:</strong><br>' . ($sent ? 'wysłane' : 'niewysłane / SMTP nieaktywne') . '</p>';
}

add_action('save_post_winnica_message', function (int $post_id): void {
    if (
        !isset($_POST['winnica_message_status_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['winnica_message_status_nonce'])), 'winnica_message_status')
        || !current_user_can('edit_post', $post_id)
        || wp_is_post_autosave($post_id)
    ) {
        return;
    }

    $status = sanitize_key(wp_unslash($_POST['winnica_message_status'] ?? 'new'));
    if (isset(WINNICA_MESSAGE_STATUSES[$status])) {
        update_post_meta($post_id, '_contact_status', $status);
    }
});

add_filter('manage_winnica_message_posts_columns', function (array $columns): array {
    return [
        'cb'             => $columns['cb'],
        'title'          => 'Wiadomość',
        'contact_status' => 'Status',
        'contact_data'   => 'Kontakt',
        'date'           => 'Data',
    ];
});

add_action('manage_winnica_message_posts_custom_column', function (string $column, int $post_id): void {
    if ($column === 'contact_status') {
        $status = get_post_meta($post_id, '_contact_status', true) ?: 'new';
        echo esc_html(WINNICA_MESSAGE_STATUSES[$status] ?? $status);
    }
    if ($column === 'contact_data') {
        echo esc_html(get_post_meta($post_id, '_contact_email', true));
        $phone = get_post_meta($post_id, '_contact_phone', true);
        if ($phone) {
            echo '<br>' . esc_html($phone);
        }
    }
}, 10, 2);

add_action('restrict_manage_posts', function (string $post_type): void {
    if ($post_type !== 'winnica_message') {
        return;
    }
    $current = sanitize_key(wp_unslash($_GET['contact_status'] ?? ''));
    echo '<select name="contact_status"><option value="">Wszystkie statusy</option>';
    foreach (WINNICA_MESSAGE_STATUSES as $value => $label) {
        printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($label));
    }
    echo '</select>';
});

add_action('pre_get_posts', function (WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'winnica_message') {
        return;
    }
    $status = sanitize_key(wp_unslash($_GET['contact_status'] ?? ''));
    if (isset(WINNICA_MESSAGE_STATUSES[$status])) {
        $query->set('meta_key', '_contact_status');
        $query->set('meta_value', $status);
    }
});

add_action('wp_dashboard_setup', function (): void {
    if (!current_user_can('edit_winnica_messages')) {
        return;
    }

    wp_add_dashboard_widget('winnica_messages_widget', 'Winnica — wiadomości i rezerwacje', function (): void {
        if (!current_user_can('edit_winnica_messages')) {
            return;
        }

        $counts = [];
        foreach (WINNICA_MESSAGE_STATUSES as $status => $label) {
            $counts[$status] = (int) (new WP_Query([
                'post_type'      => 'winnica_message',
                'post_status'    => 'private',
                'meta_key'       => '_contact_status',
                'meta_value'     => $status,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]))->found_posts;
        }
        echo '<p><strong>Nowe:</strong> ' . esc_html((string) $counts['new']) . ' &nbsp; <strong>Zarezerwowane:</strong> ' . esc_html((string) $counts['booked']) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=winnica_message')) . '">Otwórz panel wiadomości</a></p>';
    });
});
