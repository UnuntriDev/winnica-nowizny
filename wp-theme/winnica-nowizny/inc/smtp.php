<?php
/**
 * SMTP transport configured through environment variables or constants.
 * Secrets are never stored in the database or repository.
 */

defined('ABSPATH') || exit;

function winnica_config_value(string $name, string $default = ''): string
{
    if (defined($name)) {
        return trim((string) constant($name));
    }

    $value = getenv($name);
    return $value === false ? $default : trim((string) $value);
}

function winnica_smtp_is_configured(): bool
{
    return winnica_config_value('WINNICA_SMTP_HOST') !== '';
}

add_action('phpmailer_init', function (PHPMailer\PHPMailer\PHPMailer $mailer): void {
    if (!winnica_smtp_is_configured()) {
        return;
    }

    $host       = winnica_config_value('WINNICA_SMTP_HOST');
    $port       = (int) winnica_config_value('WINNICA_SMTP_PORT', '587');
    $username   = winnica_config_value('WINNICA_SMTP_USER');
    $password   = winnica_config_value('WINNICA_SMTP_PASS');
    $encryption = strtolower(winnica_config_value('WINNICA_SMTP_ENCRYPTION', 'tls'));
    $from       = sanitize_email(winnica_config_value('WINNICA_SMTP_FROM_EMAIL', $username));
    $from_name  = winnica_config_value('WINNICA_SMTP_FROM_NAME', 'Winnica Nowizny');

    $mailer->isSMTP();
    $mailer->Host       = $host;
    $mailer->Port       = $port > 0 ? $port : 587;
    $mailer->SMTPAuth   = $username !== '';
    $mailer->Username   = $username;
    $mailer->Password   = $password;
    $mailer->Timeout    = 12;
    $mailer->SMTPAutoTLS = true;

    if (in_array($encryption, ['tls', 'ssl', 'smtps'], true)) {
        $mailer->SMTPSecure = $encryption === 'smtps' ? 'ssl' : $encryption;
    }

    if ($from) {
        $mailer->setFrom($from, $from_name, false);
    }
});

add_action('wp_mail_failed', function (WP_Error $error): void {
    update_option('winnica_last_mail_error', [
        'time'    => time(),
        'message' => sanitize_text_field($error->get_error_message()),
    ], false);
});

add_action('admin_notices', function (): void {
    if (!current_user_can('manage_options') || winnica_smtp_is_configured()) {
        return;
    }

    echo '<div class="notice notice-warning"><p><strong>Winnica Nowizny:</strong> SMTP nie jest jeszcze skonfigurowane. Wiadomości są zapisywane w panelu, ale wysyłka e-mail wymaga uzupełnienia zmiennych <code>WINNICA_SMTP_*</code>.</p></div>';
});
