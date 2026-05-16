<?php
/**
 * Planista przypomnień follow-up dla wiadomości e-mail z prośbą o ocenę.
 *
 * Nasłuchuje na hook `ihumbak_wrs_email_send_complete` i — o ile wysyłka
 * zakończyła się sukcesem — planuje kolejny krok sekwencji (follow-up)
 * przez Ihumbak_WRS_Email_Scheduler::schedule_followup(). Łańcuch jest
 * przerywany, gdy:
 *   - wynik pochodzi z wysyłki testowej,
 *   - status wyniku jest inny niż STATUS_SENT (np. SKIPPED lub FAILED),
 *   - następny krok przekracza MAX_FOLLOWUPS.
 *
 * @package Ihumbak_WooCommerce_Rating_Stars
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ihumbak_WRS_Email_Followup_Scheduler
 *
 * Reaguje na zakończenie wysyłki e-mail i planuje następny krok follow-up.
 */
class Ihumbak_WRS_Email_Followup_Scheduler {

    /**
     * Konstruktor — rejestruje hook nasłuchujący na wynik wysyłki.
     *
     * @since 1.2.0
     */
    public function __construct() {
        add_action(
            'ihumbak_wrs_email_send_complete',
            array( $this, 'on_send_complete' ),
            10,
            3
        );
    }

    /**
     * Obsługuje zakończenie wysyłki i planuje kolejny krok follow-up.
     *
     * Metoda jest wywoływana przez hook `ihumbak_wrs_email_send_complete`
     * zarówno po wysyłkach wyzwalanych przez Action Scheduler, jak i po
     * ręcznym ponownym wysłaniu z poziomu zamówienia. Wysyłki testowe
     * NIE uruchamiają tego hooka (patrz: Ihumbak_WRS_Email_Sender::send_test).
     *
     * @since 1.2.0
     *
     * @param Ihumbak_WRS_Email_Send_Result $result   Wynik wysyłki.
     * @param int                           $order_id ID zamówienia.
     * @param int                           $step     Bieżący krok sekwencji (0 = wysyłka początkowa).
     */
    public function on_send_complete( Ihumbak_WRS_Email_Send_Result $result, int $order_id, int $step ): void {
        // Wysyłki testowe nigdy nie wyzwalają łańcucha follow-up.
        if ( $result->is_test() ) {
            return;
        }

        // Łańcuch kontynuujemy wyłącznie po pomyślnej wysyłce.
        // STATUS_SKIPPED lub STATUS_FAILED zatrzymują sekwencję.
        if ( $result->get_status() !== Ihumbak_WRS_Email_Send_Result::STATUS_SENT ) {
            return;
        }

        // Sprawdzenie czy następny krok mieści się w dozwolonym zakresie.
        $next_step = $step + 1;
        if ( $next_step > Ihumbak_WRS_Email_Scheduler::MAX_FOLLOWUPS ) {
            return;
        }

        // Próba zaplanowania follow-upu; zwracana wartość jest ignorowana —
        // brak wpisu w konfiguracji lub wyłączona funkcja to warunki normalne.
        Ihumbak_WRS_Email_Scheduler::schedule_followup( $order_id, $next_step );
    }
}
