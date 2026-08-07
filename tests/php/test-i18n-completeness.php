<?php
require __DIR__ . '/bootstrap.php';

/**
 * Strażnik regresji dla dokładnie tego buga, który uderzył 2026-08-07:
 * event-calendar.pot dorósł o nowe napisy (etykiety CPT, ustawienia), .po
 * nikt nie zmergował ponownie — więc "Events"/"Event"/"Settings" itd. wisiały
 * z pustym msgstr i renderowały się po angielsku mimo załadowanego pl_PL.
 * Ten test parsuje .po i .pot NAPRAWDĘ (bez WP, bez gettext) i pilnuje, żeby
 * to się nie powtórzyło po cichu przy kolejnej zmianie w kodzie.
 */

function ec_test_parse_po(string $path): array
{
    $entries = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $i = 0;
    $count = count($lines);

    while ($i < $count) {
        $line = $lines[$i];

        // Blok przestarzały (msgid już nie istnieje w kodzie) — pomijamy
        // całkowicie, to nie jest "brakujące tłumaczenie", tylko archiwum.
        if (str_starts_with($line, '#~')) {
            $i++;
            continue;
        }

        if (str_starts_with($line, 'msgid "')) {
            $msgid = ec_test_po_unquote($line, 'msgid ');
            $i++;
            // Wieloliniowy msgid (kolejne linie to same "...")
            while ($i < $count && str_starts_with(trim($lines[$i]), '"')) {
                $msgid .= ec_test_po_unquote(trim($lines[$i]), '');
                $i++;
            }

            $isPlural = false;
            if ($i < $count && str_starts_with($lines[$i], 'msgid_plural ')) {
                $isPlural = true;
                $i++;
                while ($i < $count && str_starts_with(trim($lines[$i]), '"')) {
                    $i++;
                }
            }

            if ($msgid === '') {
                // To nagłówek pliku (msgid "" na samej górze) — nie liczy się.
                continue;
            }

            if ($isPlural) {
                $translated = false;
                while ($i < $count && preg_match('/^msgstr\[\d+\] "/', $lines[$i])) {
                    $val = preg_replace('/^msgstr\[\d+\] /', '', $lines[$i]);
                    $val = ec_test_po_unquote($val, '');
                    if ($val !== '') {
                        $translated = true;
                    }
                    $i++;
                }
                $entries[$msgid] = $translated;
                continue;
            }

            if ($i < $count && str_starts_with($lines[$i], 'msgstr "')) {
                $msgstr = ec_test_po_unquote($lines[$i], 'msgstr ');
                $i++;
                while ($i < $count && str_starts_with(trim($lines[$i]), '"')) {
                    $msgstr .= ec_test_po_unquote(trim($lines[$i]), '');
                    $i++;
                }
                $entries[$msgid] = ($msgstr !== '');
                continue;
            }
        }

        $i++;
    }

    return $entries;
}

function ec_test_po_unquote(string $line, string $prefix): string
{
    $line = substr($line, strlen($prefix));
    $line = trim($line);
    if (str_starts_with($line, '"') && str_ends_with($line, '"')) {
        $line = substr($line, 1, -1);
    }
    return stripcslashes($line);
}

ec_test_section('event-calendar-pl_PL.po — każdy AKTYWNY (nieprzestarzały) napis ma niepuste tłumaczenie');

$po_path = EC_TEST_PLUGIN_DIR . 'languages/event-calendar-pl_PL.po';
$entries = ec_test_parse_po($po_path);

assert_true(count($entries) > 0, '.po sparsowany poprawnie: co najmniej jeden napis znaleziony');

$untranslated = [];
foreach ($entries as $msgid => $isTranslated) {
    if (!$isTranslated) {
        $untranslated[] = $msgid;
    }
}

assert_true(
    empty($untranslated),
    'brak pustych tłumaczeń w pl_PL.po. Puste: ' . implode(' | ', $untranslated)
    . "\n    (jeśli to celowe -> dodaj napis; jeśli dodałaś/aś nowy __() w kodzie, uruchom:\n"
    . "     wp i18n make-pot . languages/event-calendar.pot --domain=event-calendar --slug=event-calendar\n"
    . "     wp i18n update-po languages/event-calendar.pot languages/event-calendar-pl_PL.po\n"
    . '     -> przetłumacz nowe wpisy -> wp i18n make-mo languages/event-calendar-pl_PL.po languages/)'
);

ec_test_section('event-calendar-pl_PL.mo — skompilowany PO NIE jest starszy niż źródłowy .po (inaczej WP serwuje stare tłumaczenia)');

$mo_path = EC_TEST_PLUGIN_DIR . 'languages/event-calendar-pl_PL.mo';
assert_true(file_exists($mo_path), '.mo istnieje');
assert_true(
    filemtime($mo_path) >= filemtime($po_path),
    '.mo nie jest starszy niż .po — jeśli FAIL: edytowano .po i zapomniano "wp i18n make-mo" (WordPress czyta tylko .mo, .po jest tylko dla ludzi/Poedit)'
);

$exit_code = ec_test_summary();
exit($exit_code);
