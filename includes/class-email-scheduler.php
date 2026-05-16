<?php
/**
 * Planista wysyłki wiadomości e-mail z prośbą o ocenę.
 *
 * Nasłuchuje na zmianę statusu zamówienia WooCommerce i w odpowiednim
 * momencie kolejkuje zadanie Action Scheduler (`ihumbak_wrs_send_review_email`).
 * W przypadku zwrotu lub anulowania zamówienia odwołuje zaplanowane zadania.
 *
 * Klasa nie zawiera logiki wysyłki — jej rolą jest wyłącznie zarządzanie
 * kolejką zadań AS. Właściwy sender zostanie zaimplementowany w issue #6.
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ihumbak_WRS_Email_Scheduler
 *
 * Zarządza harmonogramem Action Scheduler dla wiadomości e-mail.
 *
 * Kontrakt publiczny (dla kodu zewnętrznego i przyszłych etapów):
 *
 * - SEND_ACTION_HOOK = 'ihumbak_wrs_send_review_email'
 *   Hook wywoływany przez Action Scheduler. Handlery muszą przyjmować
 *   tablicę argumentów w formacie zwracanym przez build_args():
 *     [ 'order_id' => <int>, 'step' => <int> ]
 *
 * - AS_GROUP = 'ihumbak_wrs'
 *   Grupa Action Scheduler używana przez wszystkie zadania pluginu.
 *
 * - build_args( int $order_id, int $step ): array
 *   Jedyne dozwolone miejsce konstruowania tablicy argumentów dla AS.
 *   Każdy nowy krok sekwencji wysyłki musi zostać dopisany do stałej STEPS,
 *   aby cancel_pending_for_order() mógł go odwołać (issue #9).
 */
class Ihumbak_WRS_Email_Scheduler {

    /**
     * Nazwa hooka Action Scheduler dla zadania wysyłki.
     */
    const SEND_ACTION_HOOK = 'ihumbak_wrs_send_review_email';

    /**
     * Grupa Action Scheduler używana przez plugin.
     */
    const AS_GROUP = 'ihumbak_wrs';

    /**
     * Numer pierwszego kroku sekwencji wysyłki.
     */
    const STEP_INITIAL = 0;

    /**
     * Maksymalna liczba przypomnień follow-up (nie licząc wysyłki początkowej).
     *
     * @since 1.2.0
     */
    const MAX_FOLLOWUPS = 3;

    /**
     * Wszystkie znane kroki sekwencji wysyłki.
     *
     * UWAGA: Ta tablica MUSI zawierać MAX_FOLLOWUPS + 1 wpisów (kroki 0..MAX_FOLLOWUPS).
     * cancel_pending_for_order() musi odwołać każdy krok osobno z pełnymi argami
     * z build_args() — Action Scheduler dopasowuje argumenty zadań dokładnie po
     * pełnej tablicy (domyślnie `partial_args_matching = 'off'`). Dodanie nowego
     * kroku wymaga aktualizacji tej stałej ORAZ stałej MAX_FOLLOWUPS.
     *
     * @since 1.2.0 Rozszerzona z [0] do [0, 1, 2, 3] (issue #9).
     */
    const STEPS = array( 0, 1, 2, 3 );

    /**
     * Konstruktor — rejestruje hooki.
     */
    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Rejestruje hooki WordPressa.
     */
    public function register_hooks(): void {
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
    }

    /**
     * Obsługuje zmianę statusu zamówienia WooCommerce.
     *
     * @param int       $order_id   ID zamówienia.
     * @param string    $old_status Poprzedni status (bez prefiksu wc-).
     * @param string    $new_status Nowy status (bez prefiksu wc-).
     * @param \WC_Order $order      Obiekt zamówienia — wymagany przez sygnaturę hooka,
     *                              nie jest tu bezpośrednio używany.
     */
    public function on_status_changed( $order_id, $old_status, $new_status, $order ): void {
        $order_id = (int) $order_id;

        if ( $order_id <= 0 ) {
            return;
        }

        if ( ! $this->is_action_scheduler_available() ) {
            return;
        }

        if ( $new_status === $this->get_trigger_status() ) {
            $this->schedule_initial_email( $order_id );
        } elseif ( in_array( $new_status, array( 'refunded', 'cancelled' ), true ) && $this->should_cancel_on_refund() ) {
            $this->cancel_pending_for_order( $order_id );
        }
    }

