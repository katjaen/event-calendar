<?php
require __DIR__ . '/bootstrap.php';

/**
 * ec_normalize_event_end_date() (event-calendar.php) — hooked na
 * updated_post_meta/added_post_meta, więc łapie _event_start/_event_end
 * niezależnie OD TEGO, KTO JE ZAPISAŁ (sidebar Gutenberga w tym pluginie to
 * nie jedyna droga — patrz komentarz przy "MIRRORED_POST_TYPES" w
 * event-calendar.php). Testy piszą meta wprost przez update_post_meta(),
 * dokładnie tak jak zrobiłby to zewnętrzny kod, żeby sprawdzić hook, nie
 * konkretny UI.
 */

ec_test_section('ec_normalize_event_end_date() — całodniowe bez daty końcowej -> koniec = start (nie znika z kalendarza)');

ec_test_reset_state();
update_post_meta(1, '_event_all_day', '1');
update_post_meta(1, '_event_start', '2026-09-10');
assert_equal('2026-09-10', get_post_meta(1, '_event_end', true), 'pusty _event_end po zapisaniu startu -> dostaje tę samą datę');

ec_test_section('ec_normalize_event_end_date() — zwykłe (nie całodniowe) bez daty końcowej -> +1h od startu');

ec_test_reset_state();
update_post_meta(2, '_event_all_day', '0');
update_post_meta(2, '_event_start', '2026-09-10T14:00');
assert_equal('2026-09-10T15:00', get_post_meta(2, '_event_end', true), 'pusty _event_end -> start + 1h');

ec_test_section('ec_normalize_event_end_date() — data końcowa wcześniejsza niż początkowa -> poprawiona');

ec_test_reset_state();
update_post_meta(3, '_event_all_day', '0');
update_post_meta(3, '_event_start', '2026-09-10T14:00');
update_post_meta(3, '_event_end', '2026-09-09T09:00'); // dzień wcześniej
assert_equal('2026-09-10T15:00', get_post_meta(3, '_event_end', true), 'koniec przed startem -> nadpisany na start + 1h');

ec_test_section('ec_normalize_event_end_date() — całodniowe, data końcowa wcześniejsza -> poprawiona na start');

ec_test_reset_state();
update_post_meta(4, '_event_all_day', '1');
update_post_meta(4, '_event_start', '2026-09-10');
update_post_meta(4, '_event_end', '2026-09-05');
assert_equal('2026-09-10', get_post_meta(4, '_event_end', true), 'koniec przed startem (całodniowe) -> nadpisany na start');

ec_test_section('ec_normalize_event_end_date() — poprawna data końcowa (>= start) zostaje nietknięta');

ec_test_reset_state();
update_post_meta(5, '_event_all_day', '0');
update_post_meta(5, '_event_start', '2026-09-10T14:00');
update_post_meta(5, '_event_end', '2026-09-12T10:00'); // wielodniowe, poprawne
assert_equal('2026-09-12T10:00', get_post_meta(5, '_event_end', true), 'poprawny zakres wielodniowy nie jest ruszany');

ec_test_section('ec_normalize_event_end_date() — sam koniec, bez startu -> nic się nie dzieje (nie ma z czym porównać)');

ec_test_reset_state();
update_post_meta(6, '_event_end', '2026-09-09');
assert_equal('', get_post_meta(6, '_event_start', true), '_event_start nadal puste');
assert_equal('2026-09-09', get_post_meta(6, '_event_end', true), '_event_end zostaje bez zmian, brak startu do porównania');

$exit_code = ec_test_summary();
exit($exit_code);
