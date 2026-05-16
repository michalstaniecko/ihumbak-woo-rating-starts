<?php
/**
 * Generator kuponów jednorazowych tworzonych automatycznie dla zamówień.
 *
 * Odpowiada za leniwe tworzenie i trwałe wiązanie kuponu WooCommerce z
 * konkretnym zamówieniem. Kupon jest tworzony wyłącznie gdy tryb kuponu
 * ustawiony w opcji `ihumbak_wrs_email_coupon_mode` ma wartość `auto`.
 * Jeden kupon na zamówienie — kolejne wywołania dla tego samego zamówienia
 * zwracają kod już istniejącego, ważnego kuponu bez ponownego tworzenia.
 *
 * Wygenerowane kupony są oznaczane w postmeta kluczem `_ihumbak_wrs_auto_generated`,
 * co umożliwia ich identyfikację przy odinstalowaniu wtyczki (bez usuwania —
 * klienci mogą jeszcze posiadać kody).
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Coupon_Generator
 *
 * Leniwie inicjalizowana (przez Ihumbak_WRS_Email_Sender) klasa odpowiedzialna
 * za generowanie unikalnych, jednorazowych kuponów procentowych dla zamówień.
 */
class Ihumbak_WRS_Coupon_Generator {

	/**
	 * Klucz meta przechowujący ID wygenerowanego kuponu w zamówieniu.
	 *
	 * @since 1.5.0
	 */
	const ORDER_META_KEY = '_ihumbak_wrs_generated_coupon_id';

	/**
	 * Klucz postmeta identyfikujący kupony auto-generowane przez wtyczkę.
	 *
	 * @since 1.5.0
	 */
	const COUPON_META_MARKER = '_ihumbak_wrs_auto_generated';

	/**
	 * Prefiks kodu kuponu (THX- + 8 znaków z alfabetu bezpiecznego).
	 *
	 * @since 1.5.0
	 */
	const CODE_PREFIX = 'THX-';

	/**
	 * Długość losowej części kodu kuponu (bez prefiksu).
	 *
	 * @since 1.5.0
	 */
	const CODE_RANDOM_LENGTH = 8;

	/**
	 * Alfabet bezpieczny — wyklucza mylące znaki (0/O, 1/I/L).
	 *
	 * @since 1.5.0
	 */
	const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/**
	 * Maksymalna liczba prób wygenerowania unikalnego kodu.
	 *
	 * @since 1.5.0
	 */
	const CODE_MAX_RETRIES = 5;

	/**
	 * Czas trwania blokady transient (w sekundach).
	 *
	 * @since 1.5.0
	 */
	const LOCK_TIMEOUT = 30;

	/**
	 * Maksymalny czas oczekiwania na zwolnienie blokady (w sekundach).
	 *
	 * @since 1.5.0
	 */
	const LOCK_WAIT_MAX = 2;

	// -------------------------------------------------------------------------
	// Publiczne API
	// -------------------------------------------------------------------------

