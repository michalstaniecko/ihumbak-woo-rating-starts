<?php
/**
 * Narzędzia administracyjne dla wysyłki e-maili z prośbą o ocenę.
 *
 * Klasa dostarcza dwa interfejsy administracyjne:
 *  1. Formularz testowego wysyłania wiadomości na stronie ustawień e-mail.
 *  2. Akcja ręcznego ponownego wysłania wiadomości z ekranu edycji zamówienia WC.
 *
 * Obydwa wejścia są chronione przez uprawnienie `manage_woocommerce` i nonce.
 * Wyniki są prezentowane przez przejściowe powiadomienia admina (transient, 60s).
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Admin_Email_Tools
 *
 * Rejestruje formularz testu wysyłki na stronie ustawień e-mail oraz akcję
 * ręcznego ponownego wysłania z ekranu zamówienia WooCommerce.
 */
class Ihumbak_WRS_Admin_Email_Tools {

	// -------------------------------------------------------------------------
	// Stałe
	// -------------------------------------------------------------------------

	/**
	 * Nazwa akcji admin-post.php dla testu wysyłki.
	 */
	const ADMIN_POST_TEST = 'ihumbak_wrs_email_test_send';

	/**
	 * Akcja nonce dla formularza testu wysyłki.
	 */
	const NONCE_ACTION = 'ihumbak_wrs_email_test_send_nonce';

	/**
	 * Nazwa pola nonce w formularzu.
	 */
	const NONCE_FIELD = '_ihumbak_wrs_nonce';

	/**
	 * Klucz akcji zamówienia WooCommerce (bez prefiksu `woocommerce_order_action_`).
	 */
	const ORDER_ACTION_KEY = 'ihumbak_wrs_send_review_request';

	/**
	 * Prefiks nazwy transientu przechowującego oczekujące powiadomienie admina.
	 * Sufiks to ID zalogowanego użytkownika.
	 */
	const NOTICE_TRANSIENT_PREFIX = 'ihumbak_wrs_admin_notice_';

	// -------------------------------------------------------------------------
	// Konstruktor
	// -------------------------------------------------------------------------

	/**
	 * Konstruktor — rejestracja wszystkich hooków.
	 */
	public function __construct() {
		// Formularz testu wysyłki — handler admin-post.php.
		add_action( 'admin_post_' . self::ADMIN_POST_TEST, array( $this, 'handle_test_send' ) );

		// Akcja na ekranie zamówienia WooCommerce.
		add_filter( 'woocommerce_order_actions', array( $this, 'register_order_action' ) );
		add_action( 'woocommerce_order_action_' . self::ORDER_ACTION_KEY, array( $this, 'handle_order_action' ) );

		// Meta-box ze statusem wysyłki na ekranie zamówienia (legacy + HPOS).
		add_action( 'add_meta_boxes', array( $this, 'register_order_meta_box' ), 30, 2 );

		// Powiadomienia admina.
		add_action( 'admin_notices', array( $this, 'render_pending_notice' ) );

		// Formularz testu na stronie ustawień — wstrzykiwany przez hook.
		add_action( 'ihumbak_wrs_after_email_settings_form', array( $this, 'render_test_send_box' ) );
	}

	// -------------------------------------------------------------------------
	// Formularz testu wysyłki
	// -------------------------------------------------------------------------

