<?php
/**
 * The image pipeline, exercised on real files.
 *
 * Real JPEG/PNG images are written to a temporary directory, resized, and the
 * results inspected — a mocked pipeline would not catch the things that
 * actually go wrong here: upscaling a small original, losing PNG transparency,
 * or emitting a srcset pointing at files that were never written.
 *
 * Run:  php tests/image_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function upload_url($p) { return preg_match('#^https?://#i', $p) ? $p : '/lp_tifaw/' . ltrim($p, '/'); }

require_once $ROOT . '/src/Models/Image.php';

if (!Image::available()) {
    echo "SKIP: ext-gd with WebP support is not available in this PHP build.\n";
    exit(0);
}

// Work inside the real uploads directory (Image resolves paths from the app
// root), then clean up. Names are random so a parallel run cannot collide.
$stamp = bin2hex(random_bytes(6));
$made  = [];
function make_source(string $name, int $w, int $h, string $type): string {
    global $ROOT, $made;
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 140, 60));
    imagefilledellipse($im, (int)($w / 2), (int)($h / 2), (int)($w / 3), (int)($h / 3),
                       imagecolorallocate($im, 30, 30, 30));
    $rel = 'uploads/' . $name;
    $abs = $ROOT . '/' . $rel;
    if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0775, true);
    $type === 'png' ? imagepng($im, $abs) : imagejpeg($im, $abs, 90);
    imagedestroy($im);
    $made[] = $abs;
    return $rel;
}

// ── a large photo gets every width ─────────────────────────────────────────
$big = make_source("t_{$stamp}_big.jpg", 2000, 1500, 'jpg');
$w   = Image::generate($big);

check('a large image produces every width', $w === [480, 800, 1400], implode(',', $w));
foreach (Image::WIDTHS as $width) {
    $variant = $ROOT . '/' . preg_replace('/\.[^.\/]+$/', '', $big) . ".{$width}w.webp";
    $made[] = $variant;
    check("the {$width}px variant exists", is_file($variant));
    if (is_file($variant)) {
        $info = getimagesize($variant);
        check("the {$width}px variant is that wide", $info[0] === $width, (string)$info[0]);
        check("the {$width}px variant is WebP",      $info['mime'] === 'image/webp', $info['mime']);
        check("the {$width}px variant keeps the aspect ratio",
            abs(($info[0] / $info[1]) - (2000 / 1500)) < 0.02);
    }
}

// The point of the exercise: the derivative must be smaller than the original.
$origSize = filesize($ROOT . '/' . $big);
$smallest = filesize($ROOT . '/' . preg_replace('/\.[^.\/]+$/', '', $big) . '.480w.webp');
check('the mobile variant is much smaller than the original',
    $smallest < $origSize / 2, round($origSize / 1024) . 'KB → ' . round($smallest / 1024) . 'KB');

// ── srcset ─────────────────────────────────────────────────────────────────
$srcset = Image::srcset($big);
check('srcset is produced',            $srcset !== null);
check('srcset lists every width',
    $srcset && substr_count($srcset, 'w,') + 1 === 3, (string)$srcset);
check('srcset entries carry a width descriptor', $srcset && str_contains($srcset, '480w'));
check('srcset points at real files',
    $srcset && !array_filter(explode(', ', $srcset), function ($e) use ($ROOT) {
        $rel = ltrim(str_replace('/lp_tifaw/', '', explode(' ', $e)[0]), '/');
        return !is_file($ROOT . '/' . $rel);
    }));

$dim = Image::dimensions($big);
check('dimensions are reported', $dim === ['w' => 2000, 'h' => 1500], json_encode($dim));

// ── a small original is never upscaled ─────────────────────────────────────
$small = make_source("t_{$stamp}_small.jpg", 300, 200, 'jpg');
$sw = Image::generate($small);
check('a small image produces one variant only', count($sw) === 1, implode(',', $sw));
$smallVariant = $ROOT . '/' . preg_replace('/\.[^.\/]+$/', '', $small) . '.480w.webp';
$made[] = $smallVariant;
check('the variant is not upscaled', is_file($smallVariant) && getimagesize($smallVariant)[0] === 300,
    is_file($smallVariant) ? (string)getimagesize($smallVariant)[0] : 'missing');

// ── PNG transparency survives ──────────────────────────────────────────────
$png = 'uploads/' . "t_{$stamp}_alpha.png";
$pngAbs = $ROOT . '/' . $png;
$im = imagecreatetruecolor(900, 600);
imagealphablending($im, false);
imagesavealpha($im, true);
imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagepng($im, $pngAbs);
imagedestroy($im);
$made[] = $pngAbs;

Image::generate($png);
$pngVariant = $ROOT . '/' . preg_replace('/\.[^.\/]+$/', '', $png) . '.480w.webp';
$made[] = $pngVariant;
check('a PNG produces a variant', is_file($pngVariant));
if (is_file($pngVariant)) {
    $v = imagecreatefromwebp($pngVariant);
    $alpha = (imagecolorat($v, 5, 5) >> 24) & 0x7F;
    imagedestroy($v);
    check('PNG transparency is preserved', $alpha > 100, 'alpha=' . $alpha);
}

// ── external URLs are left alone ───────────────────────────────────────────
check('an external URL generates nothing', Image::generate('https://cdn.example.com/a.jpg') === []);
check('an external URL has no srcset',     Image::srcset('https://cdn.example.com/a.jpg') === null);
check('an external URL has no dimensions', Image::dimensions('https://cdn.example.com/a.jpg') === null);

// ── missing and unreadable files degrade quietly ───────────────────────────
check('a missing file generates nothing', Image::generate('uploads/does-not-exist.jpg') === []);
check('a missing file has no srcset',     Image::srcset('uploads/does-not-exist.jpg') === null);

$notAnImage = $ROOT . '/uploads/' . "t_{$stamp}_bogus.jpg";
file_put_contents($notAnImage, 'this is not an image');
$made[] = $notAnImage;
check('a corrupt file generates nothing', Image::generate('uploads/' . "t_{$stamp}_bogus.jpg") === []);

// ── purge removes derivatives ──────────────────────────────────────────────
Image::purge($big);
$stillThere = array_filter(Image::WIDTHS, fn($x) =>
    is_file($ROOT . '/' . preg_replace('/\.[^.\/]+$/', '', $big) . ".{$x}w.webp"));
check('purge removes every derivative', !$stillThere, implode(',', $stillThere));
check('purge leaves the original',      is_file($ROOT . '/' . $big));

// ── the helper's markup ────────────────────────────────────────────────────
// responsive_img lives in helpers.php, which needs config and a session to
// load, so its source is asserted on instead — what matters is which attributes
// it emits.
$helpers = file_get_contents($ROOT . '/config/helpers.php');
check('helper emits srcset',            str_contains($helpers, 'srcset="'));
check('helper emits sizes',             str_contains($helpers, 'sizes="'));
check('helper emits width and height',  str_contains($helpers, 'width="') && str_contains($helpers, 'height="'));
check('helper marks the hero as high priority', str_contains($helpers, 'fetchpriority="high"'));
check('helper lazy-loads by default',   str_contains($helpers, "\$attrs['loading'] ?? 'lazy'"));
check('helper decodes async when lazy', str_contains($helpers, 'decoding="async"'));
check('helper falls back to the placeholder', str_contains($helpers, 'placeholder.svg'));

$productView = file_get_contents($ROOT . '/src/Views/product.php');
check('the first slide loads eagerly',
    (bool)preg_match("/'loading' => \\\$i === 0 \\? 'eager' : 'lazy'/", $productView));
check('the gallery uses the helper', str_contains($productView, "responsive_img(\$g['url']"));
check('no bare <img> is left on the product page',
    !preg_match('/<img\s+(?!class="thumb")[^>]*src="<\?=\s*e\(upload_url/', $productView));

// ── uploads trigger generation ─────────────────────────────────────────────
$bootstrap = file_get_contents($ROOT . '/admin/_bootstrap.php');
check('single uploads generate variants',   substr_count($bootstrap, "Image::generate('uploads/' . \$name)") === 2);

// cleanup
foreach (array_unique($made) as $f) if (is_file($f)) @unlink($f);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
