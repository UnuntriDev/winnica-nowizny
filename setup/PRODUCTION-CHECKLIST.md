# Checklista wdrożeniowa: czynności na serwerze produkcyjnym

Pełny opis procedury jest w [SETUP.md](SETUP.md). Ten plik jest listą kontrolną
do odhaczania w trakcie wdrożenia, w kolejności wykonywania.

Paczkę motywu budujesz **lokalnie** przez `sh setup/build-release.sh`. Poniższa
lista zaczyna się w momencie, gdy masz gotowy plik `dist/winnica-nowizny-*.zip`
i dostęp do panelu hostingu.

**Docelowy hosting:** vh.pl, plan Wizytówka. Ze specyfikacji planu wynika, co
poniżej jest przesądzone, a co wymaga ustawienia: SSH, SFTP i WP-CLI są w
pakiecie, więc migracja idzie ścieżką z WP-CLI. Serwerem jest **LiteSpeed, nie
Apache**, PHP sięga 8.5, a backup robi się dwa razy dziennie z retencją 31 dni.

## 0. Zanim zaczniesz

- [ ] domena `winnicanowizny.pl` wskazuje na serwery nazw vh.pl,
- [ ] PHP na koncie **ustawione ręcznie** na 8.2 lub nowsze. Plan oferuje wersje
      od 5.6 do 8.5, a domyślna bywa starsza; Timber 2 na niej nie wystartuje,
- [ ] SSH działa: `ssh uzytkownik@serwer` wpuszcza, a `wp --info` odpowiada,
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
- [ ] sprawdzone, **co dołożył instalator 1-click**. Hostingi z LiteSpeed często
      wgrywają LSCache razem z WordPressem, więc lista wtyczek po instalacji
      może nie być pusta: `wp plugin list`,
- [ ] żadna inna wtyczka nie jest aktywna.

Sprawdź, że `wp-content/themes/winnica-nowizny/vendor/autoload.php` oraz
`assets/dist/.vite/manifest.json` faktycznie są na serwerze. Bez nich motyw
odpowiada kontrolowanym błędem 503, co jest zachowaniem celowym.

### Czego nie włączać, mimo że hosting to reklamuje

- [ ] **LiteSpeed Cache: nie instalować.** Motyw ma własny cache całych stron na
      transientach, z TTFB rzędu 0,35 s. LSCache dołożyłby drugą warstwę
      **przed PHP**, poza zasięgiem logiki motywu. Trzy skutki: formularz
      dostaje nonce i podpisany token w HTML, więc serwowanie tego samego
      dokumentu po wygaśnięciu nonce kończy się odpowiedzią `contact=security`
      zamiast podziękowania; unieważnianie cache przy zapisie wina albo strony
      przestaje cokolwiek znaczyć, bo LSCache dalej wydaje starą wersję;
      diagnostyka przestaje działać, bo żądanie nie dociera do PHP. Bez wtyczki
      serwer nie cache'uje dynamicznego PHP i nic się nie dubluje.
- [ ] **Redis: zostawić wyłączony.** Kolejna warstwa bez zysku przy stronie,
      która renderuje się w 0,35 s. Transienty motywu działają na bazie.

Imunify360 działa po stronie serwera i potrafi przenieść plik do kwarantanny.
Normalnego kodu zwykle nie rusza, ale jeśli po wgraniu strona nagle zwróci 500
albo zniknie plik motywu, to jest pierwsze miejsce do sprawdzenia w panelu.

## 3. Baza i migracja adresów

Plan daje SSH i WP-CLI, więc to jest ścieżka domyślna.

- [ ] zrzut `setup/backups/produkcja-import-*.sql` wgrany na serwer
      i zaimportowany (phpMyAdmin albo `mysql <`),
- [ ] migracja uruchomiona przez SSH, z katalogu głównego WordPressa:

```bash
OLD_WP_URL=http://localhost:8080 WP_URL=https://winnicanowizny.pl WP_ADMIN_EMAIL=kontakt@winnicanowizny.pl sh setup/migrate-production.sh
```

Skrypt robi `wp search-replace --precise --skip-columns=guid`, czyli podmianę
świadomą serializacji, ustawia `home`, `siteurl`, `admin_email` i `blog_public`,
odświeża rewrite rules, czyści cache, a na końcu powtarza to samo wyszukiwanie
jako `--dry-run`.

- [ ] przebieg kontrolny na końcu wypisał **same zera**. Jeśli nie, coś zostało
      i migracja nie jest skończona,
