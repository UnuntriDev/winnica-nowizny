#!/bin/sh
# Winnica Nowizny — WordPress initial setup via WP-CLI
# Usage: docker compose run --rm wpcli sh /setup/install.sh

set -e

# Support running as root in Docker
if [ "$(id -u)" = "0" ]; then
  WP="wp --allow-root"
else
  WP="wp"
fi

echo "⏳ Waiting for WordPress files..."
until [ -f /var/www/html/wp-includes/version.php ]; do
  sleep 2
done

echo "🔧 Installing WordPress..."
ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@winnicanowizny.local}"
SITE_URL="${WP_URL:-http://localhost:8080}"
GENERATED_PASSWORD=0
if [ -z "$ADMIN_PASSWORD" ]; then
  ADMIN_PASSWORD="$(tr -dc 'A-Za-z0-9!@#%+=' </dev/urandom | head -c 24)"
  GENERATED_PASSWORD=1
fi
$WP core install \
  --url="$SITE_URL" \
  --title="Winnica Nowizny" \
  --admin_user=admin \
  --admin_password="$ADMIN_PASSWORD" \
  --admin_email="$ADMIN_EMAIL" \
  --locale=pl_PL \
  --skip-email

echo "📦 Installing pinned plugin versions..."
$WP plugin install advanced-custom-fields --version=6.8.6 --activate
$WP plugin install timber-library --version=1.23.4 --activate

echo "🎨 Activating theme..."
$WP theme activate winnica-nowizny

echo "🇵🇱 Setting up Polish locale..."
$WP language core install pl_PL
$WP site switch-language pl_PL
$WP option update timezone_string "Europe/Warsaw"
$WP option update date_format "j F Y"
$WP option update time_format "H:i"

echo "📝 Setting up permalinks..."
$WP rewrite structure '/%postname%/' --hard
$WP rewrite flush

echo "🧹 Removing default content..."
$WP post delete 1 --force 2>/dev/null || true
$WP post delete 2 --force 2>/dev/null || true
$WP plugin delete hello 2>/dev/null || true
$WP plugin delete akismet 2>/dev/null || true
$WP theme delete twentytwentyfour 2>/dev/null || true
$WP theme delete twentytwentyfive 2>/dev/null || true

