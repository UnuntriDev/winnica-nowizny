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
$WP post meta update $FRONT_ID hero_subtitle "Gdzie pasja rodzi wyjątkowy smak, a widok zatrzymuje czas"
# Oba wyjscia zostaja w hero. Powtorka "Odwiedz nas" zniknela po stronie paska
# nawigacji, ktory na desktopie nie pokazuje juz tego przycisku.
$WP post meta update $FRONT_ID hero_cta_primary_text "Odwiedź nas"
$WP post meta update $FRONT_ID hero_cta_primary_url "#wizyta"
$WP post meta update $FRONT_ID hero_cta_secondary_text "Poznaj nasze wina"
$WP post meta update $FRONT_ID hero_cta_secondary_url "#wina"

# Historia
$WP post meta update $FRONT_ID historia_show 1
$WP post meta update $FRONT_ID historia_label "Nasza historia"
$WP post meta update $FRONT_ID historia_title "Rodzinna pasja<br><em>zakwita w winorośli</em>"
$WP post meta update $FRONT_ID historia_text_1 "Jesteśmy rodziną, która od ponad 20 lat z pasją zajmuje się winiarstwem. Nasza winnica to gospodarstwo enoturystyczne, w którym zamiłowanie do uprawy winorośli łączymy z gościnnością."
$WP post meta update $FRONT_ID historia_text_2 "Winnica została założona w 2005 roku. Położona jest na słonecznym, południowo-zachodnim stoku Pogórza Rożnowskiego, skąd rozciąga się malowniczy widok na Beskid Wyspowy i Beskid Sądecki."
$WP post meta update $FRONT_ID historia_text_3 "Naszą misją jest dzielenie się tym wyjątkowym miejscem oraz przynoszenie naszym gościom radości, spokoju i harmonii płynących z bliskiego kontaktu z naturą."
$WP post meta update $FRONT_ID historia_stat_1_number "2005"
$WP post meta update $FRONT_ID historia_stat_1_label "Rok założenia"
$WP post meta update $FRONT_ID historia_stat_2_number "1,5 ha"
$WP post meta update $FRONT_ID historia_stat_2_label "Powierzchnia"
$WP post meta update $FRONT_ID historia_stat_3_number "7"
$WP post meta update $FRONT_ID historia_stat_3_label "Odmian"

# Doświadczenia
$WP post meta update $FRONT_ID exp_show 1
$WP post meta update $FRONT_ID exp_label "Doświadczenia"
$WP post meta update $FRONT_ID exp_title "Poczuj charakter winnicy"
$WP post meta update $FRONT_ID exp_card_1_title "Degustacja w piwnicy"
$WP post meta update $FRONT_ID exp_card_1_desc "Degustacja win oraz produktów regionalnych w zabytkowej piwnicy"
$WP post meta update $FRONT_ID exp_card_2_title "Spacer po winnicy"
$WP post meta update $FRONT_ID exp_card_2_desc "Oprowadzanie po winnicy z historią terroir"
$WP post meta update $FRONT_ID exp_card_3_title "Karmienie danieli"
$WP post meta update $FRONT_ID exp_card_3_desc "Bliskie spotkanie z sympatycznymi zwierzętami podczas pobytu w winnicy"
$WP post meta update $FRONT_ID exp_card_4_title "Park linowy"
$WP post meta update $FRONT_ID exp_card_4_desc "Trasa z tyrolką dla dzieci w wieku 3–10 lat"

# Wina
$WP post meta update $FRONT_ID wines_show 1
$WP post meta update $FRONT_ID wines_label "Nasze wina"
$WP post meta update $FRONT_ID wines_title "Smak Pogórza"
$WP post meta update $FRONT_ID wines_note "Wina sprzedajemy wyłącznie na miejscu w winnicy, przez cały rok."
$WP post meta update $FRONT_ID wines_count "5"
$WP post meta update $FRONT_ID wines_count 6

# Piwnica
$WP post meta update $FRONT_ID cellar_show 1
$WP post meta update $FRONT_ID cellar_year "1891"
$WP post meta update $FRONT_ID cellar_title "Piwnica z historią"
$WP post meta update $FRONT_ID cellar_text_1 "Szczególny charakter Winnicy nadaje zabytkowa piwnica, której historia sięga 1891 roku. Pierwotnie została wybudowana w pobliskiej Wytrzyszczce, gdzie przez wiele lat pełniła funkcję budynku gospodarczego."
$WP post meta update $FRONT_ID cellar_text_2 "W 2012 roku przeniesiono ją na teren Winnicy i nadano jej nowe życie. Dziś przechowujemy w niej nasze wina, organizujemy degustacje i chętnie przyjmujemy gości."
$WP post meta update $FRONT_ID cellar_cta_text "Zarezerwuj degustację w piwnicy"
$WP post meta update $FRONT_ID cellar_cta_url "#wizyta"

# Galeria
$WP post meta update $FRONT_ID galeria_show 1
$WP post meta update $FRONT_ID galeria_label "Galeria"
$WP post meta update $FRONT_ID galeria_title "Chwile w winnicy"

