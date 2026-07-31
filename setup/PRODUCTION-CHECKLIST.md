# Checklista wdrożeniowa: czynności na serwerze produkcyjnym

Pełny opis procedury jest w [SETUP.md](SETUP.md). Ten plik jest listą kontrolną
do odhaczania w trakcie wdrożenia, w kolejności wykonywania.

Paczkę motywu budujesz **lokalnie** przez `sh setup/build-release.sh`. Poniższa
lista zaczyna się w momencie, gdy masz gotowy plik `dist/winnica-nowizny-*.zip`
i dostęp do panelu hostingu.

## 0. Zanim zaczniesz

- [ ] domena `winnicanowizny.pl` wskazuje na serwery nazw vh.pl,
- [ ] PHP na koncie ustawione na 8.2 lub nowsze (Timber 2 nie wystartuje niżej),
- [ ] wiesz, czy konto ma dostęp SSH; jeśli nie, patrz krok 3b,
- [ ] hasło do skrzynki `kontakt@winnicanowizny.pl` masz w menedżerze haseł,
      a nie w repozytorium, notatniku ani historii poleceń.

## 1. Certyfikat i przekierowanie

- [ ] WordPress zainstalowany autoinstalatorem z panelu,
- [ ] włączony darmowy certyfikat SSL,
- [ ] wymuszone przekierowanie HTTP na HTTPS,
- [ ] `https://winnicanowizny.pl` otwiera się bez ostrzeżenia przeglądarki,
- [ ] `http://winnicanowizny.pl` przekierowuje kodem 301,
- [ ] to samo sprawdzone dla wariantu z `www`.

HSTS zostawiasz na później. Krok 9.

## 2. Pliki

Przez SFTP:

- [ ] zawartość ZIP-a do `wp-content/themes/winnica-nowizny`,
- [ ] Advanced Custom Fields (wersja darmowa) do `wp-content/plugins`,
- [ ] lokalne `uploads/` do `wp-content/uploads`,
- [ ] motyw aktywowany, ACF aktywne,
- [ ] **`timber-library` nie jest wgrane ani aktywne**. Timber jedzie w
      `vendor/` motywu, stara wtyczka koliduje z tą wersją,
- [ ] żadna inna wtyczka nie jest aktywna.

Sprawdź, że `wp-content/themes/winnica-nowizny/vendor/autoload.php` oraz
`assets/dist/.vite/manifest.json` faktycznie są na serwerze. Bez nich motyw
odpowiada kontrolowanym błędem 503, co jest zachowaniem celowym.

## 3a. Baza i migracja adresów, wariant z SSH

- [ ] lokalny zrzut bazy zaimportowany na serwer (phpMyAdmin albo `mysql <`),
- [ ] migracja uruchomiona z katalogu głównego WordPressa:

```bash
OLD_WP_URL=http://localhost:8080 WP_URL=https://winnicanowizny.pl WP_ADMIN_EMAIL=kontakt@winnicanowizny.pl sh setup/migrate-production.sh
```

Skrypt robi `wp search-replace --precise --skip-columns=guid`, czyli podmianę
świadomą serializacji, ustawia `home`, `siteurl`, `admin_email`, `blog_public`,
odświeża rewrite rules i czyści cache.

- [ ] `wp option get home` zwraca adres HTTPS.

## 3b. Baza i migracja adresów, wariant bez SSH

Jeżeli plan nie daje SSH ani WP-CLI:

- [ ] zrzut zaimportowany przez phpMyAdmin **bez żadnych ręcznych podmian**,
- [ ] `home` i `siteurl` ustawione tymczasowo w phpMyAdmin (to jedyne dwa
      pola, które są zwykłymi łańcuchami, a nie tablicą zserializowaną),
- [ ] zainstalowana wtyczka **Better Search Replace**, podmiana
      `http://localhost:8080` na `https://winnicanowizny.pl` na wszystkich
      tabelach, z zaznaczonym trybem obsługi serializacji, bez kolumny `guid`,
- [ ] wtyczka **dezaktywowana i usunięta** zaraz po migracji,
- [ ] Ustawienia → Bezpośrednie odnośniki zapisane ponownie (odświeża reguły).

**Czego nie robić w żadnym wariancie:** nie otwieraj dumpu SQL w edytorze i nie
podmieniaj w nim adresu szukaj-zamień. `wp_options` trzyma `theme_mods` jako
tablicę zserializowaną z prefiksami długości. Nowy adres ma inną długość niż
stary, prefiks przestaje się zgadzać i PHP odrzuca całą tablicę. Efekt: telefon,
e-mail, adres i linki społecznościowe znikają ze strony naraz.

## 4. wp-config.php

Wpisy idą **powyżej** linii `That's all, stop editing`:

```php
define('WP_ENVIRONMENT_TYPE', 'production');
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
putenv('WINNICA_SMTP_HOST=...');
putenv('WINNICA_SMTP_PORT=587');
putenv('WINNICA_SMTP_USER=kontakt@winnicanowizny.pl');
putenv('WINNICA_SMTP_PASS=...');
putenv('WINNICA_SMTP_FROM_EMAIL=kontakt@winnicanowizny.pl');
putenv('WINNICA_SMTP_FROM_NAME=Winnica Nowizny');
```

