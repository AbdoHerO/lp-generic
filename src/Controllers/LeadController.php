<?php
require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Models/Lead.php';

class LeadController {
    public function thankYou(): void {
        render('thank-you', ['title' => 'تم استلام طلبك']);
    }

    public function submit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { not_found(); return; }

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
            try { Lead::syncToSheetDB($lead, $leadItems); } catch (Throwable $e) { /* ignore */ }

            redirect(base_url('thank-you?o=' . $leadId));
        } catch (Throwable $e) {
            $this->fail('حدث خطأ أثناء حفظ الطلب، الرجاء المحاولة مرة أخرى');
        }
    }

    private function fail(string $msg): void {
        http_response_code(422);
        render('product-error', ['title' => 'خطأ', 'message' => $msg], 'public');
        exit;
    }
}
