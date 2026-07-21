<?php
require __DIR__ . '/bootstrap.php';

ec_test_section('ec_darken_border() — przyciemnia realny kolor o EC_BORDER_DARKEN_BOOST (20%)');

ec_test_reset_state();
// #d3c1ef = rgb(211,193,239); przy 20% ciemniej: round(211*0.8)=169(0xa9),
// round(193*0.8)=154(0x9a), round(239*0.8)=191(0xbf)
assert_equal('#a99abf', ec_darken_border('#d3c1ef'), 'domyślny kolor wydarzenia przyciemniony poprawnie');

ec_test_section('ec_darken_border() — własny procent przyciemnienia');

ec_test_reset_state();
assert_equal('#000000', ec_darken_border('#ffffff', 100), '100% przyciemnienia -> czarny');
assert_equal('#ffffff', ec_darken_border('#ffffff', 0), '0% przyciemnienia -> bez zmian');

ec_test_section('ec_darken_border() — pusty kolor dostaje fallback zamiast fatalnego błędu');

ec_test_reset_state();
assert_equal('#9b7bc4', ec_darken_border(''), 'pusty string -> stały fallback, nie próbuje liczyć hexdec()');
assert_equal('#9b7bc4', ec_darken_border(null), 'null -> ten sam fallback');

ec_test_section('ec_get_click_behavior() — zawsze zwraca poprawną wartość, nawet gdy opcja nie istnieje/jest śmieciem');

ec_test_reset_state();
assert_equal('link', ec_get_click_behavior(), 'świeży install (brak wpisu w opcjach) -> "link"');

ec_test_reset_state();
update_option(EC_OPTION_CLICK_BEHAVIOR, 'popup-jeszcze-nie-istnieje');
assert_equal('link', ec_get_click_behavior(), 'nieznana/przestarzała wartość w bazie -> fallback do "link", nie przepuszczona 1:1');

ec_test_reset_state();
update_option(EC_OPTION_CLICK_BEHAVIOR, 'link');
assert_equal('link', ec_get_click_behavior(), 'poprawna wartość zachowana');

ec_test_section('ec_render_click_behavior_field() — renderuje <select> z zaznaczoną bieżącą opcją');

ec_test_reset_state();
ob_start();
ec_render_click_behavior_field();
$html = ob_get_clean();
assert_true(str_contains($html, '<select'), 'renderuje element <select>');
assert_true(str_contains($html, 'selected="selected"'), 'aktualna wartość ("link") ma atrybut selected');

$exit_code = ec_test_summary();
exit($exit_code);