# Opinie gości
# Teksty przepisane z profilu Google winnicy. Podpisy zapisane od razu w formie
# skroconej (imie i inicjal nazwiska), zeby repozytorium nie przechowywalo pelnych
# danych autorow. winnica_short_author() przepuszcza taka forme bez zmian.
# Opinia 1 jest skrocona, stad wielokropek na koncu.
$WP post meta update $FRONT_ID opinie_show 1
$WP post meta update $FRONT_ID opinie_label "Opinie gości"
$WP post meta update $FRONT_ID opinie_title "Co mówią nasi goście"
$WP post meta update $FRONT_ID opinie_rating "5,0"
# Zaokrąglona w dół: "ponad 160" pozostaje prawdą, kiedy opinii przybywa.
$WP post meta update $FRONT_ID opinie_count "160"
$WP post meta update $FRONT_ID opinie_url "https://www.google.com/maps/place/?q=place_id:ChIJQz77EMr1PUcRwi-it-NhdWc"
$WP post meta update $FRONT_ID opinie_1_text "Winnicę Nowizny odwiedziliśmy już jakiś czas temu zupełnie przypadkowo, gdy zauważyliśmy drewniany drogowskaz przy drodze i zdecydowanie warto było zajechać :). Od tego miejsca zaczęło się nasze zainteresowanie polskim winem. Nigdy nie sądziliśmy, że u nas może powstawać tak smaczne wino, które w niczym nie ustępuje producentom z Włoch, Francji czy Hiszpanii…"
$WP post meta update $FRONT_ID opinie_1_author "Radek S."
$WP post meta update $FRONT_ID opinie_2_text "Polecam z całego serca!! Pierogi z dziczyzną top, ale pozostałe też nie odbiegają. Cisza spokój, przepyszne wino idealne do obiadu lub na prezent. Atrakcje dla każdego, degustacja, pyszne jedzenie, przestrzeń dla dzieci, park linowy, daniele. Atmosfera rodzinna, a właściciele do serca przyłóż, na pewno wrócimy ❤️"
$WP post meta update $FRONT_ID opinie_2_author "Ania E."
$WP post meta update $FRONT_ID opinie_3_text "Przepiękne miejsce na mapie okolic Nowego Sącza. Można tu usiąść i delektować się winem w wyjątkowych okolicznościach przyrody. Dodatkowym atutem jest to, że można również spróbować bardzo dobrych pierogów."
$WP post meta update $FRONT_ID opinie_3_author "Katarzyna G."
$WP post meta update $FRONT_ID opinie_4_text "Bardzo sympatyczni właściciele, potrafią zadbać o przyjemną atmosferę, ciekawie opowiedzieć o winie. Mieliśmy przyjemność przetestować białe półsłodkie- było pyszne!"
$WP post meta update $FRONT_ID opinie_4_author "Agnieszka K."
$WP post meta update $FRONT_ID opinie_5_text "Pyszne pierogi, świetne widoki oraz dodatkowe atrakcje (hodowla danieli, park linowy). Winnica oferuje 6 rodzajów win (3 wytrawne, 3 półsłodkie) z winogron rosnących na stoku. Można przejść się po winnicy i zobaczyć jak rosną winogrona. Zdecydowanie polecam w trakcie urlopu/wakacji w Iwkowej lub okolicy!"
$WP post meta update $FRONT_ID opinie_5_author "Łukasz P."
$WP post meta update $FRONT_ID opinie_6_text "Pierogi ze śliwkami. I wszystko jasne. Ci, którzy byli, wiedzą. A Ci, którzy jeszcze nie byli powinni to szybko zmienić. Z wielu garnków i pieców miałem okazję pierogi jeść. Ale te tutaj, a w szczególności te ze śliwką były wyjątkowe, aromatyczne i nie obyło się bez drugiej porcji. Do tego piękny widok na winnicę. Świetne miejsce na obiad w drodze."
$WP post meta update $FRONT_ID opinie_6_author "Szymon S."

# Wizyta
$WP post meta update $FRONT_ID wizyta_show 1
$WP post meta update $FRONT_ID wizyta_label "Pozostańmy w kontakcie"
$WP post meta update $FRONT_ID wizyta_title "Odwiedź nas"
$WP post meta update $FRONT_ID wizyta_address "Połom Mały 60<br>32-862 Porąbka Iwkowska"
$WP post meta update $FRONT_ID wizyta_contact '<a href="mailto:kontakt@winnicanowizny.pl">kontakt@winnicanowizny.pl</a><br><a href="tel:+48607578156">tel. 607 578 156</a>'
$WP post meta update $FRONT_ID wizyta_hours "Poza sezonem wakacyjnym<br>Sobota–niedziela: 11:00–20:00<br><br>Lipiec–sierpień<br>Poniedziałek–sobota: 11:00–20:00<br>Niedziela: 14:00–20:00"
$WP post meta update $FRONT_ID wizyta_hours_note "W godzinach otwarcia zapraszamy bez rezerwacji.<br>Nasze wina są dostępne w sprzedaży na miejscu przez cały rok."
# Informacje o dojeździe należy uzupełnić po ich potwierdzeniu.
$WP post meta update $FRONT_ID wizyta_form_title "Rezerwacja wizyty"
$WP post meta update $FRONT_ID wizyta_form_subtitle "Formularz jest potrzebny tylko przy grupach od 8 osób. W mniejszym gronie wpadaj bez zapowiedzi w godzinach otwarcia. Odpowiadamy w ciągu 24h."
$WP post meta update $FRONT_ID wizyta_show_map 1

echo ""
echo "✅ Homepage seeded with 9 sections!"
echo "   Hero → Historia → Doświadczenia → Wina → Piwnica → Galeria → Opinia → Terroir → Wizyta"