	/**
	 * Zwraca kod kuponu dla zamówienia — tworzy nowy jeśli żaden ważny nie istnieje.
	 *
	 * Logika:
	 * 1. Sprawdza, czy WC_Coupon jest dostępny.
	 * 2. Odczytuje meta zamówienia `_ihumbak_wrs_generated_coupon_id`.
	 * 3. Jeśli znaleziony kupon jest ważny (istnieje, nie wygasł, usage_count < usage_limit) — zwraca jego kod.
	 * 4. Acquire transient lock (30s); jeśli zablokowany — czeka maks. 2s i ponownie odczytuje meta.
	 * 5. Generuje unikalny kod (THX-XXXXXXXX), tworzy WC_Coupon, zapisuje meta zamówienia.
	 * 6. Zwalnia blokadę, zwraca kod kuponu.
	 *
	 * @since 1.5.0
	 *
	 * @param \WC_Order $order     Zamówienie, dla którego generowany jest kupon.
	 * @param int       $discount  Procent rabatu (1–100).
	 * @param int       $days      Liczba dni ważności kuponu (1–365).
	 * @return string Kod kuponu (małe litery, jak WooCommerce przechowuje) lub pusty ciąg przy błędzie.
	 */
	public function get_or_create_for_order( \WC_Order $order, int $discount, int $days ): string {
		// Krok 1 — Weryfikacja dostępności WC_Coupon.
		if ( ! class_exists( '\WC_Coupon' ) ) {
			$this->log_failure( 'WC_Coupon niedostępny — pomijanie generowania kuponu', array( 'order_id' => $order->get_id() ) );
			return '';
		}

		$order_id = $order->get_id();

		// Krok 2 — Sprawdź istniejący kupon przypisany do zamówienia.
		$existing = $this->get_existing_valid_coupon( $order );
		if ( '' !== $existing ) {
			return $existing;
		}

		// Krok 3 — Spróbuj przejąć blokadę transient, by uniknąć race condition.
		$lock_key = 'ihumbak_wrs_coupon_lock_' . $order_id;

		if ( ! $this->acquire_lock( $lock_key ) ) {
			// Ktoś inny właśnie generuje — poczekaj na zwolnienie locka i sprawdź ponownie meta.
			$this->wait_briefly( $lock_key );
			$order->read_meta_data( true ); // Odśwież dane z bazy.
			$after_wait = $this->get_existing_valid_coupon( $order );
			if ( '' !== $after_wait ) {
				return $after_wait;
			}
			// Jeśli nadal pusto — spróbujmy mimo to (lock mógł wygasnąć lub inny proces się wyłożył).
		}

		// Krok 4 — Wygeneruj unikalny kod.
		$code = $this->generate_unique_code();
		if ( '' === $code ) {
			$this->log_failure( 'Nie udało się wygenerować unikalnego kodu kuponu', array( 'order_id' => $order_id ) );
			$this->release_lock( $lock_key );
			return '';
		}

		// Krok 5 — Utwórz kupon WooCommerce.
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( (float) $discount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 0 );
		$coupon->set_date_expires( time() + $days * DAY_IN_SECONDS );
		$coupon->set_email_restrictions( array() ); // Celowo puste — kupon jest zbywalny.
		$coupon->set_description(
			sprintf(
				/* translators: %d: ID zamówienia */
				__( 'Auto-generated for order #%d (review request)', 'ihumbak-woo-rating-stars' ),
				$order_id
			)
		);

		// Krok 6 — Zapisz kupon.
		$coupon_id = $coupon->save();

		if ( ! $coupon_id ) {
			$this->log_failure( 'WC_Coupon::save() zwróciło 0 lub false — kupon nie został zapisany', array( 'order_id' => $order_id, 'code' => $code ) );
			$this->release_lock( $lock_key );
			return '';
		}

		// Krok 7 — Oznacz kupon jako auto-wygenerowany (dla skryptu odinstalowania).
		update_post_meta( $coupon_id, self::COUPON_META_MARKER, '1' );

		// Krok 8 — Zapisz ID kuponu w meta zamówienia (HPOS-safe).
		$order->update_meta_data( self::ORDER_META_KEY, $coupon_id );
		$order->save();

		// Krok 9 — Zwolnij blokadę i zwróć kod.
		$this->release_lock( $lock_key );

		return $coupon->get_code();
	}

	// -------------------------------------------------------------------------
	// Implementacja prywatna
	// -------------------------------------------------------------------------

	/**
	 * Sprawdza, czy do zamówienia przypisany jest ważny kupon, i zwraca jego kod.
	 *
	 * Kupon jest uznawany za ważny, gdy:
	 * - istnieje post o danym ID i jest typu 'shop_coupon',
	 * - nie jest wygasły (date_expires === null lub date_expires > now),
	 * - usage_count < usage_limit (gdy usage_limit > 0).
	 *
	 * @since 1.5.0
	 *
	 * @param \WC_Order $order Zamówienie.
	 * @return string Kod ważnego kuponu lub pusty ciąg gdy brak.
	 */
	private function get_existing_valid_coupon( \WC_Order $order ): string {
		$coupon_id = (int) $order->get_meta( self::ORDER_META_KEY, true );

		if ( $coupon_id <= 0 ) {
			return '';
		}

		$coupon_post = get_post( $coupon_id );

		if (
			! ( $coupon_post instanceof \WP_Post )
			|| 'shop_coupon' !== $coupon_post->post_type
		) {
			return '';
		}

		// Sprawdź ważność przez obiekt WC_Coupon (HPOS-safe).
		$coupon = new \WC_Coupon( $coupon_id );

		// Sprawdź datę wygaśnięcia.
		$expires = $coupon->get_date_expires();
		if ( $expires instanceof \WC_DateTime && $expires->getTimestamp() <= time() ) {
			return ''; // Kupon wygasł.
		}

		// Sprawdź limit użyć.
		$usage_limit = (int) $coupon->get_usage_limit();
		$usage_count = (int) $coupon->get_usage_count();

		if ( $usage_limit > 0 && $usage_count >= $usage_limit ) {
			return ''; // Kupon już wykorzystany.
		}

		return $coupon->get_code();
	}

