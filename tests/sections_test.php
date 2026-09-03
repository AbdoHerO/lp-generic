<?php
/**
 * Sections — the landing-page content model.
 *
 * The thing being protected here is an operator's copy: a save must never drop
 * a field, reorder rows, or lose a key the current form does not render.
 *
 * Run:  php tests/sections_test.php
 */

$ROOT = dirname(__DIR__);

// Sections uses clean_string() from helpers; provide it without a database.
function clean_string($v, int $max = 500): string {
    $v = is_string($v) ? trim($v) : '';
    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    return mb_substr($v, 0, $max, 'UTF-8');
}
require_once $ROOT . '/src/Models/Sections.php';

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

// ── decode ─────────────────────────────────────────────────────────────────
$real = '{"hero":{"headline":"سروال كاجوال كلاس","subheadline":"إطلالة راقية","badges":["مريح بزاف","جودة عالية"],"cta":"اطلب الآن"},'
      . '"features":[{"icon":"🚚","title":"الشحن مجاني","text":"توصيل سريع"}],'
      . '"testimonials":[{"name":"مريم","text":"زوين بزاف"}],'
      . '"faqs":[{"q":"سؤال؟","a":"جواب."}],'
      . '"countdown_title":"تخفيض 50%","cta_text":"اطلب الآن واستفد"}';

$d = Sections::decode($real);
check('decode keeps the headline',  $d['hero']['headline'] === 'سروال كاجوال كلاس');
check('decode keeps badges as a list', $d['hero']['badges'] === ['مريح بزاف', 'جودة عالية']);
check('decode keeps the emoji icon', $d['features'][0]['icon'] === '🚚');
check('decode keeps testimonials',   $d['testimonials'][0]['name'] === 'مريم');
check('decode keeps faqs',           $d['faqs'][0]['a'] === 'جواب.');
check('decode keeps countdown',      $d['countdown_title'] === 'تخفيض 50%');

check('decode of null gives a usable shape',
    Sections::decode(null)['hero']['headline'] === '' && Sections::decode(null)['features'] === []);
check('decode of broken JSON does not throw',
    Sections::decode('{not json')['features'] === []);
check('decode of a JSON array does not throw',
    is_array(Sections::decode('[1,2,3]')['faqs']));

// Badges written as a comma string by an older editor still load.
check('decode accepts legacy comma-string badges',
    Sections::decode('{"hero":{"badges":"a, b ,c"}}')['hero']['badges'] === ['a', 'b', 'c']);

// ── round-trip: decode → encode → decode is stable ─────────────────────────
$once  = Sections::decode($real);
$twice = Sections::decode(Sections::encode($once));
check('encode/decode round-trips exactly', $once == $twice);
check('encode does not escape Arabic', !str_contains(Sections::encode($once), '\u06'));
check('encode does not escape slashes', !str_contains(Sections::encode(
    Sections::decode('{"cta_text":"a/b"}')), '\/'));

// ── fromPost: the structured editor ────────────────────────────────────────
$post = [
    'sec' => [
        'hero' => [
            'headline' => '  عنوان  ', 'subheadline' => 'وصف',
            'badges' => 'شحن مجاني, ضمان ,, الدفع عند الاستلام', 'cta' => 'اطلب',
        ],
        'features' => [
            'icon'  => ['🚚', '💵', ''],
            'title' => ['شحن', 'دفع', ''],
            'text'  => ['سريع', 'عند الاستلام', ''],
        ],
        'testimonials' => ['name' => ['مريم', ''], 'text' => ['رائع', '']],
        'faqs'         => ['q' => ['سؤال؟'], 'a' => ['جواب.']],
        'countdown_title' => 'عرض ينتهي قريباً',
        'cta_text'        => 'اطلب الآن',
    ],
];
$s = Sections::fromPost($post);

check('fromPost trims the headline',      $s['hero']['headline'] === 'عنوان');
check('fromPost splits badges',           $s['hero']['badges'] === ['شحن مجاني', 'ضمان', 'الدفع عند الاستلام']);
check('fromPost drops empty badge slots', !in_array('', $s['hero']['badges'], true));
check('fromPost zips parallel arrays',    count($s['features']) === 2, count($s['features']) . ' rows');
check('fromPost keeps field pairing',
    $s['features'][1] === ['icon' => '💵', 'title' => 'دفع', 'text' => 'عند الاستلام']);
