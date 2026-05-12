# lp_tifaw — Multi-product COD ecommerce (PHP 8 + MySQL)

Mobile-first, Arabic RTL, premium editorial design.
Public site + admin panel. No build step. XAMPP-ready.

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

## 4. Sections JSON — the only structured field you edit by hand

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

> **Tradeoff (intentional)**: storing sections as JSON keeps the MVP small and
> the editor flexible. The downside is sections aren't queryable from SQL —
> but only the admin writes them, so this is acceptable. If you later need
> richer editing, swap to a `product_sections` table without changing the
> public template.

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

## 6. Pixels & GTM

Add IDs in **Admin → Settings**. They are injected into `layouts/public.php`.
`thank-you.php` fires `Purchase` (Facebook) and pushes `purchase` (GTM).

## 7. Editing landing-page content from admin

| What you want to change | Where |
| --- | --- |
| Hero / features / FAQ / testimonials | Product edit → "Sections JSON" |
| Slider & gallery images | Product edit → "الصور" section |
| Color swatches, sizes, tiers | Product edit → "مجموعات الخيارات" |
| Pricing tiers (1×/2×/3×) | Product edit → "العروض" |
| Brand color & store name | Settings |
| Privacy / terms / refund pages | Settings (HTML) |

## 8. Project tree

See the architecture section in the answer above.

## 9. Production notes

- Set `app.env = production` in `config/config.php` to hide errors.
- Set `security.cookie_secure = true` when serving over HTTPS.
- Move the project to its own vhost so `base_url` becomes `''`, then update
  `RewriteBase /` in `.htaccess`.
