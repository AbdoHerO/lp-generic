<?php
/**
 * Sections — the editorial content of a landing page.
 *
 * Stored as JSON in products.sections_json because only the admin writes it and
 * the public template reads it whole. This class is the single place that knows
 * its shape, so the form editor, the raw-JSON editor and the page template can
 * never disagree about what a "feature" is.
 *
 * Shape:
 *   hero            {headline, subheadline, badges[], cta}
 *   features        [{icon, title, text}]
 *   testimonials    [{name, text}]
 *   faqs            [{q, a}]
 *   countdown_title string
 *   cta_text        string
 *
 * Anything else already in the column is preserved untouched — a page edited by
 * a future version of this admin must not lose keys this version cannot render.
 */
class Sections {
    /** Keys this editor owns. Everything else in the JSON passes through. */
    private const MANAGED = ['hero', 'features', 'testimonials', 'faqs', 'countdown_title', 'cta_text'];

    public static function blank(): array {
        return [
            'hero' => ['headline' => '', 'subheadline' => '', 'badges' => [], 'cta' => 'اطلب الآن'],
            'features' => [], 'testimonials' => [], 'faqs' => [],
            'countdown_title' => '', 'cta_text' => 'اطلب الآن',
        ];
    }

    /** Parse the stored column into a normalised array, never throwing. */
    public static function decode(?string $json): array {
        $data = json_decode((string)$json, true);
        if (!is_array($data)) $data = [];
        return self::normalise($data);
    }

    /** Fills in every managed key so templates never need null checks. */
    public static function normalise(array $d): array {
        $hero = is_array($d['hero'] ?? null) ? $d['hero'] : [];
        $badges = $hero['badges'] ?? [];
        if (is_string($badges)) {
            $badges = array_values(array_filter(array_map('trim', explode(',', $badges))));
        }

        $d['hero'] = [
            'headline'    => (string)($hero['headline'] ?? ''),
            'subheadline' => (string)($hero['subheadline'] ?? ''),
            'badges'      => array_values(array_filter(array_map('strval', (array)$badges), fn($b) => trim($b) !== '')),
            'cta'         => (string)($hero['cta'] ?? 'اطلب الآن'),
        ];

        $d['features']     = self::rows($d['features']     ?? [], ['icon', 'title', 'text']);
        $d['testimonials'] = self::rows($d['testimonials'] ?? [], ['name', 'text']);
        $d['faqs']         = self::rows($d['faqs']         ?? [], ['q', 'a']);

        $d['countdown_title'] = (string)($d['countdown_title'] ?? '');
        $d['cta_text']        = (string)($d['cta_text'] ?? '');

        return $d;
    }

    /** @param list<string> $fields */
    private static function rows($raw, array $fields): array {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $clean = [];
            foreach ($fields as $f) $clean[$f] = trim((string)($row[$f] ?? ''));
            // A row with nothing in it is noise in the JSON and an empty card
            // on the page.
            if (implode('', $clean) !== '') $out[] = $clean;
        }
        return $out;
    }

    /**
     * Build sections from the structured editor's POST payload.
     *
     * Repeatable rows arrive as parallel arrays (`sec[features][title][]`,
     * `sec[features][text][]`, …) rather than indexed groups, so adding and
     * reordering rows in the browser needs no index bookkeeping — the DOM order
     * is the submitted order.
     *
     * @param array $existing decoded current value, so unmanaged keys survive
     */
    public static function fromPost(array $post, array $existing = []): array {
        $sec = is_array($post['sec'] ?? null) ? $post['sec'] : [];

        // Start from what is already stored so a key this form does not render
        // is carried forward rather than dropped.
        $out = $existing;
        foreach (self::MANAGED as $k) unset($out[$k]);

        $hero = is_array($sec['hero'] ?? null) ? $sec['hero'] : [];
        $out['hero'] = [
            'headline'    => clean_string($hero['headline'] ?? '', 200),
            'subheadline' => clean_string($hero['subheadline'] ?? '', 300),
            'badges'      => array_values(array_filter(
                                array_map(fn($b) => clean_string($b, 60),
                                    explode(',', (string)($hero['badges'] ?? ''))),
                                fn($b) => $b !== '')),
            'cta'         => clean_string($hero['cta'] ?? '', 80),
        ];

        $out['features']     = self::zip($sec['features']     ?? [], ['icon' => 20, 'title' => 120, 'text' => 400]);
        $out['testimonials'] = self::zip($sec['testimonials'] ?? [], ['name' => 80, 'text' => 500]);
        $out['faqs']         = self::zip($sec['faqs']         ?? [], ['q' => 300, 'a' => 1000]);

        $out['countdown_title'] = clean_string($sec['countdown_title'] ?? '', 200);
        $out['cta_text']        = clean_string($sec['cta_text'] ?? '', 120);

        return self::normalise($out);
    }

    /**
     * Turn parallel field arrays into a list of rows.
     *
     * @param array<string,int> $fields field name => max length
     */
    private static function zip($raw, array $fields): array {
        if (!is_array($raw)) return [];

        $count = 0;
        foreach (array_keys($fields) as $f) {
            if (is_array($raw[$f] ?? null)) $count = max($count, count($raw[$f]));
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $row = [];
            foreach ($fields as $f => $max) {
                $row[$f] = clean_string($raw[$f][$i] ?? '', $max);
            }
            if (implode('', $row) !== '') $out[] = $row;
        }
        return $out;
    }

    public static function encode(array $sections): string {
        return (string)json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Validate a hand-edited JSON string.
     *
     * @return array{ok: bool, error: ?string, sections: array}
     */
    public static function validateJson(?string $json): array {
        $json = trim((string)$json);
        if ($json === '') return ['ok' => true, 'error' => null, 'sections' => self::blank()];

        $data = json_decode($json, true);
        if ($data === null) {
            return ['ok' => false, 'error' => json_last_error_msg(), 'sections' => []];
        }
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'JSON must describe an object', 'sections' => []];
        }
        return ['ok' => true, 'error' => null, 'sections' => self::normalise($data)];
    }

    /** A one-line summary for the product list: "3 مميزات · 2 آراء · 4 أسئلة". */
    public static function summary(array $s): string {
        $bits = [];
        if (!empty($s['hero']['headline'])) $bits[] = 'عنوان';
        if ($n = count($s['features'] ?? []))     $bits[] = "$n مميزات";
        if ($n = count($s['testimonials'] ?? [])) $bits[] = "$n آراء";
        if ($n = count($s['faqs'] ?? []))         $bits[] = "$n أسئلة";
        return $bits ? implode(' · ', $bits) : 'فارغ';
    }
}
