<?php
/**
 * Lekki test CLI dla `Ihumbak_WRS_Email_Template`.
 * Uruchomienie: `php tests/test-email-template.php`
 * Kod wyjścia 0 = pass, 1 = pierwszy fail. Bez frameworka.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require __DIR__ . '/../includes/class-email-template.php';

$ran = 0;

/**
 * Asercja równości — przy niezgodności drukuje szczegóły i kończy z kodem 1.
 */
function assert_eq($expected, $actual, $label) {
    global $ran;
    $ran++;
    if ($expected !== $actual) {
        echo "FAIL: {$label}\n";
        echo "  Expected: " . var_export($expected, true) . "\n";
        echo "  Actual:   " . var_export($actual, true) . "\n";
        exit(1);
    }
}

/**
 * Asercja prawdziwości warunku boolowskiego.
 */
function assert_true($cond, $label) {
    assert_eq(true, (bool) $cond, $label);
}

/**
 * Asercja fałszywości warunku boolowskiego.
 */
function assert_false($cond, $label) {
    assert_eq(false, (bool) $cond, $label);
}

// ---------------------------------------------------------------------------
// 1. Wszystkie sześć znanych tokenów zostaje podstawionych.
// ---------------------------------------------------------------------------
$known = Ihumbak_WRS_Email_Template::get_known_placeholders();

$context_all = array(
    'customer_name'       => 'Jan Łukasz Żółć',
    'customer_first_name' => 'Jan',
    'order_id'            => '12345',
    'order_date'          => '2026-05-15',
    'site_name'           => 'Sklep Testowy',
    'site_url'            => 'https://example.com',
);

$template_all = '{customer_name} {customer_first_name} {order_id} {order_date} {site_name} {site_url}';
$out_all = Ihumbak_WRS_Email_Template::render($template_all, $context_all);

foreach ($known as $key) {
    assert_true(
        strpos($out_all, $context_all[$key]) !== false,
        "Placeholder {$key} substituted"
    );
}
assert_false(strpos($out_all, '{') !== false, 'No opening brace remains after full substitution');
assert_false(strpos($out_all, '}') !== false, 'No closing brace remains after full substitution');

// ---------------------------------------------------------------------------
// 2. Nieznany token zostaje usunięty (zastąpiony pustym ciągiem).
// ---------------------------------------------------------------------------
$out2 = Ihumbak_WRS_Email_Template::render('Hello {foo} world.', array());
assert_eq('Hello  world.', $out2, 'Unknown placeholder stripped to empty string');
assert_false(strpos($out2, '{foo}') !== false, 'Unknown placeholder does not leak into output');

// ---------------------------------------------------------------------------
// 3. Brakujący klucz w kontekście renderuje pusty ciąg.
// ---------------------------------------------------------------------------
$out3 = Ihumbak_WRS_Email_Template::render('Order {order_id}.', array());
assert_eq('Order .', $out3, 'Missing context key renders empty');

// ---------------------------------------------------------------------------
// 4. Wartość pusty ciąg w kontekście renderuje pusty ciąg (bez literału tokenu).
// ---------------------------------------------------------------------------
$out4 = Ihumbak_WRS_Email_Template::render('Hi {customer_name}.', array('customer_name' => ''));
assert_eq('Hi .', $out4, 'Empty string context value renders empty');
assert_false(strpos($out4, '{customer_name}') !== false, 'Token literal does not appear when value is empty string');

// ---------------------------------------------------------------------------
// 5. Powtórzony token — oba wystąpienia zostają zastąpione.
// ---------------------------------------------------------------------------
$out5 = Ihumbak_WRS_Email_Template::render('{site_name} – {site_name}', array('site_name' => 'X'));
assert_eq('X – X', $out5, 'Repeated placeholder both substituted');

// ---------------------------------------------------------------------------
// 6. Malformed tokeny przechodzą przez silnik bez zmian.
// ---------------------------------------------------------------------------
$malformed_cases = array(
    '{}'       => '{}',
    '{ foo }'  => '{ foo }',
    '{Foo}'    => '{Foo}',
    '{foo'     => '{foo',
    'foo}'     => 'foo}',
);

foreach ($malformed_cases as $input => $expected) {
    $out6 = Ihumbak_WRS_Email_Template::render($input, array());
    assert_eq($expected, $out6, "Malformed token passes through unchanged: {$input}");
}

// ---------------------------------------------------------------------------
// 7. to_plain_text() — smoke test z tagami blokowymi i encjami HTML.
// ---------------------------------------------------------------------------
$html7 = '<p>Hello <strong>world</strong>.</p><p>Second &amp; line.</p>';
$out7  = Ihumbak_WRS_Email_Template::to_plain_text($html7);

assert_false(strpos($out7, '<') !== false, 'to_plain_text: no < in output');
assert_false(strpos($out7, '>') !== false, 'to_plain_text: no > in output');
assert_true(strpos($out7, 'Hello world.') !== false, 'to_plain_text: contains "Hello world."');
assert_true(strpos($out7, 'Second & line.') !== false, 'to_plain_text: contains "Second & line." (entity decoded)');
assert_true(strpos($out7, "\n") !== false, 'to_plain_text: contains newline (paragraph spacing preserved)');

// ---------------------------------------------------------------------------
// 8. Silnik NIE escapuje wartości HTML (kontrakt — wywołujący odpowiada za escaping).
// ---------------------------------------------------------------------------
// Blokuje przyszłe zmiany, które nieświadomie dodałyby escaping do silnika.
$out8 = Ihumbak_WRS_Email_Template::render(
    'Name: {customer_name}',
    array('customer_name' => '<script>alert(1)</script>')
);
assert_true(
    strpos($out8, '<script>alert(1)</script>') !== false,
    'Engine does NOT escape HTML values (documented contract — raw value passes through verbatim)'
);

// ---------------------------------------------------------------------------
// 9. Wartość null w kontekście renderuje pusty ciąg (bezpieczeństwo PHP 8.1+).
// ---------------------------------------------------------------------------
$out9 = Ihumbak_WRS_Email_Template::render('Hi {customer_name}.', array('customer_name' => null));
assert_eq('Hi .', $out9, 'null context value renders empty (PHP 8.1+ safety)');

// ---------------------------------------------------------------------------
// 10. Wartość tablicowa w kontekście renderuje pusty ciąg (defensywnie).
// ---------------------------------------------------------------------------
$out10 = Ihumbak_WRS_Email_Template::render('Hi {customer_name}.', array('customer_name' => array('a')));
assert_eq('Hi .', $out10, 'Array context value renders empty (defensive)');

// ---------------------------------------------------------------------------
// Wszystkie asercje przeszły.
// ---------------------------------------------------------------------------
echo "OK: {$ran} assertions passed.\n";
exit(0);
