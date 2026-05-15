<?php
/**
 * Lekki test CLI dla statycznego helpera
 * `Ihumbak_WRS_Email_Sender::filter_items_against_rated_set()`.
 *
 * Uruchomienie: `php tests/test-email-sender-skip-already-rated.php`
 * Kod wyjścia 0 = wszystkie asercje przeszły, 1 = pierwszy fail.
 * Brak frameworka testowego — analogicznie do tests/test-email-template.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Stub stałej wymaganej przez autoloader (nie istnieje poza WP).
if ( ! defined( 'Ihumbak_WRS_Email_Scheduler::SEND_ACTION_HOOK' ) ) {
	// Zamiast ładować cały scheduler (i zależności WP), definiujemy stub klasy.
	if ( ! class_exists( 'Ihumbak_WRS_Email_Scheduler' ) ) {
		class Ihumbak_WRS_Email_Scheduler {
			const SEND_ACTION_HOOK = 'ihumbak_wrs_send_review_email';
		}
	}
}

require __DIR__ . '/../includes/class-email-sender.php';

$ran = 0;

/**
 * Asercja równości — przy niezgodności drukuje szczegóły i kończy z kodem 1.
 *
 * @param mixed  $expected Oczekiwana wartość.
 * @param mixed  $actual   Rzeczywista wartość.
 * @param string $label    Opis asercji.
 */
function assert_eq( $expected, $actual, $label ) {
	global $ran;
	$ran++;
	if ( $expected !== $actual ) {
		echo "FAIL: {$label}\n";
		echo '  Expected: ' . var_export( $expected, true ) . "\n";
		echo '  Actual:   ' . var_export( $actual, true ) . "\n";
		exit( 1 );
	}
}

// ---------------------------------------------------------------------------
// Asercja 1 — Pusty zbiór ocenionych produktów → wejście bez zmian.
// ---------------------------------------------------------------------------
$result1 = Ihumbak_WRS_Email_Sender::filter_items_against_rated_set(
	array( 10, 20, 30 ),
	array()
);
assert_eq(
	array( 10, 20, 30 ),
	$result1,
	'Pusty zbiór ocenionych → wszystkie produkty przechodzą'
);

// ---------------------------------------------------------------------------
// Asercja 2 — Wszystkie produkty już ocenione → zwraca pustą tablicę.
// ---------------------------------------------------------------------------
$result2 = Ihumbak_WRS_Email_Sender::filter_items_against_rated_set(
	array( 10, 20, 30 ),
	array( 10, 20, 30 )
);
assert_eq(
	array(),
	$result2,
	'Wszystkie ocenione → wynik pusty'
);

// ---------------------------------------------------------------------------
// Asercja 3 — Częściowe pokrycie → tylko nieocenione ID, kolejność zachowana.
// ---------------------------------------------------------------------------
$result3 = Ihumbak_WRS_Email_Sender::filter_items_against_rated_set(
	array( 10, 20, 30, 40 ),
	array( 20, 40 )
);
assert_eq(
	array( 10, 30 ),
	$result3,
	'Częściowe pokrycie → tylko nieocenione, kolejność oryginalna'
);

// ---------------------------------------------------------------------------
// Asercja 4 — Duplikaty ID w wejściu → de-duplikacja, kolejność zachowana.
// ---------------------------------------------------------------------------
$result4 = Ihumbak_WRS_Email_Sender::filter_items_against_rated_set(
	array( 10, 20, 10, 30, 20 ), // 10 i 20 powtórzone (np. warianty tego samego rodzica).
	array( 30 )
);
assert_eq(
	array( 10, 20 ),
	$result4,
	'Duplikaty ID w wejściu są de-duplikowane, kolejność pierwszego wystąpienia zachowana'
);

// ---------------------------------------------------------------------------
// Asercja 5 — Bezpieczeństwo typów: string "42" i int 42 traktowane jako równe.
// ---------------------------------------------------------------------------
$result5 = Ihumbak_WRS_Email_Sender::filter_items_against_rated_set(
	array( '42', '99' ),  // ciągi jako wejście (np. z get_items).
	array( 42 )           // int w zbiorze ocenionych (z bazy).
);
assert_eq(
	array( 99 ),
	$result5,
	'String "42" i int 42 traktowane jako równe — rzutowanie typów działa poprawnie'
);

// ---------------------------------------------------------------------------
// Wszystkie asercje przeszły.
// ---------------------------------------------------------------------------
echo "OK: {$ran} assertions passed.\n";
exit( 0 );
