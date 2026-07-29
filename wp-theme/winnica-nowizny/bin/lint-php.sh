#!/bin/sh

set -eu

THEME_DIR="${1:-/var/www/html/wp-content/themes/winnica-nowizny}"
find "$THEME_DIR" \
  \( -path "$THEME_DIR/node_modules" -o -path "$THEME_DIR/vendor" \) -prune \
  -o -type f -name '*.php' -exec php -l '{}' ';'
