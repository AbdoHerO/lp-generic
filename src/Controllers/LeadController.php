<?php
require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Lead.php';
require_once __DIR__ . '/../Models/Throttle.php';
require_once __DIR__ . '/../Models/PixelServer.php';
require_once __DIR__ . '/../Models/Log.php';
require_once __DIR__ . '/../Models/Experiment.php';
require_once __DIR__ . '/../Models/Draft.php';

class LeadController {
    /**
     * Order-submission limits.
     *
     * A flood of fake orders costs twice: it buries the real ones in the call
     * queue, and it poisons the conversion signal both ad platforms optimise
     * against. The cap is per address and generous enough that a family
     * ordering from one connection is never affected.
     */
    private const RATE_MAX      = 5;
    private const RATE_WINDOW   = 600;   // 10 minutes
    private const MIN_FILL_TIME = 3;     // seconds a human needs, at minimum
    private const MAX_FORM_AGE  = 21600; // 6 hours — a stale tab, re-render it

    /**
     * Order confirmation. This is where Purchase / CompletePayment fire, so it
     * needs the real order value and the same pixels the landing page used.
     *
     * The lead is only exposed when the session that created it asks for it —
     * without that check, ?o=1..N would let anyone read order totals and would
     * let a refreshed or shared URL inflate conversions.
     */
    public function thankYou(): void {
        $orderId  = (int)($_GET['o'] ?? 0);
        $purchase = null;

        if ($orderId > 0 && ($_SESSION['last_lead_id'] ?? 0) === $orderId) {
            $lead = Lead::find($orderId);
            if ($lead) {
                $product = Product::find((int)$lead['product_id']);
                pixel_context_set($product);

                // One Purchase per order: the flag is set on the first render,
                // so a refresh or a back-button revisit does not report the
                // same sale twice.
                $alreadyFired = !empty($_SESSION['purchase_fired'][$orderId]);
                if (!$alreadyFired) {
                    $_SESSION['purchase_fired'] = [$orderId => true];
                    $purchase = [
                        'order_id' => $orderId,
                        'id'       => $lead['product_slug'],
                        'name'     => $product['title'] ?? $lead['product_slug'],
                        'value'    => (float)$lead['total_price'],
                        'quantity' => (int)$lead['quantity'],
                        'currency' => 'MAD',
                        'phone'    => $lead['phone'],
                        'event_id' => 'purchase.' . $orderId,
                    ];
                }
            }
        }

        // An order confirmation in the index is both useless and a privacy leak.
        render('thank-you', ['title' => 'تم استلام طلبك', 'purchase' => $purchase, 'noindex' => true]);
    }

    /**
     * Capture a phone number typed into the form but never submitted.
     *
     * Rate-limited on the same budget as an order, so it cannot be used to
     * enumerate or flood. Answers 204 always — the browser must learn nothing
     * from the response, and the shopper must never see anything happen.
     */
    public function draft(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { not_found(); return; }

        // Deliberately quiet on every failure path.
        $noop = function (): void { http_response_code(204); exit; };

        if (settings_get('capture_drafts', '1') !== '1') $noop();

        $bucket = 'draft:' . Throttle::ip();
        if (Throttle::tooMany($bucket, 20, 600)) $noop();
        Throttle::hit($bucket);

        $stamp = form_token_check($_POST['form_ts'] ?? null);
        if (!$stamp['ok']) $noop();

        $productId = (int)($_POST['product_id'] ?? 0);
        $product   = $productId ? Product::find($productId) : null;
        if (!$product) $noop();

        Draft::capture(
            $productId,
            (string)($_POST['phone'] ?? ''),
            $_POST['fullname'] ?? null,
            (int)($_POST['offer_id'] ?? 0) ?: null
        );
        $noop();
    }

    public function submit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { not_found(); return; }

        // ── Bot and flood defences ────────────────────────────────────────
        // All three are invisible to a real shopper: no captcha, no extra step.