echo "🔄 Syncing ACF field groups from JSON..."
for f in /var/www/html/wp-content/themes/winnica-nowizny/acf-json/*.json; do
  $WP acf json import "$f" 2>/dev/null || true
done

echo "📄 Creating front page..."
$WP post create --post_type=page --post_title="Strona główna" --post_status=publish --post_name=home
FRONT_ID=$($WP post list --post_type=page --name=home --field=ID)
$WP option update show_on_front page
$WP option update page_on_front "$FRONT_ID"

echo "📄 Creating privacy page..."
PRIVACY_ID=$($WP post create --post_type=page --post_title="Polityka prywatności" --post_status=publish --post_name=polityka-prywatnosci --post_content="$(cat /setup/privacy-policy.html)" --porcelain)
$WP option update wp_page_for_privacy_policy "$PRIVACY_ID"

echo "🦌 Setting site icon..."
SITE_ICON_ID=$($WP media import /var/www/html/wp-content/themes/winnica-nowizny/assets/images/site-icon.png \
  --title="Ikona Winnicy Nowizny" --porcelain)
$WP option update site_icon "$SITE_ICON_ID"

echo "🍷 Creating wine posts..."
# Real range from the winery. Card label comes from the 'rodzaj' post meta (not the
# taxonomy). This is a showcase — no price/availability. Cut-out bottle photos
# (transparent WebP) are set as featured images via the Media Library, not seeded here.
$WP post create --post_type=wino --post_title="Bukowiec" --post_status=publish --post_name=bukowiec --menu_order=1 \
  --post_excerpt="Świeże i cytrusowe, z nutami jabłka i białego pieprzu."

# Szpilówka appears twice; the card label (rodzaj) tells the two apart, so the
# title stays short.
$WP post create --post_type=wino --post_title="Szpilówka" --post_status=publish --post_name=szpilowka-wytrawna --menu_order=2 \
  --post_excerpt="Pełne i słoneczne, z nutami dojrzałej gruszki i moreli."

$WP post create --post_type=wino --post_title="Szpilówka" --post_status=publish --post_name=szpilowka-polslodka --menu_order=3 \
  --post_excerpt="Łagodne, z nutami brzoskwini, miodu i dojrzałych jabłek."

$WP post create --post_type=wino --post_title="Rosé" --post_status=publish --post_name=rose --menu_order=4 \
  --post_excerpt="Świeże, z nutami truskawki, maliny i czerwonych porzeczek."

$WP post create --post_type=wino --post_title="Marszałek" --post_status=publish --post_name=marszalek --menu_order=5 \
  --post_excerpt="Głębokie, z nutami leśnych jagód, wiśni i przypraw."

echo "🏷️ Setting wine meta (rodzaj = card label, szczep = helper line)..."
$WP post meta update $($WP post list --post_type=wino --name=bukowiec --field=ID) rodzaj "Białe wytrawne"
$WP post meta update $($WP post list --post_type=wino --name=szpilowka-wytrawna --field=ID) rodzaj "Białe wytrawne"
$WP post meta update $($WP post list --post_type=wino --name=szpilowka-polslodka --field=ID) rodzaj "Białe półsłodkie"
$WP post meta update $($WP post list --post_type=wino --name=rose --field=ID) rodzaj "Różowe półsłodkie"
$WP post meta update $($WP post list --post_type=wino --name=marszalek --field=ID) rodzaj "Czerwone wytrawne"

$WP post meta update $($WP post list --post_type=wino --name=bukowiec --field=ID) szczep "Bianka"
$WP post meta update $($WP post list --post_type=wino --name=szpilowka-wytrawna --field=ID) szczep "Solaris"
$WP post meta update $($WP post list --post_type=wino --name=szpilowka-polslodka --field=ID) szczep "Solaris"
$WP post meta update $($WP post list --post_type=wino --name=rose --field=ID) szczep "Cabernet Cortis"
$WP post meta update $($WP post list --post_type=wino --name=marszalek --field=ID) szczep "Marechal Foch"

echo "🧭 Creating menu..."
$WP menu create "Menu główne"
$WP menu location assign "Menu główne" primary
$WP menu item add-custom "Menu główne" "Historia" "#historia"
$WP menu item add-custom "Menu główne" "Doświadczenia" "#doswiadczenia"
$WP menu item add-custom "Menu główne" "Wina" "#wina"
$WP menu item add-custom "Menu główne" "Galeria" "#galeria"
$WP menu item add-custom "Menu główne" "Odwiedź nas" "#wizyta"

echo "⚙️ Setting Customizer options..."
$WP theme mod set winnica_phone "607 578 156"
$WP theme mod set winnica_email "winnicanowizny@op.pl"
$WP theme mod set winnica_address "Połom Mały 60, 32-862 Porąbka Iwkowska"
$WP theme mod set winnica_instagram "https://www.instagram.com/winnicanowizny/"
$WP theme mod set winnica_facebook "https://www.facebook.com/winnicanowizny"
$WP theme mod set winnica_footer_desc "Rodzinna winnica na Pogórzu Rożnowskim. Tradycja, pasja i smak od 2005 roku."

echo ""
echo "✅ Setup complete!"
echo "🌐 Site: $SITE_URL"
echo "🔑 Admin: ${SITE_URL%/}/wp-admin"
echo "   Login: admin"
if [ "$GENERATED_PASSWORD" = "1" ]; then
  echo "   Jednorazowo wygenerowane hasło: $ADMIN_PASSWORD"
else
  echo "   Hasło: wartość zmiennej WP_ADMIN_PASSWORD"
fi
echo ""
echo "⚠️  Next steps:"
echo "   1. ACF fields auto-sync from acf-json/ folder"
echo "   2. Run: sh /setup/seed-homepage.sh"
echo "   3. Set cut-out bottle photos (transparent WebP) as each wine's featured image"
echo "   4. Set Customizer options: Appearance → Customize → Winnica Nowizny"
