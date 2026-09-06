<?php
/**
 * Public layout safety — the rules that keep the landing page from blanking.
 *
 * The bug this guards against: a honeypot field hidden with `left:-9999px`
 * had no positioned ancestor, so its containing block was the initial
 * containing block, above <body>. body's overflow-x:hidden therefore could not
 * clip it, and in an RTL document leftward overflow is the natural scroll
 * direction — the page grew ~10,000px of scrollable width and opened scrolled
 * into the empty part. Visitors saw a blank beige screen and the ads spent
 * against it.
 *
 * Everything here is cheap and static, because the failure is invisible in
 * every test that only checks the HTML: the markup was correct the whole time.
 *
 * Run:  php tests/layout_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

/** Strip comments so the rules below never match an explanation of the bug. */
function css(string $file): string {
    return preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents($file)) ?? '';
}

$publicCss = [
    'theme.css'   => css($ROOT . '/public/assets/css/theme.css'),
    'product.css' => css($ROOT . '/public/assets/css/product.css'),
    'home.css'    => css($ROOT . '/public/assets/css/home.css'),
];

// ── no off-screen positioning anywhere on the public pages ─────────────────
// Any of these pushes a box outside the page. In LTR the browser usually hides
// the damage; in RTL it becomes scrollable space and the page opens blank.
$offscreen = [
    '/(?:left|right|top|bottom)\s*:\s*-\s*\d{3,}/i'      => 'a large negative offset',
    '/text-indent\s*:\s*-\s*\d{3,}/i'                    => 'a large negative text-indent',
    '/margin-(?:left|right|inline-start|inline-end)\s*:\s*-\s*\d{3,}/i' => 'a large negative margin',
    '/(?:inset-inline|inset)\s*:\s*-\s*\d{3,}/i'         => 'a large negative inset',
];
foreach ($publicCss as $name => $body) {
    foreach ($offscreen as $re => $what) {
        preg_match($re, $body, $m);
        check("$name has no $what", !$m, $m ? trim($m[0]) : '');
    }
}

// ── the honeypot specifically ──────────────────────────────────────────────
$product = $publicCss['product.css'];
preg_match('/\.hp-field\s*\{([^}]*)\}/', $product, $hp);
$rule = $hp[1] ?? '';

check('the honeypot rule exists', $rule !== '');
$moves = preg_match('/(left|right|top|bottom)\s*:/i', $rule, $mv);
check('it does not move the element', !$moves, $moves ? trim($mv[0]) : '');
check('it is clipped instead',       str_contains($rule, 'clip-path'));
check('it stays 1px, not zero',      str_contains($rule, 'width:1px') || str_contains($rule, 'width: 1px'));

// display:none would defeat the point — most form-filling bots skip those.
check('it is not display:none',      !preg_match('/display\s*:\s*none/i', $rule));
check('it is invisible to people',   str_contains($rule, 'opacity:0') || str_contains($rule, 'opacity: 0'));
check('it cannot be clicked',        str_contains($rule, 'pointer-events:none') || str_contains($rule, 'pointer-events: none'));

$view = file_get_contents($ROOT . '/src/Views/product.php');
check('the honeypot is out of the tab order', str_contains($view, 'tabindex="-1"'));
check('the honeypot is hidden from screen readers', str_contains($view, 'aria-hidden="true"'));

// ── nothing can escape the order form to the page root ────────────────────
// A positioned ancestor is what keeps an absolutely positioned descendant from
// using the initial containing block and expanding the document.
preg_match('/\.lead-form\s*\{([^}]*)\}/', $product, $lf);
check('the order form is a containing block',
    str_contains($lf[1] ?? '', 'position: relative') || str_contains($lf[1] ?? '', 'position:relative'));

// ── the root clips horizontal overflow ────────────────────────────────────
$theme = $publicCss['theme.css'];
check('the root hides horizontal overflow', (bool)preg_match('/overflow-x\s*:\s*hidden/i', $theme));
check('and clips it where supported',       (bool)preg_match('/overflow-x\s*:\s*clip/i', $theme));
check('hidden comes first as the fallback',
    strpos($theme, 'overflow-x: hidden') < strpos($theme, 'overflow-x: clip'));

// ── the page is RTL, which is why all of the above matters ────────────────
$layout = file_get_contents($ROOT . '/src/Views/layouts/public.php');
check('the document is RTL',        str_contains($layout, 'dir="rtl"'));
check('the viewport is declared',   str_contains($layout, 'name="viewport"'));
check('the viewport allows zoom',   str_contains($layout, 'maximum-scale=5'));
check('the viewport is not fixed-width',
    !preg_match('/width\s*=\s*\d{3,}/', $layout));

// ── images cannot overflow either ─────────────────────────────────────────
// responsive_img() emits width/height attributes; without max-width they would
// force the intrinsic width and push the page wide.
check('images are capped to their container', (bool)preg_match('/img[^{]*\{[^}]*max-width\s*:\s*100%/i', $theme));
check('image height stays automatic',         (bool)preg_match('/img[^{]*\{[^}]*height\s*:\s*auto/i', $theme));

$helpers = file_get_contents($ROOT . '/config/helpers.php');
check('responsive_img sets width and height',
    str_contains($helpers, 'width="') && str_contains($helpers, 'height="')
    && str_contains($helpers, "\$dim['w']") && str_contains($helpers, "\$dim['h']"));

// ── 100vw is a trap on a page with a scrollbar ────────────────────────────
// It includes the scrollbar, so `width:100vw` overflows by its width. Only the
// `sizes` attribute may use it — that is a hint to the image picker, not layout.
foreach ($publicCss as $name => $body) {
    check("$name never sizes a box with 100vw",
        !preg_match('/(?<!max-)width\s*:\s*100vw/i', $body));
}

// ── slider slides are contained ───────────────────────────────────────────
preg_match('/\.p-slider\s*\{([^}]*)\}/', $product, $ps);
check('the slider clips its slides', str_contains($ps[1] ?? '', 'overflow: hidden')
                                     || str_contains($ps[1] ?? '', 'overflow:hidden'));
check('the slider is a containing block',
    str_contains($ps[1] ?? '', 'position: relative') || str_contains($ps[1] ?? '', 'position:relative'));

// ── every absolutely positioned rule has a positioned ancestor ────────────
// Reported rather than asserted: the list is short enough to eyeball, and a new
// entry here is the smell that produced this outage.
$abs = [];
foreach ($publicCss as $name => $body) {
    preg_match_all('/([^{}]+)\{([^}]*position\s*:\s*absolute[^}]*)\}/i', $body, $m, PREG_SET_ORDER);
    foreach ($m as $r) {
        $sel = trim(preg_replace('/\s+/', ' ', $r[1]));
        if (preg_match('/(left|right|top|bottom|inset)\s*:\s*-/i', $r[2])) {
            $abs[] = "$name: $sel";
        }
    }
}
check('no absolutely positioned rule uses a negative offset', !$abs, implode(' | ', $abs));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