	/**
	 * Renderuje kartę z formularzem testowego wysyłania wiadomości.
	 *
	 * Wywoływana przez hook `ihumbak_wrs_after_email_settings_form` wstrzykiwany
	 * na końcu render_page() w Ihumbak_WRS_Admin_Email_Settings — po zamknięciu
	 * głównego formularza ustawień.
	 */
	public function render_test_send_box(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$current_user_email = (string) wp_get_current_user()->user_email;
		?>
		<div class="ihumbak-wrs-email-test-card card" style="max-width:none;margin-top:24px;">
			<h2><?php esc_html_e( 'Wyślij testowy e-mail / Send test email', 'ihumbak-woo-rating-stars' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ADMIN_POST_TEST ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="ihumbak_wrs_test_recipient">
									<?php esc_html_e( 'Odbiorca / Recipient', 'ihumbak-woo-rating-stars' ); ?>
								</label>
							</th>
							<td>
								<input
									type="email"
									id="ihumbak_wrs_test_recipient"
									name="recipient"
									value="<?php echo esc_attr( $current_user_email ); ?>"
									class="regular-text"
									required
								/>
								<p class="description">
									<?php esc_html_e( 'Adres e-mail, na który zostanie wysłana wiadomość testowa.', 'ihumbak-woo-rating-stars' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ihumbak_wrs_test_sample_order_id">
									<?php esc_html_e( 'ID zamówienia (opcjonalne) / Sample order ID (optional)', 'ihumbak-woo-rating-stars' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="ihumbak_wrs_test_sample_order_id"
									name="sample_order_id"
									value=""
									class="small-text"
									min="1"
									step="1"
								/>
								<p class="description">
									<?php esc_html_e( 'Treść testowa zawiera dane najnowszego ukończonego zamówienia, jeśli nie podasz ID. Wpisz ID, aby użyć konkretnego zamówienia.', 'ihumbak-woo-rating-stars' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php
				submit_button(
					__( 'Wyślij testowy e-mail / Send test email', 'ihumbak-woo-rating-stars' ),
					'secondary',
					'ihumbak_wrs_send_test',
					true
				);
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Obsługuje przesłanie formularza testowego wysyłania wiadomości.
	 *
	 * Waliduje uprawnienia i nonce, pobiera i sanitizuje dane wejściowe,
	 * wywołuje send_test() na obiekcie sendera, zapisuje powiadomienie
	 * w transiencie i przekierowuje z powrotem na stronę ustawień.
	 */
	public function handle_test_send(): void {
		// Weryfikacja uprawnień.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__( 'Brak uprawnień / Insufficient permissions.', 'ihumbak-woo-rating-stars' ),
				'',
				array( 'response' => 403 )
			);
		}

		// Weryfikacja nonce.
		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		// Sanitizacja danych wejściowych.
		$recipient = sanitize_email( (string) wp_unslash( $_POST['recipient'] ?? '' ) );

		$sample_order_id_raw = isset( $_POST['sample_order_id'] ) ? absint( $_POST['sample_order_id'] ) : 0;
		$sample_order_id     = $sample_order_id_raw > 0 ? $sample_order_id_raw : null;

		// Walidacja adresu e-mail odbiorcy.
		if ( empty( $recipient ) || ! is_email( $recipient ) ) {
			$this->store_notice( 'error', __( 'Podany adres e-mail odbiorcy jest nieprawidłowy.', 'ihumbak-woo-rating-stars' ) );
			$this->redirect_to_settings();
			return;
		}

		// Wywołanie sendera.
		$sender = new Ihumbak_WRS_Email_Sender();
		$result = $sender->send_test( $recipient, $sample_order_id );

		// Zapisanie powiadomienia z wynikiem.
		if ( Ihumbak_WRS_Email_Send_Result::STATUS_SENT === $result->get_status() ) {
			$this->store_notice(
				'success',
				sprintf(
					/* translators: %s: adres e-mail odbiorcy */
					__( 'Testowa wiadomość e-mail została wysłana na adres %s.', 'ihumbak-woo-rating-stars' ),
					esc_html( $recipient )
				)
			);
		} else {
			$notice_type = Ihumbak_WRS_Email_Send_Result::STATUS_SKIPPED === $result->get_status() ? 'warning' : 'error';
			$this->store_notice(
				$notice_type,
				$result->get_message()
					?: __( 'Nie udało się wysłać testowej wiadomości e-mail.', 'ihumbak-woo-rating-stars' )
			);
		}

		$this->redirect_to_settings();
	}

	// -------------------------------------------------------------------------
	// Akcja ręcznego ponownego wysłania z ekranu zamówienia
	// -------------------------------------------------------------------------

	/**
	 * Rejestruje akcję ręcznego wysłania w meta-boxie "Order actions" WooCommerce.
	 *
	 * Filtr `woocommerce_order_actions` — WC obsługuje nonce dla tej akcji
	 * wewnętrznie przez swój własny mechanizm meta-boksa.
	 *
	 * @param array $actions Istniejące akcje zamówień.
	 * @return array Akcje z dodaną akcją wysyłki e-maila.
	 */
	public function register_order_action( array $actions ): array {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}

		$actions[ self::ORDER_ACTION_KEY ] = __( 'Wyślij e-mail z prośbą o ocenę / Send review request email', 'ihumbak-woo-rating-stars' );

		return $actions;
	}

	/**
	 * Obsługuje akcję ręcznego wysłania wiadomości z ekranu zamówienia.
	 *
	 * Wywoływana przez hook `woocommerce_order_action_{key}` — WooCommerce
	 * przekazuje obiekt zamówienia jako pierwszy argument. Nonce jest
	 * weryfikowane przez WC przed wywołaniem tego hooka.
	 *
	 * Respektuje reguły pomijania (skip rules) identycznie jak ścieżka AS-driven.
	 * Zapisuje notatkę do zamówienia we wszystkich przypadkach (audyt).
	 *
	 * @param \WC_Order $order Obiekt zamówienia WooCommerce.
	 */
	public function handle_order_action( $order ): void {
		// Defensywna weryfikacja typu i uprawnień.
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Wywołanie sendera — respektuje skip rules.
		$sender = new Ihumbak_WRS_Email_Sender();
		$result = $sender->send_for_order( $order->get_id(), 0 );

		// Zapisz powiadomienie dla admina.
		$this->store_notice_for_result( $result, $order );

		// Zapisz notatkę do zamówienia (ślad audytu).
		$note = $this->format_order_note( $result, $order );
		$order->add_order_note( $note, false, true );
	}

	// -------------------------------------------------------------------------
	// Meta-box statusu wysyłki na ekranie zamówienia (issue #9)
	// -------------------------------------------------------------------------

	/**
	 * Rejestruje meta-box pokazujący status wysyłki dla bieżącego zamówienia.
	 *
	 * Obsługuje oba ekrany: klasyczny CPT (`shop_order`) i HPOS
	 * (`woocommerce_page_wc-orders`). Pierwszy argument hooka `add_meta_boxes`
	 * to post_type dla legacy i screen_id dla HPOS — dla zamówień obie wartości
	 * trafiają do listy dozwolonych, więc kontroler obsługuje obie ścieżki.
	 *
	 * @since 1.2.0
	 *
	 * @param string $screen_id Post type (legacy) lub screen ID (HPOS).
	 * @param mixed  $object    WP_Post (legacy) lub WC_Order (HPOS); nieużywany tutaj.
	 */
	public function register_order_meta_box( $screen_id, $object = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $object );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$allowed = array( 'shop_order', 'woocommerce_page_wc-orders' );
		if ( ! in_array( $screen_id, $allowed, true ) ) {
			return;
		}

		add_meta_box(
			'ihumbak_wrs_review_emails',
			__( 'Wiadomości z prośbą o ocenę / Review request emails', 'ihumbak-woo-rating-stars' ),
			array( $this, 'render_order_meta_box' ),
			$screen_id,
			'side',
			'default'
		);
	}

	/**
	 * Renderuje treść meta-boxu statusu wysyłki dla pojedynczego zamówienia.
	 *
	 * Pokazuje:
	 *  - zaplanowane wysyłki AS per krok (z timestampem),
	 *  - skróconą konfigurację follow-upów,
	 *  - hint o tym, że ręczna wysyłka uruchamia łańcuch przypomnień.
	 *
	 * Nie pokazuje historii wysyłek (osobny ticket #11 — log wysyłek).
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $post_or_order WP_Post (legacy) lub WC_Order (HPOS).
	 */
	public function render_order_meta_box( $post_or_order ): void {
		// Sprowadzenie do WC_Order niezależnie od trybu.
		if ( $post_or_order instanceof \WC_Order ) {
			$order = $post_or_order;
		} elseif ( is_object( $post_or_order ) && isset( $post_or_order->ID ) ) {
			$order = wc_get_order( (int) $post_or_order->ID );
		} else {
			$order = false;
		}

		if ( ! ( $order instanceof \WC_Order ) ) {
			echo '<p>' . esc_html__( 'Nie udało się załadować zamówienia. / Could not load order.', 'ihumbak-woo-rating-stars' ) . '</p>';
			return;
		}

		$order_id = (int) $order->get_id();
		$enabled  = (bool) get_option( 'ihumbak_wrs_email_enabled', false );

		if ( ! $enabled ) {
			echo '<p>' . esc_html__( 'Wysyłka e-maili jest globalnie wyłączona. / Email sending is globally disabled.', 'ihumbak-woo-rating-stars' ) . '</p>';
			return;
		}

		// 1. Zaplanowane kroki.
		$pending = Ihumbak_WRS_Email_Scheduler::get_pending_steps_for_order( $order_id );

		echo '<p style="margin-top:0;"><strong>' . esc_html__( 'Zaplanowane / Scheduled', 'ihumbak-woo-rating-stars' ) . '</strong></p>';

		if ( empty( $pending ) ) {
			echo '<p style="margin:0 0 12px 0;">' . esc_html__( 'Brak zaplanowanych wysyłek. / No scheduled sends.', 'ihumbak-woo-rating-stars' ) . '</p>';
		} else {
			echo '<ul style="margin:0 0 12px 0; padding-left: 1.2em;">';
			$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			foreach ( $pending as $step => $timestamp ) {
				$label = $this->format_step_label( (int) $step );
				$when  = wp_date( $datetime_format, (int) $timestamp );
				printf(
					'<li><strong>%1$s</strong><br><span class="description">%2$s</span></li>',
					esc_html( $label ),
					esc_html( (string) $when )
				);
			}
			echo '</ul>';
		}

		// 2. Konfiguracja follow-upów.
		$followups = get_option( 'ihumbak_wrs_email_followups', array() );
		if ( ! is_array( $followups ) ) {
			$followups = array();
		}
		$followups   = array_values( array_filter( $followups, 'is_array' ) );
		$config_max  = (int) Ihumbak_WRS_Email_Scheduler::MAX_FOLLOWUPS;
		$config_used = min( $config_max, count( $followups ) );

		echo '<p><strong>' . esc_html__( 'Konfiguracja follow-up / Follow-up configuration', 'ihumbak-woo-rating-stars' ) . '</strong></p>';

		if ( 0 === $config_used ) {
			echo '<p style="margin:0 0 12px 0;">' . esc_html__( 'Brak skonfigurowanych przypomnień. / No follow-ups configured.', 'ihumbak-woo-rating-stars' ) . '</p>';
		} else {
			$delays_short = array();
			for ( $i = 0; $i < $config_used; $i++ ) {
				$entry = $followups[ $i ];
				$days  = isset( $entry['delay_days'] ) ? (int) $entry['delay_days'] : 0;
				if ( $days > 0 ) {
					$delays_short[] = sprintf(
						/* translators: %d: liczba dni opóźnienia / number of delay days */
						esc_html__( '%dd', 'ihumbak-woo-rating-stars' ),
						$days
					);
				}
			}

			printf(
				'<p style="margin:0 0 12px 0;">%s: %s</p>',
				esc_html(
					sprintf(
						/* translators: %1$d: configured count, %2$d: max allowed */
						__( 'Przypomnień / Follow-ups: %1$d/%2$d', 'ihumbak-woo-rating-stars' ),
						$config_used,
						$config_max
					)
				),
				esc_html( implode( ', ', $delays_short ) )
			);
		}

		// 3. Hint o łańcuchu przy ręcznej wysyłce.
		echo '<p class="description" style="margin-top:12px;">';
		echo esc_html__( 'Ręczne wysłanie (Order actions → „Wyślij e-mail z prośbą o ocenę") uruchamia również skonfigurowany łańcuch follow-up.', 'ihumbak-woo-rating-stars' );
		echo '<br><em>';
		echo esc_html__( 'Manually sending the email (Order actions → "Send review request email") will also trigger the configured follow-up chain.', 'ihumbak-woo-rating-stars' );
		echo '</em></p>';
	}

	/**
	 * Formatuje etykietę kroku do wyświetlenia w UI.
	 *
	 * Krok 0 to wysyłka początkowa; kroki 1..MAX_FOLLOWUPS to follow-upy.
	 *
	 * @since 1.2.0
	 *
	 * @param int $step Numer kroku.
	 * @return string Sformatowana etykieta.
	 */
	private function format_step_label( int $step ): string {
		if ( $step <= 0 ) {
			return __( 'Krok 0 (wysyłka początkowa) / Step 0 (initial)', 'ihumbak-woo-rating-stars' );
		}

		return sprintf(
			/* translators: 1: numer kroku AS, 2: numer follow-up */
			__( 'Krok %1$d (follow-up #%2$d) / Step %1$d (follow-up #%2$d)', 'ihumbak-woo-rating-stars' ),
			$step,
			$step
		);
	}

	// -------------------------------------------------------------------------
	// Powiadomienia admina
	// -------------------------------------------------------------------------

	/**
	 * Renderuje oczekujące powiadomienie admina z transientu.
	 *
	 * Wywoływana przez hook `admin_notices`. Powiadomienie jest wyświetlane
	 * wyłącznie na stronach powiązanych z funkcją (settings page, order screens).
	 * Transient jest usuwany po wyświetleniu.
	 */
	public function render_pending_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Dozwolone ekrany: klasyczny ekran zamówień (shop_order), HPOS (woocommerce_page_wc-orders)
		// oraz strona ustawień e-mail rozpoznawana po query var `page` (niezależne od locale —
		// hookname submenu WordPressa pochodzi z sanitize_title(menu_title), więc zmienia się
		// wraz z tłumaczeniem etykiety menu).
		$is_email_settings_screen = isset( $_GET['page'] )
			&& Ihumbak_WRS_Admin_Email_Settings::PAGE_SLUG === sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed_order_screens = array( 'shop_order', 'woocommerce_page_wc-orders' );

		if ( ! $is_email_settings_screen && ! in_array( $screen->id, $allowed_order_screens, true ) ) {
			return;
		}

		$user_id   = get_current_user_id();
		$transient = self::NOTICE_TRANSIENT_PREFIX . $user_id;
		$notice    = get_transient( $transient );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $transient );

