#!/bin/sh
# Sklada paczke motywu gotowa do wgrania na hosting wspoldzielony.
#
# Na serwerze nie ma Node ani Composera w obiegu, wiec vendor i assets/dist
# musza byc zbudowane tutaj i pojechac razem z plikami. Motyw czyta manifest
# Vite i odmawia startu przy niekompletnym buildzie, wiec niedokonczona paczka
# konczy sie biala strona, a nie stopniowa degradacja.
#
# Uzycie z katalogu glownego repozytorium:
#   sh setup/build-release.sh
#
# Wynik: dist/winnica-nowizny-RRRRMMDD-HHMM.zip

set -eu

THEME_DIR="wp-theme/winnica-nowizny"
OUT_DIR="dist"
STAMP="$(date +%Y%m%d-%H%M)"
NAME="winnica-nowizny"
STAGE="$OUT_DIR/$NAME"
ARCHIVE="$OUT_DIR/$NAME-$STAMP.zip"

[ -d "$THEME_DIR" ] || { echo "Uruchom z katalogu glownego repozytorium" >&2; exit 1; }

echo "==> Zaleznosci PHP"
# Repozytorium nie zaklada Composera na maszynie: lokalnie leci przez obraz
# Dockera, tak jak w README. Jesli jednak jest zainstalowany natywnie, uzywamy
# go, bo jest szybszy i nie wymaga dzialajacego demona.
if command -v composer >/dev/null 2>&1; then
  ( cd "$THEME_DIR" && composer install --no-dev --optimize-autoloader --quiet )
elif command -v docker >/dev/null 2>&1; then
  docker run --rm -v "$(pwd)/$THEME_DIR:/app" -w /app composer:2.8 \
    install --no-dev --optimize-autoloader --quiet
else
  echo "Potrzebny Composer albo Docker" >&2
  exit 1
fi

echo "==> Frontend"
( cd "$THEME_DIR" && npm ci --silent && npm run build )

# Manifest jest jedynym dowodem, ze build faktycznie powstal. Sprawdzamy go
# zanim cokolwiek spakujemy, zeby blad wyszedl tu, a nie na produkcji.
[ -f "$THEME_DIR/assets/dist/.vite/manifest.json" ] || { echo "Brak manifestu Vite, build sie nie udal" >&2; exit 1; }
[ -f "$THEME_DIR/vendor/autoload.php" ] || { echo "Brak vendor/autoload.php" >&2; exit 1; }

echo "==> Kopiowanie plikow"
rm -rf "$STAGE"
mkdir -p "$STAGE"

# Wszystko, czego motyw potrzebuje w czasie dzialania. Reszta to narzedzia
# budowania i zrodla, ktore na serwerze sa martwym ciezarem.
for item in \
  404.php acf-json assets functions.php front-page.php inc index.php \
  page.php screenshot.jpg style.css templates vendor
do
  [ -e "$THEME_DIR/$item" ] || { echo "Brakuje $item" >&2; exit 1; }
  cp -R "$THEME_DIR/$item" "$STAGE/"
done

# Zrodla fontow i obrazow sa juz w assets/dist z hashami w nazwach. Katalogi
# zrodlowe zostawiaja w paczce kilka megabajtow, ktorych nikt nie serwuje.
rm -rf "$STAGE/assets/fonts" "$STAGE/assets/img"

echo "==> Pakowanie"
rm -f "$ARCHIVE"
# Git Bash na Windows nie ma zipa. PowerShell tworzy ten sam format, wiec
# paczka wychodzi identyczna niezaleznie od maszyny.
if command -v zip >/dev/null 2>&1; then
  ( cd "$OUT_DIR" && zip -qr "$(basename "$ARCHIVE")" "$NAME" )
elif command -v powershell >/dev/null 2>&1; then
  # Nie Compress-Archive: w PowerShellu 5.1 zapisuje separatory jako "\", przez
  # co unzip na serwerze robi z drzewa katalogow plaska liste plikow o nazwach
  # z backslashami. Powod jest opisany w setup/zip-dir.ps1.
  powershell -NoProfile -ExecutionPolicy Bypass -File "setup/zip-dir.ps1" \
    -SourceDir "$STAGE" -DestinationZip "$ARCHIVE"
else
  echo "Potrzebny zip albo PowerShell" >&2
  exit 1
fi
rm -rf "$STAGE"

echo
echo "Paczka: $ARCHIVE"
du -sh "$ARCHIVE"
echo
echo "Wgraj zawartosc na serwer do wp-content/themes/winnica-nowizny"
echo "Nie wgrywaj plugins/timber-library: koliduje z Timberem z vendor/"
