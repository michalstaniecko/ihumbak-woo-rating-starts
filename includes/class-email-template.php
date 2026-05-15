<?php
/**
 * Silnik renderowania szablonów email.
 * Czysta logika, bez hooków. Używany przez sender, test-send i podgląd.
 *
 * Klasa nie zawiera żadnych ciągów widocznych dla użytkownika,
 * dlatego nie używa funkcji tłumaczących __() ani esc_html().
 *
 * @package ihumbak-woo-rating-stars
 * @since   1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ihumbak_WRS_Email_Template {

    /**
     * Lista obsługiwanych nazw tokenów (bez nawiasów klamrowych).
     */
    const KNOWN_PLACEHOLDERS = array(
        'customer_name',
        'customer_first_name',
        'customer_last_name',
        'order_number',
        'order_date',
        'site_name',
        'site_url',
        'products_list',
        'rating_links_list',
    );

    /**
     * Wyrażenie regularne dopasowujące tokeny w szablonie.
     * Pasuje wyłącznie do liter małych i podkreślenia wewnątrz nawiasów klamrowych.
     * Tokeny malformed (np. {}, { foo }, {Foo}, {foo) NIE są dopasowywane
     * i celowo przechodzą przez silnik bez zmian — zachowanie zachowawcze.
     */
    const PLACEHOLDER_PATTERN = '/\{([a-z_]+)\}/';

    /**
     * Renderuje szablon, zastępując tokeny {placeholder} wartościami z tablicy kontekstu.
     *
     * Kontrakt escapowania:
     *   Silnik NIE escapuje wartości. Wywołujący odpowiada za przekazanie wartości
     *   przygotowanych przez esc_html() gdy szablon jest treścią HTML (body),
     *   oraz surowych wartości dla linii tematu (subject).
     *
     * Zachowanie dla malformed tokenów:
     *   Tokeny niezgodne ze wzorcem {[a-z_]+} (np. {}, { foo }, {Foo}, {foo)
     *   przechodzą przez silnik bez zmian — nie są modyfikowane ani usuwane.
     *
     * Zachowanie dla nieznanych tokenów:
     *   Token obecny w szablonie, ale nieobecny jako klucz w $context,
     *   jest zastępowany pustym ciągiem znaków.
     *
     * @param string $template Szablon z tokenami {placeholder}.
     * @param array  $context  Tablica asocjacyjna klucz => wartość podstawień.
     *
     * @return string Wyrenderowany ciąg znaków.
     */
    public static function render($template, $context) {
        return preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function ($matches) use ($context) {
                $key = $matches[1];

                if (!array_key_exists($key, $context)) {
                    return '';
                }

                $v = $context[$key];

                if (is_scalar($v) || (is_object($v) && method_exists($v, '__toString'))) {
                    return (string) $v;
                }

                return '';
            },
            $template
        );
    }

    /**
     * Konwertuje HTML na czysty tekst z zachowaniem struktury akapitów.
     *
     * Tagi blokowe (</p>, </div>, </li>, </h1>–</h6>) oraz <br> (wszystkie warianty)
     * są zastępowane znakiem nowej linii przed usunięciem znaczników, dzięki czemu
     * odstępy między akapitami zostają zachowane w wersji tekstowej.
     *
     * Fallback wp_strip_all_tags vs strip_tags:
     *   Gdy funkcja wp_strip_all_tags() jest dostępna (środowisko WordPress), jest używana.
     *   W przeciwnym razie używane jest natywne strip_tags() — umożliwia to uruchomienie
     *   klasy poza środowiskiem WP (np. testy CLI w czystym PHP).
     *
     * @param string $html Ciąg HTML do konwersji.
     *
     * @return string Czysty tekst z zachowanymi odstępami między akapitami.
     */
    public static function to_plain_text($html) {
        $s = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Zamień tagi blokowe i <br> na znaki nowej linii przed usunięciem tagów.
        $s = preg_replace(
            '/<br\s*\/?>\s*|<\/(?:p|div|li|h[1-6])>/i',
            "\n",
            $s
        );

        if (function_exists('wp_strip_all_tags')) {
            $s = wp_strip_all_tags($s);
        } else {
            $s = strip_tags($s);
        }

        // Zredukuj trzy lub więcej kolejnych nowych linii do dwóch.
        $s = preg_replace("/\n{3,}/", "\n\n", $s);

        return trim($s);
    }

    /**
     * Zwraca listę obsługiwanych nazw tokenów (bez nawiasów klamrowych).
     *
     * @return array Tablica indeksowana ciągów znaków.
     */
    public static function get_known_placeholders() {
        return self::KNOWN_PLACEHOLDERS;
    }
}
