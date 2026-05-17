<?php
/**
 * Resolver szablonów e-mail uwzględniający język zamówienia.
 *
 * Klasa statyczna i bezstanowa. Rozwiązuje prawidłową wartość pola tematu,
 * nagłówka i treści wiadomości na podstawie aktywnego języka zamówienia.
 *
 * Algorytm fallback (per pole):
 *  1. Gdy żadna wtyczka wielojęzyczna nie jest aktywna → wartość z klucza bazowego.
 *  2. Gdy $lang === null LUB $lang === język domyślny → klucz bazowy.
 *  3. Gdy $lang nie jest prawidłowym kodem języka → klucz bazowy.
 *  4. Pobierz wartość z klucza z sufiksem `_{lang}`.
 *  5. Gdy wartość niepusta (po trim) → zwróć ją.
 *  6. W przeciwnym razie → klucz bazowy (fallback do języka domyślnego).
 *
 * Klucze opcji:
 *  - Bazowe (język domyślny): ihumbak_wrs_email_subject | _heading | _body
 *  - Lokalne (inne języki):   ihumbak_wrs_email_subject_{lang} | _heading_{lang} | _body_{lang}
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Email_Template_Resolver
 *
 * Bezstanowy resolver opcji szablonu e-mail z obsługą wielojęzyczności.
 */
class Ihumbak_WRS_Email_Template_Resolver {

	/**
	 * Zwraca temat wiadomości dla podanego kodu języka.
	 *
	 * @param string|null $lang Kod języka (np. 'pl') lub null dla domyślnego.
	 * @return string Wartość opcji tematu.
	 */
	public static function get_subject( ?string $lang = null ): string {
		return self::resolve( 'subject', $lang );
	}

	/**
	 * Zwraca nagłówek wiadomości dla podanego kodu języka.
	 *
	 * @param string|null $lang Kod języka (np. 'pl') lub null dla domyślnego.
	 * @return string Wartość opcji nagłówka.
	 */
	public static function get_heading( ?string $lang = null ): string {
		return self::resolve( 'heading', $lang );
	}

	/**
	 * Zwraca treść wiadomości (HTML) dla podanego kodu języka.
	 *
	 * @param string|null $lang Kod języka (np. 'pl') lub null dla domyślnego.
	 * @return string Wartość opcji treści.
	 */
	public static function get_body( ?string $lang = null ): string {
		return self::resolve( 'body', $lang );
	}

	// -------------------------------------------------------------------------
	// Prywatny rdzeń algorytmu
	// -------------------------------------------------------------------------

	/**
	 * Rozwiązuje wartość opcji szablonu dla danego pola i języka.
	 *
	 * @param string      $field 'subject' | 'heading' | 'body'.
	 * @param string|null $lang  Kod języka lub null.
	 * @return string Wartość opcji.
	 */
	private static function resolve( string $field, ?string $lang ): string {
		$base_key = 'ihumbak_wrs_email_' . $field;

		// Krok 1 — bez wtyczki wielojęzycznej: zawsze klucz bazowy.
		if ( ! Ihumbak_WRS_Multilingual::is_active() ) {
			return (string) get_option( $base_key, '' );
		}

		// Normalizuj kod języka.
		$lang = ( null !== $lang ) ? strtolower( trim( $lang ) ) : '';

		// Krok 2 — brak kodu lub to jest język domyślny → klucz bazowy.
		if ( '' === $lang || $lang === Ihumbak_WRS_Multilingual::get_default_language() ) {
			return (string) get_option( $base_key, '' );
		}

		// Krok 3 — nieprawidłowy kod języka → klucz bazowy.
		if ( ! Ihumbak_WRS_Multilingual::is_valid_lang_code( $lang ) ) {
			return (string) get_option( $base_key, '' );
		}

		// Krok 4 — pobierz wartość zlokalizowaną.
		$lang_key   = $base_key . '_' . sanitize_key( $lang );
		$lang_value = (string) get_option( $lang_key, '' );

		// Krok 5 — niepusta wartość zlokalizowana → zwróć ją.
		if ( '' !== trim( $lang_value ) ) {
			return $lang_value;
		}

		// Krok 6 — fallback do klucza bazowego.
		return (string) get_option( $base_key, '' );
	}
}
