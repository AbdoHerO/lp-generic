# tujjar.store — Enhancement roadmap

> **Status: every item below is implemented**, except 0.1, which needs an action
> in the SheetDB dashboard that only you can take. Each heading carries what was
> actually built and where. Kept as the record of what changed and why, and as
> the starting point for the next round.
>
> Verified by 718 assertions across 14 test files (`php tests/<name>.php`).

Effort is rough: **S** ≈ half a day · **M** ≈ 1–3 days · **L** ≈ a week or more.

---
## Tier 0 — Do these first (security and money)

### 0.1 Rotate the SheetDB token · S

> **⚠ STILL YOURS TO DO.** The token can only be rotated in the SheetDB dashboard. The panel now nags about it: `SecurityAudit` flags the exact leaked value on the dashboard with a link to Settings, and stops as soon as you change it.

`_diag.php` sat on the public domain with the SheetDB URL and API token in plain
source and a guess-able `?key=diag2026`. The file has been deleted in this
release, but anyone who fetched it still holds a working token that can write to
your orders sheet. Rotate it in SheetDB, then update Settings.

### 0.2 Throttle the admin login · S

> **Done** — `src/Models/Throttle.php`, applied in `admin/login.php`. 5 failures per username+address in 15 minutes, then a timed lockout. Failures are recorded whether or not the username exists, so lockout behaviour never confirms a real one. A disabled account fails identically to a wrong password.

`admin/login.php` accepts unlimited attempts against a known username (`admin`).
Add an attempts table keyed by IP + username, lock for 15 minutes after 5
failures, and log every attempt. Roughly 30 lines.

### 0.3 Rate-limit `POST /lead/submit` · S

> **Done** — three defences, all invisible to shoppers: an off-screen honeypot field, an HMAC-signed render timestamp (rejects sub-3-second and stale submissions), and 5 orders per address per 10 minutes returning 429. A tripped honeypot answers as if it worked, so a bot learns nothing.

Nothing stops a script inserting ten thousand fake orders — which poisons your
order queue *and* your pixel data. Cap by IP (e.g. 5 per 10 minutes) and add a
honeypot field plus a minimum time-on-page check. Both are invisible to real
shoppers and stop most bots.

### 0.4 Move the production DB password out of the file · S

> **Done** — `config/env.php`. Config files now name their secrets (`env_required('DB_PASSWORD')`) instead of containing them; values come from real environment variables first, then `config/.env`. Your production credentials were moved to `config/.env.production` — upload it to the server as `config/.env`. A test reads those files and fails if any secret appears in a tracked file.

`config/config.prod.php` has a plaintext password committed to a gitignored file
on a shared host. The Docker path already reads it from the environment — do the
same on Hostinger with `getenv()` and a `.htaccess`-protected env file.

### 0.5 Conversions API for Meta and TikTok · M

> **Done** — `src/Models/PixelServer.php`. Purchases are reported server-side to Graph API and TikTok Events API with the same `event_id` the browser uses, so nothing double-counts. Phone, name and city are normalised then SHA-256 hashed before they leave the process; Moroccan numbers are converted to E.164 first, without which they match nobody. Enable in Settings, then add an Access Token per pixel.

The biggest revenue item on this list. Around a fifth of Moroccan mobile traffic
blocks pixel scripts, so your ad platforms are optimising on incomplete data.
The groundwork is already in place: the `pixels` table has `access_token` and
`test_event_code` columns, and every browser event already carries an `event_id`
for deduplication. What remains is a server-side `PixelServer::send()` that POSTs
`Purchase` (and ideally `Lead`) to both Graph API and TikTok Events API from
`LeadController::submit()`, with the hashed phone as the match key.
**Expect a 15–30% lift in reported conversions**, which directly improves how
both platforms bid for you.

---

## Tier 1 — The admin panel

### 1.1 Replace the `sections_json` textarea with a real section editor · L

