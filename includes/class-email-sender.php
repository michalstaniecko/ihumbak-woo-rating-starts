<?php
/**
 * Sender wiadomości e-mail z prośbą o ocenę produktu.
 *
 * Obsługuje hook Action Scheduler `ihumbak_wrs_send_review_email` zaplanowany
 * przez `Ihumbak_WRS_Email_Scheduler`. Implementuje pięcioetapowy potok
 * reguł pomijania (feature flag, stan zamówienia, wykluczenia produktów,
 * wykluczenia kategorii, już ocenione), buduje kontekst dla silnika szablonów
 * z issue #4, renderuje temat i treść, po czym wysyła wiadomość przez `wp_mail`.
 *
 * Klasa nie loguje trwale błędów ani nie wyświetla powiadomień admina —
 * wszelkie diagnostyki trafiają wyłącznie do error_log gdy WP_DEBUG i
 * WP_DEBUG_LOG są aktywne.
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Email_Sender
 *
 * Nasłuchuje na hook AS `ihumbak_wrs_send_review_email` i wysyła wiadomość
 * e-mail z prośbą o ocenę zakupionych produktów.
 */
class Ihumbak_WRS_Email_Sender {

	/**
	 * Nazwa hooka Action Scheduler — identyczna ze stałą schedulera.
	 * Reużycie stałej eliminuje duplikację stringa w kodzie.
	 */
	const HOOK_SEND = Ihumbak_WRS_Email_Scheduler::SEND_ACTION_HOOK;

	/**
	 * Priorytet rejestracji hooka.
	 */
	const PRIORITY = 10;

	/**
	 * Liczba argumentów przyjmowanych przez handler hooka.
	 * Action Scheduler rozpakowuje tablicę z build_args() pozycyjnie
	 * (do_action_ref_array), więc callback otrzymuje (int $order_id, int $step).
	 */
	const ACCEPTED_ARGS = 2;

	/**
	 * Konstruktor — rejestruje hook i utrzymuje logikę wiring testowalną.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Rejestruje handler hooka Action Scheduler.
	 */
	public function register_hooks(): void {
		add_action( self::HOOK_SEND, array( $this, 'handle_send' ), self::PRIORITY, self::ACCEPTED_ARGS );
	}

	// -------------------------------------------------------------------------
	// Punkt wejścia wywoływany przez Action Scheduler
	// -------------------------------------------------------------------------

