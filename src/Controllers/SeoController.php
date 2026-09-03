<?php
require_once __DIR__ . '/../Models/Product.php';

/**
 * robots.txt and sitemap.xml.
 *
 * Both are generated rather than static files: the sitemap has to list whatever
 * products are active right now, and a stale hand-written file is worse than
 * none — it tells search engines to keep crawling pages that were retired.
 *
 * Admin, uploads and the order endpoints are disallowed. There is nothing to
 * index there, and a crawler following /lead/submit is pure noise in the logs.
 *
 * Building and emitting are separate so the output can be asserted on without a
 * web server and without the route's exit() ending the caller.
 */
class SeoController {
    public function robots(): void {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo $this->robotsBody();
        exit;
    }

    public function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $this->sitemapBody();
        exit;
    }

    public function robotsBody(): string {
        $base = $this->origin();
        return "User-agent: *\n"
             . "Disallow: /admin/\n"
             . "Disallow: /thank-you\n"
             . "Disallow: /lead/\n"
             . "Disallow: /search\n"
             . "Allow: /\n\n"
             . "Sitemap: {$base}" . base_url('sitemap.xml') . "\n";
    }

    public function sitemapBody(): string {
        $base = $this->origin();
        $url  = fn(string $path) => $base . base_url($path);

        $products = db()->query(
            "SELECT slug, updated_at FROM products WHERE status = 1 ORDER BY updated_at DESC"
        )->fetchAll();
        $cats = db()->query("SELECT slug FROM categories ORDER BY position")->fetchAll();

        $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $out .= $this->entry($url('/'), null, 'daily', '1.0');

        // Landing pages are the point of the site, so they carry the highest
        // priority after the home page and are ordered by recency.
        foreach ($products as $p) {
            $out .= $this->entry($url($p['slug']), $this->date($p['updated_at']), 'weekly', '0.9');
        }
        foreach ($cats as $c) {
            $out .= $this->entry($url('category/' . $c['slug']), null, 'weekly', '0.5');
        }
        foreach (['page/privacy', 'page/terms', 'page/refund'] as $page) {
            $out .= $this->entry($url($page), null, 'yearly', '0.2');
        }

        return $out . "</urlset>\n";
    }

    private function entry(string $loc, ?string $lastmod, string $freq, string $priority): string {
        $out  = "  <url>\n";
        $out .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        if ($lastmod) $out .= "    <lastmod>{$lastmod}</lastmod>\n";
        $out .= "    <changefreq>{$freq}</changefreq>\n";
        $out .= "    <priority>{$priority}</priority>\n";
        return $out . "  </url>\n";
    }

    private function date(?string $ts): ?string {
        if (!$ts) return null;
        $t = strtotime($ts);
        return $t ? date('Y-m-d', $t) : null;
    }

    /** Scheme + host, with no trailing slash. */
    private function origin(): string {
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = request_is_https() ? 'https' : 'http';
        return $scheme . '://' . $host;
    }
}