	/**
	 * Generuje unikalny kod kuponu w formacie THX-XXXXXXXX.
	 *
	 * Używa kryptograficznie bezpiecznego `random_int()` do losowania
	 * z alfabetu bezpiecznego (bez mylących znaków O/0/I/1/L).
	 * Weryfikuje unikalność przez `wc_get_coupon_id_by_code()`.
	 * Maksymalnie CODE_MAX_RETRIES prób.
	 *
	 * @since 1.5.0
	 *
	 * @return string Unikalny kod lub pusty ciąg gdy wszystkie próby zawiodły.
	 */
	private function generate_unique_code(): string {
		$alphabet     = self::CODE_ALPHABET;
		$alphabet_len = strlen( $alphabet );
		$length       = self::CODE_RANDOM_LENGTH;

		for ( $attempt = 0; $attempt < self::CODE_MAX_RETRIES; $attempt++ ) {
			$random = '';
			for ( $i = 0; $i < $length; $i++ ) {
				try {
					$random .= $alphabet[ random_int( 0, $alphabet_len - 1 ) ];
				} catch ( \Exception $e ) {
					$this->log_failure( 'random_int rzucił wyjątek', array( 'error' => $e->getMessage() ) );
					return '';
				}
			}

			$code = self::CODE_PREFIX . $random;

			// Sprawdź unikalność — wc_get_coupon_id_by_code() zwraca 0 gdy nie znaleziono.
			if ( 0 === (int) wc_get_coupon_id_by_code( $code ) ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Próbuje przejąć blokadę dla operacji na kuponie zamówienia.
	 *
	 * Używa `add_option()` jako atomowej operacji INSERT — InnoDB wymusza UNIQUE
	 * na kolumnie `option_name`, więc drugi równoległy proces dostanie false.
	 * `set_transient` NIE jest atomowy (zawsze nadpisuje), dlatego nie jest używany.
	 * Jako zabezpieczenie przed permanentnym lockiem po awarii procesu: jeśli opcja
	 * istnieje dłużej niż LOCK_TIMEOUT sekund, jest usuwana i lock jest przejmowany.
	 *
	 * @since 1.5.0
	 *
	 * @param string $lock_key Klucz blokady (nazwa opcji w wp_options).
	 * @return bool True jeśli blokada przejęta, false jeśli zablokowany.
	 */
	private function acquire_lock( string $lock_key ): bool {
		// add_option jest atomowy — zwraca false gdy opcja już istnieje (lock zajęty).
		// autoload='no' — nie zaśmiecamy pamięci podręcznej opcji autoładowanych.
		$added = add_option( $lock_key, (string) time(), '', 'no' );

		if ( ! $added ) {
			// Lock istnieje. Sprawdź czy nie wygasł (ochrona przed martwym procesem).
			$created_at = (int) get_option( $lock_key, 0 );
			if ( $created_at > 0 && ( time() - $created_at ) > self::LOCK_TIMEOUT ) {
				// Stary lock — odzyskaj go.
				delete_option( $lock_key );
				$added = add_option( $lock_key, (string) time(), '', 'no' );
			}
		}

		return (bool) $added;
	}

	/**
	 * Zwalnia blokadę przez usunięcie opcji z wp_options.
	 *
	 * @since 1.5.0
	 *
	 * @param string $lock_key Klucz blokady (nazwa opcji w wp_options).
	 */
	private function release_lock( string $lock_key ): void {
		delete_option( $lock_key );
	}

	/**
	 * Krótkie oczekiwanie na zwolnienie locka (maks. LOCK_WAIT_MAX sekund).
	 *
	 * Pętla 100ms — wychodzi wcześnie gdy klucz lock-opcji zniknie z bazy
	 * (proces trzymający lock zakończył pracę). Pozwala drugiemu procesowi
	 * od razu odczytać świeżą meta z `get_or_create_for_order()` zamiast
	 * blokować na pełne 2s.
	 *
	 * @since 1.5.0
	 *
	 * @param string $lock_key Klucz opcji-locka do obserwacji.
	 */
	private function wait_briefly( string $lock_key ): void {
		$waited_us = 0;
		$max_us    = self::LOCK_WAIT_MAX * 1000000;
		$step_us   = 100000; // 0.1 s.

		while ( $waited_us < $max_us ) {
			usleep( $step_us );
			$waited_us += $step_us;

			// `wp_cache_delete` zapewnia świeży odczyt z bazy (autoload='no' więc
			// opcja nie jest w cache'u autoloaded, ale obiektowy cache może mieć stały kosz).
			wp_cache_delete( $lock_key, 'options' );
			if ( false === get_option( $lock_key, false ) ) {
				return;
			}
		}
	}

	/**
	 * Loguje błąd wyłącznie gdy WP_DEBUG i WP_DEBUG_LOG są aktywne.
	 *
	 * Nie wyświetla powiadomień admina ani nie wywołuje trigger_error().
	 * Komunikaty logów nie wymagają tłumaczenia.
	 *
	 * @since 1.5.0
	 *
	 * @param string $message Opis błędu (po polsku, wewnętrzny).
	 * @param array  $context Dodatkowe dane diagnostyczne.
	 */
	private function log_failure( string $message, array $context = array() ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			return;
		}

		$entry = '[IHUMBAK_WRS Coupon_Generator] ' . $message;

		if ( ! empty( $context ) ) {
			$entry .= ' | ' . wp_json_encode( $context );
		}

		error_log( $entry ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
