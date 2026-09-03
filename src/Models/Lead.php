<?php
class Lead {
    public static function create(array $data, array $items): int {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO leads
              (product_id, product_slug, offer_id, offer_label, quantity, total_price,
               fullname, phone, city, address, notes, status, source, ab_variant,
               utm_source, utm_medium, utm_campaign, fbclid, ttclid, gclid, ip, user_agent)
              VALUES
              (:product_id, :product_slug, :offer_id, :offer_label, :quantity, :total_price,
               :fullname, :phone, :city, :address, :notes, 'new', :source, :ab_variant,
               :utm_source, :utm_medium, :utm_campaign, :fbclid, :ttclid, :gclid, :ip, :user_agent)";
            $st = $pdo->prepare($sql);
            $st->execute($data);
            $leadId = (int)$pdo->lastInsertId();

            $stItem = $pdo->prepare("INSERT INTO lead_items (lead_id, unit_index, options_json) VALUES (:lead_id,:idx,:opts)");
            foreach ($items as $i => $opts) {
                $stItem->execute([
                    ':lead_id' => $leadId,
                    ':idx'     => $i + 1,
                    ':opts'    => json_encode($opts, JSON_UNESCAPED_UNICODE),
                ]);
            }
            $pdo->commit();
            return $leadId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function find(int $id): ?array {
        $st = db()->prepare("SELECT * FROM leads WHERE id=:i LIMIT 1");
        $st->execute([':i' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function items(int $leadId): array {
        $st = db()->prepare("SELECT * FROM lead_items WHERE lead_id=:l ORDER BY unit_index");
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    }

    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array {
        $where = "1=1";
        $params = [];
        if (!empty($filters['phone'])) {
            $where .= " AND l.phone LIKE :phone"; $params[':phone'] = '%' . $filters['phone'] . '%';
        }
        if (!empty($filters['status'])) {
            $where .= " AND l.status = :status"; $params[':status'] = $filters['status'];
        }
        if (!empty($filters['product_id'])) {
            $where .= " AND l.product_id = :pid"; $params[':pid'] = (int)$filters['product_id'];
        }
        if (!empty($filters['source'])) {
            $where .= " AND l.source = :src"; $params[':src'] = $filters['source'];
        }
        if (!empty($filters['from'])) {
            $where .= " AND l.created_at >= :from"; $params[':from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= " AND l.created_at <= :to";   $params[':to']   = $filters['to']   . ' 23:59:59';
        }
        $countSt = db()->prepare("SELECT COUNT(*) FROM leads l LEFT JOIN products p ON p.id = l.product_id WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT l.*, p.title AS product_title FROM leads l
                LEFT JOIN products p ON p.id = l.product_id
                WHERE $where ORDER BY l.id DESC LIMIT $perPage OFFSET $offset";
        $st = db()->prepare($sql);
        $st->execute($params);
        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function updateStatus(int $leadId, string $status, ?string $note, ?int $adminId): void {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $cur = self::find($leadId);
            if (!$cur) throw new RuntimeException('Lead not found');
            $st = $pdo->prepare("UPDATE leads SET status=:s WHERE id=:i");
            $st->execute([':s' => $status, ':i' => $leadId]);
            $log = $pdo->prepare("INSERT INTO lead_status_logs (lead_id, from_status, to_status, note, admin_id) VALUES (:l,:f,:t,:n,:a)");
            $log->execute([
                ':l' => $leadId, ':f' => $cur['status'], ':t' => $status, ':n' => $note, ':a' => $adminId
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Other orders from the same phone in the last N days.
     *
     * A repeat number is either a returning customer worth prioritising or the
     * same person ordering twice by accident — both are worth seeing before the
     * confirmation call, and neither is visible today.
     */
    public static function duplicatesFor(string $phone, int $excludeLeadId = 0, int $days = 30): array {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 8) return [];

        $st = db()->prepare("SELECT l.id, l.fullname, l.status, l.total_price, l.created_at,
                                    p.title AS product_title
                             FROM leads l LEFT JOIN products p ON p.id = l.product_id
                             WHERE l.phone = :phone AND l.id <> :id
                               AND l.created_at >= (NOW() - INTERVAL :days DAY)
                             ORDER BY l.id DESC LIMIT 10");
        $st->bindValue(':phone', $phone);
        $st->bindValue(':id', $excludeLeadId, PDO::PARAM_INT);
        $st->bindValue(':days', $days, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** Phones that appear on more than one order in the window, for the list badge. */
    public static function duplicatePhones(array $leadRows, int $days = 30): array {
        $phones = array_values(array_unique(array_filter(array_column($leadRows, 'phone'))));
        if (!$phones) return [];

        $in = implode(',', array_fill(0, count($phones), '?'));
        $st = db()->prepare("SELECT phone, COUNT(*) AS n FROM leads
                             WHERE phone IN ($in) AND created_at >= (NOW() - INTERVAL ? DAY)
                             GROUP BY phone HAVING n > 1");
        $st->execute(array_merge($phones, [$days]));

        $out = [];
        foreach ($st->fetchAll() as $r) $out[$r['phone']] = (int)$r['n'];
        return $out;
    }

    public static function statusLogs(int $leadId): array {
        $st = db()->prepare("SELECT * FROM lead_status_logs WHERE lead_id=:l ORDER BY id DESC");
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    }

    public static function dashboardStats(): array {
        $pdo = db();
        $today = date('Y-m-d') . ' 00:00:00';
        $stToday = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE created_at >= :d");
        $stToday->execute([':d' => $today]);
        return [
            'total_leads'     => (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
            'new_today'       => (int)$stToday->fetchColumn(),
            'active_products' => (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status=1")->fetchColumn(),
            'confirmed'       => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status='confirmed'")->fetchColumn(),
            'cancelled'       => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status='cancelled'")->fetchColumn(),
        ];
    }

    public static function syncToSheetDB(array $lead, array $items): void {
        if (settings_get('sheetdb_enabled') !== '1') return;
        $url = settings_get('sheetdb_url');
        if (!$url) return;
        $token = settings_get('sheetdb_token');

        // Extract colors and sizes from items (matching old api2.js column structure)
        $colors = [];
        $sizes  = [];
        foreach ($items as $it) {
            $opts = json_decode($it['options_json'] ?? '{}', true) ?: [];
            if (!empty($opts['color'])) $colors[] = $opts['color'];
            if (!empty($opts['size']))  $sizes[]  = $opts['size'];
        }
        $colorsString = implode(', ', $colors);
        $sizesString  = implode(', ', $sizes);

        // Detect traffic source (matches old hasFbclidParameter logic)
        $trafic = 'Organique';
        if (!empty($lead['fbclid'])) $trafic = 'Facebook';
        elseif (!empty($lead['ttclid'])) $trafic = 'Tiktok';
        elseif (!empty($lead['gclid'])) $trafic = 'Google Ads';
        elseif (!empty($lead['utm_source'])) $trafic = $lead['utm_source'];

        // Format date like the old JS: "12/05/2026 à 12:22:59"
        $dt = new DateTime($lead['created_at'] ?? 'now');
        $createdAt = $dt->format('d/m/Y') . ' à ' . $dt->format('H:i:s');

        $payload = ['data' => [[
            // ── Columns matching the existing Google Sheet ──────────────
            'date'         => $lead['created_at'],
            'destinataire' => $lead['fullname'],
            'telephone'    => (string)$lead['phone'],
            'ville'        => $lead['city'] ?: '-',
            'adresse'      => $lead['address'],
            'prix'         => (string)$lead['total_price'],
            'produit'      => 'Pant-' . $colorsString . '-' . $sizesString,
            'id_intern'    => '',
            'change'       => '0',
            'ouvrir_colis' => '1',
            'essayage'     => '1',
            'quantity'     => (string)$lead['quantity'],
            'color'        => $colorsString,
            'size'         => $sizesString,
            'createdAt'    => $createdAt,
            'montant'      => (string)$lead['total_price'],
            'status'       => 'en cours',
            'trafic'       => $trafic,
        ]]];

        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // Log failure silently (won't break the order flow)
        if ($httpCode < 200 || $httpCode >= 300) {
            require_once __DIR__ . '/Log.php';
            Log::error('SheetDB rejected a lead', [
                'http'     => $httpCode,
                'response' => substr((string)$resp, 0, 300),
                'lead_id'  => $lead['id'] ?? null,
            ]);
        }
    }
}
