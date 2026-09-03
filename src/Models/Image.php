<?php
/**
 * Image — resizing and WebP derivatives for uploaded product photos.
 *
 * The problem this solves: a cover image was served at whatever size the phone
 * that took it produced. A 4 MB, 4000px JPEG reaching a shopper on 3G is a
 * multi-second delay before the page shows anything, and on a COD landing page
 * load time *is* conversion rate.
 *
 * On upload, each image is written at three widths in WebP (plus the original,
 * for browsers and crawlers that want it). The page then emits srcset and lets
 * the browser pick.
 *
 * Everything here degrades: if the GD extension is missing, or a particular
 * image cannot be decoded, the original is used unchanged and nothing breaks.
 * External CDN URLs are passed through untouched — they are already someone
 * else's pipeline.
 */
class Image {
    /** Widths worth generating for a mobile-first, single-column layout. */
    public const WIDTHS = [480, 800, 1400];

    /** WebP quality. 82 is the point where artefacts stop being visible on photos. */
    private const QUALITY = 82;

    /** Never upscale, and never try to decode something absurd. */
    private const MAX_SOURCE_PIXELS = 50_000_000;

    public static function available(): bool {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /**
     * Generate derivatives for an uploaded file.
     *
     * @param string $relPath e.g. "uploads/p_abc123.jpg" (relative to the app root)
     * @return list<int> the widths actually written
     */
    public static function generate(string $relPath): array {
        if (!self::available()) return [];
        if (preg_match('#^https?://#i', $relPath)) return [];   // external CDN

        $abs = self::root() . '/' . ltrim($relPath, '/');
        if (!is_file($abs)) return [];

        $info = @getimagesize($abs);
        if (!$info) return [];
        [$srcW, $srcH] = $info;
        if ($srcW * $srcH > self::MAX_SOURCE_PIXELS) return [];

        $src = self::load($abs, $info['mime'] ?? '');
        if (!$src) return [];

        $written = [];
        foreach (self::WIDTHS as $w) {
            // Never upscale: a 600px original re-encoded at 1400px is a bigger
            // file with no more detail in it.
            if ($w > $srcW && $written) break;
            $targetW = min($w, $srcW);
            $targetH = (int)round($srcH * ($targetW / $srcW));

            $dst = imagecreatetruecolor($targetW, $targetH);
            // Preserve transparency for PNG sources.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

            $out = self::variantPath($abs, $w);
            if (@imagewebp($dst, $out, self::QUALITY)) {
                @chmod($out, 0644);
                $written[] = $w;
            }
            imagedestroy($dst);

            if ($targetW === $srcW) break;   // the original was smaller than the next step
        }
        imagedestroy($src);

        return $written;
    }

    /** Remove every derivative of an image (used when the original is replaced). */
    public static function purge(string $relPath): void {
        if (preg_match('#^https?://#i', $relPath)) return;
        $abs = self::root() . '/' . ltrim($relPath, '/');
        foreach (self::WIDTHS as $w) {
            $v = self::variantPath($abs, $w);
            if (is_file($v)) @unlink($v);
        }
    }

    /**
     * The srcset for an image, or null when there is nothing to offer.
     *
     * Returns null rather than an empty string so the caller can omit the
     * attribute entirely — an empty srcset is invalid and some browsers treat
     * it as "no image".
     */
    public static function srcset(string $relPath): ?string {
        if (preg_match('#^https?://#i', $relPath)) return null;

        $abs   = self::root() . '/' . ltrim($relPath, '/');
        $parts = [];
        foreach (self::WIDTHS as $w) {
            $variant = self::variantPath($abs, $w);
            if (is_file($variant)) {
                $parts[] = upload_url(self::variantRel($relPath, $w)) . ' ' . $w . 'w';
            }
        }
        return $parts ? implode(', ', $parts) : null;
    }

    /** Intrinsic size of the original, for width/height attributes. */
    public static function dimensions(string $relPath): ?array {
        if (preg_match('#^https?://#i', $relPath)) return null;
        $abs = self::root() . '/' . ltrim($relPath, '/');
        if (!is_file($abs)) return null;
        $info = @getimagesize($abs);
        return $info ? ['w' => (int)$info[0], 'h' => (int)$info[1]] : null;
    }

    // ── paths ──────────────────────────────────────────────────────────────

    /** uploads/p_abc.jpg → uploads/p_abc.480w.webp */
    private static function variantRel(string $relPath, int $w): string {
        return preg_replace('/\.[^.\/]+$/', '', $relPath) . '.' . $w . 'w.webp';
    }

    private static function variantPath(string $absPath, int $w): string {
        return preg_replace('/\.[^.\/]+$/', '', $absPath) . '.' . $w . 'w.webp';
    }

    private static function root(): string {
        return dirname(__DIR__, 2);
    }

    private static function load(string $abs, string $mime) {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($abs),
            'image/png'  => @imagecreatefrompng($abs),
            'image/webp' => @imagecreatefromwebp($abs),
            'image/gif'  => @imagecreatefromgif($abs),
            default      => false,
        } ?: null;
    }
}