> **Done** — `admin/views/partials/section-editor.php` + `src/Models/Sections.php`. Structured fields for hero, features, testimonials and FAQs, with add/remove/drag-to-reorder and an emoji picker. Rows post as parallel arrays, so the DOM order is the submitted order. The raw JSON view remains behind a toggle with live validation and a line number on error; `sections_mode` decides which pane the server reads. Keys the form does not render are preserved through a save.

**This is the single biggest improvement available.** Right now the entire
editorial content of a landing page — hero, features, testimonials, FAQs — is a
raw JSON textarea. One missing comma rejects the whole save. It is the one part
of the admin a non-technical person cannot use.

Replace it with repeatable field groups: a Hero panel with headline/subheadline/
badge-chips/CTA inputs, and add/remove/reorder rows for features (icon picker +
title + text), testimonials (name + text + star rating) and FAQs (question +
answer). Keep writing to the same `sections_json` column so the public template
never changes, and keep the JSON view behind a "متقدم" toggle for power users.

Do this in two steps: (a) a JSON-backed form builder that round-trips the
existing shape; (b) drag-to-reorder, reusing the gallery's existing sort code.

### 1.2 Live preview beside the editor · M

> **Done** — `admin/views/partials/live-preview.php`. A dockable panel rendering the real `?preview=1` URL at phone/tablet/full width, opening itself after a save and remembering whether it was open.

An iframe of `/{slug}?preview=1` pinned next to the form, refreshed on save.
Today the loop is edit → save → switch tab → reload. For a landing page you tune
a dozen times before launch, that friction is the whole job.

### 1.3 Section templates / page presets · M

> **Done** — `src/Models/PageTemplate.php` + `admin/templates/*.json`. Four presets (apparel, electronics, cosmetics, home) fill in content, option groups and pricing tiers. Prices are deliberately left at zero and the product starts inactive. Adding a preset is dropping a JSON file in.

"إنشاء من قالب": pick *Apparel*, *Electronics*, *Cosmetics*, *Home* and get a
complete `sections_json` plus sensible offers and option groups pre-filled. Clone
already covers "same as last time"; this covers "new category, same structure".
Store the presets as JSON files in `admin/templates/`.

### 1.4 Inline validation and autosave in the editor · S

> **Done** — live JSON validation with a line number, plus an unsaved-changes guard (`unsaved-guard.php`): a visible badge, a confirm on internal links, and the browser's own warning on leaving.

Validate `sections_json` as the admin types (green/red border, error line
number), warn before navigating away with unsaved changes, and draft to
`localStorage`. Cheap, and it prevents the "I lost twenty minutes of copy" call.

### 1.5 Bulk product actions · S

> **Done** — select-all plus activate, deactivate, retire, restore, permanent delete, and bulk-assign a Meta or TikTok pixel. Assigning a pixel to a whole category is now one action.

The orders list has bulk select; the products list does not. Add activate,
deactivate, delete and — most useful — **bulk-assign a pixel**, so switching a
whole category to a new ad account is one action instead of thirty.

### 1.6 Pagination and search on the products list · S

> **Done** — `Product::paginate()` with search across title/slug/description, filters for status, category and pixel, and 25-per-page paging. The list also shows each page's order count.

Unbounded `SELECT *` with no pager. Fine at ten products, unusable at three
hundred. Add a search box, a status filter and 25-per-page pagination — the
`Lead::paginate()` pattern is already there to copy.

### 1.7 A real dashboard · M

> **Done** — the call queue first (it is the daily job), then 30-day orders, confirmed revenue, confirmation rate and AOV, a dependency-free SVG-less sparkline, revenue by source, and the top five landing pages.

Five counters is not a dashboard. Add: orders and revenue per day for the last
30 days (a small SVG sparkline, no chart library needed), conversion rate per
landing page, revenue per product, the top 5 pages by orders, and a
confirmed/cancelled/no-answer breakdown. You already store everything needed;
it's a handful of `GROUP BY` queries.

