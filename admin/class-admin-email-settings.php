<?php
/**
 * Ustawienia wiadomości e-mail z prośbą o ocenę.
 *
 * Klasa rejestruje podstronę administratora pozwalającą skonfigurować
 * automatyczne wiadomości e-mail wysyłane do klientów po realizacji
 * zamówienia. Sam silnik wysyłki oraz podstawianie placeholderów
 * zostaną zaimplementowane w osobnym zgłoszeniu — tutaj odpowiadamy
 * wyłącznie za interfejs ustawień (Settings API).
 *
 * @package Ihumbak_WRS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klasa Ihumbak_WRS_Admin_Email_Settings.
 *
 * Rejestruje submenu, opcje (Settings API) wraz z sekcjami i polami.
 * Wszystkie etykiety dwujęzyczne (PL/EN) zgodnie z konwencją projektu.
 */
class Ihumbak_WRS_Admin_Email_Settings {

    /**
     * Grupa opcji używana przez Settings API.
     */
    const OPTION_GROUP = 'ihumbak_wrs_email_settings_group';

    /**
     * Slug strony ustawień.
     */
    const PAGE_SLUG = 'ihumbak-wrs-email-settings';

    /**
     * Konstruktor — rejestracja hooków.
     *
     * Priorytet 20 przy admin_menu zapewnia, że menu nadrzędne
     * 'ihumbak-wrs-ratings' (z klasy Admin_Panel) zostało już dodane.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 20 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Dodaje podstronę "Email Review Requests" do menu Quick Ratings.
     */
    public function add_submenu() {
        add_submenu_page(
            'ihumbak-wrs-ratings',
            __( 'Email Review Requests', 'ihumbak-woo-rating-stars' ),
            __( 'Email Review Requests', 'ihumbak-woo-rating-stars' ),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Rejestruje wszystkie opcje wraz z sanitizerami,
     * a następnie buduje sekcje i pola formularza.
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_enabled',
            array(
                'type'              => 'boolean',
                'default'           => false,
                'sanitize_callback' => array( $this, 'sanitize_bool' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_trigger_status',
            array(
                'type'              => 'string',
                'default'           => 'completed',
                'sanitize_callback' => array( $this, 'sanitize_trigger_status' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_delay_days',
            array(
                'type'              => 'integer',
                'default'           => 7,
                'sanitize_callback' => array( $this, 'sanitize_delay_days' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_skip_refunded',
            array(
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => array( $this, 'sanitize_bool' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_skip_already_rated',
            array(
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => array( $this, 'sanitize_bool' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_excluded_products',
            array(
                'type'              => 'array',
                'default'           => array(),
                'sanitize_callback' => array( $this, 'sanitize_id_list' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_excluded_categories',
            array(
                'type'              => 'array',
                'default'           => array(),
                'sanitize_callback' => array( $this, 'sanitize_id_list' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_subject',
            array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_body',
            array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'wp_kses_post',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_from_name',
            array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_from_email',
            array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => array( $this, 'sanitize_optional_email' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_reply_to',
            array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => array( $this, 'sanitize_optional_email' ),
            )
        );

        $this->add_sections_and_fields();
    }

    /**
     * Definiuje sekcje i pola formularza ustawień.
     */
    public function add_sections_and_fields() {
        // Sekcja 1: Ogólne.
        add_settings_section(
            'ihumbak_wrs_email_general',
            __( 'Ogólne / General', 'ihumbak-woo-rating-stars' ),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field(
            'ihumbak_wrs_email_enabled',
            __( 'Włącz wysyłkę / Enable sending', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_enabled' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_general',
            array( 'label_for' => 'ihumbak_wrs_email_enabled' )
        );

        add_settings_field(
            'ihumbak_wrs_email_trigger_status',
            __( 'Status wyzwalający / Trigger status', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_trigger_status' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_general',
            array( 'label_for' => 'ihumbak_wrs_email_trigger_status' )
        );

        add_settings_field(
            'ihumbak_wrs_email_delay_days',
            __( 'Opóźnienie (dni) / Delay (days)', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_delay_days' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_general',
            array( 'label_for' => 'ihumbak_wrs_email_delay_days' )
        );

        add_settings_field(
            'ihumbak_wrs_email_skip_refunded',
            __( 'Pomijaj zwroty / Skip refunded', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_skip_refunded' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_general',
            array( 'label_for' => 'ihumbak_wrs_email_skip_refunded' )
        );

        add_settings_field(
            'ihumbak_wrs_email_skip_already_rated',
            __( 'Pomijaj już ocenione / Skip already rated', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_skip_already_rated' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_general',
            array( 'label_for' => 'ihumbak_wrs_email_skip_already_rated' )
        );

        // Sekcja 2: Wykluczenia.
        add_settings_section(
            'ihumbak_wrs_email_exclusions',
            __( 'Wykluczenia / Exclusions', 'ihumbak-woo-rating-stars' ),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field(
            'ihumbak_wrs_email_excluded_products',
            __( 'Wykluczone produkty / Excluded products', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_excluded_products' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_exclusions',
            array( 'label_for' => 'ihumbak_wrs_email_excluded_products' )
        );

        add_settings_field(
            'ihumbak_wrs_email_excluded_categories',
            __( 'Wykluczone kategorie / Excluded categories', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_excluded_categories' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_exclusions',
            array( 'label_for' => 'ihumbak_wrs_email_excluded_categories' )
        );

        // Sekcja 3: Treść wiadomości.
        add_settings_section(
            'ihumbak_wrs_email_content',
            __( 'Treść wiadomości / Email content', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_content_section_intro' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'ihumbak_wrs_email_subject',
            __( 'Temat / Subject', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_subject' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_content',
            array( 'label_for' => 'ihumbak_wrs_email_subject' )
        );

        add_settings_field(
            'ihumbak_wrs_email_body',
            __( 'Treść / Body', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_body' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_content',
            array( 'label_for' => 'ihumbak_wrs_email_body' )
        );

        add_settings_field(
            'ihumbak_wrs_email_from_name',
            __( 'Nazwa nadawcy / From name', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_from_name' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_content',
            array( 'label_for' => 'ihumbak_wrs_email_from_name' )
        );

        add_settings_field(
            'ihumbak_wrs_email_from_email',
            __( 'E-mail nadawcy / From email', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_from_email' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_content',
            array( 'label_for' => 'ihumbak_wrs_email_from_email' )
        );

        add_settings_field(
            'ihumbak_wrs_email_reply_to',
            __( 'Adres odpowiedzi / Reply-To', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_reply_to' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_content',
            array( 'label_for' => 'ihumbak_wrs_email_reply_to' )
        );
    }

    /* ----------------------------------------------------------------
     *  Sanitizery
     * ---------------------------------------------------------------- */

    /**
     * Sanitizuje wartość logiczną (checkbox).
     *
     * @param mixed $value Surowa wartość z formularza.
     * @return bool
     */
    public function sanitize_bool( $value ) {
        return (bool) rest_sanitize_boolean( $value );
    }

    /**
     * Sanitizuje status zamówienia używany jako trigger.
     * Akceptuje status z prefiksem 'wc-' lub bez. Stała wartość zwracana
     * jest bez prefiksu (np. 'completed'). W razie nieprawidłowej wartości
     * zwraca domyślne 'completed' i dodaje błąd ustawień.
     *
     * @param mixed $value Wartość z formularza.
     * @return string
     */
    public function sanitize_trigger_status( $value ) {
        $value = sanitize_key( (string) $value );

        if ( ! function_exists( 'wc_get_order_statuses' ) ) {
            add_settings_error(
                'ihumbak_wrs_email_trigger_status',
                'wc_missing',
                __( 'WooCommerce nieaktywne — zachowano domyślny status.', 'ihumbak-woo-rating-stars' ),
                'warning'
            );
            return 'completed';
        }

        $allowed    = array_keys( wc_get_order_statuses() ); // np. 'wc-completed'.
        $normalized = ( 0 === strpos( $value, 'wc-' ) ) ? $value : 'wc-' . $value;

        if ( in_array( $normalized, $allowed, true ) ) {
            return substr( $normalized, 3 ); // odetnij prefiks 'wc-'.
        }

        add_settings_error(
            'ihumbak_wrs_email_trigger_status',
            'invalid_status',
            __( 'Wybrany status zamówienia jest nieprawidłowy. Ustawiono domyślny: completed.', 'ihumbak-woo-rating-stars' ),
            'error'
        );

        return 'completed';
    }

    /**
     * Sanitizuje liczbę dni opóźnienia (0–365).
     *
     * @param mixed $value Wartość z formularza.
     * @return int
     */
    public function sanitize_delay_days( $value ) {
        $int     = absint( $value );
        $clamped = min( 365, max( 0, $int ) );

        if ( (int) $value !== $clamped ) {
            add_settings_error(
                'ihumbak_wrs_email_delay_days',
                'delay_clamped',
                __( 'Liczba dni została ograniczona do zakresu 0–365.', 'ihumbak-woo-rating-stars' ),
                'warning'
            );
        }

        return $clamped;
    }

    /**
     * Sanitizuje listę identyfikatorów (tablica intów).
     *
     * @param mixed $value Wartość z formularza (tablica lub string).
     * @return array<int,int>
     */
    public function sanitize_id_list( $value ) {
        if ( is_string( $value ) ) {
            $value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $ids = array_map( 'absint', $value );
        $ids = array_filter( $ids ); // usuwa zera.

        return array_values( array_unique( $ids ) );
    }

    /**
     * Sanitizuje opcjonalny adres e-mail (dopuszcza pusty string).
     *
     * @param mixed $value Wartość z formularza.
     * @return string
     */
    public function sanitize_optional_email( $value ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return '';
        }

        $sanitized = sanitize_email( $value );

        if ( $sanitized && is_email( $sanitized ) ) {
            return $sanitized;
        }

        $current     = current_filter(); // np. 'sanitize_option_ihumbak_wrs_email_reply_to'.
        $option_slug = ( 'sanitize_option_ihumbak_wrs_email_reply_to' === $current )
            ? 'ihumbak_wrs_email_reply_to'
            : 'ihumbak_wrs_email_from_email';

        add_settings_error(
            $option_slug,
            'invalid_email',
            __( 'Podany adres e-mail jest nieprawidłowy.', 'ihumbak-woo-rating-stars' ),
            'error'
        );

        return '';
    }

    /* ----------------------------------------------------------------
     *  Renderery sekcji i pól
     * ---------------------------------------------------------------- */

    /**
     * Wyświetla wprowadzenie do sekcji "Treść wiadomości" wraz z listą
     * dostępnych placeholderów.
     */
    public function render_content_section_intro() {
        $placeholders = array(
            'customer_first_name' => __( 'imię klienta', 'ihumbak-woo-rating-stars' ),
            'customer_last_name'  => __( 'nazwisko klienta', 'ihumbak-woo-rating-stars' ),
            'order_number'        => __( 'numer zamówienia', 'ihumbak-woo-rating-stars' ),
            'order_date'          => __( 'data zamówienia', 'ihumbak-woo-rating-stars' ),
            'products_list'       => __( 'lista produktów z zamówienia', 'ihumbak-woo-rating-stars' ),
            'rating_links_list'   => __( 'lista linków do oceny produktów', 'ihumbak-woo-rating-stars' ),
            'site_name'           => __( 'nazwa sklepu', 'ihumbak-woo-rating-stars' ),
            'coupon_code'         => __( 'kod kuponu (zostanie dodane w kolejnym etapie)', 'ihumbak-woo-rating-stars' ),
        );

        echo '<p>' . esc_html__( 'Dostępne placeholdery (możesz ich użyć w temacie i treści):', 'ihumbak-woo-rating-stars' ) . '</p>';
        echo '<ul style="list-style: disc; padding-left: 1.5em;">';
        foreach ( $placeholders as $key => $desc ) {
            echo '<li><code>' . esc_html( '{' . $key . '}' ) . '</code> — ' . esc_html( $desc ) . '</li>';
        }
        echo '</ul>';
        echo '<p><em>' . esc_html__( 'Placeholdery {products_list} i {rating_links_list} są w pełni obsługiwane. Placeholder {coupon_code} zostanie dodany w kolejnym etapie.', 'ihumbak-woo-rating-stars' ) . '</em></p>';
    }

    /**
     * Renderuje pole "Włącz wysyłkę".
     */
    public function render_enabled() {
        $value = (bool) get_option( 'ihumbak_wrs_email_enabled', false );
        ?>
        <input type="hidden" name="ihumbak_wrs_email_enabled" value="0" />
        <input type="checkbox" id="ihumbak_wrs_email_enabled" name="ihumbak_wrs_email_enabled" value="1" <?php checked( true, $value ); ?> />
        <p class="description">
            <?php esc_html_e( 'Włącza automatyczne wysyłanie wiadomości z prośbą o ocenę.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje listę statusów zamówienia jako trigger.
     */
    public function render_trigger_status() {
        $current = (string) get_option( 'ihumbak_wrs_email_trigger_status', 'completed' );

        if ( ! function_exists( 'wc_get_order_statuses' ) ) {
            ?>
            <select id="ihumbak_wrs_email_trigger_status" name="ihumbak_wrs_email_trigger_status" disabled>
                <option value="completed" selected>completed</option>
            </select>
            <p class="description">
                <?php esc_html_e( 'WooCommerce nie jest aktywne — lista statusów jest niedostępna.', 'ihumbak-woo-rating-stars' ); ?>
            </p>
            <?php
            return;
        }

        $statuses = wc_get_order_statuses();
        ?>
        <select id="ihumbak_wrs_email_trigger_status" name="ihumbak_wrs_email_trigger_status">
            <?php foreach ( $statuses as $key => $label ) :
                $unprefixed = ( 0 === strpos( $key, 'wc-' ) ) ? substr( $key, 3 ) : $key;
                ?>
                <option value="<?php echo esc_attr( $unprefixed ); ?>" <?php selected( $current, $unprefixed ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Status zamówienia, którego osiągnięcie wyzwala wysyłkę wiadomości.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole liczby dni opóźnienia.
     */
    public function render_delay_days() {
        $value = (int) get_option( 'ihumbak_wrs_email_delay_days', 7 );
        ?>
        <input type="number" id="ihumbak_wrs_email_delay_days" name="ihumbak_wrs_email_delay_days"
               value="<?php echo esc_attr( (string) $value ); ?>" min="0" max="365" step="1" class="small-text" />
        <p class="description">
            <?php esc_html_e( '0 = wyślij natychmiast po zmianie statusu.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole "Pomijaj zwroty".
     */
    public function render_skip_refunded() {
        $value = (bool) get_option( 'ihumbak_wrs_email_skip_refunded', true );
        ?>
        <input type="hidden" name="ihumbak_wrs_email_skip_refunded" value="0" />
        <input type="checkbox" id="ihumbak_wrs_email_skip_refunded" name="ihumbak_wrs_email_skip_refunded" value="1" <?php checked( true, $value ); ?> />
        <p class="description">
            <?php esc_html_e( 'Nie wysyłaj wiadomości dla zamówień, które zostały zwrócone.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole "Pomijaj już ocenione".
     */
    public function render_skip_already_rated() {
        $value = (bool) get_option( 'ihumbak_wrs_email_skip_already_rated', true );
        ?>
        <input type="hidden" name="ihumbak_wrs_email_skip_already_rated" value="0" />
        <input type="checkbox" id="ihumbak_wrs_email_skip_already_rated" name="ihumbak_wrs_email_skip_already_rated" value="1" <?php checked( true, $value ); ?> />
        <p class="description">
            <?php esc_html_e( 'Pomijaj produkty, które klient już ocenił.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole "Wykluczone produkty" jako textarea z listą ID.
     */
    public function render_excluded_products() {
        $value = get_option( 'ihumbak_wrs_email_excluded_products', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }
        $display = implode( ',', array_map( 'absint', $value ) );
        ?>
        <textarea id="ihumbak_wrs_email_excluded_products" name="ihumbak_wrs_email_excluded_products"
                  rows="3" class="large-text code"><?php echo esc_textarea( $display ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Lista identyfikatorów produktów, dla których nie wysyłamy wiadomości. Oddziel przecinkami lub spacjami.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje wybór kategorii produktów do wykluczenia.
     */
    public function render_excluded_categories() {
        $value = get_option( 'ihumbak_wrs_email_excluded_categories', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }
        $value = array_map( 'absint', $value );

        if ( ! taxonomy_exists( 'product_cat' ) ) {
            ?>
            <p class="description">
                <?php esc_html_e( 'Taksonomia kategorii produktów (product_cat) nie jest dostępna — aktywuj WooCommerce.', 'ihumbak-woo-rating-stars' ); ?>
            </p>
            <?php
            return;
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            ?>
            <p class="description">
                <?php esc_html_e( 'Brak dostępnych kategorii produktów.', 'ihumbak-woo-rating-stars' ); ?>
            </p>
            <?php
            return;
        }
        ?>
        <input type="hidden" name="ihumbak_wrs_email_excluded_categories[]" value="" />
        <select id="ihumbak_wrs_email_excluded_categories" name="ihumbak_wrs_email_excluded_categories[]" multiple size="8">
            <?php foreach ( $terms as $term ) : ?>
                <option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $value, true ), true ); ?>>
                    <?php echo esc_html( $term->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Trzymaj Ctrl/Cmd, aby zaznaczyć kilka.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole tematu wiadomości.
     */
    public function render_subject() {
        $value = (string) get_option( 'ihumbak_wrs_email_subject', '' );
        ?>
        <input type="text" id="ihumbak_wrs_email_subject" name="ihumbak_wrs_email_subject"
               value="<?php echo esc_attr( $value ); ?>" class="large-text" />
        <p class="description">
            <?php esc_html_e( 'Temat wiadomości. Możesz użyć dostępnych placeholderów.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole treści wiadomości (wp_editor).
     */
    public function render_body() {
        $value = (string) get_option( 'ihumbak_wrs_email_body', '' );
        wp_editor(
            $value,
            'ihumbak_wrs_email_body',
            array(
                'textarea_name' => 'ihumbak_wrs_email_body',
                'media_buttons' => false,
                'textarea_rows' => 10,
            )
        );
        ?>
        <p class="description">
            <?php esc_html_e( 'Treść HTML wiadomości. Dozwolone tagi zgodne z wp_kses_post.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole nazwy nadawcy.
     */
    public function render_from_name() {
        $value = (string) get_option( 'ihumbak_wrs_email_from_name', '' );
        ?>
        <input type="text" id="ihumbak_wrs_email_from_name" name="ihumbak_wrs_email_from_name"
               value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Pozostaw puste, aby użyć domyślnych ustawień WordPressa.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole adresu nadawcy.
     */
    public function render_from_email() {
        $value = (string) get_option( 'ihumbak_wrs_email_from_email', '' );
        ?>
        <input type="email" id="ihumbak_wrs_email_from_email" name="ihumbak_wrs_email_from_email"
               value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Pozostaw puste, aby użyć domyślnych ustawień WordPressa.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje pole Reply-To.
     */
    public function render_reply_to() {
        $value = (string) get_option( 'ihumbak_wrs_email_reply_to', '' );
        ?>
        <input type="email" id="ihumbak_wrs_email_reply_to" name="ihumbak_wrs_email_reply_to"
               value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Pozostaw puste, aby użyć domyślnych ustawień WordPressa.', 'ihumbak-woo-rating-stars' ); ?>
        </p>
        <?php
    }

    /**
     * Renderuje stronę ustawień.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Brak uprawnień / Insufficient permissions.', 'ihumbak-woo-rating-stars' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Email Review Requests', 'ihumbak-woo-rating-stars' ); ?></h1>
            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                <div class="notice notice-warning"><p>
                    <?php esc_html_e( 'WooCommerce nie jest aktywne. Niektóre pola są niedostępne.', 'ihumbak-woo-rating-stars' ); ?>
                </p></div>
            <?php endif; ?>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>
            <?php
            /**
             * Hook uruchamiany po zamknięciu głównego formularza ustawień e-mail.
             *
             * Używany przez Ihumbak_WRS_Admin_Email_Tools::render_test_send_box()
             * do wstrzyknięcia formularza testowego wysyłania wiadomości.
             *
             * @since 1.4.0
             */
            do_action( 'ihumbak_wrs_after_email_settings_form' );
            ?>
        </div>
        <?php
    }
}
