/**
 * Toggle widoczności podpaneli (fixed / auto) dla pola "Kupon dla klienta"
 * na podstawie wybranej wartości radia `ihumbak_wrs_email_coupon_mode`.
 *
 * Wyłącznie warstwa prezentacyjna — backend zawsze odczytuje wszystkie
 * trzy opcje niezależnie, więc brak JS nie psuje funkcjonalności (jedynie
 * pokazuje wszystkie podpanele jednocześnie).
 *
 * @package Ihumbak_WRS
 * @since   1.5.0
 */
(function () {
    'use strict';

    var radios     = document.querySelectorAll( 'input[name="ihumbak_wrs_email_coupon_mode"]' );
    var panelFixed = document.getElementById( 'ihumbak-wrs-coupon-subpanel-fixed' );
    var panelAuto  = document.getElementById( 'ihumbak-wrs-coupon-subpanel-auto' );

    if ( ! radios.length ) {
        return;
    }

    /**
     * Aktualizuje widoczność podpaneli na podstawie wybranego radia.
     *
     * @param {string} val Aktualna wartość pola radio.
     */
    function updatePanels( val ) {
        if ( panelFixed ) {
            panelFixed.style.display = ( val === 'fixed' ) ? '' : 'none';
        }
        if ( panelAuto ) {
            panelAuto.style.display = ( val === 'auto' ) ? '' : 'none';
        }
    }

    radios.forEach( function ( radio ) {
        radio.addEventListener( 'change', function () {
            updatePanels( this.value );
        } );
    } );

    // Inicjalizacja — upewnij się, że stan DOM odpowiada wczytanej wartości.
    var checked = document.querySelector( 'input[name="ihumbak_wrs_email_coupon_mode"]:checked' );
    if ( checked ) {
        updatePanels( checked.value );
    }
}());
