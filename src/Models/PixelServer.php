<?php
/**
 * PixelServer — server-side conversion reporting (Meta CAPI + TikTok Events API).
 *
 * Why this exists: roughly a fifth of Moroccan mobile traffic blocks
 * connect.facebook.net and analytics.tiktok.com, so the browser pixels
 * under-report by that much and both platforms bid against incomplete data.
 * Sending the same conversion from the server closes that gap.
 *
 * Deduplication is what makes it safe to send twice. Every browser event
 * already carries an event id; the server sends the SAME id for the same order,
 * and each platform then counts one conversion no matter how many copies arrive.
 * The ids are deterministic (`purchase.{leadId}`), not random, precisely so the
 * two channels agree without having to coordinate.
 *
 * Identity matching is hashed here, never in the browser: phone and name are
 * normalised then SHA-256'd before they leave this process. Neither platform
 * receives a readable customer detail.
 *
 * Failure is always silent to the shopper. A conversion that did not report is
 * a measurement problem; an order that failed to save is a business one, so
 * this never throws into the order flow.
 */
class PixelServer {
    private const FB_API_VERSION = 'v21.0';
    private const TIMEOUT        = 6;

    /**
     * Report a completed order to whichever pixels that landing page uses.
     *
     * @param array $lead    the leads row
     * @param array $product the products row (carries the pixel assignment)
     */
    public static function reportPurchase(array $lead, array $product): void {
        if (settings_get('capi_enabled', '0') !== '1') return;

        require_once __DIR__ . '/Pixel.php';
        $pixels = Pixel::resolve($product);

        $eventId = 'purchase.' . (int)$lead['id'];
        $user    = self::userData($lead);

        if (!empty($pixels['facebook']['access_token'])) {
            self::sendMeta($pixels['facebook'], $lead, $product, $eventId, $user);
        }
        if (!empty($pixels['tiktok']['access_token'])) {
            self::sendTikTok($pixels['tiktok'], $lead, $product, $eventId, $user);
        }
    }

    // ── identity ───────────────────────────────────────────────────────────

    /**
     * Normalise then hash the identifiers both platforms accept.
     *
     * Normalisation matters more than it looks: "0612345678" and "+212612345678"
     * hash differently, so an un-normalised phone matches nobody and the whole
     * exercise reports zero attribution.
     */
    private static function userData(array $lead): array {
        $out = [];

        $phone = self::normalisePhone((string)($lead['phone'] ?? ''));
        if ($phone !== '') $out['ph'] = hash('sha256', $phone);

        $name = trim(mb_strtolower((string)($lead['fullname'] ?? ''), 'UTF-8'));
        if ($name !== '') {
            $parts = preg_split('/\s+/u', $name) ?: [];
            if (!empty($parts[0])) $out['fn'] = hash('sha256', $parts[0]);
            if (count($parts) > 1) $out['ln'] = hash('sha256', (string)end($parts));
        }

        $city = trim(mb_strtolower((string)($lead['city'] ?? ''), 'UTF-8'));
        if ($city !== '') $out['ct'] = hash('sha256', preg_replace('/\s+/u', '', $city) ?? $city);

        $out['country'] = hash('sha256', 'ma');
        return $out;
    }

    /**
     * Moroccan numbers to E.164 without the '+', which is what both platforms
     * hash against: 0612345678 → 212612345678.
     */
    public static function normalisePhone(string $raw): string {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') return '';

        if (str_starts_with($digits, '00'))  $digits = substr($digits, 2);
        if (str_starts_with($digits, '212')) return $digits;
        if (str_starts_with($digits, '0'))   return '212' . substr($digits, 1);

        // A bare local number with the leading zero omitted (612345678).
        if (strlen($digits) === 9 && in_array($digits[0], ['6', '7'], true)) {
            return '212' . $digits;
        }
        return $digits;
    }

    // ── Meta Conversions API ───────────────────────────────────────────────

