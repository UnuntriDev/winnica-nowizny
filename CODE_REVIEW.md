# Finalne code review przed wdrożeniem produkcyjnym

**Projekt:** Winnica Nowizny  
**Zakres:** własny motyw WordPress, PHP, Twig/Timber, JavaScript, CSS, ACF JSON, formularz, SMTP, SEO, zgody, cache, Docker i skrypty operacyjne  
**Data audytu:** 2026-07-29  
**Rewizja:** branch `feat/audit-fixes`, commit `fe9f13e`; drzewo robocze było czyste w chwili audytu

## Podsumowanie

Znaleziono:

- 2 problemy krytyczne,
- 7 problemów wysokich,
- 10 problemów średnich,
- 7 problemów niskich.

Najpoważniejszym problemem kodu jest wyłączone autoescapowanie w aktywnym Timber 1.23.4. Dane ACF i odtworzone dane formularza są w wielu miejscach umieszczane w HTML bez escapowania kontekstowego. W przypadku pól formularza istnieje kompletna ścieżka do wstrzyknięcia atrybutu HTML: dane użytkownika są zapisywane w transient, klucz trafia do URL, a wartości wracają do atrybutu `value` bez escapowania.

Najpoważniejszym problemem środowiska jest publiczny plik `/wp-content/debug.log`: odpowiada kodem 200, ma 5 257 270 bajtów i zawiera ścieżki systemowe, nazwy klas oraz historię błędów PHP.

W obecnym stanie projekt działa lokalnie, ale nie powinien zostać wdrożony na produkcję przed usunięciem problemów krytycznych i wysokich.

## Stan po wdrożeniu poprawek — 2026-07-30

Wszystkie problemy z raportu, które można rozwiązać w kodzie i konfiguracji
repozytorium, zostały naprawione. Elementy wymagające prawdziwych danych lub
zewnętrznej infrastruktury są teraz wymuszane przez profil produkcyjny i nie mają
wymyślonych wartości zastępczych.

| Punkt | Stan | Wdrożone rozwiązanie |
|---|---|---|
| K1 | Naprawiony | Timber 2/Twig 3 ma globalne autoescape; `raw` ograniczono do przejrzanych wyników WordPressa. Automatyczny test odrzuconego formularza potwierdza escapowanie próby wstrzyknięcia atrybutu. |
| K2 | Naprawiony | Produkcja ma wyłączone logowanie do webrootu, Apache blokuje `*.log`, a stary `debug.log` usunięto. Test HTTP zwraca 403. |
| W1 | Wymaga danych produkcyjnych | Walidacja konfiguracji SMTP jest fail-closed, a compose wymaga kompletu `WINNICA_SMTP_*`. Do testu dostarczenia potrzebne są prawdziwe dane operatora i konfiguracja SPF/DKIM/DMARC. |
| W2 | Naprawiony | Wiadomości mają osobne capabilities instalowane wyłącznie administratorowi; widget i operacje panelu sprawdzają dedykowane uprawnienie. |
| W3 | Naprawiony | `assets/dist` jest śledzone, manifest walidowany, produkcja bez kompletnego buildu zwraca kontrolowane 503, a lokalny fallback ładuje JS jako moduł. |
| W4 | Naprawiony w repozytorium | Dodano osobny fail-closed compose produkcyjny, obraz wieloetapowy, prywatną sieć bazy, wymagane sekrety i integrację z zewnętrznym reverse proxy. Certyfikat i proxy trzeba skonfigurować na hoście. |
| W5 | Naprawiony | Obraz i rdzeń są przypięte do WordPressa 7.0.2 na PHP 8.2; ACF zaktualizowano do 6.8.6. |
| W6 | Naprawiony | Motyw migrowano z nieutrzymywanej wtyczki Timber 1 do Composerowego Timber 2.5.1 i Twig 3.28.0. Brak zależności daje kontrolowane 503. |
| W7 | Naprawiony procesowo | Skrypt `migrate-production.sh` wykonuje bezpieczny search-replace, ustawia HTTPS, odświeża rewrite rules i czyści cache. Uruchomienie wymaga docelowej domeny. |
| S1–S5 | Naprawione | Wyłączono publiczny REST win, usunięto niepotwierdzone godziny ze schema, dodano release do cache, zaufane proxy dla IP oraz błędy formularza per pole połączone przez ARIA. |
| S6 | Przygotowany, wymaga infrastruktury | Dodano szyfrowane kopie off-site i health check/workflow. Harmonogram, magazyn `rclone`, klucz `age` oraz odbiorca alertów muszą zostać skonfigurowane poza repozytorium. |
| S7–S10 | Naprawione | Usunięto martwe pola ACF, ujednolicono seed i dokumentację, dodano zoptymalizowane AVIF-y oraz uzależniono redirecty od braku opublikowanej strony. |
| N1–N6 | Naprawione | Endpoint health jest minimalny, wersje serwera ukryte, granica dat zgodna w PHP/JS, wersja motywu ma jedno źródło, martwy kod usunięty, bezpośredni URL motywu nie powoduje 500. |
| N7 | Częściowo zależny od właściciela | Ikona witryny została przygotowana i ustawiana przez instalator. Produkcyjny `WP_ADMIN_EMAIL` jest obowiązkowy, ale musi zostać potwierdzony przez właściciela. |

### Weryfikacja po naprawach

