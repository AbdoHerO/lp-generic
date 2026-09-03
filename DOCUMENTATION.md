# tujjar.store — Full Technical Documentation

> Multi-product, cash-on-delivery (COD) landing-page engine for the Moroccan
> market. PHP 8 + MySQL, no build step, Arabic RTL front end, admin panel that
> generates every landing page from the database.
>
> Repository/infrastructure name remains `lp_tifaw`; the **brand** is
> `tujjar.store`. See [Branding](#13-branding) for why those are separate.

---

## Table of contents

1. [What this application is](#1-what-this-application-is)
2. [Architecture at a glance](#2-architecture-at-a-glance)
3. [Request lifecycle](#3-request-lifecycle)
4. [Database schema](#4-database-schema)
5. [Public site — routes and views](#5-public-site-routes-and-views)
6. [The landing page, section by section](#6-the-landing-page-section-by-section)
7. [Order (lead) flow](#7-order-lead-flow)
8. [Admin panel](#8-admin-panel)
9. [Tracking: pixels and events](#9-tracking-pixels-and-events)
10. [Landing-page content, campaigns and experiments](#10-landing-page-content-campaigns-and-experiments)
11. [Performance, SEO and the order flow](#11-performance-seo-and-the-order-flow)
12. [Settings reference](#12-settings-reference)
13. [Branding](#13-branding)
14. [Security model](#14-security-model)
15. [Configuration and environments](#15-configuration-and-environments)
16. [Migrations](#16-migrations)
17. [Local development](#17-local-development)
18. [Deployment](#18-deployment)
19. [Testing](#19-testing)
20. [Operational runbook](#20-operational-runbook)
21. [Known limitations](#21-known-limitations)

---

## 1. What this application is

A single PHP application that serves two audiences:

| Audience | Surface | Purpose |
| --- | --- | --- |
| Shoppers | `/`, `/{product-slug}`, `/thank-you` | Browse and place COD orders |
| You | `/admin/*` | Create products, build landing pages, manage pixels, work the order queue |

The business model it encodes is **paid-traffic COD selling**: you run a Meta or
TikTok ad, it points at one product landing page, the visitor picks an offer
(1×/2×/3× bundles), fills in name + phone + address, and you call them to confirm.
No cart, no checkout, no online payment. Everything the ad and the page need —
copy, images, prices, tracking pixels — is editable from the admin panel without
touching code.

**Stack**
- PHP 8.2, no framework, no Composer dependencies
- MySQL 8 / MariaDB 11 (utf8mb4)
- Vanilla JS and CSS, no build step
- Apache with `mod_rewrite` (XAMPP locally, `php:8.2-apache` in Docker)

---

## 2. Architecture at a glance

```
                       ┌──────────────────────────────────────────────┐
   Browser ──────────► │ index.php  (single front controller)         │
                       │   └─ src/Router.php   pattern → controller   │
                       └──────────────────────────────────────────────┘
                                        │
              ┌─────────────────────────┼─────────────────────────┐
              ▼                         ▼                         ▼
      HomeController           ProductController           LeadController
      (catalogue, search)      (landing page)              (submit, thank-you)
              │                         │                         │
              └─────────────┬───────────┴─────────────┬───────────┘
                            ▼                         ▼
                     src/Models/*            config/helpers.php
                  Product, Lead, Pixel,      render(), e(), csrf,
                  Settings                   pixel_context(), …
                            │
                            ▼
                     config/database.php  ── db() ── PDO ── MySQL
                            │                          │
                            │                          └─ config/migrations.php
                            ▼
                   src/Views/**  (PHP templates)
                     layouts/public.php
                       ├─ partials/pixels-head.php   ← per-page Meta/TikTok
                       ├─ partials/header.php
                       ├─ {view}.php                  ← page body
                       └─ partials/footer.php


   /admin/*.php  ──►  admin/_bootstrap.php  ──►  admin_render()  ──►  admin/views/*
   (each admin page is its own front-controller-free entry point)
```

Two deliberate architectural choices worth knowing:

- **The admin panel does not use the router.** Each admin page is a standalone
  `.php` file that requires `admin/_bootstrap.php`. Simple, and it means the
  `.htaccess` "real file wins" rule serves admin pages directly.
- **Landing-page content lives in one JSON column** (`products.sections_json`)
  rather than a `product_sections` table. It keeps the editor flexible and the
  MVP small; the cost is that sections aren't queryable from SQL. Only the admin
  writes them, so that trade is acceptable — see [Known limitations](#21-known-limitations).

### File map

```
index.php                     Front controller: route table + dispatch
.htaccess                     Rewrites, blocks /config /src /sql, security headers

config/
  config.php                  Environment loader (local → prod → hard fail)
  config.local.php            Local credentials      (gitignored)
  config.prod.php             Production credentials (gitignored, container-generated)
  config.example.php          Template (reads env(), holds no secrets)
  .env.example                Secrets template for non-Docker hosts
  env.php                     env() / env_required(): real env vars, then config/.env
  autoload.php                Dependency-free autoloader over src/
  tokens.php                  Signed form timestamps (anti-bot)
  database.php                db(): PDO singleton + first-boot schema import
  migrations.php              Incremental, versioned schema migrations
  helpers.php                 Bootstrap, session, render(), escaping, CSRF,
                              settings_get(), pixel context, responsive_img(),
                              app_secret(), morocco_cities(), logo helpers

src/
  Router.php                  {slug} pattern matcher, base_url stripping
  Controllers/
    HomeController.php        /, /search, /category/{slug}
    ProductController.php     /{slug}  — the landing page
    LeadController.php        POST /lead/submit, GET /thank-you
    PageController.php        /page/privacy|terms|refund
    SeoController.php         /robots.txt, /sitemap.xml (generated)
  Models/
    Product.php               Products, media, offers, option groups, soft delete
    Lead.php                  Orders, status log, stats, duplicates, SheetDB sync
    Pixel.php                 Pixel library + per-page resolution
    PixelServer.php           Meta CAPI + TikTok Events API (server-side conversions)
    Sections.php              Landing-page content: decode, form round-trip, validate
    Experiment.php            A/B variants, sticky assignment, revenue-based results
    PageTemplate.php          Presets that scaffold a new landing page
    Image.php                 WebP derivatives, srcset, intrinsic dimensions
    Report.php                Revenue and confirmation-rate aggregation
    Draft.php                 Abandoned-form capture (never an order)
    Admin.php                 Accounts and the two roles
    Activity.php              Who changed what
    Throttle.php              Shared rate limiter (login + orders)
    Log.php                   JSON logging with redaction
    SecurityAudit.php         The dashboard's warning panel
    Settings.php              Key/value settings
  Views/
    layouts/public.php        <head>, canonical, GTM/GA, pixels, per-page theme
    partials/pixels-head.php  Meta + TikTok base code and the LPX bridge
    partials/structured-data.php  Product / FAQPage / BreadcrumbList JSON-LD
    partials/header.php       Logo, search, call CTA, trust strip
    partials/footer.php       Logo, policy links, phone/WhatsApp/Facebook
    home.php                  Catalogue grid
    product.php               The landing page template
    thank-you.php             Confirmation + Purchase / CompletePayment
    policy.php  404.php  product-error.php

admin/
  _bootstrap.php              Auth guards, CSRF guard, admin_render(), uploads
  login.php  logout.php
  index.php                   Dashboard (KPIs + last 8 orders)
  products.php                Product list
  product-edit.php            The landing-page builder (the big one)
  product-clone.php           Deep copy of a product and all its children
  product-delete.php
  leads.php  lead-detail.php  leads-export.php  leads-delete.php
  drafts.php                  Abandoned-form call-back list
  reports.php                 Revenue by page, source, campaign
  pixels.php                  Pixel library CRUD
  categories.php              Category CRUD
  users.php                   Admin accounts and roles
  activity.php                Audit trail + recent errors
  settings.php                Store-wide settings + brand assets
  templates/*.json            Landing-page presets
  views/                      Templates for all of the above
  views/partials/             section-editor, page-options, live-preview, unsaved-guard

public/assets/
  css/theme.css               Design tokens, header, footer, buttons
  css/product.css             Landing-page styles
  css/admin.css               Admin panel styles
  css/home.css                Catalogue grid
  js/product.js               Offers, options, slider, validation, countdown, events
  js/home.js                  (placeholder)
  img/logo.svg                tujjar.store wordmark (light backgrounds)
  img/logo-light.svg          tujjar.store wordmark (dark backgrounds)
  img/favicon.svg             Browser tab icon
  img/placeholder.svg

sql/
  schema.sql                  Fresh install (DROPs then CREATEs — never on a live DB)
  seed.sql                    Default admin, settings, pixels, demo product
  upgrade-2026-09-pixels-and-rebrand.sql   Manual fallback for the migration

bin/
  db.php                      status / migrate / seed / fresh / backup / wait / images
  maintenance.php             daily / backup / prune / health  (the cron entry point)

storage/
  logs/                       JSON application logs (gitignored)
  backups/                    Scheduled database dumps (gitignored)

tests/                        14 files, 718 assertions, no dependencies
  pixel_resolution_test.php   Per-page pixel resolution
  capi_test.php               Conversions API payloads and hashing
  sections_test.php           Content round-trip, no lost keys
  reports_test.php            Revenue arithmetic against a hand-built ledger
  products_list_test.php      Search, filters, paging, trash
  roles_test.php              Role guards, audit trail, log redaction
  templates_test.php          Presets and what applying one produces
  image_test.php              The resize pipeline on real files
  seo_test.php                robots, sitemap, JSON-LD
  order_submit_test.php       Both response modes of the order endpoint
  security_test.php           env(), signed tokens, rate limiting
  public_render_test.php      Landing page + thank-you templates end to end
  admin_render_test.php       Pixel manager, section editor, campaign options
  deployment_test.php         Deployment contract: git, image, web exposure, SQL

uploads/                      Admin-uploaded images (gitignored; Docker volume)
docker/  Dockerfile  docker-compose.yml  Jenkinsfile  DEPLOYMENT.md
```

---

## 3. Request lifecycle

A public request, end to end:

1. **Apache** — `.htaccess` blocks `/config`, `/src`, `/sql`. If the path is a
   real file or directory (assets, uploads, `/admin/…`) it is served directly.
   Everything else rewrites to `index.php`.
2. **`config/helpers.php`** — loads config, sets timezone and error display,
   requires `config/database.php`, starts the session with a `HttpOnly`,
   `SameSite=Lax` cookie.
3. **`db()`** (first query) — opens PDO, creates the database if missing, runs
   `_auto_migrate()` (imports `schema.sql` + `seed.sql` when the `admins` table
   is absent), then `run_migrations()` for incremental changes.
4. **`Router::dispatch()`** — strips `base_url`, normalises the trailing slash,
   matches patterns in registration order. `/{slug}` is registered **last** so it
   only catches what nothing else claimed.
5. **Controller** — loads data, calls `pixel_context_set()` where relevant, calls
   `render($view, $data, 'public')`.
6. **`render()`** — buffers the view into `$content`, then includes the layout,
   which emits `<head>` (including the resolved pixels), header, `$content`, footer.

### Routing table

| Method | Pattern | Handler |
| --- | --- | --- |
| GET | `/` | `HomeController::index` |
| GET | `/search?q=` | `HomeController::search` |
| GET | `/category/{slug}` | `HomeController::category` |
| GET | `/thank-you?o={id}` | `LeadController::thankYou` |
| POST | `/lead/submit` | `LeadController::submit` |
| GET | `/page/privacy` `/page/terms` `/page/refund` | `PageController` |
| GET | `/{slug}` | `ProductController::show` — **must stay last** |

**Reserved slugs** (rejected as product slugs): `admin`, `public`, `uploads`,
`config`, `src`, `sql`, `assets`.

---

## 4. Database schema

```
categories ──1:N──► products ──1:N──► product_media
                        │       ──1:N──► product_offers
                        │       ──1:N──► product_option_groups ──1:N──► product_option_values
                        │
                        └───1:N──► leads ──1:N──► lead_items
                                     └───1:N──► lead_status_logs

pixels  ◄──── products.fb_pixel_id / products.tt_pixel_id   (soft reference, see below)

admins        settings (k/v)        schema_migrations
activity_log  throttle_hits         lead_drafts
```

### `products` — one row is one landing page

| Column | Notes |
| --- | --- |
| `slug` | Unique. This *is* the URL: `/casual-pants` |
| `title`, `short_desc`, `full_desc` | Basic copy |
| `cover_image`, `og_image` | Local path (`uploads/…`) **or** a full external URL |
| `base_price`, `compare_price` | Display only — real prices live on offers |
| `badges` | Comma-separated; rendered as chips |
| `status` | `1` public, `0` hidden (still reachable by an admin with `?preview=1`) |
| `seo_title`, `seo_description` | `<title>` / meta description overrides |
| `fb_pixel_id`, `tt_pixel_id` | Per-page pixel choice — see [§9](#9-tracking-pixels-and-events) |
| `sections_json` | All editorial content: hero, features, testimonials, FAQs, CTA |
| `sections_json_b` | Variant B's content, when the page is A/B testing |
| `ab_enabled`, `ab_split` | Whether the test is running, and the percentage seeing A |
| `accent_color`, `cta_color` | Per-page theme overrides. `NULL` means "use the store colour" |
| `campaign_ends_at` | A real deadline for the countdown. `NULL` means the rolling timer |
| `deleted_at` | Soft delete. Retired pages vanish from the store; their orders remain |

### `product_offers` — the pricing tiers the shopper picks between

`label`, `quantity`, `total_price`, `compare_price`, `is_recommended`,
`free_shipping`, `is_default`, `requires_options`, `position`.

`total_price` is the **whole bundle** price, not per unit. `requires_options = 1`
means the shopper must choose colour/size for every unit in the bundle.

### `product_option_groups` / `product_option_values`

Generic, not apparel-specific. A group has a technical `name` (`color`, `size`,
`tier`, `material`), an Arabic `label`, and a `type`:

| Type | Rendered as |
| --- | --- |
| `select` / `swatch` | `<select>` dropdown |
| `radio` | Pill buttons |
| `text` | Free-text input |

Values may carry a `swatch` hex colour. When an offer's `quantity` is 3, the
group repeats three times — one set of choices per unit.

### `leads` / `lead_items` / `lead_status_logs`

One `leads` row per order, one `lead_items` row per unit (holding that unit's
option choices as JSON), and an append-only `lead_status_logs` audit trail.

Status values: `new`, `called`, `confirmed`, `shipped`, `delivered`,
`cancelled`, `no_answer`.

Attribution columns captured automatically: `source`, `utm_source`,
`utm_medium`, `utm_campaign`, `fbclid`, `ttclid`, `gclid`, `ip`, `user_agent`,
and `ab_variant` when the page was split-testing.

### `pixels` — the pixel library (added 2026-09)

| Column | Notes |
| --- | --- |
| `platform` | `facebook` or `tiktok` |
| `name` | Friendly label shown in the dropdowns |
| `pixel_id` | The real Meta / TikTok id |
| `is_default` | One per platform; used by pages left on "inherit" |
| `status` | `0` stops it firing anywhere, without deleting it |
| `access_token`, `test_event_code` | Reserved for a future Conversions API |

`products.fb_pixel_id` / `tt_pixel_id` reference this table **without a foreign
key**, on purpose: the column needs a third state (`0` = "no pixel on this
page") that a FK would reject. `Pixel::delete()` resets any product pointing at
a removed row back to `NULL`.

### The supporting tables

| Table | Purpose |
| --- | --- |
| `admins` | Panel accounts. `role` is `admin` or `agent`; `status` disables one without deleting it |
| `activity_log` | Who changed what: actor, action, entity, summary, address |
| `throttle_hits` | The shared rate-limit ledger — `login:{user}:{ip}` and `lead:{ip}` buckets |
| `lead_drafts` | Abandoned-form captures. Deliberately **not** leads: never an order, never a conversion |
| `schema_migrations` | Which incremental migrations have run |
| `settings` | Key/value store-wide configuration |

`throttle_hits`, `lead_drafts` and `activity_log` all grow without bound if left
alone, so `bin/maintenance.php prune` trims them on the nightly cron — 1 day, 60
days and 180 days respectively.

---

## 5. Public site — routes and views

| Page | View | What it shows |
| --- | --- | --- |
| Home / category / search | `home.php` | Hero copy, category bar, product grid |
| Landing page | `product.php` | The full sales page — see next section |
| Thank you | `thank-you.php` | Confirmation + conversion events |
| Policies | `policy.php` | Admin-authored HTML from `settings` |
| 404 | `404.php` | |
| Order error | `product-error.php` | Server-side validation failure (HTTP 422) |

Every page is wrapped by `layouts/public.php`, which handles `<title>`, meta and
Open Graph tags, favicon, fonts, the accent-colour CSS variable, GTM, GA4, and
the per-page pixel injection.

---

## 6. The landing page, section by section

`src/Views/product.php` renders, in order:

| # | Section | Source | Optional |
| --- | --- | --- | --- |
| 1 | Admin preview bar | `?preview=1` + logged-in admin | yes |
| 2 | Top banner | `settings.header_banner` | yes |
| 3 | Hero: image slider + headline + badge list + jump CTA | `product_media` (kind `slider`) and `sections_json.hero` | slider optional |
| 4 | **Order form**: offer cards, per-unit options, price, customer fields | `product_offers`, `product_option_groups` | no |
| 5 | Gallery grid | `product_media` (kind `gallery`) | yes |
| 6 | Features | `sections_json.features` | yes |
| 7 | Testimonials | `sections_json.testimonials` | yes |
| 8 | Countdown timer | `sections_json.countdown_title` + `settings.countdown_hours` | no |
| 9 | FAQ accordion | `sections_json.faqs` | yes |
| 10 | Related products | 4 random active products | yes |
| 11 | Sticky bottom CTA | — | no |

Offers and option groups are **rendered by JavaScript** from
`window.PRODUCT_DATA`, not by PHP. `product.js` builds every offer card with its
option selects pre-rendered inside, so switching offers preserves choices.
Inputs inside non-selected offer cards are `disabled`, which keeps them out of
both browser validation and the POST body.

### `sections_json` shape

```json
{
  "hero": {
    "headline":    "سروال كاجوال كلاس",
    "subheadline": "إطلالة راقية وراحة طوال اليوم",
    "badges":      ["مريح بزاف", "جودة عالية", "الدفع عند الاستلام"],
    "cta":         "اطلب الآن"
  },
  "features":     [{ "icon": "🚚", "title": "الشحن مجاني", "text": "توصيل سريع" }],
  "testimonials": [{ "name": "مريم", "text": "زوين بزاف ومريح" }],
  "faqs":         [{ "q": "هل تقبلون الدفع عند الاستلام؟", "a": "نعم، في كل المدن." }],
  "countdown_title": "تخفيض 50% و الشحن السريع بالمجان",
  "cta_text":        "اطلب الآن واستفد من العرض"
}
```

Invalid JSON is rejected on save: the previous value is kept and the editor shows
a warning rather than losing your content.

### The countdown

`product.js` stores an end timestamp in `localStorage` per product
(`lp_cd_end_{productId}`) and restarts it once it expires. It is a persuasion
device, not a real deadline.

---

## 7. Order (lead) flow

```
Shopper fills the form
        │
        ▼
 product.js validates client-side   ── offer chosen? options per unit? name ≥3?
        │                              phone /^0[6-7]\d{8}$/? address ≥5?
        ├─ fires InitiateCheckout (Meta) / InitiateCheckout (TikTok)
        ▼
POST /lead/submit
        │
        ├─ Product::find + status check
        ├─ Product::findOffer(offer_id, product_id)   ← scoped to the product
        ├─ re-validate every field server-side
        ├─ collect per-unit options  opt_{offerId}_{group}_{unitIndex}
        ├─ **total_price recomputed from the offer row** — the client price is ignored
        ├─ Lead::create()  → leads + lead_items, in one transaction
        ├─ Lead::syncToSheetDB()  → optional Google Sheet row (failure is swallowed)
        ├─ $_SESSION['last_lead_id'] = id
        ▼
302 → /thank-you?o={id}
        │
        ├─ lead revealed only if $_SESSION['last_lead_id'] matches
        ├─ pixels resolved from THAT order's product
        └─ fires Purchase (Meta) / CompletePayment (TikTok) with the real value,
           once per order
```

**Price integrity.** The browser never supplies a price. It supplies `offer_id`;
the server reads `total_price` from `product_offers`. Editing the DOM changes
nothing.

**SheetDB sync.** When `sheetdb_enabled = 1`, `Lead::syncToSheetDB()` POSTs a row
whose column names match a pre-existing Google Sheet (`destinataire`, `ville`,
`trafic`, …). It runs server-side with an 8-second timeout; the token never
reaches the browser, and a failure is logged without breaking the order.

---

## 8. Admin panel

Sign in at `/admin/login.php`. Sessions carry `admin_id`; every page calls
`admin_require_auth()` and every POST passes through `admin_require_csrf()`.

### Dashboard — `admin/index.php`
Five KPI cards (total orders, today, active products, confirmed, cancelled) and
the last 8 orders.

### Products — `admin/products.php`
List with cover thumbnail, category, price, status, **the pixels each page
reports to**, public URL, and per-row actions: Edit · Preview · Clone · Delete.

**Clone** deep-copies the product, its media, offers, option groups, option
values and pixel selections into a new inactive product with a unique
`-copy` slug. This is how you launch a variant page for a different ad account.

### Product editor — `admin/product-edit.php`
The landing-page builder. On a new product only the top form appears; the child
editors appear after the first save.

| Panel | What it controls |
| --- | --- |
| المعلومات الأساسية | Title, slug, category, descriptions, prices, badge chips, active toggle |
| الصور والـSEO | Cover image, OG image (upload **or** external URL), SEO title/description |
| **التتبع والبكسلات** | Meta pixel and TikTok pixel dropdowns for **this page only** |
| أقسام صفحة المنتج (JSON) | `sections_json` — hero, features, testimonials, FAQs |
| العروض | Pricing tiers: label, quantity, total, compare, default/recommended/free-shipping flags |
| مجموعات الخيارات | Option groups and their values (with colour swatches) |
| صور السلايدر | Hero slider images |
| صور الجسم (المعرض) | Gallery images — **drag to reorder**, saved by fetch |

Images accept a file upload or a pasted external URL (CDN-hosted images are
stored as absolute URLs and served as-is).

### Orders — `admin/leads.php`, `admin/lead-detail.php`
Filter by phone, status, product, source and date range. Bulk-select and delete.
Export all orders to CSV (UTF-8 with BOM, so Excel opens Arabic correctly).
The detail page shows the customer, the order, the per-unit option choices, the
attribution data, and a status timeline; changing status writes a log entry.

### Pixels — `admin/pixels.php`  *(new)*
The pixel library. Per platform: name, id, default flag, status, **which landing
pages use it**, and notes. Add, edit, set-as-default, delete. Deleting resets any
page that used it back to "inherit" rather than orphaning it.

### Reports — `admin/reports.php`
Revenue, confirmation rate and average order value over a date range, with quick
ranges (today / 7d / 30d / this month / last month). The **landing page × source**
cross-tab is the table that answers which ad account to scale: the same page can
be profitable on TikTok and a loss on Meta, and neither number alone shows that.

Throughout, "revenue" means **confirmed + shipped + delivered**. Counting `new`
orders as revenue is how a COD store convinces itself a campaign works while half
the orders are about to be cancelled on the phone.

### Drafts — `admin/drafts.php`  *(the call-back list)*
Shoppers who typed a valid phone number into the order form and left. The ad that
brought them is already paid for, so one call converts a share of them. Stored in
their own table: a draft never reaches the order queue, the revenue report or a
conversion pixel. Auto-pruned after 60 days.

### Categories — `admin/categories.php`
Name, slug and position, with product counts. Deleting a category leaves its
products uncategorised rather than removing them.

### Users — `admin/users.php`
Two roles. **admin** does everything; **agent** sees orders, drafts and reports
only. Every admin-only page calls `admin_require_admin()` — the hidden navigation
link is a courtesy, the guard is the control. Demoting, disabling or deleting the
last remaining admin is refused, because there is no way back through the UI.

### Activity — `admin/activity.php`
Who changed what: products, pixels, categories, settings, accounts, and logins,
with the actor, the address and a summary. Settings edits name the keys that
actually changed. The page also surfaces recent application errors from
`storage/logs/`, so a failing SheetDB sync is noticed without opening a terminal.

### Settings — `admin/settings.php`
Store name; the three brand assets (light logo, dark logo, favicon) with a
one-click reset to the shipped tujjar.store artwork; support phone; WhatsApp;
Facebook handle; accent colour; footer button visibility; fallback tracking ids
and GTM/GA4; SheetDB credentials; the three policy pages as HTML; and the admin
password.

---

## 9. Tracking: pixels and events

### 9.1 What changed

Previously there was **one** Meta pixel id and **one** TikTok pixel id in
Settings, applied to every page — and the TikTok id was read but never actually
rendered, so TikTok tracked nothing at all. Now:

- A **pixel library** holds as many Meta and TikTok pixels as you need.
- **Each landing page chooses its own** pixel per platform, from a dropdown.
- Both platforms fire a complete, matching funnel of events.

This is what lets you run a Meta campaign for product A on ad account 1 and a
TikTok campaign for product B on ad account 2, from the same domain, without the
two accounts polluting each other's data.

### 9.2 Resolution rules

Each product stores one value per platform:

| Stored value | Meaning | Dropdown label |
| --- | --- | --- |
| `NULL` | Inherit | «افتراضي — {name}» |
| `0` | Fire nothing for this platform on this page | «بدون تتبع لهذه المنصة» |
| `N` | Use `pixels.id = N` | «{name} — {pixel_id}» |

Inherit falls back, in order:
1. the pixel marked `is_default` for that platform (and `status = 1`);
2. the legacy `settings.fb_pixel_id` / `settings.tiktok_pixel_id` value;
3. nothing — the platform's script is simply not emitted.

A page pinned to a **paused or deleted** pixel fires *nothing* for that platform.
It deliberately does not fall back to the default: silently sending one
advertiser's conversions to another advertiser's account is worse than sending
none. This behaviour is covered by `tests/pixel_resolution_test.php`.

Pages with no product (home, categories, policies) always use the platform
defaults.

### 9.3 The event funnel

`partials/pixels-head.php` emits both base codes and a small bridge,
`window.LPX`, so every call site fires both platforms with one line:

| Intent | Meta (`fbq`) | TikTok (`ttq`) | Fired when |
| --- | --- | --- | --- |
| — | `PageView` | `ttq.page()` | Every page load |
| `view_content` | `ViewContent` | `ViewContent` | Landing page opens |
| `add_to_cart` | `AddToCart` | `AddToCart` | Shopper clicks an offer card |
| `initiate_checkout` | `InitiateCheckout` | `InitiateCheckout` | Form passes validation and submits |
| `lead` | `Lead` | `SubmitForm` | Order confirmed (thank-you) |
| `purchase` | `Purchase` | `CompletePayment` | Order confirmed (thank-you) |

Details that matter:

- **The default offer selection is not tracked.** Preselecting the recommended
  bundle on page load is not shopper intent; counting it would make AddToCart
  equal PageView and destroy your funnel.
- **Purchase fires once per order.** The lead id is claimed by the session that
  created it, and a `purchase_fired` flag stops a refresh or back-button visit
  from re-reporting the sale.
- **Purchase carries the server-side value** — the recomputed offer price, in MAD
  — plus the product slug as `content_ids` / `content_id`, so your Meta and
  TikTok catalogues line up with your URLs.
- Every event carries an `eventID` (Meta) / `event_id` (TikTok), ready for
  browser↔server deduplication if you later add the Conversions API.
- The shopper's phone is passed to `ttq.identify()` for match quality; TikTok's
  SDK hashes it in the browser before transmission.
- Events are also pushed to `dataLayer`, so GTM can consume the same funnel.

### 9.4 Verifying a pixel works

1. Open the landing page with `?pxdebug=1` and watch the console for `[LPX]`
   lines — each shows the intent, platform, event name and payload.
2. Install **Meta Pixel Helper** and **TikTok Pixel Helper**; both should show
   exactly the pixel id you assigned to that page and no other.
3. Meta: Events Manager → Test Events. TikTok: Assets → Events → Test Event.
4. Walk the funnel: load → click an offer → submit → thank-you. You should see
   `PageView, ViewContent → AddToCart → InitiateCheckout → Purchase/CompletePayment`.
5. Check `view-source:` for `fbq('init', …)` and `ttq.load(…)` — the ids are
   printed in an HTML comment above each block with the pixel's friendly name.

> **Ad-blockers.** Roughly a fifth of Moroccan mobile traffic blocks
> `connect.facebook.net` and `analytics.tiktok.com`. Browser-side pixels will
> always under-report. The Conversions API is the fix — see
> [Known limitations](#21-known-limitations).

---

## 10. Landing-page content, campaigns and experiments

### The section editor

`products.sections_json` is still the storage, but it is no longer hand-edited.
`admin/views/partials/section-editor.php` renders structured fields for the hero,
features, testimonials and FAQs, with add / remove / drag-to-reorder and an emoji
picker.

Repeatable rows post as **parallel arrays** — `sec[features][title][]`,
`sec[features][text][]` — rather than indexed groups, so the DOM order is the
submitted order and reordering needs no index bookkeeping.

The raw JSON view remains behind a toggle, with live validation that reports the
line number of a syntax error. A hidden `sections_mode` field decides which pane
the server reads, so the two can never fight over the column. Keys the form does
not render (added by a future version, say) are carried through a save untouched.

### Starting from a template

**Products → + منتج جديد** offers four presets — apparel, electronics, cosmetics,
home — that scaffold the content, option groups and pricing tiers. Prices are left
at zero and the product starts inactive on purpose: a preset that guessed prices
would either be ignored or, worse, published. Adding a preset is dropping a JSON
file into `admin/templates/`.

### Campaign options, per page

| Option | Effect |
| --- | --- |
| Accent colour | Overrides `--accent` for this page only, to match the ad creative |
| CTA colour | Overrides the order button colour |
| `campaign_ends_at` | A real deadline: the timer counts to that instant for every visitor and **stops**; the section disappears once it passes |

Leaving a colour unset stores `NULL`, not a copy of the current store colour — so
changing the store theme later still reaches the page.

### A/B testing

Variant B is one extra column (`sections_json_b`). Offers, images and options stay
shared deliberately: a test that changes two things at once cannot be read.

- **Assignment is sticky** per visitor per product — a cookie, with a
  deterministic hash of address + user agent as the fallback, so a visitor with
  cookies disabled gets a stable variant instead of flipping on every load.
- **The variant is written onto the order** (`leads.ab_variant`), which is what
  makes the result readable against revenue rather than clicks.
- **Results are reported by confirmed revenue**, and the verdict says *"sample
  still too small"* below 40 orders rather than showing a percentage that invites
  a call on nine.

---

## 11. Performance, SEO and the order flow

### Images

Uploads generate WebP at 480 / 800 / 1400px (`src/Models/Image.php`). Templates
emit `srcset`/`sizes` with explicit `width`/`height` to stop layout shift; the
hero image loads eagerly at high priority and everything else lazily. The pipeline
never upscales, preserves PNG transparency, and passes CDN URLs through untouched.
If `ext-gd` is missing, the original is served and nothing breaks.

Backfill images uploaded before this existed:

```bash
php bin/db.php images          # skips ones already done
php bin/db.php images --force  # rebuild everything
```

### SEO

- Product, FAQPage and BreadcrumbList JSON-LD on every landing page.
- `/sitemap.xml`, generated from live products — a stale hand-written file is
  worse than none, because it keeps crawlers on retired pages.
- `/robots.txt`, disallowing admin, thank-you, `/lead/` and search.
- Canonical URLs that strip `?fbclid` and UTM parameters, so every ad variant
  stops looking like duplicate content.
- `noindex` on thank-you, search results and the order-error page.

No `aggregateRating` is ever emitted. Testimonials become `Review` nodes with no
star rating, because the store does not collect ratings — inventing one is what
gets rich results revoked for a whole domain.

### Order submission

The form posts over `fetch` and swaps in an inline confirmation before
redirecting. Three things this buys: the shopper stays on the page while it saves,
the back button cannot resubmit, and — the reason it matters most — **Purchase
fires on a page that is still open**. Firing a pixel during a redirect is how
conversions get dropped on slow mobile connections.

If `fetch` is unavailable or the request fails, the ordinary form POST still runs.

### Anti-bot

Three defences, none of which a shopper ever sees: an off-screen honeypot field,
an HMAC-signed render timestamp (rejecting submissions faster than 3 seconds or
older than 6 hours), and a rate limit of 5 orders per address per 10 minutes.
A tripped honeypot answers as though it worked — telling a bot it was detected
only makes the next attempt smarter.

---

## 12. Settings reference

All in the `settings` table, edited at `/admin/settings.php`, read with
`settings_get($key, $default)` (cached per request).

| Key | Purpose |
| --- | --- |
| `store_name` | Brand name in `<title>`, footer, admin sidebar |
| `store_logo` | Logo for light backgrounds (site header) |
| `store_logo_light` | Logo for dark backgrounds (footer, admin sidebar) |
| `store_favicon` | Browser tab icon |
| `support_phone` | Header "call us" and footer link |
| `whatsapp` | Footer WhatsApp button (`wa.me`) |
| `facebook_handle` | Footer Facebook button |
| `accent_color` | `--accent` CSS variable, theme colour |
| `header_banner` | Free-shipping strip above the landing page |
| `countdown_hours` | Countdown duration |
| `show_footer_phone` / `_whatsapp` / `_facebook` | Footer button visibility |
| `fb_pixel_id` / `tiktok_pixel_id` | **Fallback only** — the pixel library supersedes these |
| `gtm_id`, `ga_id` | Google Tag Manager and GA4 |
| `sheetdb_enabled`, `sheetdb_url`, `sheetdb_token` | Google Sheet lead sync |
| `policy_privacy`, `policy_terms`, `policy_refund` | Policy page HTML |

---

---

## 13. Branding

The brand is **tujjar.store**. Three artwork files ship with the app:

| File | Used by |
| --- | --- |
| `public/assets/img/logo.svg` | Site header (light background) |
| `public/assets/img/logo-light.svg` | Footer and admin sidebar (dark background) |
| `public/assets/img/favicon.svg` | Browser tab, Apple touch icon |

They are SVG, so they stay sharp at any size and cost ~1 KB each. The wordmark
is `tujjar` in graphite with `.store` in the accent gold, beside a shopping-bag
mark; the light variant swaps the text to white and the accent to a lighter gold.

**To change the brand** you never edit code: Settings → upload a new logo for
each background, or paste a CDN URL, or tick "إعادة الشعارات إلى تصميم
tujjar.store الافتراضي" to restore the shipped artwork. If no logo is set, the
header falls back to `◆ {store_name}`.

**What was deliberately *not* renamed:** the repository folder (`lp_tifaw`), the
Docker Compose project (`lp-tifaw`), the database name, the session cookie
(`LPTIFAW_SESS`) and the Jenkins job. Those are infrastructure identifiers —
renaming them would orphan the production Docker volumes (uploads and database
included) and break the CloudForge pipeline, for zero user-visible benefit. The
customer-facing name, `app.name` in every config, `APP_NAME` in Docker, and all
brand artwork are now tujjar.store.

---

## 14. Security model

**What is already in place**

| Area | Control |
| --- | --- |
| SQL | PDO prepared statements everywhere; `ATTR_EMULATE_PREPARES = false` |
| XSS | `e()` (`htmlspecialchars`, `ENT_QUOTES`) on all output except admin-authored policy HTML |
| CSRF | Random 32-byte token in the session; every admin POST verified with `hash_equals` |
| Passwords | `password_hash` bcrypt; session id regenerated on login |
| Session | `HttpOnly`, `SameSite=Lax`, `Secure` in production |
| Price tampering | Total recomputed server-side from `offer_id` |
| Order privacy | `/thank-you?o=N` reveals an order only to the session that created it |
| Uploads | 5 MB cap, MIME sniffed with `finfo`, random filename, extension from the sniffed type (no SVG, no PHP) |
| Directory traversal | `.htaccess` blocks `/config`, `/src`, `/sql`; `Options -Indexes` in `/admin` |
| Headers | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` |
| Secrets | SheetDB token and pixel access tokens stay server-side |
| Docker | MySQL publishes no port; app binds `127.0.0.1` only; config file `chmod 640` |

**Removed in this pass:** `_diag.php` — a leftover diagnostic endpoint reachable
at `/_diag.php?key=diag2026` that printed the SheetDB URL and token, rewrote
settings rows, and hard-coded a live API token in the source. Recover it from
git history if ever needed (`git show ad07675:_diag.php`), but do not redeploy
it. **Rotate that SheetDB token** — it was in a public file.

**Still open** — see [Known limitations](#21-known-limitations) for rate limiting, login
throttling, admin roles, and the plaintext production password in
`config/config.prod.php`.

---

## 15. Configuration and environments

`config/config.php` is a loader, not a config file. It returns the first of:

1. `config/config.local.php` — local development (gitignored)
2. `config/config.prod.php` — production (gitignored; the Docker entrypoint
   generates it from environment variables at container start)
3. otherwise it stops with HTTP 503 and instructions

```php
return [
    'app' => [
        'name'     => 'tujjar.store',
        'base_url' => '/lp_tifaw',      // '' when served at a domain root
        'env'      => 'development',    // 'production' hides errors
        'timezone' => 'Africa/Casablanca',
    ],
    'db'       => ['host','port','name','user','pass','charset'],
    'security' => ['session_name', 'cookie_secure'],
];
```

`base_url` is the one setting that breaks everything when wrong. It must match
where the app is mounted: `/lp_tifaw` under XAMPP, `''` at a domain root. The
router strips it from every incoming path and `base_url()` prepends it to every
generated link.

---

## 16. Migrations

Two mechanisms, with different jobs:

**`sql/schema.sql` + `sql/seed.sql`** — fresh installs only. `schema.sql` begins
with `DROP TABLE`, so it can never be run against a live database.
`_auto_migrate()` imports both exactly once, when the `admins` table is absent.

**`config/migrations.php`** — incremental changes to databases that already
exist. Each migration is a named function recorded in `schema_migrations` after
it succeeds; `run_migrations()` runs the unapplied ones on every request (one
indexed SELECT of overhead). Failures are logged, never fatal — a bad migration
cannot take the storefront down.

Migrations shipped in this release:

| Version | Effect |
| --- | --- |
| `2026_09_03_001_pixels` | Creates `pixels`; imports the two existing settings ids as platform defaults |
| `2026_09_03_002_product_pixel` | Adds `products.fb_pixel_id` and `products.tt_pixel_id` |
| `2026_09_03_003_rebrand` | Sets `store_name` to `tujjar.store` and points the logo/favicon at the new SVGs — **only if the values are still the shipped CasaLux defaults**, so a store that already customised them keeps its own |

`sql/upgrade-2026-09-pixels-and-rebrand.sql` does the same thing by hand, for a
database user without `ALTER` rights or a scheduled maintenance window.

### Adding a migration

```php
// config/migrations.php
function migrations_list(): array {
    return [
        // … existing …
        '2026_10_01_004_my_change' => 'migration_my_change',
    ];
}

function migration_my_change(PDO $pdo): void {
    if (!_mig_has_column($pdo, 'products', 'my_column')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN my_column VARCHAR(80) NULL");
    }
}
```

Keep them idempotent — use `_mig_has_column()` / `_mig_has_table()` / `IF NOT
EXISTS` — so a lost `schema_migrations` row is harmless. Add the same change to
`schema.sql` for fresh installs.

---

## 17. Local development

1. Put the project at `C:\xampp\htdocs\lp_tifaw\`.
2. Start Apache and MySQL in the XAMPP control panel.
3. Copy `config/config.example.php` to `config/config.local.php` and fill in your
   DB credentials (XAMPP default: `root` with an empty password).
4. Open `http://localhost/lp_tifaw/` — the database, schema and seed data are
   created automatically on the first request.
5. Admin: `http://localhost/lp_tifaw/admin/login.php` — `admin` / `admin123`.
   **Change it immediately in Settings.**

Ensure `uploads/` is writable. If you mount the app somewhere other than
`/lp_tifaw`, update `app.base_url`.

### Adding a product, end to end

1. Products → **+ منتج جديد**. Fill title, slug, price, cover image. Save.
2. Paste your content into **أقسام صفحة المنتج (JSON)** — a starter template is
   pre-filled.
3. **العروض**: add the 1× / 2× / 3× tiers, mark one default and one recommended.
4. **مجموعات الخيارات**: add `color` (swatch) and `size` (select) with values.
   For non-apparel products define whatever groups fit — `tier`, `material`, …
5. **صور السلايدر** and **صور المعرض**: upload or paste URLs; drag gallery
   images to reorder.
6. **التتبع والبكسلات**: choose the Meta and TikTok pixels for this page.
7. Preview with 👁, then tick **منتج نشط** and save.

---

## 18. Deployment

Production runs in Docker behind CloudForge-managed Nginx, deployed by Jenkins.
`DEPLOYMENT.md` holds the full runbook; the shape is:

```
Cloudflare → Nginx (CloudForge, tujjar.store) → 127.0.0.1:HOST_PORT
                                                     │
                                    ┌────────────────┴────────────────┐
                                    │ app (php:8.2-apache)            │
                                    │ db  (mysql:8.0, no host port)   │
                                    └─────────────────────────────────┘
                             volumes: uploads, sessions, dbdata
```

The entrypoint generates `config/config.prod.php` from environment variables at
container start (so no credential is baked into an image layer), waits for MySQL,
imports the schema on first boot, and rotates the seeded admin password to
`ADMIN_PASSWORD`.

### Deployment modes

The pipeline takes a `DEPLOY_MODE` parameter. Everything it deploys comes from
one git commit — `checkout scm`, an image built from that checkout, and the SQL
in `sql/*.sql` from the same commit. Nothing is uploaded by hand.

| Mode | Builds | Database | For |
| --- | --- | --- | --- |
| `deploy` *(default)* | yes | migrations only | Normal releases |
| `deploy-with-seed` | yes | migrations, then `seed.sql` if the catalogue is empty | First launch, wiped staging |
| `deploy-fresh` | yes | DROPs everything, re-imports schema + seed | Rebuilding a demo box. Needs `CONFIRM_DESTRUCTIVE=FRESH` |
| `rebuild-no-cache` | yes, `--no-cache` | migrations only | A layer change is not being picked up |
| `restart` | no | migrations only | Env-file change, stuck container |
| `rollback` | no | migrations only | Fast revert to `ROLLBACK_TAG` |

**Preflight** rejects a bad run before anything is built: a missing environment
credential, `deploy-fresh` without its confirmation, `rollback` without a tag,
and — the one that matters most here — **a checkout missing any deploy-critical
file**. That check is what catches a file written locally but never committed,
before it becomes a 500 on the live domain.

`BACKUP_DB` (default on, forced for `deploy-fresh`) dumps with `mysqldump` from
the *database* container, deliberately not the app container: the app image
running at that moment is the one being replaced. Dumps go to
`$JENKINS_HOME/backups/lp-tifaw/`, gzipped, 0600, last 14 kept, never archived as
build artifacts — they hold customer PII.

See `DEPLOYMENT.md` for the full mode reference and the restore command.

### `bin/db.php` — the database tool

Every mode drives one CLI, which is also the manual tool on XAMPP or over SSH:

```bash
php bin/db.php status              # tables, row counts, migrations, pixels
php bin/db.php migrate             # apply pending migrations (idempotent)
php bin/db.php seed [--force]      # import seed.sql if the catalogue is empty
php bin/db.php fresh --force       # DROP everything, rebuild from schema + seed
php bin/db.php backup [path]       # logical dump, no mysqldump binary needed
php bin/db.php wait [seconds]      # block until the database answers
```

It uses the application's own connection and its own SQL files, so the deploy
can never disagree with what the app expects. Three locks keep it off the web:
a `PHP_SAPI !== 'cli'` guard, a `.htaccess` deny on `/bin/`, and a vhost
`DirectoryMatch` deny. `seed` on a populated catalogue is a no-op rather than an
error, so `deploy-with-seed` is safe to run against a live store.

**After deploying this release**, the migrations run on the first request. Verify:

```bash
docker compose -p lp-tifaw exec db \
  mysql -u root -p"$DB_ROOT_PASSWORD" lp_tifaw \
  -e "SELECT version FROM schema_migrations; SELECT id,platform,name,pixel_id,is_default FROM pixels;"
```

**Back up before every deploy** — the uploads volume is the only copy of admin
images:

```bash
docker run --rm -v lp-tifaw_uploads:/src -v "$PWD":/out alpine \
  tar czf /out/uploads-$(date +%F).tar.gz -C /src .
docker exec lp-tifaw-db-1 sh -c \
  'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction lp_tifaw' \
  > db-$(date +%F).sql
```

---

## 19. Testing

There is no test framework. Fourteen dependency-free executable tests ship with
the app — none needs MySQL or a config file, so they run anywhere PHP does:

```bash
for t in tests/*.php; do php "$t" || exit 1; done
```

| Test | Assertions | Covers |
| --- | --- | --- |
| `pixel_resolution_test.php` | 16 | Every state of per-page pixel resolution |
| `capi_test.php` | 43 | Phone normalisation, hashing, event-id dedup, payload shape |
| `sections_test.php` | 58 | Content round-trip; unmanaged keys survive a save |
| `reports_test.php` | 60 | Revenue arithmetic against a hand-counted ledger |
| `products_list_test.php` | 71 | Search, filters, paging, soft delete, trash |
| `roles_test.php` | 78 | Role guards on every page, audit trail, log redaction |
| `templates_test.php` | 78 | Preset files and what applying one produces |
| `image_test.php` | 42 | Real resizing: widths, transparency, no upscaling |
| `seo_test.php` | 56 | robots, sitemap, JSON-LD, canonical |
| `order_submit_test.php` | 37 | JSON and HTML paths stay in step |
| `security_test.php` | 47 | env parsing, signed tokens, rate-limit windows |
| `public_render_test.php` | 41 | The real templates through the real layout |
| `admin_render_test.php` | 35 | Pixel manager, section editor, campaign options |
| `deployment_test.php` | 56 | The deployment contract itself |

All exit non-zero on failure, so they drop straight into CI.

Everything else is manual. Syntax-check the tree before deploying:

```bash
find . -name '*.php' -not -path './.git/*' -exec php -l {} \; | grep -v 'No syntax errors'
node --check public/assets/js/product.js
```

---

## 20. Operational runbook

| Symptom | Where to look |
| --- | --- |
| Blank page / 503 "Configuration missing" | No `config.local.php` or `config.prod.php` |
| Every URL 404s | `base_url` doesn't match the mount point; or `mod_rewrite` is off |
| Landing page 404s but shows in admin | `status = 0` — use `?preview=1` to check |
| Pixel not firing | Admin → Pixels: is it `مفعّل`? Is the page pinned to it or on «بدون تتبع»? Then `?pxdebug=1` |
| Wrong pixel firing | Product editor → التتبع والبكسلات; the products list shows every page's choice at a glance |
| Purchase not firing | Only fires once per order, for the session that placed it. Re-open a fresh order to test |
| Orders not in Google Sheet | Settings → `sheetdb_enabled`; check the PHP error log for `SheetDB sync failed` |
| Images 404 in production | The `uploads` volume was recreated — restore from backup |
| Arabic shows as `????` | Connection or column charset is not `utf8mb4` |
| Migration didn't apply | `SELECT * FROM schema_migrations`; check the error log; or run `sql/upgrade-2026-09-*.sql` by hand |

---

## 21. Known limitations

Every item that was on this list has since been addressed — see
**[ENHANCEMENTS.md](ENHANCEMENTS.md)** for what was built for each, with one
exception noted below. What remains:

- **The SheetDB token has not been rotated.** It was published in `_diag.php` on
  the live domain; only you can replace it in the SheetDB dashboard. The admin
  dashboard flags it until you do.
- **`Product::related()` still uses `ORDER BY RAND()`** — fine at this catalogue
  size, a full table sort at scale.
- **A/B significance is a heuristic**, not a statistical test: a 40-order floor
  and a 10% margin. It is honest about being early, but it is not a p-value.
- **One store, one currency, one country.** MAD and Morocco are assumed
  throughout — the city list, the phone validation and the CAPI country hash.
- **No queue.** The Conversions API call and the SheetDB sync happen inline in
  the order request, each with a short timeout. A slow platform adds latency to
  the shopper's submission rather than being retried in the background.

### For reference — what the original list said

- **`sections_json` is hand-edited JSON.** One misplaced comma and the whole
  editorial content of a page is rejected. This is the single biggest usability
  gap in the admin.
- **No Conversions API.** Browser pixels alone under-report by roughly the
  ad-blocker rate.
- **No login throttling and no rate limit on `/lead/submit`.** Brute-force and
  spam-order floods are both currently unmitigated.
- **One admin role.** Anyone who can read orders can also delete products.
- **`config/config.prod.php` holds a plaintext DB password** on the Hostinger
  deployment (the Docker path generates it from env instead).
- **`Product::related()` uses `ORDER BY RAND()`** — fine at this catalogue size,
  a full table sort at scale.
- **No pagination on the products list**, and `Pixel::describeChoice()` issues a
  query per row (an N+1 that only matters past a few hundred products).
- **No image resizing.** A 4 MB phone photo is served to mobile shoppers as-is.
- **No sitemap, no `robots.txt`, no structured data** (Product/FAQ JSON-LD).
- **The anti-devtools script** on landing pages (blocking right-click, F12,
  Ctrl+U, copy and text selection) degrades the experience for real shoppers and
  stops nobody who actually wants the source.
- **No soft delete.** Deleting a product cascades its orders away permanently.

---

*Last updated: 2026-09-03*
