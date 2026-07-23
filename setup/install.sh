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
GENERATED_PASSWORD=0
if [ -z "$ADMIN_PASSWORD" ]; then
  ADMIN_PASSWORD="$(tr -dc 'A-Za-z0-9!@#%+=' </dev/urandom | head -c 24)"
  GENERATED_PASSWORD=1
fi
$WP core install \
  --url="http://localhost:8080" \
  --title="Winnica Nowizny" \
  --admin_user=admin \
  --admin_password="$ADMIN_PASSWORD" \
  --admin_email=admin@winnicanowizny.local \
  --locale=pl_PL \
  --skip-email

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

echo "📦 Installing plugins..."
$WP plugin install advanced-custom-fields --activate
$WP plugin install timber-library --activate
$WP plugin install wordpress-seo --activate

echo "🔄 Syncing ACF field groups from JSON..."
for f in /var/www/html/wp-content/themes/winnica-nowizny/acf-json/*.json; do
  $WP acf json import "$f" 2>/dev/null || true
done

echo "📄 Creating front page..."
$WP post create --post_type=page --post_title="Strona główna" --post_status=publish --post_name=home
FRONT_ID=$($WP post list --post_type=page --name=home --field=ID)
$WP option update show_on_front page
$WP option update page_on_front "$FRONT_ID"

echo "📄 Creating additional pages..."
$WP post create --post_type=page --post_title="O nas" --post_status=publish --post_name=o-nas
$WP post create --post_type=page --post_title="Kontakt" --post_status=publish --post_name=kontakt
PRIVACY_ID=$($WP post create --post_type=page --post_title="Polityka prywatności" --post_status=publish --post_name=polityka-prywatnosci --post_content="$(cat /setup/privacy-policy.html)" --porcelain)
$WP option update wp_page_for_privacy_policy "$PRIVACY_ID"

echo "🍷 Creating wine posts..."
$WP post create --post_type=wino --post_title="Solaris" --post_status=publish --post_name=solaris \
  --post_excerpt="Białe wino wytrawne o delikatnym, owocowym charakterze z nutami jabłka i moreli."

$WP post create --post_type=wino --post_title="Johanniter" --post_status=publish --post_name=johanniter \
  --post_excerpt="Aromatyczne białe wino z wyraźnymi nutami cytrusów i białych kwiatów."

$WP post create --post_type=wino --post_title="Rondo" --post_status=publish --post_name=rondo \
  --post_excerpt="Czerwone wino wytrawne o pełnym ciele, z nutami ciemnych owoców i delikatną taninowością."

$WP post create --post_type=wino --post_title="Regent" --post_status=publish --post_name=regent \
  --post_excerpt="Eleganckie czerwone wino z wyraźną strukturą i nutami wiśni oraz czekolady."

$WP post create --post_type=wino --post_title="Hibernal" --post_status=publish --post_name=hibernal \
  --post_excerpt="Białe wino półwytrawne z delikatną słodyczą i nutami miodu i gruszki."

$WP post create --post_type=wino --post_title="Różowe Nowizny" --post_status=publish --post_name=rozowe-nowizny \
  --post_excerpt="Świeże wino różowe z nutami truskawki i maliny, idealne na letnie wieczory."

echo "🏷️ Creating wine categories..."
$WP term create rodzaj-wina "Białe wytrawne" --slug=biale-wytrawne 2>/dev/null || true
$WP term create rodzaj-wina "Białe półwytrawne" --slug=biale-polwytrawne 2>/dev/null || true
$WP term create rodzaj-wina "Czerwone wytrawne" --slug=czerwone-wytrawne 2>/dev/null || true
$WP term create rodzaj-wina "Różowe" --slug=rozowe 2>/dev/null || true

echo "🔗 Assigning categories to wines..."
$WP post term set $($WP post list --post_type=wino --name=solaris --field=ID) rodzaj-wina biale-wytrawne
$WP post term set $($WP post list --post_type=wino --name=johanniter --field=ID) rodzaj-wina biale-wytrawne
$WP post term set $($WP post list --post_type=wino --name=rondo --field=ID) rodzaj-wina czerwone-wytrawne
$WP post term set $($WP post list --post_type=wino --name=regent --field=ID) rodzaj-wina czerwone-wytrawne
$WP post term set $($WP post list --post_type=wino --name=hibernal --field=ID) rodzaj-wina biale-polwytrawne
$WP post term set $($WP post list --post_type=wino --name=rozowe-nowizny --field=ID) rodzaj-wina rozowe

echo "🧭 Creating menu..."
$WP menu create "Menu główne"
$WP menu location assign "Menu główne" primary
$WP menu item add-custom "Menu główne" "Historia" "#historia"
$WP menu item add-custom "Menu główne" "Doświadczenia" "#doswiadczenia"
$WP menu item add-custom "Menu główne" "Wina" "#wina"
$WP menu item add-custom "Menu główne" "Galeria" "#galeria"
$WP menu item add-custom "Menu główne" "Zaplanuj wizytę" "#wizyta"

echo "⚙️ Setting Customizer options..."
$WP theme mod set winnica_phone "607 578 156"
$WP theme mod set winnica_email "winnicanowizny@op.pl"
$WP theme mod set winnica_address "Połom Mały 60, 32-862 Porąbka Iwkowska"
$WP theme mod set winnica_instagram "https://www.instagram.com/winnicanowizny/"
$WP theme mod set winnica_facebook "https://www.facebook.com/winnicanowizny"
$WP theme mod set winnica_footer_desc "Rodzinna winnica na Pogórzu Rożnowskim. Tradycja, pasja i smak od 2005 roku."

echo ""
echo "✅ Setup complete!"
echo "🌐 Site: http://localhost:8080"
echo "🔑 Admin: http://localhost:8080/wp-admin"
echo "   Login: admin"
if [ "$GENERATED_PASSWORD" = "1" ]; then
  echo "   Jednorazowo wygenerowane hasło: $ADMIN_PASSWORD"
else
  echo "   Hasło: wartość zmiennej WP_ADMIN_PASSWORD"
fi
echo ""
echo "⚠️  Next steps:"
echo "   1. ACF fields auto-sync from acf-json/ folder"
echo "   2. Run: sh /setup/seed-acf-meta.sh"
echo "   3. Run: sh /setup/seed-homepage.sh"
echo "   4. Upload wine photos via Media Library"
echo "   5. Set Customizer options: Appearance → Customize → Winnica Nowizny"