check('fromPost drops the empty row',     count($s['testimonials']) === 1);
check('fromPost keeps faqs',              $s['faqs'][0]['q'] === 'سؤال؟');
check('fromPost keeps countdown',         $s['countdown_title'] === 'عرض ينتهي قريباً');

// Row order is the submitted order — this is what makes drag-to-reorder work.
$reordered = $post;
$reordered['sec']['features'] = ['icon' => ['💵', '🚚'], 'title' => ['دفع', 'شحن'], 'text' => ['b', 'a']];
$r = Sections::fromPost($reordered);
check('fromPost preserves row order', $r['features'][0]['title'] === 'دفع');

// A row whose only content is in the last column must survive.
$sparse = ['sec' => ['faqs' => ['q' => ['', 'س2'], 'a' => ['ج1', '']]]];
$sp = Sections::fromPost($sparse);
check('fromPost keeps a partially filled row', count($sp['faqs']) === 2, count($sp['faqs']) . ' rows');
check('fromPost keeps the answer-only row',    $sp['faqs'][0]['a'] === 'ج1');

// Empty POST must not explode.
check('fromPost with no sec key works', Sections::fromPost([])['features'] === []);
check('fromPost with a scalar sec works', Sections::fromPost(['sec' => 'x'])['faqs'] === []);

// ── unmanaged keys survive an edit ─────────────────────────────────────────
// The failure this prevents: a future admin adds "video_url", an older admin
// saves the page, and the video silently disappears.
$existing = Sections::decode('{"hero":{"headline":"old"},"video_url":"https://x/y.mp4","custom":{"a":1}}');
$merged   = Sections::fromPost($post, $existing);
check('an unmanaged scalar key survives a save', ($merged['video_url'] ?? null) === 'https://x/y.mp4');
check('an unmanaged object key survives a save', ($merged['custom']['a'] ?? null) === 1);
check('managed keys are replaced, not merged',   $merged['hero']['headline'] === 'عنوان');

// ── validateJson ───────────────────────────────────────────────────────────
$v = Sections::validateJson('{"hero":{"headline":"x"}}');
check('validateJson accepts good JSON', $v['ok'] === true && $v['sections']['hero']['headline'] === 'x');

$v = Sections::validateJson('{"hero": }');
check('validateJson rejects broken JSON', $v['ok'] === false);
check('validateJson explains why',        is_string($v['error']) && $v['error'] !== '');

check('validateJson treats empty as blank', Sections::validateJson('')['ok'] === true);
check('validateJson rejects a bare array',  Sections::validateJson('"a string"')['ok'] === false);

// ── length limits are enforced server-side ─────────────────────────────────
$long = ['sec' => ['hero' => ['headline' => str_repeat('ا', 500)]]];
check('fromPost caps the headline length',
    mb_strlen(Sections::fromPost($long)['hero']['headline']) === 200,
    mb_strlen(Sections::fromPost($long)['hero']['headline']) . ' chars');

// ── summary ────────────────────────────────────────────────────────────────
check('summary counts the parts',
    Sections::summary($d) === 'عنوان · 1 مميزات · 1 آراء · 1 أسئلة', Sections::summary($d));
check('summary of an empty page says so', Sections::summary(Sections::blank()) === 'فارغ');

// ── the editor and the public template agree on the shape ──────────────────
$editor   = file_get_contents($ROOT . '/admin/views/partials/section-editor.php');
$template = file_get_contents($ROOT . '/src/Views/product.php');

foreach (['sec[hero][headline]', 'sec[hero][subheadline]', 'sec[hero][badges]', 'sec[hero][cta]',
          'sec[features][icon][]', 'sec[features][title][]', 'sec[features][text][]',
          'sec[testimonials][name][]', 'sec[testimonials][text][]',
          'sec[faqs][q][]', 'sec[faqs][a][]',
          'sec[countdown_title]', 'sec[cta_text]'] as $field) {
    check("editor posts $field", str_contains($editor, 'name="' . $field . '"'));
}
foreach (["\$sections['hero']", "\$sections['features']", "\$sections['testimonials']",
          "\$sections['faqs']", "\$sections['countdown_title']", "\$sections['cta_text']"] as $read) {
    check('product page reads ' . trim($read, '$'), str_contains($template, $read));
}
check('editor offers both modes', str_contains($editor, 'name="sections_mode"'));
check('editor keeps the raw JSON field', str_contains($editor, 'name="sections_json"'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