    /**
     * Kolejkuje pierwsze zadanie wysyłki e-mail dla zamówienia.
     *
     * Sprawdza, czy funkcjonalność jest włączona i czy zadanie nie zostało
     * już zaplanowane (deduplicja). Jeśli warunki są spełnione, rejestruje
     * jednorazowe zadanie Action Scheduler z odpowiednim opóźnieniem.
     *
     * @param int $order_id ID zamówienia.
     * @return bool True jeśli zadanie zostało zaplanowane, false w pozostałych przypadkach.
     */
    public function schedule_initial_email( int $order_id ): bool {
        if ( ! $this->is_feature_enabled() ) {
            return false;
        }

        if ( self::is_already_scheduled( $order_id, self::STEP_INITIAL ) ) {
            return false;
        }

        $timestamp = time() + $this->get_delay_seconds();

        as_schedule_single_action(
            $timestamp,
            self::SEND_ACTION_HOOK,
            self::build_args( $order_id, self::STEP_INITIAL ),
            self::AS_GROUP
        );

        return true;
    }

    /**
     * Odwołuje wszystkie oczekujące zadania AS dla danego zamówienia.
     *
     * Action Scheduler porównuje argumenty zadania dokładnie po pełnej
     * tablicy (JSON), więc każdy krok z STEPS musi zostać odwołany osobno
     * z argami zbudowanymi przez build_args(). Dzięki temu inwariancja
     * "build_args() to jedyne miejsce konstrukcji argów" jest zachowana.
     *
     * @param int $order_id ID zamówienia.
     */
    public function cancel_pending_for_order( int $order_id ): void {
        foreach ( self::STEPS as $step ) {
            as_unschedule_all_actions(
                self::SEND_ACTION_HOOK,
                self::build_args( $order_id, $step ),
                self::AS_GROUP
            );
        }
    }

    /**
     * Buduje tablicę argumentów dla Action Scheduler.
     *
     * Jedyne dozwolone miejsce konstruowania tej tablicy — zachowuje
     * stałą kolejność kluczy, co jest wymagane przez AS przy deduplikacji.
     * Klucz `order_id` MUSI być obecny we wszystkich krokach (issue #9).
     *
     * @param int $order_id ID zamówienia.
     * @param int $step     Numer kroku sekwencji wysyłki (domyślnie STEP_INITIAL = 0).
     * @return array Tablica argumentów gotowa do przekazania do funkcji AS.
     */
    public static function build_args( int $order_id, int $step = self::STEP_INITIAL ): array {
        return array(
            'order_id' => $order_id,
            'step'     => $step,
        );
    }

    /**
     * Sprawdza, czy istnieje już zaplanowane zadanie dla danego zamówienia i kroku.
     *
     * @param int $order_id ID zamówienia.
     * @param int $step     Numer kroku (domyślnie STEP_INITIAL = 0).
     * @return bool True jeśli zadanie jest już w kolejce AS.
     */
    public static function is_already_scheduled( int $order_id, int $step = self::STEP_INITIAL ): bool {
        return (bool) as_next_scheduled_action(
            self::SEND_ACTION_HOOK,
            self::build_args( $order_id, $step ),
            self::AS_GROUP
        );
    }

    // -------------------------------------------------------------------------
    // Prywatne helpery
    // -------------------------------------------------------------------------

    /**
     * Sprawdza, czy funkcjonalność e-mail jest włączona w ustawieniach.
     *
     * @return bool
     */
    private function is_feature_enabled(): bool {
        return (bool) get_option( 'ihumbak_wrs_email_enabled', false );
    }

    /**
     * Sprawdza, czy wymagane funkcje Action Scheduler są dostępne.
     *
     * Action Scheduler jest dołączony do WooCommerce, ale nigdy nie należy
     * zakładać jego dostępności bez jawnej weryfikacji.
     *
     * @return bool
     */
    private function is_action_scheduler_available(): bool {
        return function_exists( 'as_schedule_single_action' )
            && function_exists( 'as_next_scheduled_action' )
            && function_exists( 'as_unschedule_all_actions' );
    }

