#!/bin/sh
# Run inside the production WordPress container after importing the database.

set -eu

: "${OLD_WP_URL:?set OLD_WP_URL, for example http://localhost:8080}"
: "${WP_URL:?set the final HTTPS WP_URL}"

case "$WP_URL" in
  https://*) ;;
  *) echo "WP_URL must use HTTPS" >&2; exit 1 ;;
esac

WP="wp --allow-root"

echo "Replacing the local URL with the production URL..."
$WP search-replace "$OLD_WP_URL" "$WP_URL" --all-tables-with-prefix --precise --skip-columns=guid
$WP option update home "$WP_URL"
$WP option update siteurl "$WP_URL"
$WP option update blog_public 1
$WP rewrite flush --hard
$WP transient delete --all
$WP cache flush

echo "Production URL: $($WP option get home)"
echo "Search visibility: $($WP option get blog_public)"
