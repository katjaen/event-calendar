# Event Calendar

Prosty plugin WordPress do tworzenia i wyświetlania wydarzeń. Obsługuje własny typ wpisu (CPT), pola meta, blok Gutenberga, shortcode oraz REST API.

**Wersja:** 0.2.1  
**Autor:** Katarzyna Niklas  
**Licencja:** GPL-2.0+  
**Text Domain:** `event-calendar`

---

## Funkcje

- Rejestruje typ wpisu `event` z taksonomami `event_category` i `event_location`
- Panel boczny w edytorze Gutenberg do oznaczania dowolnego wpisu jako wydarzenie
- Blok `event-calendar/calendar` z konfiguracją widoku i filtrów
- Shortcode `[event_calendar]`
- REST API: `GET /wp-json/event-calendar/v1/events`
- Obsługa widoków: miesięczny, tygodniowy, dzienny (TUI Calendar 2.x)
- Wsparcie dla trybu ciemnego (light-dark CSS) i alternatywnych schematów kolorów (ACSS)
- Internacjonalizacja (i18n), gotowa wersja `pl_PL`

---

## Wymagania

- WordPress 6.0+
- PHP 7.4+
- TUI Calendar 2.1.3 (ładowany automatycznie z CDN przy użyciu shortcode/bloku)

---

## Instalacja

1. Skopiuj folder `event-calendar` do `/wp-content/plugins/`.
2. Aktywuj plugin w panelu administracyjnym WordPress.
3. Przejdź do **Wydarzeń** w menu, aby dodać pierwsze wpisy.

---

## Struktura katalogów

```
event-calendar/
├── event-calendar.php          # Główny plik pluginu (CPT, meta, REST API, shortcode)
├── inc/
│   └── query-builder.php       # Wspólna logika budowania zapytań WP_Query
├── blocks/
│   └── calendar/
│       ├── block.json          # Definicja bloku Gutenberg
│       ├── editor.js           # Komponent edytora (bez buildu)
│       ├── render.php          # Renderowanie bloku (dynamic block)
│       └── index.php           # Rejestracja bloku
├── assets/
│   ├── css/
│   │   ├── calendar.css        # Style kalendarza (CSS vars, responsywność)
│   │   └── toastui-calendar.min.css
│   └── js/
│       ├── calendar-init.js    # Inicjalizacja TUI Calendar, nawigacja, motywy
│       └── gutenberg-event-sidebar.js  # Panel boczny edytora
└── languages/
    ├── event-calendar.pot
    ├── event-calendar-pl_PL.po
    └── event-calendar-pl_PL.mo
```

---

## Oznaczanie wpisów jako wydarzenie

Każdy publiczny typ wpisu (poza stronami) może być oznaczony jako wydarzenie. W edytorze Gutenberg po prawej stronie pojawi się panel **Event Details** z polami:

| Pole                 | Opis                                                     |
| -------------------- | -------------------------------------------------------- |
| Include in Calendar? | Przełącznik — włącza wpis do kalendarza                  |
| All day              | Wydarzenie całodniowe (tylko data, bez godziny)          |
| Start Date & Time    | Data i godzina rozpoczęcia                               |
| End Date & Time      | Data i godzina zakończenia                               |
| Location             | Lokalizacja (tekst, max 255 znaków)                      |
| Color                | Kolor oznaczenia na kalendarzu (paleta lub własny kolor) |

Pola są przechowywane jako post meta:

| Klucz             | Typ           | Opis                                                   |
| ----------------- | ------------- | ------------------------------------------------------ |
| `_is_event`       | `"0"` / `"1"` | Czy wpis jest wydarzeniem                              |
| `_event_start`    | string        | Data/czas startu (`YYYY-MM-DD` lub `YYYY-MM-DDTHH:MM`) |
| `_event_end`      | string        | Data/czas końca                                        |
| `_event_all_day`  | `"0"` / `"1"` | Czy całodniowe                                         |
| `_event_location` | string        | Lokalizacja                                            |
| `_event_color`    | hex string    | Kolor (domyślnie `#d3c1ef`)                            |

---

## Wyświetlanie kalendarza

### Blok Gutenberg

