<?php
/**
 * ProductChecklist — "is this page ready to publish, and what is missing?"
 *
 * The editor has a lot of fields, and most of them are optional. Someone who
 * did not build it cannot tell which ones actually matter, so they either
 * publish a broken page or fill in everything out of caution. This turns that
 * judgement into a list.
 *
 * Two tiers, deliberately:
 *   required     — the page is broken or unsellable without it
 *   recommended  — the page works, but converts worse or is harder to find
 *
 * Every item names the tab that fixes it, so the checklist is navigation as
 * much as it is a report.
 */
class ProductChecklist {
    /**
     * @return array{
     *   required: list<array{ok:bool,label:string,hint:string,tab:string}>,
     *   recommended: list<array{ok:bool,label:string,hint:string,tab:string}>,
     *   ready: bool, done: int, total: int, percent: int
     * }
     */
    public static function build(?array $product, array $offers, array $groups,
                                 array $media, array $sections): array {
        $required = [];
        $recommended = [];

        $item = static fn(bool $ok, string $label, string $hint, string $tab): array =>
            ['ok' => $ok, 'label' => $label, 'hint' => $hint, 'tab' => $tab];

        // ── required ───────────────────────────────────────────────────────
        $required[] = $item(
            !empty($product['title']),
            'عنوان المنتج', 'الاسم الذي يظهر في المتجر وفي نتائج البحث.', 'basics'
        );

        $required[] = $item(
            !empty($product['slug']),
            'رابط الصفحة (slug)', 'العنوان في المتصفح، مثل /casual-pants.', 'basics'
        );

        $required[] = $item(
            !empty($product['cover_image']),
            'صورة الغلاف', 'أول ما يراه الزائر، وتُستعمل في المتجر ومشاركات فيسبوك.', 'basics'
        );

        // An offer with no price is the failure that looks like a working page
        // right up until someone tries to order.
        $priced = array_filter($offers, fn($o) => (float)$o['total_price'] > 0);
        $required[] = $item(
            count($priced) > 0,
            'عرض واحد بسعر', 'بدون سعر لا يمكن إتمام أي طلب.', 'offers'
        );

        $required[] = $item(
            count(array_filter($offers, fn($o) => !empty($o['is_default']))) === 1,
            'عرض افتراضي واحد', 'العرض المُحدَّد مسبقاً عند فتح الصفحة.', 'offers'
        );

        // A group with no values renders an empty dropdown the shopper cannot
        // satisfy, and the order is then rejected server-side.
        $emptyGroups = array_filter($groups, fn($g) => empty($g['values']));
        $required[] = $item(
            count($emptyGroups) === 0,
            'كل مجموعات الخيارات لها قيم',
            $emptyGroups
                ? 'مجموعة «' . reset($emptyGroups)['label'] . '» فارغة — أضف قيمها أو احذفها.'
                : 'مثل الألوان والمقاسات.',
            'offers'
        );

        // ── recommended ────────────────────────────────────────────────────
        $slider = array_filter($media, fn($m) => $m['kind'] === 'slider');
        $recommended[] = $item(
            count($slider) >= 2,
            'صورتان على الأقل في السلايدر',
            'الزوار يقلّبون الصور قبل الطلب. الموجود الآن: ' . count($slider) . '.',
            'media'
        );

        $recommended[] = $item(
            !empty($sections['hero']['headline']),
            'عنوان رئيسي مخصص',
            'بدونه تُستعمل تسمية المنتج — وهي نادراً ما تبيع بنفس القوة.',
            'content'
        );

        $recommended[] = $item(
            count($sections['features'] ?? []) >= 3,
            'ثلاث مميزات على الأقل',
            'الموجود الآن: ' . count($sections['features'] ?? []) . '.',
            'content'
        );

        $recommended[] = $item(
            count($sections['faqs'] ?? []) >= 3,
            'ثلاثة أسئلة شائعة',
            'تقلّل المكالمات وتظهر في نتائج جوجل. الموجود: ' . count($sections['faqs'] ?? []) . '.',
            'content'
        );

        $recommended[] = $item(
            count($sections['testimonials'] ?? []) >= 2,
            'رأيان من الزبناء',
            'الموجود الآن: ' . count($sections['testimonials'] ?? []) . '.',
            'content'
        );

        $recommended[] = $item(
            !empty($product['seo_title']) && !empty($product['seo_description']),
            'عنوان ووصف SEO',
            'ما يظهر في نتائج البحث. بدونه يستعمل جوجل نصاً من الصفحة.',
            'basics'
        );

        // A live page with no pixel is ad spend reporting nothing back.
        if ($product) {
            try {
                require_once __DIR__ . '/Pixel.php';
                $px = Pixel::resolve($product);
                $recommended[] = $item(
                    (bool)($px['facebook'] || $px['tiktok']),
                    'بكسل تتبع واحد على الأقل',
                    'بدونه لن تُسجَّل أي تحويلات لهذه الصفحة.',
                    'campaign'
                );
            } catch (Throwable $e) {
                // The pixels table may predate the migration; not worth failing on.
            }
        }

        $all   = array_merge($required, $recommended);
        $done  = count(array_filter($all, fn($i) => $i['ok']));
        $total = count($all);

        return [
            'required'    => $required,
            'recommended' => $recommended,
            'ready'       => count(array_filter($required, fn($i) => !$i['ok'])) === 0,
            'done'        => $done,
            'total'       => $total,
            'percent'     => $total > 0 ? (int)round($done / $total * 100) : 0,
        ];
    }

    /** Per-tab counts of unfinished items, for the badges on the tab bar. */
    public static function tabIssues(array $checklist): array {
        $out = [];
        foreach (array_merge($checklist['required'], $checklist['recommended']) as $i) {
            if (!$i['ok']) $out[$i['tab']] = ($out[$i['tab']] ?? 0) + 1;
        }
        return $out;
    }
}