- `npm run lint` — zaliczony: 10 plików JS, 19 szablonów Twig oraz wszystkie CSS i ACF JSON,
- `npm run build` — zaliczony; wynikowe pliki nie zawierają nierozwiązanych tokenów Vite,
- `npm run test:smoke` — zaliczony, w tym XSS, błędy formularza/ARIA, health, prywatny REST, blokada logu, favicon i bezpośredni URL motywu,
- `php -l` — zaliczony dla wszystkich własnych plików PHP motywu,
- `composer validate --strict` — zaliczony,
- `docker compose -f docker-compose.production.yml config --quiet` — zaliczony z testowymi wartościami,
- pełny build obrazu `winnica-nowizny:audit-test` — zaliczony,
- przeglądarka: 320, 360, 375, 768 i 1440 px bez poziomego overflow,
- menu mobilne, lightbox i cookies — zaliczone klawiaturą; fokus jest przywracany,
- logi po migracji Timber 2 — brak nowych fatal errors, warningów i deprecated.

**Zaktualizowany werdykt:** kod jest gotowy po uzupełnieniu konfiguracji
produkcyjnej. Nie należy przełączać DNS, dopóki nie zostaną podane i sprawdzone:
SMTP, potwierdzony `WP_ADMIN_EMAIL`, docelowy reverse proxy/TLS, zaufane zakresy
proxy, szyfrowany magazyn kopii oraz odbiorca alertów. Nie są to już błędy kodu,
lecz obowiązkowe dane i usługi zewnętrzne.

## Metoda i zakres weryfikacji

Przeprowadzono:

- odczyt wszystkich 19 plików PHP motywu i lint `php -l`,
- odczyt szablonów Twig, modułów JS i źródeł CSS,
- walidację składni wszystkich 10 plików ACF JSON,
- produkcyjny build Vite,
- sprawdzenie aktywnych wersji WordPressa, PHP i wtyczek przez WP-CLI,
- test działającej strony, polityki prywatności, 404, sitemap, robots.txt, REST API i health endpointu,
- inspekcję DOM, nagłówków, konsoli, obrazów, linków, formularza, lightboxa i metadanych SEO,
- odczyt logów WordPressa i kontenera.

Formularza nie wysłano ponownie w tym audycie, aby nie tworzyć wiadomości ani nie inicjować zewnętrznej wysyłki. Sprawdzono jego kod, nonce, token czasowy, walidację, zapis, routing i bieżący stan SMTP.

Widok desktopowy został sprawdzony w działającej przeglądarce przy szerokości 1280 px. Reguły dla 320, 360 i 768 px sprawdzono w kodzie CSS i DOM; końcowy test na fizycznych viewportach pozostaje pozycją checklisty po naprawach.

---

## Problemy krytyczne

### K1. Wyłączone autoescapowanie Twig umożliwia XSS

1. **Priorytet:** krytyczny
2. **Plik i linia:**  
   - `wp-theme/winnica-nowizny/inc/timber-setup.php:60`  
   - aktywna wtyczka: `/wp-content/plugins/timber-library/lib/Loader.php:160-162`  
   - aktywna wtyczka: `/wp-content/plugins/timber-library/lib/Timber.php:44`  
   - `wp-theme/winnica-nowizny/templates/partials/wizyta-formularz.twig:44-48,82,90,96`  
   - przykłady ACF: `templates/partials/hero.twig:41,49`
3. **Opis:** Timber 1.23.4 ustawia `autoescape => false`, a motyw nie nadpisuje `Timber::$autoescape`. Wartości `old.name`, `old.email`, `old.date` i inne trafiają bezpośrednio do atrybutów HTML. Dane te po błędzie formularza są zapisywane w transient w `inc/contact-form.php:100-107,231-243`, a klucz transientu trafia do adresu URL. `sanitize_text_field()` nie jest escapowaniem atrybutu HTML. Również liczne zwykłe pola ACF są renderowane przez `{{ ... }}` bez ochrony wynikającej z silnika szablonów.
4. **Możliwe skutki na produkcji:** wykonanie kodu JavaScript w przeglądarce osoby otwierającej przygotowany URL, przejęcie sesji administratora odwiedzającego frontend, modyfikacja treści lub eskalacja uprawnień. To dotyczy zarówno danych formularza, jak i potencjalnie złośliwej treści zapisanej przez konto redakcyjne.
5. **Propozycja naprawy:** włączyć `Timber::$autoescape = 'html'` przed konfiguracją katalogów Twig, następnie przejrzeć wszystkie konteksty:
   - tekst HTML pozostawić pod autoescape,
   - atrybuty escapować jako `html_attr`,
   - URL-e przepuszczać przez `esc_url()` lub bezpieczny filtr URL,
   - `|raw` zostawić wyłącznie dla wyników `wp_kses_post()`, `winnica_kses_svg()`, `winnica_kses_map_embed()`, `wp_get_attachment_image()` i `wp_nonce_field()`,
   - po zmianie wykonać test wszystkich szablonów, ponieważ treść WordPressa wymagająca HTML może potrzebować jawnego, uprzednio oczyszczonego `|raw`.

### K2. Publiczny `debug.log` ujawnia informacje techniczne