    /**
     * Zwraca status zamówienia wyzwalający wysyłkę e-maila.
     *
     * Opcja może zawierać prefiks `wc-` (np. "wc-completed") — jest on
     * usuwany, bo WooCommerce przekazuje statusy bez prefiksu do hooka
     * `woocommerce_order_status_changed`.
     *
     * @return string Klucz statusu, np. 'completed'.
     */
    private function get_trigger_status(): string {
        $status = (string) get_option( 'ihumbak_wrs_email_trigger_status', 'completed' );

        // Usuń opcjonalny prefiks wc- dodawany przez niektóre interfejsy
        if ( strncmp( $status, 'wc-', 3 ) === 0 ) {
            $status = substr( $status, 3 );
        }

        return sanitize_key( $status );
    }

    /**
     * Zwraca opóźnienie wysyłki w sekundach.
     *
     * Wartość opcji przechowywana jest w dniach. Wartości ujemne są
     * sprowadzane do 0 (wysyłka natychmiastowa po wyzwoleniu).
     *
     * @return int Liczba sekund opóźnienia.
     */
    private function get_delay_seconds(): int {
        $days = (int) get_option( 'ihumbak_wrs_email_delay_days', 7 );

        if ( $days < 0 ) {
            $days = 0;
        }

        return $days * DAY_IN_SECONDS;
    }

    /**
     * Sprawdza, czy zadania AS mają być anulowane przy zwrocie/anulowaniu.
     *
     * Ta sama flaga steruje zachowaniem zarówno dla statusu `refunded`,
     * jak i `cancelled`.
     *
     * @return bool
     */
    private function should_cancel_on_refund(): bool {
        return (bool) get_option( 'ihumbak_wrs_email_skip_refunded', true );
    }

    // -------------------------------------------------------------------------
    // Statyczne metody publiczne (follow-up, issue #9)
    // -------------------------------------------------------------------------

    /**
     * Planuje follow-up dla podanego zamówienia i numeru kroku.
     *
     * Odczytuje konfigurację przypomnień z opcji `ihumbak_wrs_email_followups`,
     * waliduje wpis dla żądanego kroku, a następnie rejestruje jednorazowe
     * zadanie Action Scheduler z odpowiednim opóźnieniem.
     *
     * Metoda jest statyczna, aby mogła być wywoływana z Ihumbak_WRS_Email_Followup_Scheduler
     * bez potrzeby utrzymywania instancji schedulera.
     *
     * @since 1.2.0
     *
     * @param int $order_id  ID zamówienia.
     * @param int $next_step Numer kroku follow-up (1..MAX_FOLLOWUPS).
     * @return bool True jeśli zadanie zostało zaplanowane, false w pozostałych przypadkach.
     */
    public static function schedule_followup( int $order_id, int $next_step ): bool {
        // Sprawdź, czy funkcjonalność e-mail jest włączona.
        if ( ! (bool) get_option( 'ihumbak_wrs_email_enabled', false ) ) {
            return false;
        }

        // Walidacja zakresu kroku — step 0 to wysyłka początkowa, 1..MAX_FOLLOWUPS to follow-upy.
        if ( $next_step < 1 || $next_step > self::MAX_FOLLOWUPS ) {
            return false;
        }

        // Odczyt konfiguracji follow-upów; re-indeksowanie, by nie polegać na kluczach.
        $followups = get_option( 'ihumbak_wrs_email_followups', array() );
        if ( ! is_array( $followups ) ) {
            $followups = array();
        }
        $followups = array_values( $followups );

        // Indeks w tablicy followups: krok 1 odpowiada wpisowi 0, krok 2 — wpisowi 1, itd.
        $entry_index = $next_step - 1;
        if ( ! isset( $followups[ $entry_index ] ) || ! is_array( $followups[ $entry_index ] ) ) {
            return false;
        }

        $entry      = $followups[ $entry_index ];
        $delay_days = isset( $entry['delay_days'] ) ? (int) $entry['delay_days'] : 0;

        // Zakres 1–365; wartości poza zakresem dyskwalifikują wpis.
        $delay_days = max( 1, min( 365, $delay_days ) );
        if ( $delay_days < 1 ) {
            return false;
        }

        // Deduplicja — nie planuj jeśli już istnieje zadanie dla tego kroku.
        if ( self::is_already_scheduled( $order_id, $next_step ) ) {
            return false;
        }

        as_schedule_single_action(
            time() + $delay_days * DAY_IN_SECONDS,
            self::SEND_ACTION_HOOK,
            self::build_args( $order_id, $next_step ),
            self::AS_GROUP
        );

        return true;
    }
}