### 1.8 Per-page and per-source performance report · M

> **Done** — `src/Models/Report.php` + `admin/reports.php`. The landing page × source cross-tab is the table that answers which ad account to scale. Revenue counts confirmed/shipped/delivered only — counting `new` orders is how a COD store convinces itself a campaign works.

Given `leads.source`, `utm_campaign`, `fbclid` and `ttclid` are already captured,
a table of *landing page × source → orders, confirmed, revenue* tells you which
ad account to scale. This is the report you'd otherwise rebuild by hand in the
Google Sheet every week.

### 1.9 Order workflow quality-of-life · S

> **Done** — click-to-call and click-to-WhatsApp in the list, inline status change from the list, and a duplicate-phone badge with the matching orders shown on the detail page before the confirmation call.

- Click-to-call and click-to-WhatsApp links on the orders list, not just detail.
- Inline status change from the list (a `<select>` that POSTs) — a caller
  currently opens and leaves every order.
- Duplicate-phone warning: flag when a phone already ordered in the last 30 days.
- A "call again later" status with a reminder date.

### 1.10 Category management UI · S

> **Done** — `admin/categories.php`. Name, slug and position, with product counts and a delete that leaves the products uncategorised rather than removing them.

`categories` has no admin screen at all — rows must be inserted with SQL. Add a
small CRUD page with name, slug and position.

### 1.11 Admin users and roles · M

> **Done** — `src/Models/Admin.php` + `admin/users.php`. Two roles: *admin* (everything) and *agent* (orders, drafts and reports only). Every admin-only page calls `admin_require_admin()` — the hidden nav link is a courtesy, the guard is the control. Demoting, disabling or deleting the last admin is refused.

One shared `admin` account and one role. Add a users table screen and two roles:
*admin* (everything) and *agent* (orders only, no products, no settings, no
delete). If anyone else ever works your order queue, this stops an accidental
product deletion.

### 1.12 Activity log · S

> **Done** — `src/Models/Activity.php` + `admin/activity.php`. Records product, pixel, category, settings and account changes plus logins, with the actor, the address and what changed. Settings edits name the keys that actually changed.

`lead_status_logs` already audits order changes. Extend the idea to product,
pixel and settings edits: who changed what, when. Invaluable the first time a
live page mysteriously changes.

---

## Tier 2 — The landing pages

### 2.1 Remove the anti-devtools script · S

> **Done** — narrowed from blocking F12/Ctrl+U/copy/selection across the whole page to image drag and right-click only, behind a Settings toggle. Shoppers can copy your phone number and print an order again.

`product.php` blocks right-click, F12, Ctrl+U/S/P, copy, and text selection. It
stops no one who wants your images — the page source is one `curl` away — while
breaking legitimate shopper behaviour: copying your phone number, saving the
page, printing an order. It also risks looking broken enough to lose sales. Drop
it, or narrow it to `dragstart` on images only.

### 2.2 Image pipeline · M

> **Done** — `src/Models/Image.php`. Uploads generate WebP at 480/800/1400px; the templates emit `srcset`/`sizes` with explicit width and height to stop layout shift, the hero image loading eagerly at high priority. Never upscales, preserves PNG transparency, passes CDN URLs through. `php bin/db.php images` backfills existing uploads.

Cover images are served at whatever size was uploaded — a 4 MB phone photo goes
straight to a 3G mobile shopper. Generate WebP at 3 widths on upload, emit
`srcset`/`sizes`, and set explicit `width`/`height` to stop layout shift. On
COD landing pages, load time *is* conversion rate.

### 2.3 Structured data and SEO basics · S

> **Done** — Product, FAQPage and BreadcrumbList JSON-LD, a generated `/sitemap.xml` listing only live pages, `/robots.txt`, canonical URLs that strip `?fbclid`, and `noindex` on thank-you and search. No `aggregateRating` is invented — that is what gets rich results revoked for a domain.