1. **Priorytet:** krytyczny
2. **Plik i linia:** `docker-compose.yml:23-26`; plik runtime `/var/www/html/wp-content/debug.log`
3. **Opis:** `HEAD /wp-content/debug.log` zwraca `200 OK`, `Content-Length: 5257270`. Log zawiera pełne ścieżki `/var/www/html/...`, wersje bibliotek i historię warningów/deprecated. `WP_DEBUG` jest obecnie wyłączony, ale stary plik pozostał w publicznym katalogu. Konfiguracja wiąże `WP_DEBUG_LOG` bezpośrednio z `WP_DEBUG`, nie blokując dostępu HTTP do pliku.
4. **Możliwe skutki na produkcji:** ujawnienie struktury serwera, wersji zależności, nazw klas, ścieżek i błędów ułatwiających atak. Przy ponownym włączeniu debugowania plik może zacząć ujawniać nowe dane.
5. **Propozycja naprawy:** usunąć obecny log przed migracją, zablokować dostęp HTTP do `wp-content/*.log`, a log produkcyjny kierować poza webroot albo do stdout/stderr kontenera. Na produkcji wymusić `WP_DEBUG=false`, `WP_DEBUG_DISPLAY=false`, `WP_DEBUG_LOG=false`.

---

## Problemy wysokie

### W1. SMTP nie jest skonfigurowany i ostatnia wysyłka zakończyła się błędem

1. **Priorytet:** wysoki
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/smtp.php:19-26`; `.env.example:8-15`; `docker-compose.yml:15-21`
3. **Opis:** hook `phpmailer_init` kończy działanie, gdy `WINNICA_SMTP_HOST` jest pusty. W bieżącym kontenerze host SMTP jest pusty. Opcja `winnica_last_mail_error` zawiera aktualny zapis: `Błędny adres: (From): wordpress@localhost`.
4. **Możliwe skutki na produkcji:** wiadomość zapisze się w panelu, ale właściciel nie otrzyma powiadomienia. Rezerwacje mogą pozostać niezauważone.
5. **Propozycja naprawy:** ustawić komplet `WINNICA_SMTP_*`, użyć nadawcy w poprawnie skonfigurowanej domenie, zweryfikować SPF/DKIM/DMARC, wysłać kontrolowane zgłoszenie i potwierdzić `_contact_email_sent=1` oraz brak nowej wartości `winnica_last_mail_error`.

### W2. Redaktorzy mają dostęp do danych osobowych z formularza

1. **Priorytet:** wysoki
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/contact-form.php:16-36,307-353,403-421`
3. **Opis:** CPT `winnica_message` używa `capability_type => post` i `map_meta_cap => true`. W praktyce role mające standardowe możliwości edycji wpisów mogą otrzymać dostęp do wiadomości, adresów e-mail, telefonów i danych rezerwacji. Widget kokpitu również nie ma własnego sprawdzenia uprawnienia przed wykonaniem zapytań i pokazaniem odnośnika.
4. **Możliwe skutki na produkcji:** nieuzasadniony dostęp do danych osobowych przez redaktorów, naruszenie zasady minimalnych uprawnień i zwiększenie skutków przejęcia konta redakcyjnego.
5. **Propozycja naprawy:** zdefiniować osobny zestaw capabilities dla wiadomości, nadać go wyłącznie administratorowi lub dedykowanej roli obsługi rezerwacji i zabezpieczyć widget, metabox, kolumny oraz zmianę statusu odpowiednim `current_user_can()`.

### W3. Deployment z repozytorium może uruchomić niezbudowany, niedziałający JavaScript

1. **Priorytet:** wysoki
2. **Plik i linia:** `.gitignore:8`; `wp-theme/winnica-nowizny/inc/assets.php:9-33,64-77`
3. **Opis:** `assets/dist` jest ignorowane przez Git. Gdy manifestu nie ma, fallback ładuje `src/js/main.js` przez zwykłe `wp_enqueue_script()`, chociaż plik zawiera instrukcje ES `import`. Skrypt nie ma ustawionego `type="module"`. Jeżeli manifest istnieje, ale jest niepoprawny albo nie ma wpisu `src/js/main.js`, kod nie ładuje ani buildu, ani fallbacku.
4. **Możliwe skutki na produkcji:** błąd składni JS, brak menu mobilnego, zgód, lightboxa, datepickera, opinii i animacji; przy uszkodzonym manifeście możliwy jest również brak stylów.
5. **Propozycja naprawy:** zbudować artefakt w CI/CD i wdrażać kompletny katalog `assets/dist` razem z motywem. Deployment powinien przerwać się, jeśli manifest lub wskazane pliki nie istnieją. Fallback źródłowy usunąć albo ładować poprawnie jako moduł tylko w jednoznacznym trybie deweloperskim.

### W4. Konfiguracja Docker nie jest bezpiecznym, fail-closed profilem produkcyjnym

1. **Priorytet:** wysoki
2. **Plik i linia:** `docker-compose.yml:3,7,11-14,50-51`; `.env.example:1-6`; `setup/SETUP.md:53-62`
3. **Opis:** ten sam compose uruchamia się bez obowiązkowych sekretów, korzystając z fallbacków `winnica_dev_2024` i `root_dev_2024`, publikuje nieszyfrowany port `8080:80` i domyślnie ustawia środowisko `local`. Repozytorium nie zawiera osobnego profilu produkcyjnego, reverse proxy ani wymuszenia TLS. Checklista dokumentacji nie wymusza przerwania startu przy pustych zmiennych.
4. **Możliwe skutki na produkcji:** uruchomienie publicznej instancji na znanych hasłach, HTTP bez TLS, błędny typ środowiska i niewłaściwe zachowanie monitoringu.
5. **Propozycja naprawy:** stworzyć osobny profil/compose produkcyjny, użyć `${VAR:?required}` dla sekretów, nie wystawiać Apache bezpośrednio do Internetu, postawić TLS na zaufanym reverse proxy, ograniczyć porty bazy i jawnie ustawić `WP_ENVIRONMENT_TYPE=production`.

