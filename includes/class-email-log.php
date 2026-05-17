<?php
/**
 * Logger prób wysyłki wiadomości e-mail.
 *
 * Nasłuchuje na hook `ihumbak_wrs_email_send_complete` i zapisuje wynik każdej
 * próby wysyłki (status: sent / skipped / failed) do dedykowanej tabeli
 * `{prefix}_woo_quick_ratings_email_log`. Klasa jest aktywna wyłącznie gdy
 * opcja `ihumbak_wrs_email_log_enabled` jest włączona — bootstrap warunkuje
 * instancjonowanie tą opcją.
 *
 * Wysyłki testowe (is_test() === true) są zawsze pomijane — nie generują wpisu
 * w logu, niezależnie od ustawień.
 *
 * Klasa nie rzuca wyjątków. Błędy insertu logowane są wyłącznie do error_log
 * gdy WP_DEBUG i WP_DEBUG_LOG są aktywne.
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Email_Log
 *
 * Logger wysyłek e-mail — persystuje wyniki do tabeli DB.
 */
class Ihumbak_WRS_Email_Log {

	// -------------------------------------------------------------------------
	// Stałe
	// -------------------------------------------------------------------------

	/** Nazwa opcji włączającej logowanie. */
	const OPTION_ENABLED = 'ihumbak_wrs_email_log_enabled';

	/** Sufiks nazwy tabeli (bez prefiksu $wpdb->prefix). */
	const TABLE_SUFFIX = 'woo_quick_ratings_email_log';

	// -------------------------------------------------------------------------
	// Konstruktor
	// -------------------------------------------------------------------------

	/**
	 * Konstruktor — rejestruje hooki wyłącznie gdy logowanie jest włączone.
	 *
	 * Bootstrap może instancjonować klasę warunkowo lub bezwarunkowo —
	 * konstruktor sam sprawdza opcję, więc oba podejścia są bezpieczne.
	 */
	public function __construct() {
		if ( self::is_enabled() ) {
			$this->register_hooks();
		}
	}

	/**
	 * Rejestruje handler hooka wysyłki e-mail.
	 *
	 * Priorytet 20 — po harmonogramie follow-up (domyślnie 10), by log
	 * tworzony był po zaplanowaniu ewentualnych kolejnych kroków.
	 */
	public function register_hooks(): void {
		add_action( 'ihumbak_wrs_email_send_complete', array( $this, 'on_send_complete' ), 20, 3 );
	}

	// -------------------------------------------------------------------------
	// Handler hooka
	// -------------------------------------------------------------------------

	/**
	 * Obsługuje zakończenie próby wysyłki i zapisuje wpis do logu.
	 *
	 * @param Ihumbak_WRS_Email_Send_Result $result   Wynik wysyłki.
	 * @param int                           $order_id ID zamówienia.
	 * @param int                           $step     Numer kroku sekwencji.
	 */
	public function on_send_complete( Ihumbak_WRS_Email_Send_Result $result, int $order_id, int $step ): void {
		// Defensywnie: send_test() nie powinien wyzwalać tego hooka, ale
		// gdyby ktoś wywołał go ręcznie — odrzucamy wpis.
		if ( $result->is_test() ) {
			return;
		}

		// Pobierz e-mail klienta bezpośrednio z zamówienia (HPOS-aware).
		$order = wc_get_order( $order_id );
		$email = $order ? (string) $order->get_billing_email() : '';

		// Mapowanie statusu wyniku na wartość do zapisu w logu.
		switch ( $result->get_status() ) {
			case Ihumbak_WRS_Email_Send_Result::STATUS_SENT:
				$status = 'sent';
				$reason = null;
				break;

			case Ihumbak_WRS_Email_Send_Result::STATUS_SKIPPED:
				$status = 'skipped';
				$reason = $result->get_reason();
				break;

			case Ihumbak_WRS_Email_Send_Result::STATUS_FAILED:
				$status = 'failed';
				$reason = $result->get_reason();
				break;

			default:
				// Nieznany status — nie loguj.
				return;
		}

		$this->log( $order_id, $email, $step, $status, $reason !== '' ? $reason : null );
	}

	// -------------------------------------------------------------------------
	// Statyczne helpery
	// -------------------------------------------------------------------------

	/**
	 * Sprawdza, czy logowanie jest włączone w opcjach.
	 *
	 * @return bool True gdy opcja jest aktywna.
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Zwraca pełną nazwę tabeli logów (z prefiksem bazy danych).
	 *
	 * @return string Pełna nazwa tabeli.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Sprawdza, czy tabela logów istnieje.
	 *
	 * @return bool True gdy tabela istnieje w bazie danych.
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Tworzy tabelę logów jeśli jeszcze nie istnieje.
	 *
	 * Deleguje do Ihumbak_WRS_Database_Migration::create_email_log_table().
	 *
	 * @return bool True gdy tabela istnieje po próbie utworzenia.
	 */
	public static function ensure_table(): bool {
		$migration = new Ihumbak_WRS_Database_Migration();
		$migration->create_email_log_table();
		return self::table_exists();
	}

	// -------------------------------------------------------------------------
	// Zapis wpisu do logu
	// -------------------------------------------------------------------------

	/**
	 * Zapisuje pojedynczy wpis logu do bazy danych.
	 *
	 * W przypadku niepowodzenia próbuje raz odtworzyć tabelę (ensure_table())
	 * i ponawia insert. Nigdy nie rzuca wyjątków.
	 *
	 * @param int         $order_id ID zamówienia.
	 * @param string      $email    Adres e-mail klienta (może być pusty dla nieznanego zamówienia).
	 * @param int         $step     Numer kroku sekwencji.
	 * @param string      $status   Status: 'sent' | 'skipped' | 'failed'.
	 * @param string|null $reason   Kod przyczyny (stała REASON_*) lub null dla statusu 'sent'.
	 * @return bool True gdy wpis został zapisany.
	 */
	public function log( int $order_id, string $email, int $step, string $status, ?string $reason = null ): bool {
		global $wpdb;

		// Whitelist statusów.
		if ( ! in_array( $status, array( 'sent', 'failed', 'skipped' ), true ) ) {
			return false;
		}

		// Sanityzacja danych wejściowych.
		$order_id = (int) $order_id;
		$email    = sanitize_email( $email ); // Pusty e-mail jest akceptowany.
		$step     = (int) $step;

		if ( null !== $reason ) {
			$reason = wp_strip_all_tags( $reason );
			// Przytnij do 191 znaków — bezpieczna długość dla indeksu na varchar(191).
			if ( strlen( $reason ) > 191 ) {
				$reason = substr( $reason, 0, 191 );
			}
			if ( '' === $reason ) {
				$reason = null;
			}
		}

		$data   = array(
			'order_id'       => $order_id,
			'customer_email' => $email,
			'step'           => $step,
			'status'         => $status,
			'reason'         => $reason,
			'created_at'     => current_time( 'mysql', true ), // UTC.
		);
		$format = array( '%d', '%s', '%d', '%s', '%s', '%s' );

		$result = $wpdb->insert( self::get_table_name(), $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( false !== $result ) {
			return true;
		}

		// Pierwsza próba nieudana — spróbuj odtworzyć tabelę i ponów insert.
		self::ensure_table();

		$result = $wpdb->insert( self::get_table_name(), $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( false !== $result ) {
			return true;
		}

		// Nadal nieudane — zaloguj błąd gdy tryb debug jest aktywny.
		if (
			defined( 'WP_DEBUG' ) && WP_DEBUG
			&& defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG
		) {
			error_log( 'Ihumbak_WRS_Email_Log insert failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return false;
	}
}
