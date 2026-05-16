<?php
/**
 * Niezmienny obiekt wyniku wysyłki wiadomości e-mail.
 *
 * Używany jako wartość zwracana przez Ihumbak_WRS_Email_Sender::send_for_order()
 * i Ihumbak_WRS_Email_Sender::send_test(). Zawiera informację o statusie
 * (sent / skipped / failed), przyczynie pominięcia lub błędu oraz przetłumaczony
 * komunikat gotowy do wyświetlenia adminowi.
 *
 * Klasa jest finalna — wartości tworzone wyłącznie przez named constructors.
 * Właściwości są typowane (PHP 7.4+).
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Email_Send_Result
 *
 * Obiekt wyniku wysyłki wiadomości e-mail — wartość niemutowalna.
 */
final class Ihumbak_WRS_Email_Send_Result {

	// -------------------------------------------------------------------------
	// Stałe statusów
	// -------------------------------------------------------------------------

	/** Wiadomość została wysłana pomyślnie. */
	const STATUS_SENT    = 'sent';

	/** Wysyłka pominięta z powodu reguły pomijania. */
	const STATUS_SKIPPED = 'skipped';

	/** Wysyłka zakończyła się niepowodzeniem. */
	const STATUS_FAILED  = 'failed';

	// -------------------------------------------------------------------------
	// Stałe przyczyn
	// -------------------------------------------------------------------------

	/** Funkcja e-maili jest wyłączona w ustawieniach. */
	const REASON_FEATURE_DISABLED  = 'feature_disabled';

	/** Zamówienie jest w stanie wykluczonym (zwrócone / anulowane). */
	const REASON_ORDER_STATE       = 'order_state_excluded';

	/** Nie udało się pobrać zamówienia. */
	const REASON_INVALID_ORDER     = 'invalid_order';

	/** Zamówienie nie ma prawidłowego adresu e-mail rozliczeniowego. */
	const REASON_INVALID_EMAIL     = 'invalid_email';

	/** Wszystkie produkty zostały wykluczone przez ustawienia. */
	const REASON_ALL_EXCLUDED      = 'all_items_excluded';

	/** Klient już ocenił wszystkie produkty z zamówienia. */
	const REASON_ALL_ALREADY_RATED = 'all_items_already_rated';

	/** Temat lub treść szablonu jest pusta po wyrenderowaniu. */
	const REASON_EMPTY_TEMPLATE    = 'empty_subject_or_body';

	/** wp_mail() zwrócił false. */
	const REASON_WP_MAIL_FAILED    = 'wp_mail_failed';

	/** Nieoczekiwany wyjątek podczas wysyłki. */
	const REASON_EXCEPTION         = 'exception';

	// -------------------------------------------------------------------------
	// Właściwości
	// -------------------------------------------------------------------------

	/**
	 * Status wyniku: STATUS_SENT | STATUS_SKIPPED | STATUS_FAILED.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Przyczyna pominięcia / błędu (jedna ze stałych REASON_*).
	 * Pusty ciąg gdy status === STATUS_SENT.
	 *
	 * @var string
	 */
	public string $reason;

	/**
	 * Przetłumaczony komunikat gotowy do wyświetlenia adminowi.
	 *
	 * @var string
	 */
	public string $message;

	/**
	 * Czy wynik pochodzi z wysyłki testowej.
	 * Test-sendy nie mogą być logowane (patrz: issue #11).
	 *
	 * @var bool
	 */
	public bool $is_test;

	// -------------------------------------------------------------------------
	// Konstruktor prywatny — używaj named constructors
	// -------------------------------------------------------------------------

	/**
	 * Konstruktor prywatny — nie tworzyć obiektów bezpośrednio.
	 *
	 * @param string $status  Stała STATUS_*.
	 * @param string $reason  Stała REASON_* (pusty ciąg gdy STATUS_SENT).
	 * @param string $message Przetłumaczony komunikat.
	 * @param bool   $is_test Czy wynik pochodzi z wysyłki testowej.
	 */
	private function __construct( string $status, string $reason, string $message, bool $is_test ) {
		$this->status  = $status;
		$this->reason  = $reason;
		$this->message = $message;
		$this->is_test = $is_test;
	}

	// -------------------------------------------------------------------------
	// Named constructors
	// -------------------------------------------------------------------------

	/**
	 * Tworzy wynik pomyślnej wysyłki.
	 *
	 * @param bool $is_test Czy wynik pochodzi z wysyłki testowej.
	 * @return self
	 */
	public static function sent( bool $is_test = false ): self {
		return new self( self::STATUS_SENT, '', '', $is_test );
	}

	/**
	 * Tworzy wynik pominięcia wysyłki.
	 *
	 * @param string $reason  Stała REASON_* opisująca przyczynę pominięcia.
	 * @param string $message Przetłumaczony komunikat dla admina.
	 * @param bool   $is_test Czy wynik pochodzi z wysyłki testowej.
	 * @return self
	 */
	public static function skipped( string $reason, string $message, bool $is_test = false ): self {
		return new self( self::STATUS_SKIPPED, $reason, $message, $is_test );
	}

	/**
	 * Tworzy wynik nieudanej wysyłki.
	 *
	 * @param string $reason  Stała REASON_* opisująca przyczynę błędu.
	 * @param string $message Przetłumaczony komunikat dla admina.
	 * @param bool   $is_test Czy wynik pochodzi z wysyłki testowej.
	 * @return self
	 */
	public static function failed( string $reason, string $message, bool $is_test = false ): self {
		return new self( self::STATUS_FAILED, $reason, $message, $is_test );
	}
}