### W5. Obraz kontenera WordPress nie odpowiada działającemu rdzeniowi

1. **Priorytet:** wysoki
2. **Plik i linia:** `docker-compose.yml:3,61`; `setup/install.sh:15-30`
3. **Opis:** compose deklaruje `wordpress:6.7-php8.2-apache`, natomiast działający wolumen zawiera WordPress 7.0.1. WP-CLI zgłasza dostępną aktualizację 7.0.2. Odtworzenie bazy/wolumenu albo start na czystym wolumenie może więc uruchomić inny rdzeń niż ten, na którym wykonano audyt.
4. **Możliwe skutki na produkcji:** niepowtarzalny deployment, konflikt wersji plików i schematu bazy, nieoczekiwany ekran aktualizacji lub regresja po odtworzeniu.
5. **Propozycja naprawy:** po testach zaktualizować WordPress do bieżącej wersji poprawkowej i przypiąć zgodny obraz/digest. Nie traktować mutowanego wolumenu rdzenia jako artefaktu wdrożeniowego.

### W6. Timber 1.23.4 generuje realne deprecated na PHP 8.2, a brak wtyczki kończy front błędem

1. **Priorytet:** wysoki
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/timber-setup.php:3,53-60`; `front-page.php:6,11`; `page.php:6`; `index.php:6`; `404.php:6`; `setup/install.sh:57-59`
3. **Opis:** motyw jawnie korzysta z API Timber 1.x. `debug.log` zawiera serie `Creation of dynamic property Timber\Post... is deprecated` oraz niezgodne typy zwrotne starego Twiga na PHP 8.2. Gdy Timbera zabraknie, `timber-setup.php` tylko dodaje notice i robi `return`, lecz pliki wejściowe nadal bezwarunkowo wywołują `Timber::get_context()`. Bezpośredni test katalogu motywu również wywołał fatal `Class "Timber" not found` w `index.php:6`.
4. **Możliwe skutki na produkcji:** rosnące logi, brak pewności działania po zmianie PHP oraz pełny błąd 500 po nieudanej instalacji lub dezaktywacji zależności.
5. **Propozycja naprawy:** zaplanować migrację do utrzymywanej wersji Timber i odpowiednio zaktualizować API motywu. Do czasu migracji przypiąć dokładnie przetestowane wersje PHP/Timber i dodać kontrolowany guard dla brakującej zależności zamiast dalszego wywoływania klasy.

### W7. Baza i instalator nadal używają adresu lokalnego

1. **Priorytet:** wysoki
2. **Plik i linia:** `setup/install.sh:27`; bieżąca baza: opcje `home`, `siteurl` i pozycje menu `primary`
3. **Opis:** bieżące `home` i `siteurl` to `http://localhost:8080`, a linki menu renderują pełne adresy `http://localhost:8080/#...`. Jest to poprawne lokalnie, ale repozytorium nie ma osobnego kroku produkcyjnej migracji URL.
4. **Możliwe skutki na produkcji:** po skopiowaniu bazy bez poprawnego search-replace canonicale, schema, sitemap, media i menu mogą wskazywać localhost lub HTTP.
5. **Propozycja naprawy:** w kontrolowanym wdrożeniu wykonać serializacyjnie bezpieczne `wp search-replace` z lokalnego URL na docelowy HTTPS, ustawić `home`/`siteurl`, zapisać permalinki, wyczyścić cache i ponownie sprawdzić menu, sitemapę, canonicale i dane strukturalne.

---

## Problemy średnie

### S1. Prywatny CPT `wino` jest publicznie dostępny przez REST

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/cpt-wino.php:21-31,40-44`
3. **Opis:** typ ma `public => false` i `publicly_queryable => false`, ale jednocześnie `show_in_rest => true`. Anonimowy `GET /wp-json/wp/v2/wino` zwraca 200 z listą win, opisami, slugami, datami i identyfikatorami mediów. Tak samo REST włączono dla taksonomii.
4. **Możliwe skutki na produkcji:** niespójność z założeniem one-page, zbędna powierzchnia API i ujawnienie struktury treści.
5. **Propozycja naprawy:** jeżeli Gutenberg dla win nie jest potrzebny, wyłączyć `show_in_rest`. Jeżeli jest potrzebny, ograniczyć endpointy dla niezalogowanych w istniejącym filtrze `rest_endpoints`, pozostawiając dostęp autoryzowanym redaktorom.

### S2. Dane strukturalne godzin otwarcia nie odpowiadają widocznej treści

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/seo.php:188-198`; `setup/seed-homepage.sh:119`
3. **Opis:** widoczny tekst mówi ogólnie „Poza sezonem wakacyjnym — sobota–niedziela 11:00–20:00”. Schema emituje ten harmonogram wyłącznie od kwietnia do czerwca i od września do listopada, pomijając grudzień–marzec. Nie ma potwierdzonej informacji, czy zimą obiekt jest zamknięty.
4. **Możliwe skutki na produkcji:** Google może pokazywać inne godziny niż strona, a klient nie wie, która wersja jest prawidłowa.
5. **Propozycja naprawy:** potwierdzić harmonogram z właścicielem i przechowywać godziny w jednym ustrukturyzowanym źródle. Widoczny tekst i `openingHoursSpecification` powinny być generowane z tych samych danych.

