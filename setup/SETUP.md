# Winnica Nowizny — uruchomienie i wdrożenie

## Wymagania lokalne

- Docker Desktop,
- Node.js 18+,
- plik `.env` utworzony na podstawie `.env.example`.

## Środowisko lokalne

```bash
docker compose up -d
docker run --rm -v "$PWD/wp-theme/winnica-nowizny:/app" -w /app composer:2.8 install
docker compose run --rm wpcli sh /setup/install.sh
docker compose run --rm wpcli sh /setup/seed-homepage.sh
```

Strona: `http://localhost:8080`. Instalator tworzy stronę główną, politykę
prywatności, pięć prezentowanych win i menu. Hasło administratora pochodzi z
`WP_ADMIN_PASSWORD`; jeśli go nie podano, instalator generuje je jednorazowo.

ACF 6.8.6 jest instalowane w wersji przypiętej. Timber 2.5.1 i Twig 3.28.0 są
zależnościami motywu zapisanymi w `composer.lock` i działają na PHP 8.2. Starej
wtyczki `timber-library` nie instalujemy.

## Frontend

```bash
cd wp-theme/winnica-nowizny
npm ci
npm run build
```

`assets/dist` jest częścią artefaktu wdrożeniowego. Produkcja bez kompletnego
manifestu Vite odpowiada kontrolowanym błędem 503. Lokalnie motyw może skorzystać
z modułowego fallbacku źródłowego.

## Wdrożenie produkcyjne

Strona idzie na hosting współdzielony vh.pl z dostępem SSH i WP-CLI. Docker jest
środowiskiem lokalnym i nie ma go na serwerze docelowym; pliki `docker-compose.production.yml`
i `Dockerfile.wordpress` zostają w repozytorium na wypadek przeniesienia na VPS,
ale poniższa procedura ich nie używa.

Wymagania po stronie hostingu: **PHP 8.2 lub nowszy** (Timber 2 nie uruchomi się
na starszej wersji), MySQL lub MariaDB, SSH i WP-CLI. Wersję PHP zwykle trzeba
ustawić ręcznie w panelu, bo domyślna na współdzielonych kontach bywa starsza.

Serwerem jest LiteSpeed, nie Apache. Czyta `.htaccess` i jest zgodny składniowo,
więc reguły z `setup/htaccess-production.txt` powinny wejść, ale po wklejeniu
trzeba sprawdzić curl-em, czy nagłówki faktycznie wychodzą. **Wtyczki LiteSpeed
Cache nie instalujemy.** Motyw ma własny cache całych stron, a LSCache stanąłby
przed PHP i serwował dokument z wygasłym nonce formularza, poza zasięgiem
unieważniania z `inc/performance.php`. Redis z tego samego powodu zostaje
wyłączony.

### 1. Przygotowanie paczki lokalnie

`vendor/` jest w `.gitignore`, a `node_modules` nie jedzie na serwer. Zbuduj
jedno i drugie przed wysyłką:

```bash
cd wp-theme/winnica-nowizny && composer install --no-dev --optimize-autoloader && npm ci && npm run build
```

Na serwer trafia katalog motywu **bez** `node_modules`, `src`, `tests` i plików
konfiguracyjnych narzędzi. `assets/dist` i `vendor` są obowiązkowe: motyw czyta
manifest Vite i odmawia startu przy niekompletnym buildzie.

### 2. WordPress i wtyczki

Zainstaluj WordPressa autoinstalatorem z panelu, włącz darmowy certyfikat SSL i
wymuś przekierowanie HTTP → HTTPS. Następnie wgraj przez SFTP:

- motyw do `wp-content/themes/winnica-nowizny`,
- Advanced Custom Fields do `wp-content/plugins`,
- zawartość `uploads/` do `wp-content/uploads`.

**Nie wgrywaj `plugins/timber-library`.** Timber jest zależnością motywu w
`vendor/`, a ta wtyczka jest nieutrzymywana i koliduje z wersją z Composera.
Katalog `plugins/` jest w `.gitignore`, więc wtyczka istnieje tylko lokalnie i
nie ma jej w repozytorium. Nie kopiuj `wp-content/plugins` hurtem na serwer:
przez SFTP idzie wyłącznie ACF.

### 3. Import bazy i migracja adresu

Zrzuć lokalną bazę i zaimportuj ją na serwerze, a potem uruchom migrację przez
SSH z katalogu WordPressa:

```bash
OLD_WP_URL=http://localhost:8080 WP_URL=https://twojadomena.pl WP_ADMIN_EMAIL=kontakt@twojadomena.pl sh setup/migrate-production.sh
```

Skrypt wymusza HTTPS w `WP_URL`, robi serializacyjnie bezpieczny search-replace,
ustawia `home`/`siteurl` i adres administratora, włącza indeksowanie, odświeża
rewrite rules i czyści cache. Nie podmieniaj domeny ręcznie w dumpie SQL, bo
zepsujesz zserializowane wartości w `wp_options`.

### 4. Konfiguracja środowiska

Na współdzielonym hostingu nie ma `.env` z compose, więc stałe trafiają do
`wp-config.php` powyżej linii `That's all, stop editing`:

