# tujjar.store — Multi-product COD ecommerce (PHP 8 + MySQL)

Mobile-first, Arabic RTL, premium editorial design.
Public site + admin panel. No build step. XAMPP-ready.

> Brand: **tujjar.store**. Repo / infrastructure identifiers stay `lp_tifaw`
> (see DOCUMENTATION.md §11).
>
> 📘 **[DOCUMENTATION.md](DOCUMENTATION.md)** — full technical reference
> 🚀 **[ENHANCEMENTS.md](ENHANCEMENTS.md)** — prioritised improvement roadmap
> 🐳 **[DEPLOYMENT.md](DEPLOYMENT.md)** — production runbook

## 1. Installation (XAMPP / Windows)

1. Place the project at: `C:\xampp\htdocs\lp_tifaw\`.
2. Start **Apache** and **MySQL** from XAMPP control panel.
3. Open phpMyAdmin → create database `lp_tifaw` (utf8mb4_unicode_ci).
4. Import `sql/schema.sql`, then import `sql/seed.sql`.
5. Edit `config/config.php` if your DB user/password differ (default: `root` / empty).
6. Make sure `uploads/` is writable (default OK on XAMPP Windows).
7. Open: `http://localhost/lp_tifaw/`
8. Admin: `http://localhost/lp_tifaw/admin/login.php`
   - Default user: `admin` / `admin123`  ← **change immediately** in Settings.

> If your install path is not `/lp_tifaw/`, update `app.base_url` in `config/config.php` and `RewriteBase` in `.htaccess` accordingly.

## 2. URL structure

- `/` homepage (all active products)
- `/category/{slug}` filter by category
- `/search?q=...` search
- `/{product-slug}` product landing page (e.g. `/casual-pants`)
- `/page/privacy` `/page/terms` `/page/refund`
- `/thank-you` after order
- `/robots.txt` `/sitemap.xml` generated from live products
- `/admin/...` admin panel

Reserved slugs (cannot be used as product slugs): `admin`, `public`, `uploads`, `config`, `src`, `sql`, `assets`.

## 3. How to add a new product

1. Admin → Products → **+ منتج جديد**.
2. Fill basic info, slug, price, cover image. Save.
3. **Sections JSON** (right panel) controls the landing-page editorial content
   (hero, features, testimonials, FAQs). A starter template is shown in the field.
4. After first save, three new editors appear:
   - **Offers** — add 1×, 2×, 3× pricing tiers.
   - **Option groups** — color/size/tier/material/etc. with values + swatches.
   - **Media** — upload slider images and gallery images.
5. Visit `http://localhost/lp_tifaw/your-slug` to see the page live.

For non-apparel products (e.g. shelves), simply create different option groups
(`tiers`, `material`, ...) instead of color/size. The product page renders
whatever option groups you define and repeats them per unit when an offer's quantity > 1.

## 4. Landing-page content

Edited with structured fields in **Products → edit → محتوى صفحة الهبوط**: hero,
features, testimonials and FAQs, with drag-to-reorder. A raw JSON view sits
behind a toggle for anything the form does not cover.

New pages can start from a preset (apparel / electronics / cosmetics / home),
which fills in the content, option groups and pricing tiers.