### S3. Cache HTML nie jest unieważniany przez samą zmianę Twig lub statycznego obrazu

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/performance.php:85-94,97-126`; `templates/partials/doswiadczenia.twig:10,31-36`
3. **Opis:** klucz cache zależy od wersji motywu i hasha manifestu Vite, ale nie od rewizji wdrożenia ani zmian szablonów i obrazów spoza bundla. Zmiana samego Twig lub zastąpienie pliku pod tym samym URL może pozostawić stary HTML przez godzinę. Ręczne `?v=20260729` dla jednego zdjęcia jest punktowym obejściem.
4. **Możliwe skutki na produkcji:** po wdrożeniu użytkownicy nadal widzą poprzedni układ lub obraz, mimo że nowe pliki są już na serwerze.
5. **Propozycja naprawy:** włączyć identyfikator commita/release do klucza cache, czyścić transient przy każdym wdrożeniu i stosować hashowane nazwy assetów zamiast ręcznych dat w szablonie.

### S4. Rate limiting opiera się wyłącznie na `REMOTE_ADDR`

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/contact-form.php:64-68,164-168`; `inc/security.php:30-34`
3. **Opis:** formularz i blokada logowania budują klucz z `$_SERVER['REMOTE_ADDR']`. Za reverse proxy lub CDN wartością może być adres proxy wspólny dla wszystkich gości.
4. **Możliwe skutki na produkcji:** kilku użytkowników może wspólnie wyczerpać limit formularza, a próby logowania do tej samej nazwy użytkownika mogą blokować się globalnie.
5. **Propozycja naprawy:** ustalić architekturę produkcyjną. Prawdziwy adres klienta odczytywać z nagłówka tylko wtedy, gdy żądanie pochodzi od zaufanego proxy; najlepiej znormalizować go na warstwie serwera i dopiero potem używać w PHP.

### S5. Błędy formularza nie są opisane przy konkretnych polach

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/templates/partials/wizyta-formularz.twig:14-18,44-48,60,82,90,96`; `inc/contact-form.php:196-228`
3. **Opis:** po walidacji pola dostają `aria-invalid="true"`, ale użytkownik otrzymuje tylko ogólny komunikat „Popraw zaznaczone pola”. Nie ma komunikatów mówiących, czy e-mail ma zły format, wiadomość jest za krótka, data jest poza zakresem itd. Pola nie mają `aria-describedby` wskazującego konkretny błąd.
4. **Możliwe skutki na produkcji:** słabsza dostępność dla czytników ekranu i większa liczba porzuconych formularzy.
5. **Propozycja naprawy:** mapować kody błędów PHP na krótkie komunikaty per pole, renderować je obok kontrolki i łączyć przez `aria-describedby`. Ogólny alert może zostać jako podsumowanie.

### S6. Brak automatycznego, zewnętrznego backupu i monitoringu w projekcie

1. **Priorytet:** średni
2. **Plik i linia:** `setup/backup.ps1:14-28`; `setup/monitor.ps1:1-10`
3. **Opis:** backup jest ręcznym skryptem zapisującym bazę i ZIP w lokalnym katalogu projektu. Monitoring jest pojedynczym wywołaniem health endpointu. Repozytorium nie definiuje harmonogramu, retencji, szyfrowania, kopii off-site ani testu odtworzenia.
4. **Możliwe skutki na produkcji:** utrata bazy lub zdjęć po awarii i brak powiadomienia o niedostępności strony.
5. **Propozycja naprawy:** skonfigurować po stronie hostingu lub infrastruktury automatyczne kopie bazy i uploads z retencją oraz kopią poza serwerem, wykonać próbne odtworzenie i podłączyć health endpoint do zewnętrznego monitora z alertami.

### S7. ACF zawiera dziesięć pól win, których frontend nie używa

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/acf-json/group_wino_details.json:62,84,106,135,173,193,213,251,289,309`; `templates/partials/wina.twig:32-35`
3. **Opis:** frontend odczytuje tylko `rodzaj` i `szczep`. Pola `rocznik`, `alkohol`, `pojemnosc`, `temperatura`, `aromat`, `smak`, `parowanie`, `nagrody`, `dostepne` i `cena` nie są renderowane. `cena` i `dostepne` są dodatkowo sprzeczne z ustalonym charakterem strony prezentacyjnej, a nie sklepu.
4. **Możliwe skutki na produkcji:** redaktor wprowadza dane, które nigdzie się nie pojawiają, i może błędnie oczekiwać funkcji sprzedażowych.
5. **Propozycja naprawy:** usunąć pola bez planowanego zastosowania, zwłaszcza `cena` i `dostepne`, albo jasno opisać grupę jako wewnętrzny katalog niewidoczny na stronie. Nie usuwać wartości z bazy bez kopii.

### S8. Dane seedujące i dokumentacja nie odtwarzają obecnego projektu