```php
define('WP_ENVIRONMENT_TYPE', 'production');
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
putenv('WINNICA_SMTP_HOST=smtp.twojadomena.pl');
putenv('WINNICA_SMTP_PORT=587');
putenv('WINNICA_SMTP_USER=kontakt@twojadomena.pl');
putenv('WINNICA_SMTP_PASS=...');
putenv('WINNICA_SMTP_FROM_EMAIL=kontakt@twojadomena.pl');
putenv('WINNICA_SMTP_FROM_NAME=Winnica Nowizny');
```

Adres nadawcy musi należeć do domeny strony. Wysyłka z cudzej domeny, na przykład
z Gmaila przez serwer winnicy, jest przez odbiorców traktowana jak podszywanie i
ląduje w spamie.

`WINNICA_TRUSTED_PROXY_CIDRS` zostaw puste. Ma sens wyłącznie wtedy, gdy przed
WordPressem stoi własny reverse proxy albo CDN; bez tego nagłówki z adresem IP
są ignorowane i limit wysyłek formularza opiera się na `REMOTE_ADDR`, co na tym
hostingu jest zachowaniem poprawnym.

## Bezpieczeństwo i dane

- `WP_DEBUG`, wyświetlanie błędów i publiczny `debug.log` są wyłączone na produkcji.
- Reguły blokujące `*.log`, `*.sql`, `*.bak` i pliki ukryte siedzą w
  `setup/apache-security.conf`, montowanym tylko lokalnie. Na hostingu
  współdzielonym gotowy odpowiednik leży w `setup/htaccess-production.txt`;
  wklej go do `.htaccess` **poniżej** bloku `# END WordPress`, bo WordPress
  nadpisuje swoją część przy każdym zapisie permalinków.
- Wiadomości formularza mają oddzielne capabilities przyznawane administratorowi,
  a nie standardowym redaktorom.
- SMTP korzysta tylko ze zmiennych `WINNICA_SMTP_*`. Po uruchomieniu wyślij jedno
  kontrolowane zgłoszenie i potwierdź dostarczenie oraz SPF/DKIM/DMARC.
- `WINNICA_TRUSTED_PROXY_CIDRS` może zawierać wyłącznie adresy rzeczywistego
  reverse proxy/CDN. Bez tej listy nagłówki przekazujące IP są ignorowane.

## Backup i monitoring

`backup.ps1` służy wyłącznie do kopii lokalnej. Na produkcji backup robi vh.pl:
dwa razy dziennie, retencja 31 dni, baza i poczta. To pokrywa najczęstszy scenariusz,
czyli zepsucie treści albo nieudaną aktualizację.

Czego ten backup nie pokrywa: utraty konta u dostawcy. Źródło strony jest w gicie,
więc realnie zagrożone są tylko baza i `uploads`. Raz na jakiś czas pobierz jedno
i drugie na dysk lokalny; przy stronie zmieniającej się kilka razy w roku to
wystarczy. Skrypt `backup-production.sh` zakłada `age` i `rclone` na własnym
serwerze i na tym hostingu nie ma zastosowania.

Sprawdź w panelu, czy odtworzenie kopii faktycznie działa, **zanim** będzie
potrzebne. Backup nieprzetestowany to założenie, nie zabezpieczenie.

Workflow `.github/workflows/uptime.yml` sprawdza publiczny endpoint zdrowia i jest
wyłączony z harmonogramu. Włącz go po wdrożeniu albo skasuj i polegaj na
monitoringu hostingu; nierozstrzygnięty zostawia fałszywe poczucie nadzoru.

## Checklista po migracji

- PHP na serwerze to 8.2 lub nowszy,
- `WP_ENVIRONMENT_TYPE=production`, `blog_public=1`,
- `home`, `siteurl`, canonicale, sitemap i schema używają docelowego HTTPS,
- migracja adresów zakończyła się przebiegiem kontrolnym bez trafień,
- `/wp-content/debug.log` zwraca 403/404,
- wtyczka `timber-library` **nie** jest wgrana ani aktywna,
- aktywna jest tylko wtyczka ACF, nic nie cache'uje stron przed PHP,
- endpoint `/wp-json/winnica/v1/health` zwraca `{"status":"ok"}`,
- formularz zapisuje wiadomość i wysyła e-mail przez SMTP, a adres nadawcy jest
  w domenie strony i ma poprawne SPF, DKIM oraz DMARC,
- `winnica_last_mail_error` zniknęło z `wp_options` po pierwszej udanej wysyłce,
- konto administratora ma własny login i adres w domenie strony,
- favikona i dane kontaktowe są potwierdzone,
- godziny otwarcia w schema zgadzają się z sekcją kontaktu,
- testy 320, 375, 768 i 1440 px oraz klawiatura/czytnik ekranu przechodzą,
- wykonano i **odtworzono** pierwszą kopię zapasową.

Rozpisana wersja operacyjna, z krokami zależnymi od konkretnego konta u
dostawcy, jest trzymana poza repozytorium.

## Utrzymanie

```bash
# Lokalna kopia
.\setup\backup.ps1

# Lokalny health check
.\setup\monitor.ps1

# Obrazy AVIF i favikona
python scripts/generate_production_assets.py
```

Przed aktualizacją WordPressa, ACF, Timbera, Twig lub PHP wykonaj kopię i test na
stagingu. Timber i Twig aktualizuj przez Composer razem z plikiem blokady.