    private static function sendMeta(array $pixel, array $lead, array $product, string $eventId, array $user): void {
        // fbc/fbp are the strongest match signals Meta has. fbc is derivable
        // from the click id we already store on the lead.
        if (!empty($lead['fbclid'])) {
            $user['fbc'] = 'fb.1.' . (strtotime((string)$lead['created_at']) * 1000) . '.' . $lead['fbclid'];
        }
        if (!empty($lead['ip']))         $user['client_ip_address']  = $lead['ip'];
        if (!empty($lead['user_agent'])) $user['client_user_agent']  = $lead['user_agent'];

        $payload = [
            'data' => [[
                'event_name'       => 'Purchase',
                'event_time'       => strtotime((string)($lead['created_at'] ?? 'now')),
                'event_id'         => $eventId,       // dedupes against the browser
                'action_source'    => 'website',
                'event_source_url' => self::pageUrl($lead),
                'user_data'        => $user,
                'custom_data'      => [
                    'currency'      => 'MAD',
                    'value'         => (float)$lead['total_price'],
                    'content_type'  => 'product',
                    'content_ids'   => [(string)$lead['product_slug']],
                    'content_name'  => (string)($product['title'] ?? $lead['product_slug']),
                    'num_items'     => (int)$lead['quantity'],
                    'order_id'      => (string)$lead['id'],
                ],
            ]],
        ];
        if (!empty($pixel['test_event_code'])) {
            $payload['test_event_code'] = $pixel['test_event_code'];
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/events?access_token=%s',
            self::FB_API_VERSION, rawurlencode((string)$pixel['pixel_id']),
            rawurlencode((string)$pixel['access_token']));

        self::post($url, $payload, [], 'Meta CAPI');
    }

    // ── TikTok Events API ──────────────────────────────────────────────────

    private static function sendTikTok(array $pixel, array $lead, array $product, string $eventId, array $user): void {
        // TikTok names the same hashed fields differently, and wants ttclid
        // passed through untouched rather than hashed.
        $ttUser = [];
        if (isset($user['ph'])) $ttUser['phone'] = $user['ph'];
        if (isset($user['fn'])) $ttUser['first_name'] = $user['fn'];
        if (isset($user['ln'])) $ttUser['last_name']  = $user['ln'];
        if (!empty($lead['ttclid']))     $ttUser['ttclid'] = $lead['ttclid'];
        if (!empty($lead['ip']))         $ttUser['ip'] = $lead['ip'];
        if (!empty($lead['user_agent'])) $ttUser['user_agent'] = $lead['user_agent'];

        $payload = [
            'event_source'    => 'web',
            'event_source_id' => (string)$pixel['pixel_id'],
            'data' => [[
                'event'      => 'CompletePayment',
                'event_time' => strtotime((string)($lead['created_at'] ?? 'now')),
                'event_id'   => $eventId,
                'user'       => $ttUser,
                'page'       => ['url' => self::pageUrl($lead)],
                'properties' => [
                    'currency'    => 'MAD',
                    'value'       => (float)$lead['total_price'],
                    'order_id'    => (string)$lead['id'],
                    'contents'    => [[
                        'content_id'   => (string)$lead['product_slug'],
                        'content_type' => 'product',
                        'content_name' => (string)($product['title'] ?? $lead['product_slug']),
                        'quantity'     => (int)$lead['quantity'],
                        'price'        => (float)$lead['total_price'],
                    ]],
                ],
            ]],
        ];
        if (!empty($pixel['test_event_code'])) {
            $payload['test_event_code'] = $pixel['test_event_code'];
        }

        self::post('https://business-api.tiktok.com/open_api/v1.3/event/track/', $payload, [
            'Access-Token: ' . $pixel['access_token'],
        ], 'TikTok Events API');
    }

    // ── transport ──────────────────────────────────────────────────────────

    private static function pageUrl(array $lead): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'tujjar.store';
        $scheme = function_exists('request_is_https') && request_is_https() ? 'https' : 'http';
        return $scheme . '://' . $host . base_url((string)$lead['product_slug']);
    }

    /**
     * One HTTP POST, with every failure mode logged and none of them fatal.
     *
     * @return bool whether the platform accepted the event
     */
    private static function post(string $url, array $payload, array $headers, string $label): bool {
        if (!function_exists('curl_init')) {
            error_log("$label skipped: ext-curl is not available");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            error_log("$label transport error: $err");
            return false;
        }
        if ($code < 200 || $code >= 300) {
            error_log("$label HTTP $code: " . substr((string)$body, 0, 400));
            return false;
        }

        // TikTok answers 200 with a non-zero `code` on rejection, so the status
        // line alone is not proof the event was accepted.
        $decoded = json_decode((string)$body, true);
        if (is_array($decoded) && isset($decoded['code']) && (int)$decoded['code'] !== 0) {
            error_log("$label rejected: " . substr((string)$body, 0, 400));
            return false;
        }

        return true;
    }
}