- [ ] `wp option get home` zwraca adres HTTPS,
- [ ] w wypisanych adresach kont zobaczyłeś, na co wskazuje konto administratora.
      `admin_email` to opcja, a konto ma własny adres i skrypt go nie rusza.

**Czego nie robić:** nie otwieraj dumpu SQL w edytorze i nie podmieniaj w nim
adresu szukaj-zamień. Zserializowane wartości w bazie mają prefiksy długości.
Nowy adres jest dłuższy od `http://localhost:8080`, więc prefiks przestaje się
zgadzać i PHP odrzuca całą wartość, zwykle po cichu.

<details>
<summary>Gdyby SSH jednak nie działało</summary>

- [ ] zrzut zaimportowany przez phpMyAdmin **bez żadnych ręcznych podmian**,
- [ ] `home` i `siteurl` ustawione w phpMyAdmin (to jedyne dwa pola, które są
      zwykłymi łańcuchami, a nie tablicą zserializowaną),
- [ ] zainstalowana wtyczka **Better Search Replace**, podmiana
      `http://localhost:8080` na `https://winnicanowizny.pl` na wszystkich
      tabelach, z zaznaczonym trybem obsługi serializacji, bez kolumny `guid`,
- [ ] wtyczka **dezaktywowana i usunięta** zaraz po migracji,
- [ ] Ustawienia → Bezpośrednie odnośniki zapisane ponownie (odświeża reguły).

</details>

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

Reguły pisane były pod Apache, a tu stoi LiteSpeed. Składniowo jest zgodny i
czyta `.htaccess`, ale to trzeba sprawdzić, a nie założyć.

- [ ] zawartość `setup/htaccess-production.txt` wklejona do `.htaccess` w
      katalogu głównym, **poniżej** bloku `# END WordPress`,
- [ ] blok PHP dla uploadów wklejony do osobnego `.htaccess` w
      `wp-content/uploads/` (w pliku źródłowym jest zakomentowany),
- [ ] `https://winnicanowizny.pl/wp-content/debug.log` zwraca 403 albo 404,
- [ ] `https://winnicanowizny.pl/xmlrpc.php` zwraca 403,
- [ ] nagłówki faktycznie wychodzą:

```bash
curl -sI https://winnicanowizny.pl/ | grep -iE "x-frame|x-content-type|referrer|permissions"
```

Powinny być cztery linie. Motyw wystawia je z PHP, więc ich brak oznacza
poważniejszy problem niż `.htaccess`. Jeśli natomiast **strona zwraca 500 zaraz
po wklejeniu reguł**, winny jest któryś blok: zacznij od zdjęcia opakowania
`<IfModule mod_headers.c>` i zostawienia samych dyrektyw `Header`, bo to
najczęstsza różnica między Apache a LiteSpeed.

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
- [ ] `curl -sI https://winnicanowizny.pl | grep -i litespeed-cache` też **nic
      nie zwraca**. Gdyby coś się pojawiło, znaczy że LSCache jednak działa i
      cache'uje stronę przed PHP, ze skutkami opisanymi w kroku 2,
- [ ] cache motywu działa: drugie żądanie pod rząd jest wyraźnie szybsze od
      pierwszego (`curl -s -o /dev/null -w "%{time_total}\n"` dwa razy),
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
      w koszu.

### Automatyczne aktualizacje: wybierz jedno miejsce

Plan Wizytówka sam aktualizuje rdzeń i wtyczki, a motyw ustawia to samo po
swojej stronie w `inc/security.php`. Dwa niezależne mechanizmy aktualizujące te
same pliki to przepis na aktualizację wykonaną w połowie.

- [ ] decyzja podjęta i zapisana: aktualizacje robi **hosting** albo
      **WordPress**, nie oba naraz.

Sensowniej zostawić to hostingowi: działa poza ruchem WordPressa i ma backup
dwa razy dziennie pod ręką. W tym wariancie trzeba zdjąć filtry z motywu:

```php
add_filter('auto_update_plugin', '__return_true');
add_filter('auto_update_theme', '__return_true');
```

Motyw i tak jest odcięty od aktualizacji nagłówkiem `Update URI: false`, więc
drugi filtr nic nie robi. Pierwszy dotyczy wyłącznie ACF, bo Timber i Twig
siedzą w `vendor/` i WordPress o nich nie wie; te aktualizuje się przez
Composer i przebudowanie paczki.

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
