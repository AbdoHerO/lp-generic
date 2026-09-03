<?php
/**
 * The order endpoint's two response modes and its bot defences.
 *
 * Checked at the source level rather than by booting a request, because the
 * controller calls exit() and redirect() on nearly every path. What matters
 * here is that the JSON branch and the HTML branch stay in step: an AJAX
 * submission that silently falls back to an HTML error page would look to the
 * shopper like the button simply stopped working.
 *
 * Run:  php tests/order_submit_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

$ctrl = file_get_contents($ROOT . '/src/Controllers/LeadController.php');
$js   = file_get_contents($ROOT . '/public/assets/js/product.js');
$view = file_get_contents($ROOT . '/src/Views/product.php');

// ── content negotiation ────────────────────────────────────────────────────
check('the controller detects an AJAX request', str_contains($ctrl, 'private function wantsJson()'));
check('detection accepts the ajax flag',        str_contains($ctrl, "\$_POST['ajax'] ?? ''"));
check('detection accepts an Accept header',     str_contains($ctrl, 'HTTP_ACCEPT'));

// ── every exit path answers in the right format ────────────────────────────
check('validation failure can answer JSON',  str_contains($ctrl, "json_response(['ok' => false, 'error' => \$msg], \$code)"));
check('success can answer JSON',             str_contains($ctrl, "'ok'       => true"));
check('the honeypot answers in kind',
    (bool)preg_match("/website.*?wantsJson\(\).*?json_response/s", $ctrl));
check('a rate limit keeps its 429 in JSON',  str_contains($ctrl, '$code = http_response_code()'));
check('a non-error status falls back to 422', str_contains($ctrl, '$code < 400) $code = 422'));

// ── the JSON success payload carries what the pixel needs ──────────────────
foreach (["'id'       => \$product['slug']", "'value'    => \$totalPrice",
          "'quantity' => \$qty", "'currency' => 'MAD'",
          "'event_id' => 'purchase.' . \$leadId", "'redirect' =>"] as $field) {
    check('success payload has ' . trim(explode('=>', $field)[0], " '"), str_contains($ctrl, $field));
}
check('the AJAX path marks the purchase as fired',
    (bool)preg_match("/wantsJson\(\)\).*?purchase_fired.*?json_response/s", $ctrl));

// Firing on the server side and again on the thank-you page would double-count,
// so the AJAX branch must claim the flag before answering.
check('the thank-you page will not re-fire it',
    str_contains($ctrl, "\$_SESSION['purchase_fired'] = [\$leadId => true];"));
check('the value sent is the server-recomputed price',
    strpos($ctrl, '$totalPrice = (float)$offer[\'total_price\'];') < strpos($ctrl, "'value'    => \$totalPrice"));

// ── the browser side ───────────────────────────────────────────────────────
check('the form submits over fetch',        str_contains($js, "fetch(form.action"));
check('it flags the request as ajax',       str_contains($js, "data.append('ajax', '1')"));
check('it asks for JSON',                   str_contains($js, "'Accept': 'application/json'"));
check('it sends cookies',                   str_contains($js, "credentials: 'same-origin'"));
check('it fires Purchase from the response', str_contains($js, "window.LPX.track('purchase', body.purchase)"));
check('it fires Lead too',                  str_contains($js, "window.LPX.track('lead'"));
check('it shows an in-place confirmation',  str_contains($js, 'function showSuccess'));
check('it redirects after confirming',      str_contains($js, 'window.location.href = body.redirect'));
check('it re-enables the button on error',  str_contains($js, 'btn.textContent = btnText'));
check('it shows the server error text',     str_contains($js, 'body.error'));

// Degradation: none of this may be load-bearing.
check('fetch is feature-detected',          str_contains($js, 'if (!window.fetch || !window.FormData) return;'));
check('a network failure falls back to a plain POST', str_contains($js, 'form.submit();'));
check('no arguments.callee (invalid in strict mode)', !str_contains($js, 'arguments.callee'));
// strpos would find the first preventDefault in the validation block, which is
// unrelated — the one that matters is the next one after the feature check.
$guardAt = strpos($js, 'if (!window.fetch || !window.FormData) return;');
check('preventDefault comes right after the feature check',
    $guardAt !== false && strpos($js, 'e.preventDefault();', $guardAt) !== false
        && strpos($js, 'e.preventDefault();', $guardAt) - $guardAt < 120,
    $guardAt === false ? 'guard missing' : 'gap ' . (strpos($js, 'e.preventDefault();', $guardAt) - $guardAt));

// InitiateCheckout must still fire before the network call, not after it.
check('InitiateCheckout fires before the request',
    strpos($js, "trackOffer('initiate_checkout', offer)") < strpos($js, 'fetch(form.action'));

// ── the anti-bot fields still ride along ───────────────────────────────────
// FormData serialises the whole form, so these travel with an AJAX submit too.
check('the honeypot is inside the form',    str_contains($view, 'name="website"'));
check('the timing stamp is inside the form', str_contains($view, 'name="form_ts"'));
check('the offer id is inside the form',    str_contains($view, 'name="offer_id"'));

// ── the ordinary POST path is untouched ────────────────────────────────────
check('the HTML redirect still exists',     str_contains($ctrl, "redirect(base_url('thank-you?o=' . \$leadId));"));
check('the HTML error page still exists',   str_contains($ctrl, "render('product-error'"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