	/**
	 * Główny handler wywoływany przez Action Scheduler.
	 *
	 * AS przekazuje wartości z build_args() pozycyjnie — odbieramy je jako
	 * dwa skalarne argumenty zamiast pojedynczej tablicy.
	 *
	 * Wykonuje pełny potok walidacji i reguł pomijania, buduje kontekst,
	 * renderuje wiadomość i wywołuje dispatch(). Wszystkie wyjątki są przechwytywane.
	 *
	 * @param int $order_id ID zamówienia.
	 * @param int $step     Numer kroku sekwencji (0 = wiadomość początkowa).
	 */
	public function handle_send( $order_id = 0, $step = 0 ): void {
		$order_id = (int) $order_id;
		$step     = (int) $step;

		try {
			$this->process( $order_id, $step );
		} catch ( \Throwable $e ) {
			$this->log_failure(
				'Nieoczekiwany wyjątek w handle_send',
				array(
					'order_id' => $order_id,
					'step'     => $step,
					'error'    => $e->getMessage(),
					'file'     => $e->getFile(),
					'line'     => $e->getLine(),
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// Potok przetwarzania (prywatny)
	// -------------------------------------------------------------------------

	/**
	 * Właściwy potok — walidacja, reguły pomijania, render, dispatch.
	 *
	 * @param int $order_id ID zamówienia przekazane przez AS.
	 * @param int $step     Numer kroku sekwencji.
	 */
	private function process( int $order_id, int $step ): void {

		// Krok 1 — Walidacja argumentów.
		if ( $order_id <= 0 ) {
			$this->log_failure( 'Nieprawidłowe order_id', array( 'order_id' => $order_id, 'step' => $step ) );
			return;
		}

		// Krok 2 — Reguła 1: Funkcjonalność wyłączona.
		if ( $this->should_skip_feature_disabled() ) {
			return;
		}

		// Krok 3 — Pobranie zamówienia + Reguła 2: Stan zamówienia.
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			$this->log_failure( 'Zamówienie nie istnieje lub nie jest WC_Order', array( 'order_id' => $order_id ) );
			return;
		}

		if ( $this->should_skip_order_state( $order ) ) {
			return;
		}

		// Krok 4 — Ustalenie odbiorcy.
		$email = $this->get_recipient_email( $order );

		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->log_failure( 'Brak prawidłowego adresu e-mail odbiorcy', array( 'order_id' => $order_id ) );
			return;
		}

		$user_id = (int) $order->get_user_id();

		// Krok 5 — Zebranie pozycji zamówienia.
		$items = $order->get_items( 'line_item' );

		if ( empty( $items ) ) {
			return;
		}

		// Krok 6 — Reguła 3: Wykluczone produkty i kategorie.
		$items = $this->filter_excluded_items( $items );

		if ( empty( $items ) ) {
			return;
		}

		// Krok 7 — Reguła 4: Już ocenione produkty.
		$items = $this->filter_already_rated_items( $items, $order );

		// Krok 8 — Reguła 5: Pusta lista po filtracji.
		if ( empty( $items ) ) {
			return;
		}

		// Krok 9 — Budowanie kontekstu, render, dispatch.
		$raw_subject = (string) get_option( 'ihumbak_wrs_email_subject', '' );
		$raw_body    = (string) get_option( 'ihumbak_wrs_email_body', '' );

		// Temat renderowany z surowymi wartościami (nagłówek plain-text).
		// Dla products_list i rating_links_list podstawiamy pusty ciąg — zapobiega
		// to wyciekowi surowego HTML do tematu, gdy admin omyłkowo wstawi te tokeny.
		$subject_context = $this->build_context( $order ) + array(
			'products_list'     => '',
			'rating_links_list' => '',
		);
		$subject = Ihumbak_WRS_Email_Template::render( $raw_subject, $subject_context );

		// Treść renderowana z wartościami bezpiecznymi HTML.
		// Skalary są escapowane przez esc_html()/esc_url(); listy produktów
		// są już bezpiecznym HTML z Ihumbak_WRS_Email_Product_List — nie escapować.
		$html_context = $this->build_html_context( $order, $items );
		$body         = Ihumbak_WRS_Email_Template::render( $raw_body, $html_context );

		if ( '' === trim( $subject ) || '' === trim( $body ) ) {
			$this->log_failure(
				'Pusty temat lub treść po renderowaniu — pominięto wysyłkę',
				array( 'order_id' => $order_id )
			);
			return;
		}

		$this->dispatch( $email, $subject, $body, $order );
	}

	// -------------------------------------------------------------------------
	// Reguły pomijania
	// -------------------------------------------------------------------------

	/**
	 * Reguła 1 — Sprawdza, czy funkcjonalność e-mail jest wyłączona.
	 *
	 * @return bool True gdy należy pominąć wysyłkę.
	 */
	private function should_skip_feature_disabled(): bool {
		return ! (bool) get_option( 'ihumbak_wrs_email_enabled', false );
	}

	/**
	 * Reguła 2 — Sprawdza, czy zamówienie jest w stanie wykluczającym wysyłkę.
	 *
	 * Pobiera status bez prefiksu `wc-` (WC_Order::get_status() zwraca bez prefiksu).
	 * Weryfikuje opcję `ihumbak_wrs_email_skip_refunded` sterującą zachowaniem.
	 *
	 * @param \WC_Order $order Obiekt zamówienia HPOS-compatible.
	 * @return bool True gdy należy pominąć wysyłkę.
	 */
	private function should_skip_order_state( \WC_Order $order ): bool {
		if ( ! (bool) get_option( 'ihumbak_wrs_email_skip_refunded', true ) ) {
			return false;
		}

		$status = $order->get_status(); // Bez prefiksu wc-.

		return in_array( $status, array( 'refunded', 'cancelled' ), true );
	}

	/**
	 * Reguła 3 — Filtruje pozycje zamówienia wykluczone przez ustawienia.
	 *
	 * Wyklucza pozycje, których product_id lub ID produktu nadrzędnego (dla
	 * wariantów) figuruje na liście wykluczonych produktów, albo których
	 * produkt należy do wykluczonej kategorii produktowej.
	 *
	 * @param \WC_Order_Item[] $items Pozycje zamówienia z get_items('line_item').
	 * @return \WC_Order_Item[] Przefiltrowane pozycje.
	 */
	private function filter_excluded_items( array $items ): array {
		$excluded_products   = (array) get_option( 'ihumbak_wrs_email_excluded_products', array() );
		$excluded_categories = (array) get_option( 'ihumbak_wrs_email_excluded_categories', array() );

		$excluded_products   = array_map( 'intval', $excluded_products );
		$excluded_categories = array_map( 'intval', $excluded_categories );

		// Jeśli brak wykluczeń — zwróć pozycje bez filtracji.
		if ( empty( $excluded_products ) && empty( $excluded_categories ) ) {
			return $items;
		}

		$filtered = array();

		foreach ( $items as $item_id => $item ) {
			if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
				continue;
			}

			$product_id = (int) $item->get_product_id();
			// Dla wariantów sprawdzamy też ID rodzica.
			$parent_id = (int) $item->get_variation_id() > 0
				? (int) $item->get_product_id()
				: 0;

			// Wyklucz po ID produktu lub rodzica.
			if ( ! empty( $excluded_products ) ) {
				if ( in_array( $product_id, $excluded_products, true ) ) {
					continue;
				}
				if ( $parent_id > 0 && in_array( $parent_id, $excluded_products, true ) ) {
					continue;
				}
			}

			// Wyklucz po kategorii produktu.
			if ( ! empty( $excluded_categories ) ) {
				$product_terms = get_the_terms( $product_id, 'product_cat' );
				if ( is_array( $product_terms ) ) {
					$product_cat_ids = wp_list_pluck( $product_terms, 'term_id' );
					$product_cat_ids = array_map( 'intval', $product_cat_ids );
					if ( array_intersect( $product_cat_ids, $excluded_categories ) ) {
						continue;
					}
				}
			}

			$filtered[ $item_id ] = $item;
		}

		return $filtered;
	}

	/**
	 * Reguła 4 — Filtruje pozycje, które klient już ocenił.
	 *
	 * Pomija całą regułę gdy opcja `ihumbak_wrs_email_skip_already_rated` jest wyłączona.
	 *
	 * Dla zalogowanych klientów (user_id > 0): sprawdza zarówno szybkie oceny
	 * (tabela woo_quick_ratings, po user_id) jak i komentarze WC (po user_id
	 * lub email autora).
	 * Dla gości (user_id = 0): nie można sprawdzić szybkich ocen (brak kolumny
	 * email) — fallback wyłącznie do komentarzy WC po adresie email autora.
	 *
	 * @param \WC_Order_Item[] $items   Pozycje po filtrze wykluczeń.
	 * @param \WC_Order        $order   Zamówienie — dostarcza user_id i email.
	 * @return \WC_Order_Item[] Pozycje, których klient jeszcze nie ocenił.
	 */
	private function filter_already_rated_items( array $items, \WC_Order $order ): array {
		if ( ! (bool) get_option( 'ihumbak_wrs_email_skip_already_rated', true ) ) {
			return $items;
		}

		$user_id = (int) $order->get_user_id();
		$email   = (string) $order->get_billing_email();

		$filtered = array();

		foreach ( $items as $item_id => $item ) {
			if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
				continue;
			}

			$product_id = (int) $item->get_product_id();

			if ( $this->customer_has_rated_product( $product_id, $user_id, $email ) ) {
				continue;
			}

			$filtered[ $item_id ] = $item;
		}

		return $filtered;
	}

