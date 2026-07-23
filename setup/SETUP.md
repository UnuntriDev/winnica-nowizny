# Winnica Nowizny — Setup Guide

## Wymagania
- Docker Desktop (Windows/Mac)
- Node.js 18+ (dla Vite build)

## Szybki start

### 1. Uruchom Docker
```bash
cd winnica-nowizny
docker compose up -d
```
WordPress dostępny na `http://localhost:8080`

Przed uruchomieniem produkcyjnym skopiuj `.env.example` do `.env` i ustaw silne hasła bazy, administratora oraz dane SMTP. Plik `.env` jest ignorowany przez Git.

### 2. Zainstaluj WordPress + demo content
```bash
docker compose run --rm wpcli sh /setup/install.sh
```

## Utrzymanie i bezpieczeństwo

- Formularz zapisuje wiadomości w panelu **Wiadomości** i obsługuje statusy: Nowa, Skontaktowano się, Zarezerwowano, Zamknięta i Spam.
- SMTP korzysta wyłącznie ze zmiennych `WINNICA_SMTP_*` w `.env`; hasło nie trafia do WordPressa ani repozytorium.
- Endpoint monitoringu: `/wp-json/winnica/v1/health`.
- Automatyczne aktualizacje rdzenia bezpieczeństwa, pluginów i motywu są włączone. Przed większymi zmianami wykonaj `setup/backup.ps1`.
- Logowanie jest blokowane na 30 minut po pięciu błędnych próbach z tego samego adresu i loginu.
- Analityka GA4 pozostaje wyłączona bez poprawnego `WINNICA_GA_ID` i uruchamia się dopiero po zgodzie użytkownika.
Instaluje WP (pl_PL), aktywuje motyw, tworzy strony, 6 win, menu.
Pluginy: ACF (free), Timber, CF7, Yoast SEO.

### 3. Seed danych
```bash
docker compose run --rm wpcli sh /setup/seed-acf-meta.sh
docker compose run --rm wpcli sh /setup/seed-homepage.sh
```

### 4. Vite (CSS/JS development)
```bash
cd wp-theme/winnica-nowizny
npm install
npm run dev    # development
npm run build  # production build → assets/dist/
```

## Logowanie
- **URL:** http://localhost:8080/wp-admin
- **Login:** admin
- **Hasło:** ustaw `WP_ADMIN_PASSWORD` przed instalacją lub zachowaj hasło wygenerowane przez instalator

## Architektura (bez ACF PRO)

| Potrzeba | Rozwiązanie |
|----------|-------------|
| Sekcje homepage | 9 osobnych grup ACF z toggle show/hide |
| Listy (stats, karty) | Stałe numerowane pola (stat_1, card_1...) |
| Ustawienia globalne | WordPress Customizer (Wygląd → Dostosuj → Winnica) |
| Pola CPT Wino | ACF field group z Tabs |

## Struktura projektu
```
winnica-nowizny/
├── docker-compose.yml
├── setup/              # Skrypty instalacyjne
├── plugins/            # Pluginy WP (gitignored)
├── uploads/            # Media (gitignored)
└── wp-theme/
    └── winnica-nowizny/  # ← Motyw WordPress
        ├── acf-json/     # 10 grup ACF (auto-sync)
        ├── inc/          # PHP modules (7 plików)
        ├── src/css/      # 12 plików CSS z tokenami
        ├── src/js/       # ES modules
        ├── templates/    # Twig (base + partials)
        └── vite.config.js
```

## Customizer (ustawienia globalne)
Wygląd → Dostosuj → Winnica Nowizny:
- **Dane kontaktowe** — telefon, email, adres, GPS, godziny
- **Social Media** — Facebook, Instagram, TikTok, YouTube
- **Stopka** — opis

## Przydatne komendy
```bash
# WP-CLI
docker compose run --rm wpcli wp cache flush
docker compose run --rm wpcli wp db export /setup/backup.sql

# Pełna kopia bazy, motywu, uploadów i pluginów (PowerShell)
.\setup\backup.ps1

# Test endpointu monitoringu
.\setup\monitor.ps1

# Warianty WebP/AVIF statycznych obrazów
docker compose run --rm --entrypoint wp wpcli eval-file /setup/optimize-images.php --allow-root

# Reset
docker compose down -v
docker compose up -d
docker compose run --rm wpcli sh /setup/install.sh
```