1. **Priorytet:** średni
2. **Plik i linia:** `setup/SETUP.md:20,31,36,93`; `setup/install.sh:58-59,80-94,140`; `setup/seed-homepage.sh:34,59-60,122`
3. **Opis:** dokumentacja mówi o sześciu winach, instalator tworzy pięć; wskazuje nieistniejący `seed-acf-meta.sh`; wymienia TikTok i YouTube, których nie ma w Customizerze. Seed zapisuje martwe `historia_signature` i `wines_archive_label`, ustawia `wines_count=6` przy pięciu winach i inną nazwę formularza. Pole `historia_signature` z `acf-json/group_homepage_historia.json:140` nie jest używane, ponieważ szablon renderuje statyczny PNG.
4. **Możliwe skutki na produkcji:** świeże środowisko różni się od audytowanego, a odtworzenie po awarii lub przez inną osobę daje niespójne dane.
5. **Propozycja naprawy:** uaktualnić instrukcję i seed do aktualnej liczby win, nazw pól i komponentów; usunąć odwołania do nieistniejącego skryptu i martwych meta; uruchomić pełny test instalacji na czystym wolumenie.

### S9. Fallback zdjęcia piwnicy jest ciężki i nie ma wariantu AVIF

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/templates/partials/piwnica.twig:14-15`; pliki `assets/images/piwnica-1891*.webp`
3. **Opis:** obrazy mają 103 632 B (480), 402 476 B (960) i 696 758 B (1280). `srcset`, wymiary i lazy loading są poprawne, lecz po ostatniej podmianie zostały tylko WebP. Wariant 1280 jest duży jak na pojedynczy obraz poniżej pierwszego ekranu.
4. **Możliwe skutki na produkcji:** większy transfer i wolniejsze przewijanie/ładowanie na słabszym łączu lub ekranie o wysokim DPR.
5. **Propozycja naprawy:** przygotować wizualnie zweryfikowany AVIF i ewentualnie ponownie dobrać jakość WebP. Zachować obecny `srcset`, rozmiary i fallback.

### S10. Przekierowania bezwarunkowo zasłonią szkice stron po ich publikacji

1. **Priorytet:** średni
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/redirects.php:8-23`; bieżąca baza: szkice `o-nas` i `kontakt`
3. **Opis:** ścieżki `/o-nas/` i `/kontakt/` zawsze dostają 301 do kotwic strony głównej. W bazie istnieją szkice o tych slugach. Publikacja szkicu w panelu nie udostępni strony, ponieważ redirect zadziała wcześniej.
4. **Możliwe skutki na produkcji:** redaktor publikuje stronę, która pozostaje niewidoczna, bez czytelnego błędu.
5. **Propozycja naprawy:** usunąć nieużywane szkice albo warunkować redirect brakiem opublikowanej strony. Decyzję one-page opisać w dokumentacji administracyjnej.

---

## Problemy niskie

### N1. Health endpoint ujawnia typ środowiska i stan indeksowania

1. **Priorytet:** niski
2. **Plik i linia:** `wp-theme/winnica-nowizny/inc/monitoring.php:27-43`
3. **Opis:** publiczny endpoint zwraca m.in. `environment` i `indexable`.
4. **Możliwe skutki na produkcji:** niewielkie ujawnienie informacji konfiguracyjnych.
5. **Propozycja naprawy:** publicznie zwracać wyłącznie minimalny status usługi; szczegóły przenieść do Site Health albo zabezpieczonego endpointu.

### N2. Nagłówki HTTP ujawniają dokładne wersje Apache i PHP

1. **Priorytet:** niski
2. **Plik i linia:** `docker-compose.yml:3`; brak własnej konfiguracji Apache/PHP
3. **Opis:** odpowiedzi zawierają `Server: Apache/2.4.62 (Debian)` i `X-Powered-By: PHP/8.2.28`.
4. **Możliwe skutki na produkcji:** łatwiejsze profilowanie stosu przez automatyczne skanery.
5. **Propozycja naprawy:** wyłączyć `expose_php`, ograniczyć `ServerTokens` i usunąć `X-Powered-By` na warstwie serwera lub reverse proxy.

### N3. Kalendarz i backend inaczej traktują dzisiejszą datę

1. **Priorytet:** niski
2. **Plik i linia:** `wp-theme/winnica-nowizny/src/js/modules/datepicker.js:63-68`; `inc/contact-form.php:217-223`
3. **Opis:** datepicker pozwala wybierać od jutra, natomiast backend odrzuca tylko daty wcześniejsze niż dziś. Dzisiejszą datę można więc wpisać ręcznie i wysłać.
4. **Możliwe skutki na produkcji:** niespójne zasady rezerwacji zależnie od sposobu wprowadzenia daty.
5. **Propozycja naprawy:** potwierdzić regułę biznesową i zastosować identyczną granicę w JS oraz PHP.

### N4. Numer wersji motywu jest niespójny

1. **Priorytet:** niski
2. **Plik i linia:** `wp-theme/winnica-nowizny/style.css:6`; `functions.php:8`
3. **Opis:** nagłówek motywu podaje `1.3.4`, a `WINNICA_VERSION` ma `1.4.0`.
4. **Możliwe skutki na produkcji:** myląca diagnostyka i niejednoznaczne wersjonowanie wydań.
5. **Propozycja naprawy:** utrzymywać jedno źródło wersji, np. czytać ją przez `wp_get_theme()->get('Version')`.

### N5. Drobny martwy kod i brak warunku dla pustego e-maila