Wstaw blok **Event Calendar** w edytorze. Dostępne ustawienia w panelu Inspector Controls:

- **View** — `month` / `week` / `day`
- **Post Type** — typ wpisu do wyświetlenia (domyślnie wszystkie publiczne)
- **Taxonomy** — filtrowanie po taksonomii przypisanej do wybranego typu wpisu
- **Terms** — lista terminów (checkboxy); obsługuje operatory `IN`, `AND`, `NOT IN`

### Shortcode

```
[event_calendar]
[event_calendar view="week"]
[event_calendar post_type="event"]
[event_calendar taxonomy="event_category" terms="5,12" operator="IN"]
```

| Atrybut     | Domyślna      | Opis                                          |
| ----------- | ------------- | --------------------------------------------- |
| `view`      | `month`       | Widok: `month`, `week`, `day`                 |
| `post_type` | _(wszystkie)_ | Slug typu wpisu                               |
| `taxonomy`  | —             | Slug taksonomii                               |
| `terms`     | —             | ID lub slugi terminów, oddzielone przecinkiem |
| `operator`  | `IN`          | `IN`, `AND`, `NOT IN`                         |

---

## REST API

### Endpoint

```
GET /wp-json/event-calendar/v1/events
```

Dostępny publicznie (bez uwierzytelnienia). Zwraca tablicę JSON z wydarzeniami.

Opcjonalny parametr `query_id` (generowany automatycznie przez shortcode/blok) ogranicza wyniki do konkretnego zestawu zapytania.

### Przykład odpowiedzi

```json
[
	{
		"id": 42,
		"title": "Nazwa wydarzenia",
		"allDay": false,
		"start": "2026-07-01T10:00",
		"end": "2026-07-01T12:00",
		"location": "Kraków",
		"description": "Opis...",
		"backgroundColor": "#ddc7ff",
		"borderColor": "#b39fd9"
	}
]
```

Nagłówek cache: `Cache-Control: public, max-age=300` (5 minut).

---

## Zakres dat

Domyślnie calendar pobiera wydarzenia z zakresu ±2 miesiące od teraz. Można to zmienić za pomocą filtrów:

```php
add_filter('ec_event_months_back',  fn() => 6);  // 6 miesięcy wstecz
add_filter('ec_event_months_ahead', fn() => 3);  // 3 miesiące do przodu
```

Limit wpisów na zapytanie (domyślnie 500):

```php
add_filter('ec_events_per_page', fn() => 200);
```

Modyfikacja argumentów `WP_Query`:

```php
add_filter('ec_events_query_args', function($args, $config) {
    // zmień $args według potrzeb
    return $args;
}, 10, 2);
```

---

## Konfiguracja kolorów

Kolor obramowania wydarzeń jest automatycznie przyciemniany o 20% względem koloru tła. Możesz zmienić tę wartość edytując stałą w `event-calendar.php`:

```php
define('EC_BORDER_DARKEN_BOOST', 20); // procent przyciemnienia
```

Źródło pierwszego dnia tygodnia:

```php
define('EVENT_CALENDAR_START_DAY_SOURCE', 'wp'); // 'wp' lub 'locale'
```

---

## Obsługa trybu ciemnego

Plugin obsługuje `light-dark()` CSS natively. Przy użyciu ACSS (Automatic CSS) dodaj klasę `color-scheme--alt` do elementu `<html>`, a kalendarz automatycznie zaktualizuje motyw.

Opcjonalny przełącznik:

```html
<button id="color-scheme-toggle">Toggle Dark Mode</button>
```

---

## Internacjonalizacja

Tłumaczenia ładowane z folderu `languages/`. Text domain: `event-calendar`.

Aby wygenerować nowy plik `.pot`:

```bash
wp i18n make-pot . languages/event-calendar.pot
```

---

## Changelog

### 0.2.1

- Poprawki stylów responsywnych na mobile
- Ulepszony reset motywu kalendarza przy zmianie schematu kolorów

### 0.2.0

- Blok Gutenberg z filtrowaniem po taksonomii
- Obsługa wielu kalendarzy na jednej stronie
- Debounced render hook

### 0.1.0

- Wersja początkowa: CPT, meta, shortcode, REST API