	/**
	 * Sprawdza, czy klient już ocenił dany produkt.
	 *
	 * Łączy weryfikację w obu źródłach danych: szybkich ocenach pluginu
	 * (dla zalogowanych) i komentarzach WooCommerce (dla wszystkich).
	 *
	 * @param int    $product_id ID produktu.
	 * @param int    $user_id    ID użytkownika (0 dla gości).
	 * @param string $email      Adres e-mail z zamówienia.
	 * @return bool True jeśli klient już ocenił produkt.
	 */
	private function customer_has_rated_product( int $product_id, int $user_id, string $email ): bool {
		global $wpdb;

		// Sprawdź szybkie oceny pluginu (tylko dla zalogowanych).
		if ( $user_id > 0 ) {
			$table = $wpdb->prefix . 'woo_quick_ratings';
			// Tabela może nie istnieć w środowiskach testowych — zabezpieczenie.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$quick_rating = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE product_id = %d AND user_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$product_id,
					$user_id
				)
			);

			if ( null !== $quick_rating ) {
				return true;
			}
		}

		// Sprawdź recenzje WooCommerce (komentarze WordPress).
		$comment_args = array(
			'post_id'    => $product_id,
			'type'       => 'review',
			'status'     => 'approve',
			'count'      => true,
			'meta_query' => array(
				array(
					'key'     => 'rating',
					'compare' => 'EXISTS',
				),
			),
		);

		// Dla zalogowanych — szukaj po user_id.
		if ( $user_id > 0 ) {
			$comment_args['user_id'] = $user_id;
			$count_by_user           = get_comments( $comment_args );
			if ( $count_by_user > 0 ) {
				return true;
			}
		}

		// Dla wszystkich (lub gdy szukanie po user_id nie dało wyniku) — szukaj po email.
		if ( ! empty( $email ) ) {
			unset( $comment_args['user_id'] );
			$comment_args['author_email'] = $email;
			$count_by_email               = get_comments( $comment_args );
			if ( $count_by_email > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Statyczny helper pomocniczy — czysta funkcja diff używana przez filter_already_rated_items()
	 * i przez test CLI.
	 *
	 * Zwraca elementy z $product_ids, które NIE figurują w $rated_product_ids.
	 * Oba zbiory są rzutowane na int dla bezpieczeństwa typów (string "42" == int 42).
	 * Duplikaty w $product_ids są eliminowane, kolejność pierwotna jest zachowana.
	 *
	 * @param int[] $product_ids       Lista ID produktów z zamówienia.
	 * @param int[] $rated_product_ids Lista ID produktów już ocenionych przez klienta.
	 * @return int[] Produkty jeszcze nieocenione.
	 */
	public static function filter_items_against_rated_set( array $product_ids, array $rated_product_ids ): array {
		$rated_product_ids = array_map( 'intval', $rated_product_ids );

		// Eliminuj duplikaty, zachowaj kolejność, rzutuj na int.
		$seen   = array();
		$unique = array();
		foreach ( $product_ids as $id ) {
			$id = (int) $id;
			if ( ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$unique[]    = $id;
			}
		}

		return array_values(
			array_filter(
				$unique,
				static function ( int $id ) use ( $rated_product_ids ): bool {
					return ! in_array( $id, $rated_product_ids, true );
				}
			)
		);
	}

	// -------------------------------------------------------------------------
	// Budowanie kontekstu
	// -------------------------------------------------------------------------

	/**
	 * Buduje tablicę kontekstu przekazywaną do Ihumbak_WRS_Email_Template::render().
	 *
	 * Wartości surowe (nie-HTML) — escaping odbywa się w build_html_context() przed
	 * wywołaniem render() dla body lub jest pominięty dla subject (nagłówek plain-text).
	 *
	 * Klucz coupon_code jest poza zakresem — silnik szablonów zastąpi go pustym
	 * ciągiem zgodnie ze swoim kontraktem dla nieznanych kluczy.
	 *
	 * @param \WC_Order $order Zamówienie.
	 * @return array<string,string> Kontekst surowy (bez HTML escaping).
	 */
	private function build_context( \WC_Order $order ): array {
		$date_created = $order->get_date_created();
		$order_date   = $date_created instanceof \WC_DateTime
			? wc_format_datetime( $date_created )
			: '';

		return array(
			'customer_name'       => $order->get_formatted_billing_full_name(),
			'customer_first_name' => $order->get_billing_first_name(),
			'customer_last_name'  => $order->get_billing_last_name(),
			'order_number'        => (string) $order->get_order_number(),
			'order_date'          => $order_date,
			'site_name'           => get_bloginfo( 'name' ),
			'site_url'            => home_url(),
		);
	}

	/**
	 * Buduje kontekst HTML dla render() treści wiadomości.
	 *
	 * Skalarne wartości z build_context() są escapowane przez esc_html()/esc_url().
	 * Wartości products_list i rating_links_list są już bezpiecznym HTML wygenerowanym
	 * przez Ihumbak_WRS_Email_Product_List — NIE należy ich owijać w esc_html().
	 *
	 * @param \WC_Order                 $order Zamówienie.
	 * @param \WC_Order_Item_Product[]  $items Przefiltrowane pozycje zamówienia.
	 * @return array<string,string> Kontekst gotowy do przekazania do silnika szablonów.
	 */
	private function build_html_context( \WC_Order $order, array $items ): array {
		$context = $this->build_context( $order );

		return array(
			'customer_name'       => esc_html( $context['customer_name'] ),
			'customer_first_name' => esc_html( $context['customer_first_name'] ),
			'customer_last_name'  => esc_html( $context['customer_last_name'] ),
			'order_number'        => esc_html( $context['order_number'] ),
			'order_date'          => esc_html( $context['order_date'] ),
			'site_name'           => esc_html( $context['site_name'] ),
			'site_url'            => esc_url( $context['site_url'] ),
			'products_list'       => Ihumbak_WRS_Email_Product_List::render_products_list( $items ),
			'rating_links_list'   => Ihumbak_WRS_Email_Product_List::render_rating_links_list( $items ),
		);
	}

	// -------------------------------------------------------------------------
	// Wysyłka
	// -------------------------------------------------------------------------

	/**
	 * Wysyła wiadomość e-mail z treścią HTML przez wp_mail.
	 *
	 * Filtr `wp_mail_content_type` jest dodawany wyłącznie na czas wywołania
	 * wp_mail i zawsze usuwany w bloku `finally` — nawet jeśli wp_mail
	 * rzuci wyjątek. Zapobiega to globalnej zmianie content-type dla innych
	 * wiadomości wysyłanych w tym samym requestu.
	 *
	 * @param string    $to       Adres odbiorcy.
	 * @param string    $subject  Wyrenderowany temat wiadomości.
	 * @param string    $body_html Wyrenderowana treść HTML.
	 * @param \WC_Order $order    Zamówienie (używane do logowania błędów).
	 * @return bool Wynik wp_mail — true jeśli wiadomość została przekazana do MTA.
	 */
	private function dispatch( string $to, string $subject, string $body_html, \WC_Order $order ): bool {
		$headers   = $this->build_headers();
		$filter_cb = array( $this, 'force_html_content_type' );

		add_filter( 'wp_mail_content_type', $filter_cb );

		$sent = false;
		try {
			$sent = (bool) wp_mail( $to, $subject, $body_html, $headers );
		} catch ( \Throwable $e ) {
			$this->log_failure(
				'wp_mail rzucił wyjątek',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
		} finally {
			remove_filter( 'wp_mail_content_type', $filter_cb );
		}

		if ( ! $sent ) {
			$this->log_failure(
				'wp_mail zwrócił false',
				array( 'order_id' => $order->get_id() )
			);
		}

		return $sent;
	}

	/**
	 * Buduje tablicę nagłówków dla wp_mail.
	 *
	 * Nagłówki From i Reply-To są emitowane wyłącznie gdy odpowiednie opcje
	 * są niepuste — w przeciwnym razie WooCommerce / WordPress używa własnych
	 * wartości domyślnych.
	 *
	 * @return string[] Tablica nagłówków email.
	 */
	private function build_headers(): array {
		$headers = array();

		$from_name_raw  = (string) get_option( 'ihumbak_wrs_email_from_name', '' );
		$from_email_raw = (string) get_option( 'ihumbak_wrs_email_from_email', '' );
		$reply_to_raw   = (string) get_option( 'ihumbak_wrs_email_reply_to', '' );

		// Defensywnie usuwamy CR/LF przeciwko header injection, potem sanityzujemy.
		$from_name  = sanitize_text_field( str_replace( array( "\r", "\n" ), '', $from_name_raw ) );
		$from_email = sanitize_email( str_replace( array( "\r", "\n" ), '', $from_email_raw ) );
		$reply_to   = sanitize_email( str_replace( array( "\r", "\n" ), '', $reply_to_raw ) );

		// sanitize_email zwraca '' dla nieprawidłowych adresów — wtedy nie emitujemy nagłówka i WC użyje domyślnego.
		if ( '' !== $from_email && is_email( $from_email ) ) {
			if ( '' !== $from_name ) {
				$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
			} else {
				$headers[] = 'From: ' . $from_email;
			}
		}

		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return $headers;
	}

	/**
	 * Wymusza typ treści text/html dla wp_mail przez filtr `wp_mail_content_type`.
	 *
	 * Metoda musi być publiczna, żeby można ją było przekazać do add_filter /
	 * remove_filter jako callable z callbackiem na obiekt.
	 *
	 * @return string MIME type wiadomości e-mail.
	 */
	public function force_html_content_type(): string {
		return 'text/html';
	}

	// -------------------------------------------------------------------------
	// Helpery
	// -------------------------------------------------------------------------

	/**
	 * Zwraca adres e-mail odbiorcy pobrany z danych rozliczeniowych zamówienia.
	 *
	 * Używa HPOS-compatible WC_Order::get_billing_email() — nigdy get_post_meta().
	 *
	 * @param \WC_Order $order Zamówienie.
	 * @return string Adres e-mail lub pusty ciąg gdy niedostępny.
	 */
	private function get_recipient_email( \WC_Order $order ): string {
		return (string) $order->get_billing_email();
	}

	/**
	 * Zwraca unikalne ID produktów (i rodziców wariantów) z pozycji zamówienia.
	 *
	 * Metoda pomocnicza — używana do budowania listy produktów do sprawdzenia
	 * w regule 4. Używa HPOS-compatible WC_Order::get_items().
	 *
	 * @param \WC_Order $order Zamówienie.
	 * @return int[] Tablica unikalnych ID produktów.
	 */
	private function get_purchased_product_ids( \WC_Order $order ): array {
		$ids = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! ( $item instanceof \WC_Order_Item_Product ) ) {
				continue;
			}
			$product_id = (int) $item->get_product_id();
			if ( $product_id > 0 ) {
				$ids[] = $product_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Loguje błąd wyłącznie gdy WP_DEBUG i WP_DEBUG_LOG są aktywne.
	 *
	 * Nie wyświetla powiadomień admina ani nie wywołuje trigger_error().
	 * Komunikaty logów nie wymagają tłumaczenia.
	 *
	 * @param string $message Opis błędu (po polsku, wewnętrzny).
	 * @param array  $context Dodatkowe dane diagnostyczne.
	 */
	private function log_failure( string $message, array $context = [] ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			return;
		}

		$entry = '[Ihumbak_WRS_Email_Sender] ' . $message;

		if ( ! empty( $context ) ) {
			$entry .= ' | ' . wp_json_encode( $context );
		}

		error_log( $entry ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
