#!/bin/sh
# Winnica Nowizny — Seed ACF meta fields for wines
# Run AFTER install.sh and AFTER ACF PRO is activated
# Usage: docker compose run --rm wpcli sh /setup/seed-acf-meta.sh

set -e

# Support running as root in Docker
if [ "$(id -u)" = "0" ]; then
  WP="wp --allow-root"
else
  WP="wp"
fi

echo "🍷 Seeding wine meta fields..."

# Solaris
ID=$($WP post list --post_type=wino --name=solaris --field=ID)
$WP post meta update $ID rodzaj "Białe wytrawne"
$WP post meta update $ID szczepy "Solaris"
$WP post meta update $ID rocznik 2023
$WP post meta update $ID alkohol 12.5
$WP post meta update $ID pojemnosc 750
$WP post meta update $ID temperatura "8-10°C"
$WP post meta update $ID aromat "Jabłko, morela, cytrusy, kwiatowe nuty"
$WP post meta update $ID smak "Delikatny, owocowy, z orzeźwiającą kwasowością i mineralnym finiszem"
$WP post meta update $ID parowanie "Ryby, owoce morza, sałatki, lekkie sery"
$WP post meta update $ID dostepne 1
$WP post meta update $ID cena 65

# Johanniter
ID=$($WP post list --post_type=wino --name=johanniter --field=ID)
$WP post meta update $ID rodzaj "Białe wytrawne"
$WP post meta update $ID szczepy "Johanniter"
$WP post meta update $ID rocznik 2023
$WP post meta update $ID alkohol 13
$WP post meta update $ID pojemnosc 750
$WP post meta update $ID temperatura "10-12°C"
$WP post meta update $ID aromat "Cytrusy, białe kwiaty, zielone jabłko"
$WP post meta update $ID smak "Elegancki, złożony, z długim mineralnym posmakiem"
$WP post meta update $ID parowanie "Drób, pasta, risotto, dojrzałe sery"
$WP post meta update $ID dostepne 1
$WP post meta update $ID cena 75

# Rondo
ID=$($WP post list --post_type=wino --name=rondo --field=ID)
$WP post meta update $ID rodzaj "Czerwone wytrawne"
$WP post meta update $ID szczepy "Rondo"
$WP post meta update $ID rocznik 2022
$WP post meta update $ID alkohol 13.5
$WP post meta update $ID pojemnosc 750
$WP post meta update $ID temperatura "16-18°C"
$WP post meta update $ID aromat "Ciemne owoce, śliwka, pieprz, delikatna wanilia"
$WP post meta update $ID smak "Pełne ciało, miękkie taniny, owocowy z korzennym finiszem"
$WP post meta update $ID parowanie "Czerwone mięsa, dziczyzna, dojrzałe sery, grillowane warzywa"
$WP post meta update $ID dostepne 1
$WP post meta update $ID cena 85

# Regent
ID=$($WP post list --post_type=wino --name=regent --field=ID)
$WP post meta update $ID rodzaj "Czerwone wytrawne"
$WP post meta update $ID szczepy "Regent"
$WP post meta update $ID rocznik 2022
$WP post meta update $ID alkohol 14
$WP post meta update $ID pojemnosc 750
$WP post meta update $ID temperatura "16-18°C"
$WP post meta update $ID aromat "Wiśnia, czekolada, tytoń, cedr"
$WP post meta update $ID smak "Wyrazisty, strukturalny, z elegancką taninowością i długim finiszem"
$WP post meta update $ID parowanie "Steki, duszone mięsa, tarte, oscypek"
$WP post meta update $ID dostepne 1
$WP post meta update $ID cena 95

# Hibernal
ID=$($WP post list --post_type=wino --name=hibernal --field=ID)
$WP post meta update $ID rodzaj "Białe półwytrawne"
$WP post meta update $ID szczepy "Hibernal"
$WP post meta update $ID rocznik 2023
$WP post meta update $ID alkohol 11.5
$WP post meta update $ID pojemnosc 750
$WP post meta update $ID temperatura "8-10°C"
$WP post meta update $ID aromat "Miód, gruszka, akacja, nuty tropikalne"
$WP post meta update $ID smak "Delikatnie słodki, harmonijny, z przyjemną kwasowością"
$WP post meta update $ID parowanie "Kuchnia azjatycka, foie gras, desery owocowe"
$WP post meta update $ID dostepne 1
$WP post meta update $ID cena 60

# Różowe Nowizny
ID=$($WP post list --post_type=wino --name=rozowe-nowizny --field=ID)
$WP post meta update $ID rodzaj "Różowe"
$WP post meta update $ID szczepy "Rondo, Regent"
$WP post meta update $ID rocznik 2023
$WP post meta update $ID alkohol 12
$WP post meta update $ID pojemnosc 750
$WP post meta update $ID temperatura "8-10°C"
$WP post meta update $ID aromat "Truskawka, malina, róża, cytrusy"
$WP post meta update $ID smak "Świeże, lekkie, owocowe z orzeźwiającym finiszem"
$WP post meta update $ID parowanie "Sałatki, pizza, tapas, letnie grille"
$WP post meta update $ID dostepne 1
$WP post meta update $ID cena 55

echo ""
echo "✅ Wine meta fields seeded!"
echo "   Solaris     — 65 PLN (białe wytrawne)"
echo "   Johanniter  — 75 PLN (białe wytrawne)"
echo "   Rondo       — 85 PLN (czerwone wytrawne)"
echo "   Regent      — 95 PLN (czerwone wytrawne)"
echo "   Hibernal    — 60 PLN (białe półwytrawne)"
echo "   Różowe      — 55 PLN (różowe)"
