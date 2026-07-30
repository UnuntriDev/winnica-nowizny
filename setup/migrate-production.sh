#!/bin/sh
# Run after importing the database, either inside a WordPress container or over
# SSH on a shared host. Run it from the WordPress root so WP-CLI finds wp-config.

set -eu

: "${OLD_WP_URL:?set OLD_WP_URL, for example http://localhost:8080}"
: "${WP_URL:?set the final HTTPS WP_URL}"
: "${WP_ADMIN_EMAIL:?set the confirmed administrative email}"

case "$WP_URL" in
  https://*) ;;
  *) echo "WP_URL must use HTTPS" >&2; exit 1 ;;
esac

# In a container WP-CLI runs as root and refuses to start without the flag. On a
# shared host the same flag is a hard error, because there you are an ordinary
# user. Deciding by the actual uid keeps one script valid on both.
if [ "$(id -u)" -eq 0 ]; then
  WP="wp --allow-root"
else
  WP="wp"
fi

command -v wp >/dev/null 2>&1 || { echo "WP-CLI not found in PATH" >&2; exit 1; }

echo "Replacing the local URL with the production URL..."
$WP search-replace "$OLD_WP_URL" "$WP_URL" --all-tables-with-prefix --precise --skip-columns=guid
$WP option update home "$WP_URL"
$WP option update siteurl "$WP_URL"
$WP option update admin_email "$WP_ADMIN_EMAIL"
$WP option update blog_public 1
$WP rewrite flush --hard
$WP transient delete --all
$WP cache flush

echo "Production URL: $($WP option get home)"
echo "Search visibility: $($WP option get blog_public)"
