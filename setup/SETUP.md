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

1. Uzupełnij wszystkie wymagane zmienne w `.env`: hasła bazy, `WP_URL` z HTTPS,
   `WINNICA_RELEASE`, SMTP, adres administratora i adresy zaufanego reverse proxy.
2. Utwórz sieć reverse proxy i skonfiguruj na nim certyfikat TLS oraz przekierowanie
   HTTP → HTTPS. Kontener WordPressa nie publikuje portu bezpośrednio.
3. Zbuduj i uruchom obraz:

```bash
docker compose -f docker-compose.production.yml build --pull
docker compose -f docker-compose.production.yml up -d
```

4. Po imporcie lokalnej bazy wykonaj serializacyjnie bezpieczną migrację URL:

```bash
docker compose -f docker-compose.production.yml exec \
  -e OLD_WP_URL=http://localhost:8080 wordpress winnica-migrate
```

Skrypt wymaga produkcyjnego `WP_URL`, aktualizuje `home`/`siteurl`, włącza
indeksowanie, odświeża rewrite rules właściwym mechanizmem WordPressa oraz czyści
cache. Nie wpisuj docelowej domeny ręcznie w dumpie SQL.

## Bezpieczeństwo i dane

- `WP_DEBUG`, wyświetlanie błędów i publiczny `debug.log` są wyłączone na produkcji.
- Logi PHP trafiają do stderr kontenera; Apache blokuje dostęp do `*.log`,
  `*.sql`, `*.bak`, plików ukrytych i nie ujawnia wersji PHP/Apache.
- Wiadomości formularza mają oddzielne capabilities przyznawane administratorowi,
  a nie standardowym redaktorom.
- SMTP korzysta tylko ze zmiennych `WINNICA_SMTP_*`. Po uruchomieniu wyślij jedno
  kontrolowane zgłoszenie i potwierdź dostarczenie oraz SPF/DKIM/DMARC.
- `WINNICA_TRUSTED_PROXY_CIDRS` może zawierać wyłącznie adresy rzeczywistego
  reverse proxy/CDN. Bez tej listy nagłówki przekazujące IP są ignorowane.

## Backup i monitoring

`backup.ps1` służy wyłącznie do kopii lokalnej. Produkcja musi mieć automatyczny
backup bazy i wolumenu uploads poza serwerem, szyfrowanie, retencję po stronie
storage i cykliczny test odtworzenia. Skrypt `backup-production.sh` tworzy
zaszyfrowaną kopię i wysyła ją przez `rclone`; uruchamiaj go z cron/systemd timer.

Workflow `.github/workflows/uptime.yml` sprawdza publiczny endpoint zdrowia.
Alerty GitHub nie zastępują alertów operatora hostingu — przypisz odbiorcę
powiadomień przed publikacją.

## Checklista po migracji

- `WP_ENVIRONMENT_TYPE=production`, `blog_public=1`,
- `home`, `siteurl`, canonicale, sitemap i schema używają docelowego HTTPS,
- `/wp-content/debug.log` zwraca 403/404,
- nagłówki nie zawierają wersji PHP/Apache,
- endpoint `/wp-json/winnica/v1/health` zwraca `{"status":"ok"}`,
- formularz zapisuje wiadomość i wysyła e-mail przez SMTP,
- GA4 nie ładuje się przed zgodą,
- favikona, dane administratora i godziny otwarcia są potwierdzone,
- testy 320, 375, 768 i 1440 px oraz klawiatura/czytnik ekranu przechodzą,
- wykonano i odtworzono pierwszą kopię zapasową.

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