		$type    = isset( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'info';
		$allowed = array( 'success', 'warning', 'error', 'info' );
		if ( ! in_array( $type, $allowed, true ) ) {
			$type = 'info';
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $notice['message'] )
		);
	}

	// -------------------------------------------------------------------------
	// Helpery prywatne
	// -------------------------------------------------------------------------

	/**
	 * Zapisuje powiadomienie w transiencie powiązanym z aktualnym użytkownikiem.
	 *
	 * TTL transientu wynosi 60 sekund — wystarcza na obsługę przekierowania
	 * i wyświetlenie powiadomienia na stronie docelowej.
	 *
	 * @param string $type    Typ powiadomienia: success | warning | error | info.
	 * @param string $message Treść powiadomienia (może zawierać bezpieczny HTML).
	 */
	private function store_notice( string $type, string $message ): void {
		$user_id   = get_current_user_id();
		$transient = self::NOTICE_TRANSIENT_PREFIX . $user_id;

		set_transient(
			$transient,
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Zapisuje powiadomienie odpowiednie dla danego obiektu wyniku wysyłki.
	 *
	 * Mapowanie statusów:
	 *  - sent    → success
	 *  - skipped → warning
	 *  - failed  → error
	 *
	 * @param Ihumbak_WRS_Email_Send_Result $result Wynik wysyłki.
	 * @param \WC_Order                    $order  Zamówienie (używane do komunikatu sukcesu).
	 */
	private function store_notice_for_result( Ihumbak_WRS_Email_Send_Result $result, \WC_Order $order ): void {
		switch ( $result->get_status() ) {
			case Ihumbak_WRS_Email_Send_Result::STATUS_SENT:
				$this->store_notice(
					'success',
					sprintf(
						/* translators: %s: numer zamówienia */
						__( 'E-mail z prośbą o ocenę dla zamówienia %s został wysłany pomyślnie.', 'ihumbak-woo-rating-stars' ),
						esc_html( (string) $order->get_order_number() )
					)
				);
				break;

			case Ihumbak_WRS_Email_Send_Result::STATUS_SKIPPED:
				$this->store_notice(
					'warning',
					$result->get_message()
						?: sprintf(
							/* translators: %s: numer zamówienia */
							__( 'E-mail dla zamówienia %s nie został wysłany (pominięto przez regułę).', 'ihumbak-woo-rating-stars' ),
							esc_html( (string) $order->get_order_number() )
						)
				);
				break;

			case Ihumbak_WRS_Email_Send_Result::STATUS_FAILED:
			default:
				$this->store_notice(
					'error',
					$result->get_message()
						?: sprintf(
							/* translators: %s: numer zamówienia */
							__( 'Nie udało się wysłać e-maila dla zamówienia %s.', 'ihumbak-woo-rating-stars' ),
							esc_html( (string) $order->get_order_number() )
						)
				);
				break;
		}
	}

	/**
	 * Buduje treść notatki do zamówienia na podstawie wyniku wysyłki.
	 *
	 * Notatka jest zapisywana jako prywatna (widoczna tylko dla admina).
	 * Rejestruje wynik (sukces / pominięcie / błąd) wraz z przyczyna i komunikatem.
	 *
	 * @param Ihumbak_WRS_Email_Send_Result $result Wynik wysyłki.
	 * @param \WC_Order                    $order  Zamówienie (nieużywane bezpośrednio, dla czytelności podpisu).
	 * @return string Treść notatki.
	 */
	private function format_order_note( Ihumbak_WRS_Email_Send_Result $result, \WC_Order $order ): string {
		switch ( $result->get_status() ) {
			case Ihumbak_WRS_Email_Send_Result::STATUS_SENT:
				return __( '[Quick Ratings] E-mail z prośbą o ocenę wysłany ręcznie przez administratora.', 'ihumbak-woo-rating-stars' );

			case Ihumbak_WRS_Email_Send_Result::STATUS_SKIPPED:
				return sprintf(
					/* translators: %s: komunikat z powodem pominięcia */
					__( '[Quick Ratings] Ręczna wysyłka pominięta. Powód: %s', 'ihumbak-woo-rating-stars' ),
					$result->get_message() ?: $result->get_reason()
				);

			case Ihumbak_WRS_Email_Send_Result::STATUS_FAILED:
			default:
				return sprintf(
					/* translators: %s: komunikat z opisem błędu */
					__( '[Quick Ratings] Ręczna wysyłka nieudana. Błąd: %s', 'ihumbak-woo-rating-stars' ),
					$result->get_message() ?: $result->get_reason()
				);
		}
	}

	/**
	 * Przekierowuje admina z powrotem na stronę ustawień e-mail po obsłużeniu formularza.
	 *
	 * Używa menu_page_url() by zbudować poprawny URL strony ustawień,
	 * następnie wywołuje wp_safe_redirect() i kończy wykonanie skryptu.
	 */
	private function redirect_to_settings(): void {
		$url = menu_page_url( Ihumbak_WRS_Admin_Email_Settings::PAGE_SLUG, false );

		// menu_page_url() może zwrócić '' jeśli strona nie jest jeszcze zarejestrowana
		// (np. w środowisku testowym). Fallback do admin.php z parametrem page.
		if ( empty( $url ) ) {
			$url = add_query_arg(
				array( 'page' => Ihumbak_WRS_Admin_Email_Settings::PAGE_SLUG ),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}
}