Per page you can also set an accent and CTA colour, a real campaign deadline,
and an A/B variant — see [DOCUMENTATION.md §10](DOCUMENTATION.md#10-landing-page-content-campaigns-and-experiments).

### The underlying JSON shape

```json
{
  "hero": {
    "headline": "...",
    "subheadline": "...",
    "badges": ["...","..."],
    "cta": "اطلب الآن"
  },
  "features":     [{"icon":"✦","title":"...","text":"..."}],
  "testimonials": [{"name":"...","text":"..."}],
  "faqs":         [{"q":"...","a":"..."}],
  "cta_text": "اطلب الآن"
}
```

> **Tradeoff (intentional)**: storing sections as JSON keeps the editor flexible
> and the public template stable. The downside is sections aren't queryable from
> SQL — but only the admin writes them, so this is acceptable.

## 5. Lead flow & security

- Form submits to `/lead/submit` (PHP backend).
- Server **recomputes** total price from `offer_id` (the client price is ignored).
- All inputs are validated and sanitized; PDO prepared statements only.
- CSRF tokens protect all admin POST forms.
- Admin passwords are hashed with `password_hash` (bcrypt).
- Image uploads are restricted to JPEG/PNG/WEBP/GIF (≤5 MB) and MIME-checked.
- UTM/`fbclid`/`ttclid`/`gclid` are auto-captured.
- Optional **SheetDB sync** runs server-side (`Lead::syncToSheetDB`). The token
  is stored in the `settings` table and **never exposed to the browser**.
- Admin sign-in is throttled: 5 failures per username+address per 15 minutes.
- Order submission is defended by a honeypot, an HMAC-signed render timestamp
  and a per-address rate limit — all invisible to real shoppers.
- Two admin roles: *admin* (everything) and *agent* (orders and reports only).
- Deleting a product **retires** it; its orders survive. Permanent deletion is a
  separate action from the trash view.
- Secrets are read from environment variables or `config/.env`, never written
  into committed PHP.

## 6. Pixels & GTM

Meta and TikTok pixels are **per landing page**, not store-wide:

1. **Admin → البكسلات** — register every pixel you own (Meta and TikTok), mark
   one default per platform.
2. **Admin → Products → edit → التتبع والبكسلات** — pick this page's Meta pixel
   and TikTok pixel from the dropdowns. Options are *افتراضي* (inherit the
   platform default), *بدون تتبع* (fire nothing here), or a specific pixel.

That is what lets you run a Meta campaign for one product on ad account A and a
TikTok campaign for another on ad account B, from the same domain.

Both platforms fire the same funnel:
`PageView → ViewContent → AddToCart → InitiateCheckout → Purchase/CompletePayment`
(TikTok uses `CompletePayment` and `SubmitForm`). The Purchase value is
recomputed server-side and fires once per order.

Verify with `?pxdebug=1` on any landing page, or with the Meta / TikTok Pixel
Helper extensions. GTM and GA4 ids remain store-wide in **Settings**; every
event is also pushed to `dataLayer`.

## 7. Editing landing-page content from admin

| What you want to change | Where |
| --- | --- |
| Hero / features / FAQ / testimonials | Product edit → "محتوى صفحة الهبوط" |
| Page colours, deadline, A/B variant | Product edit → "خيارات الحملة" |
| Slider & gallery images | Product edit → "الصور" section |
| Color swatches, sizes, tiers | Product edit → "مجموعات الخيارات" |
| Pricing tiers (1×/2×/3×) | Product edit → "العروض" |
| Meta / TikTok pixel for this page | Product edit → "التتبع والبكسلات" |
| The pixel library (add/remove pixels) | Admin → البكسلات |
| Brand color, store name, logo, favicon | Settings |
| Privacy / terms / refund pages | Settings (HTML) |
| Categories | Admin → الفئات |
| Admin accounts and roles | Admin → المستخدمون |
| Revenue by page and source | Admin → التقارير |
| Abandoned-form call-backs | Admin → لم تكتمل |
| Who changed what | Admin → السجل |

## 8. Project tree

See [DOCUMENTATION.md §2](DOCUMENTATION.md#2-architecture-at-a-glance).

## 9. Tests

No dependencies, no database, no config — they run anywhere PHP does:

14 files, 718 assertions, no framework and no dependencies:

```bash
for t in tests/*.php; do php "$t" || exit 1; done
```

## 10. Database tool

```bash
php bin/db.php status              # tables, row counts, migrations, pixels
php bin/db.php migrate             # apply pending migrations
php bin/db.php seed                # seed if the catalogue is empty
php bin/db.php fresh --force       # DESTRUCTIVE: rebuild from schema + seed
php bin/db.php backup [path]
php bin/db.php images              # build WebP sizes for existing uploads
```

## 11. Scheduled maintenance

```bash
php bin/maintenance.php daily      # backup, prune, image backfill, health
php bin/maintenance.php health     # non-zero exit when the store looks broken
```

One crontab line each — see [DEPLOYMENT.md](DEPLOYMENT.md).

CLI only — it refuses to run over HTTP, and `/bin/` is denied by `.htaccess`.

## 12. Production notes

- Set `app.env = production` in `config/config.php` to hide errors.
- Set `security.cookie_secure = true` when serving over HTTPS.
- Move the project to its own vhost so `base_url` becomes `''`, then update
  `RewriteBase /` in `.htaccess`.
