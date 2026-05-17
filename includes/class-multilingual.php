<?php
/**
 * Pomocnik detekcji i rozwiązywania języków — WPML i Polylang.
 *
 * Klasa statyczna i bezstanowa (poza wewnętrznym cache memoizacji).
 * Nie rejestruje żadnych hooków i nie jest instancjonowana z bootstrapu —
 * ładowana on-demand przez autoloader gdy wywołana przez Email_Template_Resolver
 * lub ustawienia admina.
 *
 * Gdy żadna wtyczka wielojęzyczna nie jest aktywna, każda metoda publiczna
 * zwraca wartość bezpieczną jako no-op (false / '' / [] / null).
 *
 * Kolejność priorytetu: WPML wygrywa gdy obie wtyczki są aktywne jednocześnie.
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Multilingual
 *
 * Statyczny helper detekcji wtyczek wielojęzycznych i rozwiązywania kodów języków.
 */
class Ihumbak_WRS_Multilingual {

	/**
	 * Cache wewnętrzny — unika wielokrotnych wywołań kosztownych API.
	 *
	 * @var array<string,mixed>
	 */
	private static $cache = array();

	/**
	 * Sprawdza, czy jakakolwiek obsługiwana wtyczka wielojęzyczna jest aktywna.
	 *
	 * Obsługiwane: WPML (icl_get_languages), Polylang (pll_languages_list).
	 *
	 * @return bool True gdy WPML lub Polylang jest aktywny.
	 */
	public static function is_active(): bool {
		return function_exists( 'icl_get_languages' ) || function_exists( 'pll_languages_list' );
	}

	/**
	 * Zwraca identyfikator aktywnego dostawcy wielojęzyczności.
	 *
	 * @return string 'wpml' | 'polylang' | '' (gdy żadna wtyczka nieaktywna).
	 */
	public static function get_provider(): string {
		if ( function_exists( 'icl_object_id' ) && function_exists( 'icl_get_languages' ) ) {
			return 'wpml';
		}

		if ( function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}

		return '';
	}

	/**
	 * Zwraca listę aktywnych języków jako tablicę asocjacyjną.
	 *
	 * Format każdego wpisu:
	 *   [
	 *     'code'        => 'pl',
	 *     'name'        => 'Polski',
	 *     'native_name' => 'Polski',
	 *     'is_default'  => true,
	 *   ]
	 *
	 * Wynik jest memoizowany w statycznym cache — bezpieczne do wielokrotnego wywołania.
	 *
	 * @return array<int,array{code:string,name:string,native_name:string,is_default:bool}>
	 */
	public static function get_active_languages(): array {
		if ( isset( self::$cache['active_languages'] ) ) {
			return self::$cache['active_languages'];
		}

		$provider = self::get_provider();
		$result   = array();

		if ( 'wpml' === $provider ) {
			$result = self::get_active_languages_wpml();
		} elseif ( 'polylang' === $provider ) {
			$result = self::get_active_languages_polylang();
		}

		self::$cache['active_languages'] = $result;

		return $result;
	}

	/**
	 * Zwraca kod domyślnego języka witryny.
	 *
	 * @return string Kod języka (np. 'pl') lub pusty ciąg gdy niedostępny.
	 */
	public static function get_default_language(): string {
		if ( isset( self::$cache['default_language'] ) ) {
			return (string) self::$cache['default_language'];
		}

		$provider = self::get_provider();
		$default  = '';

		if ( 'wpml' === $provider ) {
			$default = (string) apply_filters( 'wpml_default_language', null );
		} elseif ( 'polylang' === $provider && function_exists( 'pll_default_language' ) ) {
			$default = (string) pll_default_language( 'slug' );
		}

		$default = strtolower( trim( $default ) );

		self::$cache['default_language'] = $default;

		return $default;
	}

	/**
	 * Zwraca kod języka powiązanego z podanym zamówieniem WooCommerce.
	 *
	 * Próbuje kilku meta kluczy / API WPML i Polylang, by zmaksymalizować szansę
	 * wykrycia języka (WPML używał różnych kluczy meta w różnych wersjach).
	 *
	 * @param \WC_Order $order Zamówienie WooCommerce (HPOS-compatible).
	 * @return string|null Kod języka (np. 'pl') lub null gdy niewykonalne.
	 */
	public static function get_order_language( \WC_Order $order ): ?string {
		$provider = self::get_provider();

		if ( 'wpml' === $provider ) {
			return self::get_order_language_wpml( $order );
		}

		if ( 'polylang' === $provider ) {
			return self::get_order_language_polylang( $order );
		}

		return null;
	}