Add Product and FAQPage JSON-LD (you already have price, availability, FAQs and
ratings-shaped testimonials), plus a generated `sitemap.xml`, a `robots.txt`, and
canonical URLs. Free organic traffic on pages you're already paying to build.

### 2.4 AJAX order submission · S

> **Done** — the form posts over `fetch` and swaps in an inline confirmation. The conversion fires on a page that is still open, which is how Purchase events stop getting lost on slow mobile. Falls back to a normal POST when `fetch` is unavailable or the request fails.

The form does a full POST and redirect. Submitting via `fetch` and swapping in
an inline success panel is faster, keeps the shopper on the page, removes the
double-submit-on-back-button problem, and makes the conversion event fire before
any navigation can interrupt it.

### 2.5 Abandoned-form capture · M

> **Done** — `src/Models/Draft.php` + `admin/drafts.php`. A valid phone is saved once typing stops, on blur, and on tab-hide (`keepalive`). Deliberately a separate table: a draft never reaches the order queue, the revenue report or a pixel. Auto-pruned after 60 days.

Save phone and name as soon as they're valid, before the shopper submits, as a
`partial` lead. In COD selling these are often your cheapest recoverable orders —
one call converts a meaningful share of them.

### 2.6 Per-product theming · S

> **Done** — per-page accent and CTA colours, so a page can match the creative that sent the visitor. Unset means "use the store colour", stored as NULL so a later store-theme change still reaches the page.

`accent_color` is store-wide. A per-product accent and CTA colour lets a page
match the creative in the ad that sent the visitor, which measurably helps
message-match.

### 2.7 A/B testing on offers and copy · L

> **Done** — `src/Models/Experiment.php`. Variant B is one extra sections column; offers and images stay shared, because a test that changes two things cannot be read. Assignment is sticky per visitor (cookie, with a deterministic hash fallback), the variant is written onto the order, and results are reported **by confirmed revenue**. The verdict says "sample still too small" below 40 orders rather than inviting a call on nine.

Two `sections_json` variants per product, 50/50 split by cookie, variant recorded
on the lead. Combined with 1.8 you can then see which headline actually sells.

### 2.8 City autocomplete · S

> **Done** — a `datalist` of 75 Moroccan cities on a real `city` field. It stays free-text, so a village not on the list can still be typed, but the common cases are now consistent enough to filter and route by — and the SheetDB `ville` column stops being `-`.

Address is one free-text field and `city` is submitted empty — which is why the
SheetDB sync writes `-` for `ville`. A datalist of Moroccan cities gives you a
clean, filterable city column and better delivery routing.

### 2.9 Real countdown semantics · S

> **Done** — an optional `campaign_ends_at` per page. When set, the timer counts to that instant for every visitor and **stops**; the whole section disappears once it passes. Without one, the rolling per-visitor timer still applies.

The timer resets itself forever from `localStorage`. Offer a per-product real
deadline (a `campaign_ends_at` column) as an alternative, so a genuine promotion
can be genuinely time-boxed.

---

## Tier 3 — Platform and code health

### 3.1 Autoloading and namespaces · M

> **Done** — `config/autoload.php`, a dependency-free autoloader over `src/`. The explicit `require_once` calls are kept as documentation of each file's real dependencies; this is the safety net under them.

Every controller `require_once`s its models by hand. A tiny PSR-4 autoloader
(or Composer) removes a whole class of "class not found after refactor" bugs.

### 3.2 A test suite · M

> **Done** — 14 files, 718 assertions, no framework and no dependencies. Covers pixel resolution, CAPI payloads, sections round-trips, reports arithmetic, the image pipeline on real files, roles, templates, SEO output, the order endpoint, and the deployment contract itself.

`tests/pixel_resolution_test.php` proves the pattern works with zero
dependencies. Extend it to the lead pipeline: price recomputation, option
validation, offer/product scoping, CSRF rejection. These are exactly the paths
where a bug costs money.

