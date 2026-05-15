<?php
/**
 * Generator list HTML produktów na potrzeby e-maili z prośbą o ocenę.
 *
 * Klasa dostarcza dwie statyczne metody renderujące listy HTML:
 *  - render_products_list()     — lista produktów z linkami do strony produktu,
 *  - render_rating_links_list() — lista produktów z linkami kierującymi
 *    bezpośrednio do widgetu oceny (kotwica #ihumbak-wrs-rate).
 *
 * Klasa celowo NIE reimplementuje reguł pomijania (skip rules) —
 * przyjmuje gotową, przefiltrowaną tablicę $items przekazaną przez
 * Ihumbak_WRS_Email_Sender::process() po Kroku 7 (filter_already_rated_items).
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Email_Product_List
 *
 * Czysta klasa statyczna (bez hooków) generująca fragmenty HTML list produktów
 * do wstawiania w treść e-maila przez silnik szablonów.
 */
class Ihumbak_WRS_Email_Product_List {

	/**
	 * Fragment kotwicy URL wskazujący na widget oceny na stronie produktu.
	 * Musi być zgodny z atrybutem id="ihumbak-wrs-rate" w templates/widget-stars.php.
	 *
	 * @since 1.3.0
	 */
	const ANCHOR_FRAGMENT = 'ihumbak-wrs-rate';

	/**
	 * Renderuje listę HTML produktów z linkami do stron produktów.
	 *
	 * Przykładowy wynik:
	 * <ul class="ihumbak-wrs-products-list">
	 *   <li><a href="https://sklep.pl/produkt/foo/">Foo</a></li>
	 * </ul>
	 *
	 * @param WC_Order_Item_Product[] $items Przefiltrowana lista pozycji zamówienia.
	 * @return string Fragment HTML lub pusty ciąg, gdy lista byłaby pusta.
	 */
	public static function render_products_list( array $items ): string {
		return self::render_list( $items, false );
	}

	/**
	 * Renderuje listę HTML produktów z linkami do widgetu oceny (z kotwicą URL).
	 *
	 * Przykładowy wynik:
	 * <ul class="ihumbak-wrs-rating-links">
	 *   <li><a href="https://sklep.pl/produkt/foo/#ihumbak-wrs-rate">Foo</a></li>
	 * </ul>
	 *
	 * @param WC_Order_Item_Product[] $items Przefiltrowana lista pozycji zamówienia.
	 * @return string Fragment HTML lub pusty ciąg, gdy lista byłaby pusta.
	 */
	public static function render_rating_links_list( array $items ): string {
		return self::render_list( $items, true );
	}

	// -------------------------------------------------------------------------
	// Implementacja prywatna
	// -------------------------------------------------------------------------

	/**
	 * Wspólna implementacja renderowania listy HTML.
	 *
	 * Dla wariantów (variation_id > 0) używany jest permalink produktu nadrzędnego,
	 * ponieważ widget oceny jest osadzony na stronie produktu nadrzędnego — warianty
	 * nie mają własnej strony z widgetem.
	 *
	 * Kontrakt escapowania:
	 *   - href     → esc_url()
	 *   - link text → esc_html()
	 *   - fragment kotwicy → hardcoded literal (stała klasy, nie pochodzi od użytkownika)
	 *
	 * @param WC_Order_Item_Product[] $items      Pozycje zamówienia.
	 * @param bool                   $with_hash  True = dodaj kotwicę #ihumbak-wrs-rate do URL.
	 * @return string Fragment HTML lub pusty ciąg znaków.
	 */
	private static function render_list( array $items, bool $with_hash ): string {
		$list_items = array();

		foreach ( $items as $item ) {
			if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
				continue;
			}

			$name       = (string) $item->get_name();
			$product_id = (int) $item->get_product_id();

			// Dla wariantów get_product_id() zwraca ID produktu nadrzędnego — to jest
			// właśnie pożądane zachowanie, ponieważ widget żyje na stronie nadrzędnej.
			$permalink = get_permalink( $product_id );

			// Pomiń pozycję, jeśli nie udało się uzyskać poprawnego permalink.
			if ( false === $permalink || '' === $permalink ) {
				continue;
			}

			$href = $with_hash
				? esc_url( $permalink ) . '#' . self::ANCHOR_FRAGMENT
				: esc_url( $permalink );

			$list_items[] = '<li><a href="' . $href . '">' . esc_html( $name ) . '</a></li>';
		}

		// Nie generuj pustej listy — silnik szablonów zastąpi placeholder pustym ciągiem.
		if ( empty( $list_items ) ) {
			return '';
		}

		$css_class = $with_hash ? 'ihumbak-wrs-rating-links' : 'ihumbak-wrs-products-list';

		return '<ul class="' . $css_class . '">' . "\n"
			. implode( "\n", $list_items ) . "\n"
			. '</ul>';
	}
}