1. **Priorytet:** niski
2. **Plik i linia:** `wp-theme/winnica-nowizny/src/js/modules/consent.js:199`; `templates/partials/mobile-contact-bar.twig:2-4`
3. **Opis:** JS szuka `[data-consent-collapse]`, którego nie ma w szablonach. Mobilny pasek warunkuje telefon, ale zawsze renderuje `mailto:{{ site_email }}`.
4. **Możliwe skutki na produkcji:** zbędny selektor i martwy `mailto:` po wyczyszczeniu e-maila w Customizerze.
5. **Propozycja naprawy:** usunąć nieużywany selektor i dodać `{% if site_email %}` analogicznie do telefonu.

### N6. Bezpośredni URL katalogu motywu kończy się błędem 500

1. **Priorytet:** niski
2. **Plik i linia:** `wp-theme/winnica-nowizny/index.php:6`
3. **Opis:** `GET /wp-content/themes/winnica-nowizny/` uruchamia `index.php` poza bootstrapem WordPressa i kończy fatalem `Class "Timber" not found`. Treść błędu nie jest wyświetlana, ale trafia do logu serwera.
4. **Możliwe skutki na produkcji:** zbędne odpowiedzi 500 i możliwość generowania szumu w logach.
5. **Propozycja naprawy:** dodać `defined('ABSPATH') || exit;` w plikach wejściowych motywu albo zablokować bezpośrednie wykonywanie PHP w katalogu motywu na poziomie serwera.

### N7. Ustawienia tożsamości produkcyjnej wymagają potwierdzenia

1. **Priorytet:** niski
2. **Plik i linia:** bieżąca baza `wp_options.site_icon` i `wp_options.admin_email`; fallback odbiorcy w `wp-theme/winnica-nowizny/inc/contact-form.php:286`
3. **Opis:** `site_icon=0`, więc WordPress nie ma skonfigurowanej favikony. `admin_email` różni się od publicznego adresu Winnicy i nie ma potwierdzenia, że jest właściwym adresem operacyjnym klienta.
4. **Możliwe skutki na produkcji:** brak identyfikacji karty/skrótów oraz powiadomienia rdzenia wysyłane do niewłaściwej osoby.
5. **Propozycja naprawy:** ustawić ikonę witryny i potwierdzony, monitorowany adres administracyjny przed przełączeniem DNS.

---

## Elementy sprawdzone pozytywnie

- Wszystkie 19 plików PHP motywu przeszło `php -l`.
- Wszystkie 10 plików ACF JSON jest poprawnym JSON-em.
- `npm run build` zakończył się sukcesem. Wynik: CSS 50,62 kB (gzip 10,40 kB), JS 16,76 kB (gzip 6,19 kB). Vite zgłosił dwa ostrzeżenia o assetach rozwiązywanych w runtime, ale wynikowy katalog nie zawiera nierozwiązanych tokenów `__VITE_ASSET__`.
- Konsola przeglądarki była czysta.
- Strona główna i polityka prywatności zwracają 200, nieistniejąca strona zwraca 404, a przekierowania `/wina/`, `/o-nas/` i `/kontakt/` zwracają 301.
- `wp-sitemap.xml` i `robots.txt` działają; `blog_public=1`.
- Na stronie głównej jest jeden H1, jedno `<main id="main">`, działający skip-link jako pierwszy element interaktywny i brak zduplikowanych ID.
- Po uruchomieniu lazy loadingu wszystkie 20 obrazów załadowało się, żaden nie był uszkodzony i każdy miał `width` oraz `height`.
- Hero używa responsywnego AVIF/WebP, poprawnych wymiarów i źródła dopasowanego do viewportu.
- Nie ma globalnego `overflow-x: hidden`; przy desktopowym teście `scrollWidth === clientWidth`. Celowe poziome przewijanie win i opinii jest zamknięte we własnych kontenerach.
- Fallback bez JS jest poprawnie zaprojektowany: HTML startuje z `no-js`, treści `.reveal` są domyślnie widoczne, a menu ma reguły `.no-js`.
- Menu mobilne ma `aria-expanded`, focus trap, Escape, przywrócenie fokusu i blokadę scrolla. Lightbox ma `role="dialog"`, `aria-modal="true"`, focus trap i poprawne przywrócenie fokusu po Escape.
- Formularz ma nonce, honeypot, podpisany token czasowy, limit prób, walidację i sanityzację, zapisuje wiadomość jako `private` i nie buduje własnych zapytań SQL.
- Status wiadomości jest chroniony nonce i `current_user_can('edit_post', ...)`; problem W2 dotyczy zbyt szerokiego mapowania samych capabilities.
- Brak własnych shortcode'ów i brak surowych zapytań SQL.
- Pola `rodzaj` i `szczep` są zgodne między ACF a `wina.twig`.
- Techniczna logika zgód jest spójna: GA4 nie jest ładowane przed zgodą, wybór jest zapisywany pod `winnica_analytics_consent_v2` na maksymalnie 180 dni, cofnięcie usuwa dostępne cookies `_ga*`, a polityka prywatności opisuje tę samą logikę.
- Google Maps ładuje się automatycznie z `loading="lazy"` i jest opisane w polityce prywatności wraz z możliwym przekazaniem IP do Google.
- Canonical, meta description, Open Graph, Twitter card i JSON-LD są obecne tylko raz i JSON-LD jest poprawnym JSON-em. Wyjątkiem merytorycznym są godziny z S2.
- Nie znaleziono haseł SMTP ani identyfikatora GA w śledzonych plikach. `.env` i lokalne backupy SQL są ignorowane przez Git.