### 3.3 Structured logging · S

> **Done** — `src/Models/Log.php` writes newline-delimited JSON to `storage/logs/`, redacting anything that looks like a password or token. The swallowed `catch { }` around the SheetDB sync is gone. Recent warnings and errors surface on the activity page.

Failures currently go to `error_log` or are swallowed (`catch { /* ignore */ }`
around the SheetDB sync). Write to `storage/logs/app.log` with a level and
context, and surface the last N errors on the dashboard.

### 3.4 Soft delete · S

> **Done** — deleting a product now retires it: hidden from the store and the list, orders untouched. A trash view restores or permanently deletes, and only that path destroys orders — behind a typed confirmation.

Deleting a product cascades away its orders — including delivered ones you need
for accounting. Add `deleted_at` and filter it out instead.

### 3.5 Order export improvements · S

> **Done** — the export honours the active filters, names the file after them, and includes UTM columns and the per-unit option choices.

CSV export ignores the active filters and always dumps everything. Respect the
filters, add a date range, and add an XLSX or delivery-company-format export.

### 3.6 Database backups on a schedule · S

> **Done** — `bin/maintenance.php`. One crontab line: nightly backup (gzipped, `chmod 600`, last 14 kept), pruning of expired throttle/draft/audit rows, image backfill, and a health report. Documented in DEPLOYMENT.md — including that the dumps must be copied off the box.

`DEPLOYMENT.md` documents the backup commands but nothing runs them. A cron with
7-day rotation and an off-box copy. The uploads volume is the only copy of every
product image you have.

### 3.7 Health and uptime · S

> **Done** — `php bin/maintenance.php health` exits non-zero for what an uptime monitor cannot see: no active landing page, a live page with no pixel at all, a spike in logged errors, or orders stopping dead (compared against the previous 7-day average, so it only fires once there is a baseline).

`docker/health.php` exists for the container. Add an external uptime check on
`/` and an alert when orders drop to zero for N hours — the fastest way to learn
that a page broke mid-campaign.

---

## What shipped

| Sprint | Items | Outcome |
| --- | --- | --- |
| 1 | 0.2 – 0.4 | Login throttling, order rate limiting, secrets out of PHP source |
| 2 | 1.1, 1.4 | The section editor, with validation and an unsaved-changes guard |
| 3 | 0.5, 1.8 | Conversions API, then the report that shows whether it worked |
| 4 | 1.2, 1.5, 1.6, 2.1 | Live preview, bulk actions, pagination, scoped image protection |
| 5 | 2.2, 2.3, 2.4 | Image pipeline, structured data and sitemap, AJAX submit |
| 6 | 1.7, 1.9–1.12, 3.3–3.5 | Dashboard, order workflow, categories, roles, audit trail, logging, soft delete |
| 7 | 1.3, 2.5–2.9, 3.1, 3.6, 3.7 | Templates, drafts, theming, A/B, city list, real deadlines, autoloader, cron |

## Still open

1. **Rotate the SheetDB token** (0.1) — the one item that cannot be done from here.
2. **Run the migrations** — they apply on the first request after deploy;
   `php bin/db.php status` confirms.
3. **Install the cron** — see DEPLOYMENT.md. Nothing runs on a timer until it exists.
4. **Add Access Tokens** to each pixel and switch on the Conversions API in
   Settings; without them the server-side reporting stays dormant.

## Next round

Not started, and not urgent:

- **Per-city delivery fees and a shipping-company export.** The city field now
  holds clean values, which is the prerequisite.
- **Repeat-customer view.** `leads.phone` already identifies them; nothing groups by it.
- **Multi-currency / multi-country.** Everything assumes MAD and Morocco today.
- **Webhooks** on order status changes, for a delivery partner's system.
- **A statistical significance test on A/B**, rather than the 40-order floor
  and 10% margin used now.