	/**
	 * Sprawdza, czy podany kod języka jest wśród aktywnych języków witryny.
	 *
	 * @param string $lang Kod języka (np. 'pl', 'en').
	 * @return bool True gdy kod jest prawidłowy.
	 */
	public static function is_valid_lang_code( string $lang ): bool {
		if ( '' === $lang ) {
			return false;
		}

		$lang      = strtolower( $lang );
		$languages = self::get_active_languages();

		foreach ( $languages as $language ) {
			if ( strtolower( $language['code'] ) === $lang ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Czyści wewnętrzny cache memoizacji.
	 *
	 * Przydatne w testach jednostkowych — nie wywołuj w kodzie produkcyjnym.
	 *
	 * @internal
	 */
	public static function reset_cache(): void {
		self::$cache = array();
	}

	// -------------------------------------------------------------------------
	// Prywatne helpery WPML
	// -------------------------------------------------------------------------

	/**
	 * Pobiera aktywne języki z WPML.
	 *
	 * @return array<int,array{code:string,name:string,native_name:string,is_default:bool}>
	 */
	private static function get_active_languages_wpml(): array {
		$wpml_langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$default    = strtolower( (string) apply_filters( 'wpml_default_language', null ) );

		if ( ! is_array( $wpml_langs ) ) {
			return array();
		}

		$result = array();

		foreach ( $wpml_langs as $lang_data ) {
			if ( ! is_array( $lang_data ) ) {
				continue;
			}

			$code        = strtolower( (string) ( $lang_data['language_code'] ?? $lang_data['code'] ?? '' ) );
			$name        = (string) ( $lang_data['translated_name'] ?? $lang_data['native_name'] ?? $code );
			$native_name = (string) ( $lang_data['native_name'] ?? $name );

			if ( '' === $code ) {
				continue;
			}

			$result[] = array(
				'code'        => $code,
				'name'        => $name,
				'native_name' => $native_name,
				'is_default'  => ( $code === $default ),
			);
		}

		return $result;
	}

	/**
	 * Pobiera aktywne języki z Polylang.
	 *
	 * @return array<int,array{code:string,name:string,native_name:string,is_default:bool}>
	 */
	private static function get_active_languages_polylang(): array {
		if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_default_language' ) ) {
			return array();
		}

		$slugs   = pll_languages_list( array( 'fields' => 'slug' ) );
		$names   = pll_languages_list( array( 'fields' => 'name' ) );
		$default = strtolower( (string) pll_default_language( 'slug' ) );

		if ( ! is_array( $slugs ) ) {
			return array();
		}

		if ( ! is_array( $names ) ) {
			$names = array();
		}

		$result = array();

		foreach ( $slugs as $i => $slug ) {
			$code        = strtolower( (string) $slug );
			$name        = isset( $names[ $i ] ) ? (string) $names[ $i ] : $code;
			$native_name = $name; // Polylang nie rozróżnia name/native_name w prostym API.

			if ( '' === $code ) {
				continue;
			}

			$result[] = array(
				'code'        => $code,
				'name'        => $name,
				'native_name' => $native_name,
				'is_default'  => ( $code === $default ),
			);
		}

		return $result;
	}

	/**
	 * Wykrywa język zamówienia przez API WPML.
	 *
	 * Próbuje kolejno: meta 'wpml_language', meta '_wpml_language',
	 * następnie filtr 'wpml_element_language_code'.
	 *
	 * Uwaga HPOS: filtr 'wpml_element_language_code' z element_type='post_shop_order'
	 * działa wyłącznie dla klasycznych post-based orders. Pod HPOS (deklarowane przez
	 * ten plugin) ta próba zwykle zwraca null — wówczas detekcja opiera się wyłącznie
	 * o klucze meta zapisane przez WPML WooCommerce Multilingual.
	 *
	 * @param \WC_Order $order Zamówienie.
	 * @return string|null Kod języka lub null gdy niewykonalne.
	 */
	private static function get_order_language_wpml( \WC_Order $order ): ?string {
		// Próba 1 — meta bez podkreślenia (starsze WPML).
		$lang = strtolower( (string) $order->get_meta( 'wpml_language' ) );
		if ( '' !== $lang ) {
			return $lang;
		}

		// Próba 2 — meta z podkreśleniem (nowsze WPML).
		$lang = strtolower( (string) $order->get_meta( '_wpml_language' ) );
		if ( '' !== $lang ) {
			return $lang;
		}

		// Próba 3 — filtr WPML element language code (post-based orders; pod HPOS zwykle null).
		$lang = strtolower(
			(string) apply_filters(
				'wpml_element_language_code',
				null,
				array(
					'element_id'   => $order->get_id(),
					'element_type' => 'post_shop_order',
				)
			)
		);
		if ( '' !== $lang ) {
			return $lang;
		}

		return null;
	}

	/**
	 * Wykrywa język zamówienia przez API Polylang.
	 *
	 * Próbuje meta '_polylang_language', następnie pll_get_post_language().
	 *
	 * @param \WC_Order $order Zamówienie.
	 * @return string|null Kod języka lub null gdy niewykonalne.
	 */
	private static function get_order_language_polylang( \WC_Order $order ): ?string {
		// Próba 1 — meta Polylang.
		$lang = strtolower( (string) $order->get_meta( '_polylang_language' ) );
		if ( '' !== $lang ) {
			return $lang;
		}

		// Próba 2 — API Polylang (działa tylko dla klasycznych post-based orders).
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = strtolower( (string) pll_get_post_language( $order->get_id(), 'slug' ) );
			if ( '' !== $lang ) {
				return $lang;
			}
		}

		return null;
	}
}
