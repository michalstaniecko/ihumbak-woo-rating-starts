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
     * Hook suffix zwrócony przez add_submenu_page — używany do warunkowego
     * ładowania assetów wyłącznie na naszej podstronie.
     *
     * @var string
     */
    private $hook_suffix = '';

    /**
     * Konstruktor — rejestracja hooków.
     *
     * Priorytet 20 przy admin_menu zapewnia, że menu nadrzędne
     * 'ihumbak-wrs-ratings' (z klasy Admin_Panel) zostało już dodane.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 20 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Dodaje podstronę "Email Review Requests" do menu Quick Ratings.
     */
    public function add_submenu() {
        $this->hook_suffix = (string) add_submenu_page(
            'ihumbak-wrs-ratings',
            __( 'Email Review Requests', 'ihumbak-woo-rating-stars' ),
            __( 'Email Review Requests', 'ihumbak-woo-rating-stars' ),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Ładuje skrypty admina wyłącznie na podstronie ustawień e-maili.
     *
     * @param string $hook_suffix Hook suffix bieżącej strony admina.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'ihumbak-wrs-admin-coupon-mode',
            IHUMBAK_WRS_PLUGIN_URL . 'assets/js/admin-coupon-mode.js',
            array(),
            IHUMBAK_WRS_VERSION,
            true
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
            'ihumbak_wrs_email_heading',
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

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_coupon_id',
            array(
                'type'              => 'integer',
                'default'           => 0,
                'sanitize_callback' => 'absint',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_coupon_mode',
            array(
                'type'              => 'string',
                'default'           => 'none',
                'sanitize_callback' => array( $this, 'sanitize_coupon_mode' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_coupon_auto_discount',
            array(
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => array( $this, 'sanitize_coupon_auto_discount' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_coupon_auto_validity_days',
            array(
                'type'              => 'integer',
                'default'           => 30,
                'sanitize_callback' => array( $this, 'sanitize_coupon_auto_validity_days' ),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            'ihumbak_wrs_email_followups',
            array(
                'type'              => 'array',
                'default'           => array(),
                'sanitize_callback' => array( $this, 'sanitize_followups' ),
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
            'ihumbak_wrs_email_heading',
            __( 'Nagłówek wiadomości / Email heading', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_heading' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_content',
            array( 'label_for' => 'ihumbak_wrs_email_heading' )
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
            'ihumbak_wrs_email_coupon_mode',
            __( 'Kupon dla klienta / Customer coupon', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_coupon_mode_field' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_content'
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

        // Sekcja 4: Przypomnienia (follow-up).
        add_settings_section(
            'ihumbak_wrs_email_followups',
            __( 'Przypomnienia (follow-up) / Follow-up reminders', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_followups_section_intro' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'ihumbak_wrs_email_followups',
            __( 'Kolejne przypomnienia / Scheduled reminders', 'ihumbak-woo-rating-stars' ),
            array( $this, 'render_followups' ),
            self::PAGE_SLUG,
            'ihumbak_wrs_email_followups'
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
     * Sanitizuje tablicę konfiguracji przypomnień follow-up.
     *
     * Akceptuje tablicę o maksymalnie MAX_FOLLOWUPS wpisach. Każdy wpis musi
     * zawierać klucz `delay_days` z liczbą całkowitą z zakresu 1–365. Wpisy
     * niespełniające tego wymagania są pomijane. Nadmiarowe wpisy (ponad limit)
     * są odcinane z komunikatem błędu.
     *
     * @since 1.2.0
     *
     * @param mixed $value Surowa wartość z formularza (oczekiwana tablica).
     * @return array<int,array{delay_days:int}>
     */
    public function sanitize_followups( $value ) {
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        // Upewnij się, że klucze są sekwencyjne (bez luk po usunięciu wierszy).
        $value = array_values( $value );

        $max = class_exists( 'Ihumbak_WRS_Email_Scheduler' )
            ? Ihumbak_WRS_Email_Scheduler::MAX_FOLLOWUPS
            : 3;

        // Przytnij do maksymalnej dozwolonej liczby przypomnień.
        if ( count( $value ) > $max ) {
            $value = array_slice( $value, 0, $max );
            add_settings_error(
                'ihumbak_wrs_email_followups',
                'followups_capped',
                sprintf(
                    /* translators: %d: maksymalna liczba przypomnień */
                    __( 'Liczba przypomnień została ograniczona do %d.', 'ihumbak-woo-rating-stars' ),
                    $max
                ),
                'warning'
            );
        }

        $clean = array();

        foreach ( $value as $index => $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $raw_days   = isset( $entry['delay_days'] ) ? (int) $entry['delay_days'] : 0;
            $delay_days = max( 1, min( 365, $raw_days ) );

            if ( $raw_days !== $delay_days ) {
                add_settings_error(
                    'ihumbak_wrs_email_followups',
                    'followup_delay_clamped_' . $index,
                    sprintf(
                        /* translators: 1: numer przypomnienia (1-based), 2: oryginalna wartość */
                        __( 'Opóźnienie przypomnienia #%1$d zostało ograniczone do zakresu 1–365 (podano: %2$d).', 'ihumbak-woo-rating-stars' ),
                        $index + 1,
                        $raw_days
                    ),
                    'warning'
                );
            }

            $clean[] = array( 'delay_days' => $delay_days );
        }

        return $clean;
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

    /**
     * Sanitizuje tryb kuponu.
     *
     * Akceptuje wyłącznie wartości z białej listy: 'none', 'fixed', 'auto'.
     * Każda inna wartość jest sprowadzana do 'none'.
     *
     * @since 1.5.0
     *
     * @param mixed $value Surowa wartość z formularza.
     * @return string Prawidłowy tryb kuponu.
     */
    public function sanitize_coupon_mode( $value ) {
        $allowed = array( 'none', 'fixed', 'auto' );
        $value   = sanitize_key( (string) $value );

        if ( in_array( $value, $allowed, true ) ) {
            return $value;
        }

        return 'none';
    }

    /**
     * Sanitizuje procent rabatu kuponu auto (1–100).
     *
     * @since 1.5.0
     *
     * @param mixed $value Surowa wartość z formularza.
     * @return int Procent rabatu z zakresu 1–100.
     */
    public function sanitize_coupon_auto_discount( $value ) {
        $int     = absint( $value );
        $clamped = min( 100, max( 1, $int ) );

        if ( $int !== $clamped ) {
            add_settings_error(
                'ihumbak_wrs_email_coupon_auto_discount',
                'discount_clamped',
                __( 'Procent rabatu kuponu został ograniczony do zakresu 1–100.', 'ihumbak-woo-rating-stars' ),
                'warning'
            );
        }

        return $clamped;
    }

    /**
     * Sanitizuje liczbę dni ważności kuponu auto (1–365).
     *
     * @since 1.5.0
     *
     * @param mixed $value Surowa wartość z formularza.
     * @return int Liczba dni z zakresu 1–365.
     */
    public function sanitize_coupon_auto_validity_days( $value ) {
        $int     = absint( $value );
        $clamped = min( 365, max( 1, $int ) );

        if ( $int !== $clamped ) {
            add_settings_error(
                'ihumbak_wrs_email_coupon_auto_validity_days',
                'validity_days_clamped',
                __( 'Liczba dni ważności kuponu została ograniczona do zakresu 1–365.', 'ihumbak-woo-rating-stars' ),
                'warning'
            );
        }

        return $clamped;
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
            'coupon_code'         => __( 'kod kuponu (stały lub auto-generowany / fixed or auto-generated)', 'ihumbak-woo-rating-stars' ),
        );

        echo '<p>' . esc_html__( 'Dostępne placeholdery (możesz ich użyć w temacie i treści):', 'ihumbak-woo-rating-stars' ) . '</p>';
        echo '<ul style="list-style: disc; padding-left: 1.5em;">';
        foreach ( $placeholders as $key => $desc ) {
            echo '<li><code>' . esc_html( '{' . $key . '}' ) . '</code> — ' . esc_html( $desc ) . '</li>';
        }
        echo '</ul>';
        echo '<p><em>' . esc_html__( 'Wszystkie wymienione placeholdery są w pełni obsługiwane. / All listed placeholders are fully supported.', 'ihumbak-woo-rating-stars' ) . '</em></p>';
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
     * Renderuje pole nagłówka wiadomości e-mail.
     *
     * Nagłówek jest wyświetlany jako <h1> na górze wiadomości opakowanej
     * w szablon WooCommerce. Obsługuje te same placeholder-y co temat
     * (skalarne — bez {products_list} i {rating_links_list}).
     * Gdy pole jest puste, stosowany jest domyślny nagłówek zdefiniowany
     * w Ihumbak_WRS_Email_Sender::default_heading().
     */
    public function render_heading() {
        $value   = (string) get_option( 'ihumbak_wrs_email_heading', '' );
        $default = __( 'Twoja opinia jest dla nas ważna', 'ihumbak-woo-rating-stars' );
        ?>
        <input type="text" id="ihumbak_wrs_email_heading" name="ihumbak_wrs_email_heading"
               value="<?php echo esc_attr( $value ); ?>" class="large-text"
               placeholder="<?php echo esc_attr( $default ); ?>" />
        <p class="description">
            <?php
            esc_html_e(
                'Nagłówek widoczny wewnątrz wiadomości (wyświetlany jako H1 w szablonie WooCommerce). '
                . 'Pozostaw puste, aby użyć domyślnej wartości. '
                . 'Obsługuje placeholder-y skalarne (np. {customer_first_name}, {site_name}) — '
                . 'nie wstawiaj tu {products_list} ani {rating_links_list}. '
                . '/ Email heading displayed as H1 at the top of the WooCommerce-wrapped message. '
                . 'Leave empty for the default. Supports scalar placeholders.',
                'ihumbak-woo-rating-stars'
            );
            ?>
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
     * Renderuje złożone pole wyboru trybu kuponu z podpanelami.
     *
     * Zawiera:
     * - Selektor trybu (radio): none / fixed / auto.
     * - Podpanel trybu 'fixed': istniejący selektor kuponów WooCommerce.
     * - Podpanel trybu 'auto': pola discount_percent i validity_days.
     *
     * Widoczność podpaneli sterowana jest waniliowym JavaScriptem (bez jQuery).
     * Backend odczytuje wszystkie trzy opcje niezależnie od wybranego trybu.
     *
     * @since 1.5.0
     */
    public function render_coupon_mode_field() {
        $mode           = (string) get_option( 'ihumbak_wrs_email_coupon_mode', 'none' );
        $coupon_id      = (int) get_option( 'ihumbak_wrs_email_coupon_id', 0 );
        $auto_discount  = (int) get_option( 'ihumbak_wrs_email_coupon_auto_discount', 10 );
        $auto_days      = (int) get_option( 'ihumbak_wrs_email_coupon_auto_validity_days', 30 );

        // Pobierz opublikowane kupony dla trybu 'fixed'.
        $coupons      = array();
        $wc_available = post_type_exists( 'shop_coupon' );

        if ( $wc_available ) {
            $coupons = get_posts(
                array(
                    'post_type'        => 'shop_coupon',
                    'post_status'      => 'publish',
                    'posts_per_page'   => -1,
                    'orderby'          => 'title',
                    'order'            => 'ASC',
                    'no_found_rows'    => true,
                    'suppress_filters' => false,
                )
            );
        }

        $field_id = 'ihumbak_wrs_email_coupon_mode';
        ?>
        <fieldset>
            <legend class="screen-reader-text">
                <?php esc_html_e( 'Tryb kuponu', 'ihumbak-woo-rating-stars' ); ?>
            </legend>

            <?php /* Radio: none */ ?>
            <label>
                <input type="radio" name="ihumbak_wrs_email_coupon_mode"
                       id="<?php echo esc_attr( $field_id ); ?>_none"
                       value="none" <?php checked( $mode, 'none' ); ?> />
                <?php esc_html_e( 'Brak kuponu / No coupon', 'ihumbak-woo-rating-stars' ); ?>
            </label><br />

            <?php /* Radio: fixed */ ?>
            <label>
                <input type="radio" name="ihumbak_wrs_email_coupon_mode"
                       id="<?php echo esc_attr( $field_id ); ?>_fixed"
                       value="fixed" <?php checked( $mode, 'fixed' ); ?> />
                <?php esc_html_e( 'Stały kupon / Fixed coupon', 'ihumbak-woo-rating-stars' ); ?>
            </label><br />

            <?php /* Podpanel trybu 'fixed' */ ?>
            <div id="ihumbak-wrs-coupon-subpanel-fixed"
                 style="margin: 8px 0 8px 24px; <?php echo 'fixed' !== $mode ? 'display:none;' : ''; ?>">
                <?php if ( ! $wc_available ) : ?>
                    <input type="hidden" name="ihumbak_wrs_email_coupon_id"
                           value="<?php echo esc_attr( (string) $coupon_id ); ?>" />
                    <select id="ihumbak_wrs_email_coupon_id" disabled>
                        <option value="0"><?php esc_html_e( '— brak / none —', 'ihumbak-woo-rating-stars' ); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'WooCommerce nie jest aktywne — lista kuponów jest niedostępna.', 'ihumbak-woo-rating-stars' ); ?>
                    </p>
                <?php else : ?>
                    <select id="ihumbak_wrs_email_coupon_id" name="ihumbak_wrs_email_coupon_id">
                        <option value="0" <?php selected( 0, $coupon_id ); ?>>
                            <?php esc_html_e( '— brak / none —', 'ihumbak-woo-rating-stars' ); ?>
                        </option>
                        <?php foreach ( $coupons as $coupon ) : ?>
                            <option value="<?php echo esc_attr( (string) $coupon->ID ); ?>"
                                <?php selected( (int) $coupon->ID, $coupon_id ); ?>>
                                <?php echo esc_html( $coupon->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Tylko opublikowane kupony. Kupon nie jest stosowany automatycznie — klient musi wpisać kod ręcznie przy kasie. / Only published coupons. Not applied automatically — the customer must enter it at checkout.', 'ihumbak-woo-rating-stars' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php /* Radio: auto */ ?>
            <label>
                <input type="radio" name="ihumbak_wrs_email_coupon_mode"
                       id="<?php echo esc_attr( $field_id ); ?>_auto"
                       value="auto" <?php checked( $mode, 'auto' ); ?> />
                <?php esc_html_e( 'Auto-generowany kupon / Auto-generated coupon', 'ihumbak-woo-rating-stars' ); ?>
            </label>

            <?php /* Podpanel trybu 'auto' */ ?>
            <div id="ihumbak-wrs-coupon-subpanel-auto"
                 style="margin: 8px 0 8px 24px; <?php echo 'auto' !== $mode ? 'display:none;' : ''; ?>">
                <label for="ihumbak_wrs_email_coupon_auto_discount">
                    <?php esc_html_e( 'Rabat (%) / Discount (%):', 'ihumbak-woo-rating-stars' ); ?>
                </label>
                <input type="number" id="ihumbak_wrs_email_coupon_auto_discount"
                       name="ihumbak_wrs_email_coupon_auto_discount"
                       value="<?php echo esc_attr( (string) $auto_discount ); ?>"
                       min="1" max="100" step="1" class="small-text" />

                <span style="margin-left: 16px;">
                    <label for="ihumbak_wrs_email_coupon_auto_validity_days">
                        <?php esc_html_e( 'Ważność (dni) / Validity (days):', 'ihumbak-woo-rating-stars' ); ?>
                    </label>
                    <input type="number" id="ihumbak_wrs_email_coupon_auto_validity_days"
                           name="ihumbak_wrs_email_coupon_auto_validity_days"
                           value="<?php echo esc_attr( (string) $auto_days ); ?>"
                           min="1" max="365" step="1" class="small-text" />
                </span>

                <p class="description">
                    <?php esc_html_e( 'Jeden kupon jednorazowego użycia tworzony automatycznie per zamówienie. Kod w formacie THX-XXXXXXXX. Kupon jest zbywalny — klient może przekazać go znajomemu. / One single-use coupon created automatically per order. Code format: THX-XXXXXXXX. Transferable — customer may share it.', 'ihumbak-woo-rating-stars' ); ?>
                </p>
            </div>

        </fieldset>
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
     * Wyświetla wprowadzenie do sekcji "Przypomnienia (follow-up)".
     *
     * @since 1.2.0
     */
    public function render_followups_section_intro() {
        echo '<p>' . esc_html__( 'Opcjonalne przypomnienia wysyłane po e-mailu początkowym. Każde przypomnienie korzysta z tego samego szablonu (temat i treść). Przed każdą wysyłką ponownie sprawdzane są reguły pomijania — np. jeśli klient ocenił produkty po kroku 0, krok 1 zostanie automatycznie pominięty.', 'ihumbak-woo-rating-stars' ) . '</p>';
        echo '<p>' . esc_html__( 'Opóźnienie każdego przypomnienia liczone jest od momentu poprzedniej wysyłki (model względny). Łańcuch jest kontynuowany wyłącznie po skutecznej wysyłce — jeśli któryś krok zostanie pominięty lub zakończy się błędem, kolejne przypomnienia nie zostaną wysłane. Możliwe 0–3 wpisy.', 'ihumbak-woo-rating-stars' ) . '</p>';
    }

    /**
     * Renderuje repeater przypomnień follow-up.
     *
     * Wyświetla tabelę istniejących wpisów z polami opóźnienia oraz przyciskami
     * zarządzania (dodaj, usuń, przesuń w górę/dół). Sterowanie realizowane
     * jest przez wbudowany, waniliowy JavaScript (bez jQuery).
     *
     * @since 1.2.0
     */
    public function render_followups() {
        $followups = get_option( 'ihumbak_wrs_email_followups', array() );
        if ( ! is_array( $followups ) ) {
            $followups = array();
        }
        $followups = array_values( $followups );

        $max = class_exists( 'Ihumbak_WRS_Email_Scheduler' )
            ? Ihumbak_WRS_Email_Scheduler::MAX_FOLLOWUPS
            : 3;

        ?>
        <div id="ihumbak-wrs-followups-wrap">
            <table id="ihumbak-wrs-followups-table" class="widefat striped" style="max-width:520px;">
                <thead>
                    <tr>
                        <th style="width:3em;"><?php esc_html_e( '#', 'ihumbak-woo-rating-stars' ); ?></th>
                        <th><?php esc_html_e( 'Opóźnienie (dni) / Delay (days)', 'ihumbak-woo-rating-stars' ); ?></th>
                        <th><?php esc_html_e( 'Akcje / Actions', 'ihumbak-woo-rating-stars' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ihumbak-wrs-followups-body">
                    <?php foreach ( $followups as $i => $entry ) :
                        $delay = isset( $entry['delay_days'] ) ? (int) $entry['delay_days'] : 7;
                        ?>
                        <tr class="ihumbak-wrs-followup-row">
                            <td class="ihumbak-wrs-followup-num"><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
                            <td>
                                <input type="number"
                                    name="ihumbak_wrs_email_followups[<?php echo esc_attr( (string) $i ); ?>][delay_days]"
                                    value="<?php echo esc_attr( (string) $delay ); ?>"
                                    min="1" max="365" step="1" class="small-text"
                                    aria-label="<?php esc_attr_e( 'Opóźnienie w dniach', 'ihumbak-woo-rating-stars' ); ?>" />
                            </td>
                            <td>
                                <button type="button" class="button ihumbak-wrs-move-up" aria-label="<?php esc_attr_e( 'Przesuń w górę', 'ihumbak-woo-rating-stars' ); ?>">&uarr;</button>
                                <button type="button" class="button ihumbak-wrs-move-down" aria-label="<?php esc_attr_e( 'Przesuń w dół', 'ihumbak-woo-rating-stars' ); ?>">&darr;</button>
                                <button type="button" class="button ihumbak-wrs-remove-row" aria-label="<?php esc_attr_e( 'Usuń', 'ihumbak-woo-rating-stars' ); ?>"><?php esc_html_e( 'Usuń / Remove', 'ihumbak-woo-rating-stars' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="button" id="ihumbak-wrs-add-followup" class="button button-secondary"
                    <?php echo count( $followups ) >= $max ? 'disabled="disabled"' : ''; ?>>
                    <?php esc_html_e( 'Dodaj przypomnienie / Add reminder', 'ihumbak-woo-rating-stars' ); ?>
                </button>
                <span id="ihumbak-wrs-followups-limit-info" style="margin-left:8px;color:#666;">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: maksymalna liczba przypomnień */
                            __( 'Maksimum: %d przypomnienia.', 'ihumbak-woo-rating-stars' ),
                            $max
                        )
                    );
                    ?>
                </span>
            </p>

            <p class="description">
                <?php esc_html_e( 'Opóźnienie jest liczone względem daty poprzedniej wysyłki w sekwencji. Wszystkie kroki używają tego samego szablonu tematu i treści wiadomości.', 'ihumbak-woo-rating-stars' ); ?>
            </p>
        </div>

        <template id="ihumbak-wrs-followup-row-tpl">
            <tr class="ihumbak-wrs-followup-row">
                <td class="ihumbak-wrs-followup-num"></td>
                <td>
                    <input type="number"
                        name=""
                        value="7"
                        min="1" max="365" step="1" class="small-text"
                        aria-label="<?php esc_attr_e( 'Opóźnienie w dniach', 'ihumbak-woo-rating-stars' ); ?>" />
                </td>
                <td>
                    <button type="button" class="button ihumbak-wrs-move-up" aria-label="<?php esc_attr_e( 'Przesuń w górę', 'ihumbak-woo-rating-stars' ); ?>">&uarr;</button>
                    <button type="button" class="button ihumbak-wrs-move-down" aria-label="<?php esc_attr_e( 'Przesuń w dół', 'ihumbak-woo-rating-stars' ); ?>">&darr;</button>
                    <button type="button" class="button ihumbak-wrs-remove-row" aria-label="<?php esc_attr_e( 'Usuń', 'ihumbak-woo-rating-stars' ); ?>"><?php esc_html_e( 'Usuń / Remove', 'ihumbak-woo-rating-stars' ); ?></button>
                </td>
            </tr>
        </template>

        <script>
        (function () {
            'use strict';

            var MAX_ROWS    = <?php echo (int) $max; ?>;
            var wrap        = document.getElementById( 'ihumbak-wrs-followups-wrap' );
            var tbody       = document.getElementById( 'ihumbak-wrs-followups-body' );
            var addBtn      = document.getElementById( 'ihumbak-wrs-add-followup' );
            var tpl         = document.getElementById( 'ihumbak-wrs-followup-row-tpl' );

            /**
             * Przenumerowuje wiersze i aktualizuje atrybuty `name` pól input.
             */
            function reindex() {
                var rows = tbody.querySelectorAll( '.ihumbak-wrs-followup-row' );
                rows.forEach( function ( row, idx ) {
                    var numCell = row.querySelector( '.ihumbak-wrs-followup-num' );
                    if ( numCell ) {
                        numCell.textContent = idx + 1;
                    }
                    var input = row.querySelector( 'input[type="number"]' );
                    if ( input ) {
                        input.name = 'ihumbak_wrs_email_followups[' + idx + '][delay_days]';
                    }
                } );

                addBtn.disabled = ( rows.length >= MAX_ROWS );
            }

            /**
             * Zamienia miejscami dwa sąsiednie wiersze.
             *
             * Wstawia `upper` przed `lower` w DOM, dzięki czemu `upper` ląduje wyżej.
             * Aby przesunąć klikniety wiersz w dół, należy wywołać z (next, row) —
             * wówczas sąsiad poniżej zostanie wstawiony przed nim, co efektywnie
             * przesuwa klikniety wiersz w dół.
             *
             * @param {HTMLElement} upper Wiersz, który po operacji ma znaleźć się wyżej.
             * @param {HTMLElement} lower Wiersz, który po operacji ma znaleźć się niżej.
             */
            function swapRows( upper, lower ) {
                tbody.insertBefore( upper, lower );
                reindex();
            }

            // Obsługa kliknięć delegowanych na tbody.
            tbody.addEventListener( 'click', function ( e ) {
                var btn = e.target;
                if ( ! btn || btn.tagName !== 'BUTTON' ) {
                    return;
                }

                var row = btn.closest( '.ihumbak-wrs-followup-row' );
                if ( ! row ) {
                    return;
                }

                if ( btn.classList.contains( 'ihumbak-wrs-remove-row' ) ) {
                    row.remove();
                    reindex();
                } else if ( btn.classList.contains( 'ihumbak-wrs-move-up' ) ) {
                    var prev = row.previousElementSibling;
                    if ( prev && prev.classList.contains( 'ihumbak-wrs-followup-row' ) ) {
                        swapRows( row, prev );
                    }
                } else if ( btn.classList.contains( 'ihumbak-wrs-move-down' ) ) {
                    var next = row.nextElementSibling;
                    if ( next && next.classList.contains( 'ihumbak-wrs-followup-row' ) ) {
                        swapRows( next, row );
                    }
                }
            } );

            // Obsługa przycisku "Dodaj przypomnienie".
            addBtn.addEventListener( 'click', function () {
                var rows = tbody.querySelectorAll( '.ihumbak-wrs-followup-row' );
                if ( rows.length >= MAX_ROWS ) {
                    return;
                }

                var clone = document.importNode( tpl.content, true );
                tbody.appendChild( clone );
                reindex();
            } );

            // Inicjalizacja (pierwsze przenumerowanie po wyrenderowaniu PHP).
            reindex();
        }());
        </script>
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
