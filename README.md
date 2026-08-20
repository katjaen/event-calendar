# Event Calendar

Lekki, elastyczny plugin WordPress do tworzenia i wyświetlania wydarzeń. Obsługuje własny typ wpisu (CPT), pola meta, blok Gutenberga, shortcode oraz REST API.

**Wersja:** 1.0.0  
**Autor:** Katarzyna Niklas  
**Licencja:** GPL-2.0+  
**Text Domain:** `event-calendar`

---

## Spis treści

1. [Funkcje](#funkcje)
2. [Wymagania](#wymagania)
3. [Instalacja](#instalacja)
4. [Struktura katalogów](#struktura-katalogów)
5. [Oznaczanie wpisów jako wydarzenie](#oznaczanie-wpisów-jako-wydarzenie)
6. [Ustawienia](#ustawienia)
7. [Wyświetlanie kalendarza](#wyświetlanie-kalendarza)
   - [Blok Gutenberg](#blok-gutenberg)
   - [Shortcode](#shortcode)
8. [REST API](#rest-api)
   - [Endpoint](#endpoint)
   - [Przykład odpowiedzi](#przykład-odpowiedzi)
9. [Zakres dat](#zakres-dat)
10. [Konfiguracja kolorów](#konfiguracja-kolorów)
11. [Obsługa trybu ciemnego](#obsługa-trybu-ciemnego)
12. [Internacjonalizacja](#internacjonalizacja)
13. [Testy](#testy)
14. [Changelog](#changelog)
    - [Unreleased](#unreleased)
    - [1.0.0](#100)
    - [0.2.1](#021)
    - [0.2.0](#020)
    - [0.1.0](#010)

---

## Funkcje

- Rejestruje typ wpisu `event` z taksonomami `event_category` i `event_location`
- Panel boczny w edytorze Gutenberg do oznaczania dowolnego wpisu jako wydarzenie
- Blok `event-calendar/calendar` z konfiguracją widoku i filtrów
- Shortcode `[event_calendar]`
- REST API: `GET /wp-json/event-calendar/v1/events`
- Obsługa widoków: miesięczny, tygodniowy, dzienny (TUI Calendar 2.x) — atrybut `view` ustala widok POCZĄTKOWY, plus przełącznik Dzień/Tydzień/Miesiąc w nawigacji kalendarza, żeby odwiedzający mógł zmienić widok na żywo bez przeładowania
- Wsparcie dla trybu ciemnego (light-dark CSS) i alternatywnych schematów kolorów (ACSS)
- Internacjonalizacja (i18n), gotowa wersja `pl_PL`

---

## Wymagania

- WordPress 6.0+
- PHP 7.4+
- TUI Calendar 2.1.3 (dołączony do pluginu w `assets/`, bez zależności od CDN)

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
│   ├── query-builder.php       # Wspólna logika budowania zapytań WP_Query
│   └── settings.php            # Strona ustawień (Wydarzenia → Settings)
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
│       ├── gutenberg-event-sidebar.js  # Panel boczny edytora
│       └── toastui-calendar.min.js     # Biblioteka TUI Calendar (dołączona lokalnie)
├── tests/
│   ├── calendar-init.pure.test.js
│   ├── gutenberg-event-sidebar.date-logic.test.js
│   └── php/
│       ├── bootstrap.php
│       ├── test-query-builder.php
│       ├── test-color-and-settings.php
│       └── test-i18n-completeness.php
├── jest.config.js
├── package.json
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

## Ustawienia

W menu **Wydarzenia → Settings** (oraz w linku "Settings" przy pluginie na liście wtyczek) dostępna jest strona ustawień:

| Opcja            | Opis                                                                 |
| ---------------- | --------------------------------------------------------------------- |
| On event click   | Zachowanie po kliknięciu wydarzenia w kalendarzu (obecnie: `link` — przejście do wpisu) |

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
		"borderColor": "#b39fd9",
		"url": "https://example.com/event/nazwa-wydarzenia/"
	}
]
```

Nagłówek cache: `Cache-Control: public, max-age=300` (5 minut).

---

## Zakres dat

Domyślnie kalendarz pobiera wydarzenia z zakresu ±2 miesiące od teraz —
stałe `EC_DEFAULT_MONTHS_BACK` / `EC_DEFAULT_MONTHS_AHEAD` na początku
[inc/query-builder.php](inc/query-builder.php). Granica "wstecz" liczy się
od **`_event_end`** (daty zakończenia), nie startu — dzięki temu wydarzenie
wielodniowe, które zaczęło się dawno, ale skończyło niedawno, nie znika z
kalendarza przedwcześnie. Gdy `_event_end` jest puste/nieustawione (typowe
dla wydarzeń jednodniowych — sidebar Gutenberga zapisuje wtedy pusty string,
patrz `gutenberg-event-sidebar.js`), automatyczny fallback na `_event_start`.
Granica "w przód" nadal liczy się od `_event_start` (kiedy wydarzenie się
zaczyna).

Dwa sposoby nadpisania:

```php
// 1) Filtr w motywie/child-theme — zalecane, przeżywa aktualizacje wtyczki.
add_filter('ec_event_months_back',  fn() => 6);  // 6 miesięcy wstecz
add_filter('ec_event_months_ahead', fn() => 3);  // 3 miesiące do przodu
```

```php
// 2) Zmiana stałych wprost w inc/query-builder.php — szybsze przy
//    jednorazowym dostosowaniu, ale aktualizacja wtyczki nadpisze plik
//    i przywróci wartości domyślne.
define('EC_DEFAULT_MONTHS_BACK', 6);
define('EC_DEFAULT_MONTHS_AHEAD', 3);
```

Granulacja to pełne miesiące (`strtotime("-$X months")`) — nie da się tu
bezpośrednio wyrazić np. "tydzień wstecz"; potrzebny byłby osobny mechanizm.

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
WordPress czyta wyłącznie skompilowany `.mo` — `.po` to plik roboczy (Poedit
i podobne), `.pot` to sam szablon (bez tłumaczeń).

**Po dodaniu/zmianie dowolnego `__()`/`_e()` w kodzie** trzeba przejść cały
łańcuch, inaczej nowy string zostaje po angielsku mimo załadowanego `pl_PL`
(dokładnie to się stało do 2026-08-07 — patrz Changelog):

```bash
# 1. Przelicz .pot ze źródeł (nowe/zmienione stringi)
wp i18n make-pot . languages/event-calendar.pot --domain=event-calendar --slug=event-calendar

# 2. Zmerguj .pot do .po — zachowuje istniejące tłumaczenia, dopisuje nowe jako puste
wp i18n update-po languages/event-calendar.pot languages/event-calendar-pl_PL.po

# 3. Uzupełnij puste msgstr w .po (ręcznie albo w Poedit)

# 4. Przelicz .mo — BEZ TEGO KROKU WordPress nadal serwuje stare tłumaczenia
wp i18n make-mo languages/event-calendar-pl_PL.po languages/
```

`tests/php/test-i18n-completeness.php` pilnuje kroków 3–4 automatycznie —
failuje, jeśli w `.po` zostanie puste `msgstr`, albo jeśli `.mo` jest starszy
niż `.po` (czyli krok 4 pominięty).

---

## Testy

```bash
npm install
npm test          # uruchamia testy JS (Jest) i PHP
npm run test:js   # tylko testy JS (jest.config.js, środowisko jsdom)
npm run test:php  # tylko testy PHP (uruchamiane bezpośrednio, bez PHPUnit)
```

---

## Dostępność — otwarte

**Siatka kalendarza nie jest operowalna klawiaturą (WCAG 2.1.1).** Sprawdzone
na żywo 2026-08-19, nadal aktualne po audycie 2026-08-20: Tab przechodzi przez
„dziś"/strzałki prev-next/przełącznik widoku (dzień/tydzień/miesiąc), po czym
wychodzi z kalendarza — NIE wchodzi w poszczególne dni/wydarzenia w siatce.
Potwierdzone w kodzie ToastUI Calendar (`toastui-calendar.min.js`): pola
wydarzeń w widoku miesiąca/tygodnia są zwykłymi `<div>` bez `tabindex`/`role`
— biblioteka nie daje tu żadnej wbudowanej obsługi klawiatury do dogrania.
Efekt: użytkownik samej klawiatury widzi kalendarz, ale nie ma jak otworzyć
konkretnego dnia ani wydarzenia — tylko mysz/dotyk. Do zdecydowania: łatanie
DOM po każdym renderze (dopisanie `tabindex="0"` + `keydown` do pól wydarzeń,
analogicznie do `replaceMoreText()`/`replaceDayNames()`) vs. równoległa,
ukryta wizualnie lista wydarzeń jako link dla klawiatury/SR.

**Popup „+N więcej" — naprawiony (2026-08-20).** Był to realny, osobny
mechanizm od `useDetailPopup` (który faktycznie jest wyłączony i dotyczy
tylko kliku w pojedyncze wydarzenie) — popup przepełnienia dnia
(`.toastui-calendar-see-more-container`) renderuje się przez ToastUI zawsze,
niezależnie od tej flagi. Przyczyna złego pozycjonowania: `.ec-calendar` nie
miało `position` ustawionego, więc `.toastui-calendar-floating-layer` (siostra
`.toastui-calendar-layout`, nie jej dziecko) szukało najbliższego
pozycjonowanego przodka poza kalendarzem — stąd popup lądował gdzieś w
strukturze strony zamiast przy siatce. Fix: `position: relative` na
`.ec-calendar` ([assets/css/calendar.css](assets/css/calendar.css)).

---

## Changelog

### Unreleased

### 1.0.0

- Wersja oznaczona jako stabilna (SemVer 1.0.0) — plugin jest w produkcyjnym użyciu, zakres funkcji (CPT, meta, blok Gutenberga, shortcode, REST API, i18n) uznany za domknięty; opis pluginu zmieniony z "Prosty" na "Lekki, elastyczny" (poprzednie słowo zaniżało realny zakres funkcji)
- Dodany przełącznik widoku Dzień/Tydzień/Miesiąc w nawigacji kalendarza (`data-action="view"`) — atrybut `view` w shortcode/bloku dalej ustala tylko widok początkowy, teraz można go zmienić bez przeładowania strony; aktywny przycisk dostaje tę samą stonowaną wypełnioną etykietę co przycisk „Dziś"
- Uzupełnione tłumaczenie `pl_PL` — `.po` był rozjechany z kodem (etykiety CPT „Events"/„Event"/„Settings"/„Docs" i kilka innych stringów panelu Ustawień nigdy nie trafiły do tłumaczenia, mimo że w interfejsie widać było angielski tekst); `.mo` przeliczony
- Dodany `tests/php/test-i18n-completeness.php` — regresja pilnująca, żeby `.po` nie miał pustych tłumaczeń i żeby `.mo` nie był starszy niż `.po`
- Rozbudowana sekcja „Internacjonalizacja" w README o pełny workflow `make-pot` → `update-po` → `make-mo`

### 0.2.1

- Poprawki stylów responsywnych na mobile
- Ulepszony reset motywu kalendarza przy zmianie schematu kolorów

### 0.2.0

- Blok Gutenberg z filtrowaniem po taksonomii
- Obsługa wielu kalendarzy na jednej stronie
- Debounced render hook

### 0.1.0

- Wersja początkowa: CPT, meta, shortcode, REST API
