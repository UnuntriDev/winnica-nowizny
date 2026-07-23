#!/bin/sh
# Winnica Nowizny — Seed homepage ACF fields (free ACF, no Flexible Content)
# Usage: docker compose run --rm wpcli sh /setup/seed-homepage.sh

set -e

# Support running as root in Docker
if [ "$(id -u)" = "0" ]; then
  WP="wp --allow-root"
else
  WP="wp"
fi

FRONT_ID=$($WP option get page_on_front)

echo "📄 Seeding homepage fields (page ID: $FRONT_ID)..."

# Hero
$WP post meta update $FRONT_ID hero_show 1
$WP post meta update $FRONT_ID hero_label "Pogórze Rożnowskie · od 2005 roku"
$WP post meta update $FRONT_ID hero_title "Winnica <br>Nowizny"
$WP post meta update $FRONT_ID hero_subtitle "Gdzie tradycja winiarstwa spotyka piękno Pogórza Rożnowskiego"
$WP post meta update $FRONT_ID hero_cta_primary_text "Zaplanuj wizytę"
$WP post meta update $FRONT_ID hero_cta_primary_url "#wizyta"
$WP post meta update $FRONT_ID hero_cta_secondary_text "Poznaj nasze wina"
$WP post meta update $FRONT_ID hero_cta_secondary_url "#wina"

# Historia
$WP post meta update $FRONT_ID historia_show 1
$WP post meta update $FRONT_ID historia_label "Nasza historia"
$WP post meta update $FRONT_ID historia_title "Gdzie tradycja<br>spotyka pasję"
$WP post meta update $FRONT_ID historia_text_1 "Na malowniczym Pogórzu Rożnowskim, w miejscu gdzie południowe zbocza łagodnie opadają ku dolinie, od 2005 roku pielęgnujemy sztukę winiarstwa. Nasza winnica to hołd dla terroir — unikalnego połączenia gleby, klimatu i tradycji."
$WP post meta update $FRONT_ID historia_text_2 "Każda butelka opowiada historię tego miejsca. Od pierwszych sadzonek po dziś — łączymy wiedzę pokoleń z nowoczesnym podejściem do uprawy i produkcji wina."
$WP post meta update $FRONT_ID historia_stat_1_number "2005"
$WP post meta update $FRONT_ID historia_stat_1_label "Rok założenia"
$WP post meta update $FRONT_ID historia_stat_2_number "1,5 ha"
$WP post meta update $FRONT_ID historia_stat_2_label "Powierzchnia"
$WP post meta update $FRONT_ID historia_stat_3_number "7"
$WP post meta update $FRONT_ID historia_stat_3_label "Odmian"

# Doświadczenia
$WP post meta update $FRONT_ID exp_show 1
$WP post meta update $FRONT_ID exp_label "Doświadczenia"
$WP post meta update $FRONT_ID exp_title "Przeżyj winnicę"
$WP post meta update $FRONT_ID exp_card_1_title "Degustacja premium"
$WP post meta update $FRONT_ID exp_card_1_desc "5 win z komentarzem sommeliera, ser i oliwki"
$WP post meta update $FRONT_ID exp_card_2_title "Spacer po winnicy"
$WP post meta update $FRONT_ID exp_card_2_desc "Oprowadzanie po winnicy z historią terroir"
$WP post meta update $FRONT_ID exp_card_3_title "Warsztaty winiarskie"
$WP post meta update $FRONT_ID exp_card_3_desc "Stwórz własną kupażę pod okiem winiarza"
$WP post meta update $FRONT_ID exp_card_4_title "Piknik w winnicy"
$WP post meta update $FRONT_ID exp_card_4_desc "Kosz piknikowy, koc i wino z widokiem na Pogórze"

# Wina
$WP post meta update $FRONT_ID wines_show 1
$WP post meta update $FRONT_ID wines_label "Nasze wina"
$WP post meta update $FRONT_ID wines_title "Smak Pogórza"
$WP post meta update $FRONT_ID wines_archive_label "Zobacz wszystkie wina →"
$WP post meta update $FRONT_ID wines_count 6

