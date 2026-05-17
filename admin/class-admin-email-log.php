<?php
/**
 * Podstrona administratora — log wysyłki wiadomości e-mail.
 *
 * Wyświetla paginowaną tabelę wpisów z tabeli `{prefix}_woo_quick_ratings_email_log`
 * z informacjami o każdej próbie wysyłki: data, zamówienie, klient, krok sekwencji,
 * status oraz przyczyna pominięcia/błędu.
 *
 * Strona jest dostępna wyłącznie gdy tabela istnieje (opcja włączona i zapisana).
 * Linki do zamówień korzystają z WC_Order::get_edit_order_url() — metoda
 * jest w pełni kompatybilna z HPOS.
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ihumbak_WRS_Admin_Email_Log
 *
 * Rejestruje podstronę "Email Log" w menu Quick Ratings i renderuje listę wpisów.
 */
class Ihumbak_WRS_Admin_Email_Log {

	/** Slug podstrony admina. */
	const PAGE_SLUG = 'ihumbak-wrs-email-log';

	/** Liczba wpisów na stronę. */
	const PER_PAGE = 20;

	/** Nazwa akcji admin-post.php dla wyczyszczenia logu. */
	const ADMIN_POST_CLEAR = 'ihumbak_wrs_clear_log';

	// -------------------------------------------------------------------------
	// Konstruktor
	// -------------------------------------------------------------------------