## Najważniejsze poprawki wymagane przed wdrożeniem

1. Włączyć autoescapowanie Timber i poprawić wszystkie konteksty HTML, atrybutów i URL-i.
2. Usunąć publiczny `debug.log`, zablokować pliki logów i wyłączyć debugowanie produkcyjne.
3. Ograniczyć dostęp do CPT wiadomości osobnymi capabilities.
4. Zbudować i zapakować `assets/dist` jako obowiązkowy artefakt wdrożenia.
5. Skonfigurować oraz przetestować SMTP.
6. Przygotować prawdziwy profil produkcyjny: wymagane sekrety, TLS, `WP_ENVIRONMENT_TYPE=production`.
7. Wyrównać obraz WordPressa z testowaną wersją rdzenia i zaktualizować WordPress 7.0.1 → 7.0.2 oraz ACF 6.8.5 → 6.8.6 na stagingu.
8. Ustalić plan migracji z Timber 1.23.4 i do czasu migracji przypiąć przetestowany stos.
9. Wykonać bezpieczny search-replace bazy na docelowy HTTPS i wyczyścić cache.
10. Potwierdzić godziny otwarcia, dane administratora, faviconę, backupy i monitoring.

## Checklista „Gotowość WordPressa do produkcji”

### Kod i zależności

- [ ] K1 naprawione; test XSS dla wartości ACF i błędnie wypełnionego formularza
- [ ] `php -l` wszystkich plików PHP nadal przechodzi
- [ ] produkcyjny build Vite przechodzi
- [ ] `assets/dist/.vite/manifest.json` i wszystkie wskazane assety są w artefakcie
- [ ] brak błędów Twig i JS w logach oraz konsoli
- [ ] WordPress i ACF zaktualizowane oraz przetestowane
- [ ] dokładna wersja Timber/PHP przypięta; plan migracji Timber zapisany

### Konfiguracja i bezpieczeństwo

- [ ] `WP_ENVIRONMENT_TYPE=production`
- [ ] `WP_DEBUG=false`, `WP_DEBUG_DISPLAY=false`, `WP_DEBUG_LOG=false`
- [ ] `debug.log` usunięty; dostęp HTTP do `*.log` zablokowany
- [ ] silne, unikalne sekrety bazy; brak deweloperskich fallbacków
- [ ] TLS i przekierowanie HTTP → HTTPS
- [ ] `home` i `siteurl` wskazują domenę produkcyjną HTTPS
- [ ] search-replace bazy wykonany i zweryfikowany
- [ ] `blog_public=1`; health endpoint zwraca poprawny status dla środowiska production
- [ ] osobne capabilities wiadomości i test roli redaktora
- [ ] wersje PHP/Apache ukryte w nagłówkach
- [ ] prawidłowy `admin_email`, silne hasła i MFA po stronie panelu/hostingu

### Formularz, cookies i usługi zewnętrzne

- [ ] SMTP skonfigurowany; test dostarczenia, SPF/DKIM/DMARC i status wiadomości sprawdzone
- [ ] błędy formularza opisane per pole
- [ ] data minimalna zgodna w JS i PHP
- [ ] świeża sesja nie ładuje GA4 przed zgodą
- [ ] akceptacja ładuje właściwy identyfikator GA4
- [ ] odmowa i cofnięcie zgody działają
- [ ] polityka prywatności odpowiada faktycznym usługom i została zaakceptowana przez właściciela
- [ ] godziny otwarcia w treści i schema.org są identyczne

### SEO, cache i wydajność

- [ ] canonicale, sitemap i robots.txt wskazują produkcyjny HTTPS
- [ ] schema.org zwalidowana po migracji domeny
- [ ] cache wyczyszczony podczas wdrożenia
- [ ] cache używa identyfikatora release albo deployment jawnie czyści transienty
- [ ] obraz piwnicy ponownie zoptymalizowany i sprawdzony wizualnie
- [ ] favicona ustawiona

### Operacje i testy końcowe

- [ ] automatyczny backup bazy i uploads działa
- [ ] wykonano test odtworzenia kopii
- [ ] zewnętrzny monitoring i alerty działają
- [ ] test 320, 375, 768 i 1440 px bez globalnego overflow
- [ ] test menu, lightboxa, cookies, datepickera i formularza wyłącznie klawiaturą
- [ ] test z czytnikiem ekranu dla menu i błędów formularza
- [ ] test `prefers-reduced-motion`
- [ ] test strony z wyłączonym JavaScriptem
- [ ] po testach logi PHP/Apache i konsola są czyste

## Werdykt w chwili audytu (historyczny)

**Niewskazane do wdrożenia przed naprawą błędów.**

Ten werdykt opisuje stan wejściowy z 2026-07-29. Aktualny werdykt po wdrożeniu
znajduje się w sekcji „Stan po wdrożeniu poprawek — 2026-07-30” na początku raportu.

Projekt ma solidną strukturę frontendu, dobrze zbudowany formularz, poprawny mechanizm zgód, sensowne SEO i przemyślaną dostępność. Nie równoważy to jednak dwóch blockerów bezpieczeństwa: wyłączonego autoescapowania z realną ścieżką XSS oraz publicznego logu błędów. Dodatkowo brak działającego SMTP, zbyt szerokie uprawnienia do danych rezerwacji i niepewny proces dostarczania buildu są problemami, które muszą zostać zamknięte przed publikacją.