        // 1. Honeypot. The field is present in the DOM but hidden from people
        //    and marked never-autofill; only a form-filling script types in it.
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            // Answer as if it worked. Telling a bot it was detected just makes
            // the next attempt smarter.
            if ($this->wantsJson()) {
                json_response(['ok' => true, 'redirect' => base_url('thank-you')]);
            }
            redirect(base_url('thank-you'));
        }

        // 2. Timing. The token is HMAC-signed, so it cannot be fabricated, and
        //    it proves the form was actually rendered before being submitted.
        $stamp = form_token_check($_POST['form_ts'] ?? null);
        if (!$stamp['ok']) {
            $this->fail('انتهت صلاحية النموذج. الرجاء تحديث الصفحة والمحاولة من جديد.');
        }
        if ($stamp['age'] < self::MIN_FILL_TIME) {
            $this->fail('تم إرسال الطلب بسرعة غير معتادة. الرجاء المحاولة مرة أخرى.');
        }
        if ($stamp['age'] > self::MAX_FORM_AGE) {
            $this->fail('الصفحة مفتوحة منذ وقت طويل. الرجاء تحديثها لتأكيد السعر الحالي.');
        }

        // 3. Rate limit per address.
        $bucket = 'lead:' . Throttle::ip();
        if (Throttle::tooMany($bucket, self::RATE_MAX, self::RATE_WINDOW)) {
            $wait = max(1, (int)ceil(Throttle::retryAfter($bucket, self::RATE_WINDOW) / 60));
            http_response_code(429);
            $this->fail("لقد أرسلت عدة طلبات خلال وقت قصير. الرجاء المحاولة بعد {$wait} دقيقة أو الاتصال بنا مباشرة.");
        }
        Throttle::hit($bucket);

        $productId = (int)($_POST['product_id'] ?? 0);
        $offerId   = (int)($_POST['offer_id'] ?? 0);
        $product = Product::find($productId);
        if (!$product || !$product['status']) {
            $this->fail('المنتج غير متوفر');
        }
        $offer = Product::findOffer($offerId, $productId);
        if (!$offer) $this->fail('الرجاء اختيار عرض صحيح');

        $fullname = clean_string($_POST['fullname'] ?? '', 160);
        $phone    = clean_phone($_POST['phone'] ?? '');
        $city     = clean_string($_POST['city'] ?? '', 120);
        $address  = clean_string($_POST['address'] ?? '', 255);
        $notes    = clean_string($_POST['notes'] ?? '', 500);

        if (mb_strlen($fullname) < 3) $this->fail('الرجاء إدخال الاسم الكامل');
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) $this->fail('رقم الهاتف غير صحيح');
        if (mb_strlen($address) < 3)  $this->fail('الرجاء إدخال العنوان');

        // Build per-unit options based on product option groups
        $groups = Product::optionGroups($productId);
        $items = [];
        $qty = max(1, (int)$offer['quantity']);
        for ($i = 1; $i <= $qty; $i++) {
            $unit = [];
            foreach ($groups as $g) {
                // New field naming: opt_{offerId}_{group}_{idx}; fallback to legacy opt_{group}_{idx}
                $field    = "opt_{$offerId}_{$g['name']}_{$i}";
                $fallback = "opt_{$g['name']}_{$i}";
                $val = isset($_POST[$field]) ? clean_string($_POST[$field], 160)
                     : (isset($_POST[$fallback]) ? clean_string($_POST[$fallback], 160) : '');
                if ($g['is_required'] && (int)$offer['requires_options'] === 1 && $val === '') {
                    $this->fail("الرجاء اختيار {$g['label']} للوحدة رقم {$i}");
                }
                if ($val !== '') $unit[$g['name']] = $val;
            }
            $items[] = $unit;
        }

        // Server recomputes price from offer (never trust client)
        $totalPrice = (float)$offer['total_price'];
        $offerLabel = $offer['label'];

        $data = [
            ':product_id'    => $productId,
            ':product_slug'  => $product['slug'],
            ':offer_id'      => (int)$offer['id'],
            ':offer_label'   => $offerLabel,
            ':quantity'      => $qty,
            ':total_price'   => $totalPrice,
            ':fullname'      => $fullname,
            ':phone'         => $phone,
            ':city'          => $city,
            ':address'       => $address,
            ':notes'         => $notes,
            ':source'        => detect_source(),
            // Which A/B variant produced this order, so the test can be read
            // against revenue rather than clicks.
            ':ab_variant'    => Experiment::variantForOrder($product),
            ':utm_source'    => clean_string($_POST['utm_source'] ?? '', 120) ?: null,
            ':utm_medium'    => clean_string($_POST['utm_medium'] ?? '', 120) ?: null,
            ':utm_campaign'  => clean_string($_POST['utm_campaign'] ?? '', 120) ?: null,
            ':fbclid'        => clean_string($_POST['fbclid'] ?? '', 255) ?: null,
            ':ttclid'        => clean_string($_POST['ttclid'] ?? '', 255) ?: null,
            ':gclid'         => clean_string($_POST['gclid'] ?? '', 255) ?: null,
            ':ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
        ];

        try {
            $leadId = Lead::create($data, $items);
            // Optional SheetDB sync (server-side)
            $lead = Lead::find($leadId);
            $leadItems = Lead::items($leadId);
            try { Lead::syncToSheetDB($lead, $leadItems); }
            catch (Throwable $e) { Log::exception('SheetDB sync failed', $e, ['lead_id' => $leadId]); }

            // Server-side conversion, carrying the same event id the browser
            // pixel will use — so the platforms count one sale, not two, and
            // the sale is still reported when the browser pixel is blocked.
            try { PixelServer::reportPurchase($lead, $product); }
            catch (Throwable $e) { Log::exception('Conversions API report failed', $e, ['lead_id' => $leadId]); }

            // This shopper did finish, so their draft is no longer a call-back.
            Draft::markConverted($productId, $phone);

            // Claims the order for this browser session — thankYou() reveals the
            // purchase payload only to the session that placed it.
            $_SESSION['last_lead_id'] = $leadId;
            unset($_SESSION['purchase_fired']);

            if ($this->wantsJson()) {
                // The conversion payload comes back with the response so the
                // pixel fires on a page that is still open. Firing it during a
                // redirect is how Purchase events get lost on slow mobile.
                $_SESSION['purchase_fired'] = [$leadId => true];
                json_response([
                    'ok'       => true,
                    'order_id' => $leadId,
                    'redirect' => base_url('thank-you?o=' . $leadId),
                    'purchase' => [
                        'id'       => $product['slug'],
                        'name'     => $product['title'],
                        'value'    => $totalPrice,
                        'quantity' => $qty,
                        'currency' => 'MAD',
                        'event_id' => 'purchase.' . $leadId,
                        'phone'    => $phone,
                    ],
                ]);
            }

            redirect(base_url('thank-you?o=' . $leadId));
        } catch (Throwable $e) {
            // The one failure that costs a sale. It must never be silent.
            Log::exception('Order could not be saved', $e, [
                'product_id' => $productId, 'offer_id' => $offerId, 'phone' => $phone,
            ]);
            $this->fail('حدث خطأ أثناء حفظ الطلب، الرجاء المحاولة مرة أخرى');
        }
    }

    /** True when the browser asked for JSON rather than a page. */
    private function wantsJson(): bool {
        return ($_POST['ajax'] ?? '') === '1'
            || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private function fail(string $msg): void {
        // Keep whatever status was already set (429 for a rate limit); default
        // to 422 for a plain validation failure.
        $code = http_response_code();
        if (!is_int($code) || $code < 400) $code = 422;

        if ($this->wantsJson()) {
            json_response(['ok' => false, 'error' => $msg], $code);
        }
        http_response_code($code);
        render('product-error', ['title' => 'خطأ', 'message' => $msg, 'noindex' => true], 'public');
        exit;
    }
}