- [ ] host i port SMTP wzięte z panelu vh.pl, nie zgadywane,
- [ ] hasło wklejone **tylko tutaj**, na serwerze,
- [ ] `WINNICA_TRUSTED_PROXY_CIDRS` **niewpisane**. Ma sens wyłącznie przy
      własnym reverse proxy albo CDN. Puste znaczy, że limity formularza liczą
      się po `REMOTE_ADDR`, i na tym hostingu tak ma być,
- [ ] plik ma uprawnienia 640 albo 600,
- [ ] w panelu WordPressa **zniknął** żółty pasek o nieskonfigurowanym SMTP.

Ten plik nigdy nie trafia do repozytorium. W `.gitignore` nie ma go dlatego, że
w ogóle nie ma go w drzewie projektu: powstaje na serwerze.

## 5. .htaccess

- [ ] zawartość `setup/htaccess-production.txt` wklejona do `.htaccess` w
      katalogu głównym, **poniżej** bloku `# END WordPress`,
- [ ] blok PHP dla uploadów wklejony do osobnego `.htaccess` w
      `wp-content/uploads/` (w pliku źródłowym jest zakomentowany),
- [ ] `https://winnicanowizny.pl/wp-content/debug.log` zwraca 403 albo 404,
- [ ] `https://winnicanowizny.pl/xmlrpc.php` zwraca 403.

## 6. Poczta

W DNS domeny:

- [ ] SPF według instrukcji vh.pl,
- [ ] DKIM włączony i klucz opublikowany,
- [ ] DMARC, na start `p=none`, żeby zobaczyć raporty zanim zaczniesz odrzucać.

Potem jedno kontrolowane zgłoszenie przez formularz na stronie:

- [ ] wiadomość pojawiła się w panelu w Wiadomości i rezerwacje,
- [ ] e-mail dotarł na `kontakt@winnicanowizny.pl`,
- [ ] trafił do Odebranych, nie do spamu,
- [ ] `Reply-To` działa, odpowiedź idzie na adres nadawcy,
- [ ] w bazie **nie ma** już opcji `winnica_last_mail_error`. Motyw kasuje ją
      po pierwszej udanej wysyłce; jeśli została, wysyłka nie działa,
- [ ] wiadomość testowa usunięta z panelu na trwałe, razem z koszem.

Adres nadawcy musi być w domenie strony. Wysyłka „od" Gmaila przez serwer
winnicy wygląda dla odbiorcy jak podszywanie i ląduje w spamie.

## 7. Weryfikacja produkcyjna

- [ ] strona główna ładuje się i wygląda jak lokalnie,
- [ ] `curl -sI https://winnicanowizny.pl | grep -i x-winnica-cache` **nic nie
      zwraca**. Nagłówek diagnostyczny jest widoczny tylko poza produkcją,
- [ ] obecne są `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
      i `Permissions-Policy`,
- [ ] `/wp-json/winnica/v1/health` zwraca `{"status":"ok"}`,
- [ ] `/wp-json/wp/v2/users` nie zwraca listy użytkowników,
- [ ] `/sitemap.xml` i `/robots.txt` używają docelowego HTTPS,
- [ ] źródło strony: canonical, Open Graph i JSON-LD bez `localhost`,
- [ ] strona 404 pokazuje własny szablon,
- [ ] menu, kotwice sekcji, galeria, lightbox i mapa działają,
- [ ] telefon, e-mail i adres w stopce zgadzają się z rzeczywistymi,
- [ ] testy na 320, 375, 768 i 1440 px,
- [ ] przejście po stronie samą klawiaturą: Tab, Escape w menu i lightboxie,
- [ ] Google Search Console: domena potwierdzona, sitemap zgłoszona.

## 8. Panel

- [ ] hasło administratora zmienione na własne, silne, w menedżerze haseł,
- [ ] login administratora **nie** brzmi `admin`,
- [ ] `admin_email` to `kontakt@winnicanowizny.pl` i skrzynka jest czytana,
- [ ] w Wiadomościach i rezerwacjach nie ma żadnych wpisów testowych, również
      w koszu,
- [ ] automatyczne aktualizacje rdzenia włączone w panelu hostingu albo
      w WordPressie, ale nie w obu naraz.

## 9. HSTS, dopiero po tygodniu

Nie włączaj razem z resztą. Nagłówek jest nieodwracalny po stronie przeglądarki:
jeśli certyfikat przestanie działać, a przeglądarka pamięta rok, przez rok nie
wejdzie na stronę.

- [ ] minął tydzień stabilnego działania na HTTPS,
- [ ] certyfikat odnowił się automatycznie albo wiesz, kiedy to zrobi,
- [ ] odkomentowany blok z `max-age=300` z `setup/htaccess-production.txt`,
- [ ] po kolejnym tygodniu podniesione do `max-age=31536000; includeSubDomains`.

Listy preload nie zgłaszamy. Wypisanie się z niej zajmuje miesiące.

## 10. Kopia zapasowa

- [ ] backup vh.pl potwierdzony w panelu (dwa razy dziennie, retencja 31 dni),
- [ ] pobrany na dysk lokalny zrzut bazy i `uploads` z działającej produkcji,
- [ ] **odtworzenie kopii przetestowane**, zanim będzie potrzebne. Backup
      nieprzetestowany to założenie, nie zabezpieczenie.

## Po wdrożeniu

- [ ] `.github/workflows/uptime.yml`: włączony z harmonogramem albo skasowany.
      Zostawiony w zawieszeniu daje fałszywe poczucie nadzoru.
