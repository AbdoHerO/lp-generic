<?php
/**
 * Inline offer editing.
 *
 * Offers are the only thing on the page that takes money, so the two risks
 * worth guarding are: an edit reaching another product's pricing, and two
 * offers both claiming to be the default (the page preselects one, so the
 * second silently wins and the first looks ignored).
 *
 * Run:  php tests/offers_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
function db(): PDO { return $GLOBALS['PDO']; }
function clean_string($v, int $max = 500): string { return is_string($v) ? trim($v) : ''; }

$PDO->exec("CREATE TABLE product_offers (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INT,
            label TEXT, quantity INT, total_price REAL, compare_price REAL,
            is_recommended INT, free_shipping INT, is_default INT, requires_options INT, position INT)");

// Two products, so cross-product edits can be attempted.
$PDO->exec("INSERT INTO product_offers (product_id,label,quantity,total_price,compare_price,is_recommended,free_shipping,is_default,requires_options,position) VALUES
    (1,'واحد',1,249,499,0,0,1,1,1),
    (1,'إثنان',2,459,998,1,1,0,1,2),
    (2,'منتج آخر',1,99,0,0,0,1,1,1)");

/** The controller's UPDATE, extracted so the real SQL is what runs here. */
function edit_offer(PDO $pdo, int $offerId, int $productId, array $post): void {
    $data = [
        ':l'  => clean_string($post['label'] ?? '', 160),
        ':q'  => max(1, (int)($post['quantity'] ?? 1)),
        ':t'  => (float)($post['total_price'] ?? 0),
        ':c'  => ($post['compare_price'] ?? '') !== '' ? (float)$post['compare_price'] : null,
        ':r'  => !empty($post['is_recommended']) ? 1 : 0,
        ':f'  => !empty($post['free_shipping']) ? 1 : 0,
        ':d'  => !empty($post['is_default']) ? 1 : 0,
        ':ro' => !empty($post['requires_options']) ? 1 : 0,
        ':po' => (int)($post['position'] ?? 0),
        ':i'  => $offerId,
        ':p'  => $productId,
    ];
    $pdo->prepare("UPDATE product_offers SET label=:l, quantity=:q, total_price=:t,
                   compare_price=:c, is_recommended=:r, free_shipping=:f, is_default=:d,
                   requires_options=:ro, position=:po
                   WHERE id=:i AND product_id=:p")->execute($data);

    if ($data[':d']) {
        $pdo->prepare("UPDATE product_offers SET is_default = 0 WHERE product_id = :p AND id <> :i")
            ->execute([':p' => $productId, ':i' => $offerId]);
    }
}

function row(PDO $pdo, int $id): array {
    $st = $pdo->prepare("SELECT * FROM product_offers WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: [];
}

// ── a plain edit ───────────────────────────────────────────────────────────
edit_offer($PDO, 1, 1, ['label' => 'واحد ب 199', 'quantity' => 1, 'total_price' => '199.00',
                        'compare_price' => '399', 'position' => 5,
                        'is_default' => '1', 'requires_options' => '1']);
$o = row($PDO, 1);
check('the label is updated',    $o['label'] === 'واحد ب 199', $o['label']);
check('the price is updated',    (float)$o['total_price'] === 199.0, (string)$o['total_price']);
check('the compare price is updated', (float)$o['compare_price'] === 399.0);
check('the position is updated', (int)$o['position'] === 5);
check('unticked flags are cleared', (int)$o['is_recommended'] === 0);
check('ticked flags are set',    (int)$o['requires_options'] === 1);

// An empty compare price means "no compare price", not zero — a 0 would render
// a struck-through "0 د.م" next to the real one.
edit_offer($PDO, 1, 1, ['label' => 'x', 'quantity' => 1, 'total_price' => '199', 'compare_price' => '']);
check('an empty compare price becomes NULL', row($PDO, 1)['compare_price'] === null);

// ── quantity is never below 1 ──────────────────────────────────────────────
edit_offer($PDO, 1, 1, ['label' => 'x', 'quantity' => 0, 'total_price' => '199']);
check('quantity 0 is clamped to 1',  (int)row($PDO, 1)['quantity'] === 1);
edit_offer($PDO, 1, 1, ['label' => 'x', 'quantity' => -5, 'total_price' => '199']);
check('a negative quantity is clamped', (int)row($PDO, 1)['quantity'] === 1);

// ── exactly one default per product ────────────────────────────────────────
$PDO->exec("UPDATE product_offers SET is_default = 1 WHERE id IN (1,2)");   // force the broken state
edit_offer($PDO, 2, 1, ['label' => 'إثنان', 'quantity' => 2, 'total_price' => '459', 'is_default' => '1']);

check('the edited offer is the default',     (int)row($PDO, 2)['is_default'] === 1);
check('the other offer lost the default',    (int)row($PDO, 1)['is_default'] === 0);
check('exactly one default remains',
    (int)$PDO->query("SELECT COUNT(*) FROM product_offers WHERE product_id=1 AND is_default=1")->fetchColumn() === 1);

// Clearing the box on the only default leaves none — the checklist then flags
// it rather than the code silently picking one.
edit_offer($PDO, 2, 1, ['label' => 'إثنان', 'quantity' => 2, 'total_price' => '459']);
check('unticking default clears it',
    (int)$PDO->query("SELECT COUNT(*) FROM product_offers WHERE product_id=1 AND is_default=1")->fetchColumn() === 0);

// The other product must be untouched by any of this.
check('another product keeps its default', (int)row($PDO, 3)['is_default'] === 1);

// ── the edit is scoped to the product ──────────────────────────────────────
$before = row($PDO, 3);
edit_offer($PDO, 3, 1, ['label' => 'HIJACKED', 'quantity' => 9, 'total_price' => '1']);
$after = row($PDO, 3);
check('an offer from another product is not edited', $after['label'] === $before['label'], $after['label']);
check('its price is untouched',   (float)$after['total_price'] === (float)$before['total_price']);

// ── the view and controller agree ──────────────────────────────────────────
$view = file_get_contents($ROOT . '/admin/views/product-edit.php');
$ctrl = file_get_contents($ROOT . '/admin/product-edit.php');

check('the row has an edit button',   str_contains($view, 'data-edit-offer='));
check('the row has a delete button',  str_contains($view, 'data-del-offer='));
check('an edit row exists per offer', str_contains($view, 'data-offer-edit='));
check('edit rows start hidden',       str_contains($view, 'data-offer-edit="<?= $oid ?>" hidden'));

// A <form> cannot wrap a set of <td>s, so the inputs reach a form outside the
// table with form=. Both halves must be present or saving silently does nothing.
check('inputs target an outside form', str_contains($view, 'form="offerForm<?= $oid ?>"'));
check('that form exists',              str_contains($view, 'id="offerForm<?= (int)$o[\'id\'] ?>"'));
check('the form posts edit_offer',     str_contains($view, 'value="edit_offer"'));
check('the form carries CSRF',
    (bool)preg_match('/id="offerForm.*?name="_csrf"/s', $view));
check('the form carries the offer id', (bool)preg_match('/id="offerForm.*?name="offer_id"/s', $view));

foreach (['label', 'quantity', 'total_price', 'compare_price', 'position',
          'is_default', 'is_recommended', 'free_shipping', 'requires_options'] as $f) {
    check("the edit row has $f",
        (bool)preg_match('/data-offer-edit=.*?name="' . preg_quote($f, '/') . '"/s', $view));
}

check('the controller handles edit_offer', str_contains($ctrl, "\$action === 'edit_offer'"));
check('the update is scoped by product',   str_contains($ctrl, 'WHERE id=:i AND product_id=:p'));
check('single-default is enforced',        str_contains($ctrl, '$keepOneDefault'));
check('add and edit share their fields',   str_contains($ctrl, '$offerFields'));
check('edits are logged',                  str_contains($ctrl, "'offer #' . \$offerId"));
check('CSRF is required first',
    strpos($ctrl, 'admin_require_csrf()') < strpos($ctrl, "'edit_offer'"));

// Adding must not reintroduce the two-defaults state either.
check('add also enforces one default',
    (bool)preg_match("/add_offer.*?keepOneDefault/s", $ctrl));

// ── the editor cancels cleanly ─────────────────────────────────────────────
check('cancel restores the original values', str_contains($view, 'i.defaultValue'));
check('cancel restores checkboxes too',      str_contains($view, 'i.defaultChecked'));
check('only one row edits at a time',        str_contains($view, 'if (r.dataset.offerEdit !== String(id)) cancel'));
check('Escape closes the editor',            str_contains($view, "e.key !== 'Escape'"));
check('an empty offer list explains itself', str_contains($view, 'لا توجد عروض بعد'));
check('a saved row is highlighted',          str_contains($view, 'just-saved'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