	/**
	 * Konstruktor — rejestruje hooki admin_menu oraz admin-post handler.
	 *
	 * Handler `handle_clear_log` jest podpięty pod `admin_post_*`, dzięki czemu
	 * uruchamia się PRZED `admin-header.php` — wp_safe_redirect() działa
	 * poprawnie (wzorzec PRG, identyczny z Ihumbak_WRS_Admin_Email_Tools).
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 25 );
		add_action( 'admin_post_' . self::ADMIN_POST_CLEAR, array( $this, 'handle_clear_log' ) );
	}

	// -------------------------------------------------------------------------
	// Rejestracja menu
	// -------------------------------------------------------------------------

	/**
	 * Dodaje podstronę "Email Log" do menu Quick Ratings.
	 *
	 * Priorytet 25 — po ustawieniach e-mail (priorytet 20), by "Email Log"
	 * pojawiał się niżej w submenu.
	 */
	public function add_submenu(): void {
		add_submenu_page(
			'ihumbak-wrs-ratings',
			__( 'Email Log', 'ihumbak-woo-rating-stars' ),
			__( 'Email Log', 'ihumbak-woo-rating-stars' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Renderowanie strony
	// -------------------------------------------------------------------------

	/**
	 * Renderuje stronę logu wysyłki e-mail.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Brak uprawnień / Insufficient permissions.', 'ihumbak-woo-rating-stars' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Email Log', 'ihumbak-woo-rating-stars' ) . '</h1>';

		// Gdy tabela nie istnieje — pokaż komunikat i zakończ.
		if ( ! Ihumbak_WRS_Email_Log::table_exists() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Tabela logów nie istnieje jeszcze. Włącz opcję i zapisz ustawienia, aby ją utworzyć.', 'ihumbak-woo-rating-stars' );
			echo '</p></div>';
			echo '</div>';
			return;
		}

		// Notice po wyczyszczeniu logu (po przekierowaniu z `?cleared=1`).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tylko flaga UI po PRG redirect.
		if ( ! empty( $_GET['cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Log został wyczyszczony. / Log cleared.', 'ihumbak-woo-rating-stars' );
			echo '</p></div>';
		}

		global $wpdb;
		$table = Ihumbak_WRS_Email_Log::get_table_name();

		// Łączna liczba wpisów — potrzebna zarówno do paginacji jak i do zaklamrowania $paged.
		$total_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_pages = max( 1, (int) ceil( $total_count / self::PER_PAGE ) );

		// Paginacja — wartość z $_GET klamrowana do [1, $total_pages],
		// żeby ?paged=9999999 nie liczyło dużego OFFSET-u dla pustego wyniku.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET pagination.
		$paged_requested = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;
		$paged           = max( 1, min( $paged_requested, $total_pages ) );
		$offset          = ( $paged - 1 ) * self::PER_PAGE;

		// Pobranie wierszy — tylko gdy w tabeli są wpisy.
		$rows = array();
		if ( $total_count > 0 ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					self::PER_PAGE,
					$offset
				)
			);
		}

		// Przycisk "Wyczyść log" — pokaż tylko gdy są wpisy.
		if ( $total_count > 0 ) {
			$this->render_clear_log_form();
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data / Date', 'ihumbak-woo-rating-stars' ); ?></th>
					<th><?php esc_html_e( 'Zamówienie / Order', 'ihumbak-woo-rating-stars' ); ?></th>
					<th><?php esc_html_e( 'Klient / Customer', 'ihumbak-woo-rating-stars' ); ?></th>
					<th><?php esc_html_e( 'Krok / Step', 'ihumbak-woo-rating-stars' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ihumbak-woo-rating-stars' ); ?></th>
					<th><?php esc_html_e( 'Powód / Reason', 'ihumbak-woo-rating-stars' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'Brak wpisów. / No entries yet.', 'ihumbak-woo-rating-stars' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->created_at . ' UTC' ) ) ); ?></td>
							<td><?php $this->render_order_cell( (int) $row->order_id ); ?></td>
							<td><?php echo esc_html( (string) $row->customer_email ); ?></td>
							<td><?php echo esc_html( '#' . (int) $row->step ); ?></td>
							<td><?php $this->render_status_badge( (string) $row->status ); ?></td>
							<td><?php $this->render_reason_cell( (string) $row->reason ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_count > self::PER_PAGE ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'total'   => (int) ceil( $total_count / self::PER_PAGE ),
						'current' => $paged,
					)
				);
				?>
			</div>
		</div>
		<?php endif; ?>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Akcja "Wyczyść log"
	// -------------------------------------------------------------------------

	/**
	 * Handler admin-post.php dla wyczyszczenia logu.
	 *
	 * Wywoływany przez hook `admin_post_ihumbak_wrs_clear_log` PRZED
	 * załadowaniem `admin-header.php` — dzięki temu `wp_safe_redirect()`
	 * może wysłać nagłówek `Location:` (PRG pattern, brak re-submitu na F5).
	 */
	public function handle_clear_log(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__( 'Brak uprawnień / Insufficient permissions.', 'ihumbak-woo-rating-stars' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ADMIN_POST_CLEAR );

		$redirect_args = array( 'page' => self::PAGE_SLUG );

		if ( Ihumbak_WRS_Email_Log::table_exists() ) {
			global $wpdb;
			$table = Ihumbak_WRS_Email_Log::get_table_name();

			// TRUNCATE resetuje AUTO_INCREMENT i jest szybsze niż DELETE bez WHERE.
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$redirect_args['cleared'] = '1';
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Renderuje formularz z przyciskiem "Wyczyść log".
	 *
	 * Przycisk wymaga potwierdzenia (confirm) i nonce'a — chroni przed
	 * przypadkowym kliknięciem i CSRF. Formularz POST-uje do admin-post.php,
	 * co pozwala handlerowi uruchomić się przed jakimkolwiek outputem.
	 */
	private function render_clear_log_form(): void {
		$confirm = __( 'Na pewno wyczyścić cały log? Akcja jest nieodwracalna. / Clear the entire log? This cannot be undone.', 'ihumbak-woo-rating-stars' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 12px 0;" onsubmit="return confirm( <?php echo wp_json_encode( $confirm ); ?> );">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ADMIN_POST_CLEAR ); ?>" />
			<?php wp_nonce_field( self::ADMIN_POST_CLEAR ); ?>
			<button type="submit" class="button">
				<?php esc_html_e( 'Wyczyść log / Clear log', 'ihumbak-woo-rating-stars' ); ?>
			</button>
		</form>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpery renderowania komórek
	// -------------------------------------------------------------------------

	/**
	 * Renderuje komórkę z linkiem do zamówienia.
	 *
	 * Korzysta z WC_Order::get_edit_order_url() — w pełni kompatybilne z HPOS.
	 *
	 * @param int $order_id ID zamówienia.
	 */
	private function render_order_cell( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order ) {
			printf(
				'<a href="%s">#%s</a>',
				esc_url( $order->get_edit_order_url() ),
				esc_html( (string) $order_id )
			);
		} else {
			echo esc_html( '#' . $order_id );
		}
	}

	/**
	 * Renderuje kolorowy badge statusu.
	 *
	 * Style inline — bez zewnętrznego pliku CSS zgodnie z ustalonym planem.
	 *
	 * @param string $status Kod statusu: 'sent' | 'skipped' | 'failed'.
	 */
	private function render_status_badge( string $status ): void {
		switch ( $status ) {
			case 'sent':
				$label = __( 'Wysłano / Sent', 'ihumbak-woo-rating-stars' );
				$style = 'background:#d4edda;color:#155724;';
				break;

			case 'skipped':
				$label = __( 'Pominięto / Skipped', 'ihumbak-woo-rating-stars' );
				$style = 'background:#e2e3e5;color:#383d41;';
				break;

			case 'failed':
				$label = __( 'Błąd / Failed', 'ihumbak-woo-rating-stars' );
				$style = 'background:#f8d7da;color:#721c24;';
				break;

			default:
				$label = esc_html( $status );
				$style = '';
				break;
		}

		printf(
			'<span class="ihumbak-wrs-status-badge" style="%s">%s</span>',
			esc_attr( 'padding:2px 8px;border-radius:3px;font-size:11px;' . $style ),
			esc_html( $label )
		);
	}

	/**
	 * Renderuje komórkę z przyczyną wysyłki/pominięcia.
	 *
	 * Wyświetla skrócony (max 80 znaków) tekst w tagu <code> z pełnym
	 * tekstem w atrybucie title — dostępnym po najechaniu kursorem.
	 *
	 * @param string $reason Kod przyczyny lub pusty ciąg.
	 */
	private function render_reason_cell( string $reason ): void {
		if ( '' === $reason ) {
			echo '&mdash;';
			return;
		}

		$display = strlen( $reason ) > 80
			? esc_html( substr( $reason, 0, 80 ) ) . '&hellip;'
			: esc_html( $reason );

		printf(
			'<code title="%s">%s</code>',
			esc_attr( $reason ),
			$display // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above
		);
	}
}