# Piwnica
$WP post meta update $FRONT_ID cellar_show 1
$WP post meta update $FRONT_ID cellar_year "1891"
$WP post meta update $FRONT_ID cellar_title "Piwnica z historią"
$WP post meta update $FRONT_ID cellar_text_1 "Nasza piwnica to serce winnicy. Zbudowana w 1891 roku, przez ponad sto lat służyła okolicznym gospodarzom. Dziś, po starannej renowacji, jest miejscem, gdzie nasze wina dojrzewają w idealnych warunkach."
$WP post meta update $FRONT_ID cellar_text_2 "Grube kamienne mury utrzymują stałą temperaturę i wilgotność — naturalna klimatyzacja, którą doceniamy każdego dnia."
$WP post meta update $FRONT_ID cellar_cta_text "Zarezerwuj degustację w piwnicy"
$WP post meta update $FRONT_ID cellar_cta_url "#wizyta"

# Galeria
$WP post meta update $FRONT_ID galeria_show 1
$WP post meta update $FRONT_ID galeria_label "Galeria"
$WP post meta update $FRONT_ID galeria_title "Chwile w winnicy"

# Opinia
$WP post meta update $FRONT_ID opinia_show 1
$WP post meta update $FRONT_ID opinia_quote "Wina z Winnicy Nowizny to odkrycie — smak, który opowiada historię Pogórza Rożnowskiego w każdym kieliszku."
$WP post meta update $FRONT_ID opinia_author "Magazyn Wino & Styl"

# Terroir
$WP post meta update $FRONT_ID terroir_show 1
$WP post meta update $FRONT_ID terroir_label "Terroir"
$WP post meta update $FRONT_ID terroir_title "Nasz terroir"
$WP post meta update $FRONT_ID terroir_1_title "Gleba lessowa"
$WP post meta update $FRONT_ID terroir_1_desc "Żyzne gleby lessowe z domieszką wapienia, typowe dla Pogórza. Nadają winom mineralny charakter i elegancję."
$WP post meta update $FRONT_ID terroir_1_icon '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 2a15 15 0 0 1 4 10c0 4-1.5 7-4 10"/><path d="M12 2a15 15 0 0 0-4 10c0 4 1.5 7 4 10"/></svg>'
$WP post meta update $FRONT_ID terroir_2_title "Mikroklimat"
$WP post meta update $FRONT_ID terroir_2_desc "Południowa ekspozycja zapewnia maksymalne nasłonecznienie. Rożnowskie wzgórza chronią przed północnymi wiatrami."
$WP post meta update $FRONT_ID terroir_2_icon '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>'
$WP post meta update $FRONT_ID terroir_3_title "340 m n.p.m."
$WP post meta update $FRONT_ID terroir_3_desc "Wzniesienie zapewnia idealną amplitudę temperatur między dniem a nocą, kluczową dla rozwoju aromatów."
$WP post meta update $FRONT_ID terroir_3_icon '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 20l5-7 4 3 5-8 4 5"/><path d="M3 20h18"/></svg>'

# Wizyta
$WP post meta update $FRONT_ID wizyta_show 1
$WP post meta update $FRONT_ID wizyta_label "Zaplanuj wizytę"
$WP post meta update $FRONT_ID wizyta_title "Odwiedź nas"
$WP post meta update $FRONT_ID wizyta_address "Połom Mały 60<br>32-862 Porąbka Iwkowska"
$WP post meta update $FRONT_ID wizyta_contact '<a href="mailto:winnicanowizny@op.pl">winnicanowizny@op.pl</a><br><a href="tel:+48607578156">tel. 607 578 156</a>'
# Godziny otwarcia i informacje o dojeździe należy uzupełnić po ich potwierdzeniu.
$WP post meta update $FRONT_ID wizyta_form_title "Rezerwacja wizyty"
$WP post meta update $FRONT_ID wizyta_form_subtitle "Wypełnij formularz, odezwiemy się w ciągu 24h"
$WP post meta update $FRONT_ID wizyta_show_map 1

echo ""
echo "✅ Homepage seeded with 9 sections!"
echo "   Hero → Historia → Doświadczenia → Wina → Piwnica → Galeria → Opinia → Terroir → Wizyta"
