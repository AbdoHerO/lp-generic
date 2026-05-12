<?php
// Temporary diagnostic — DELETE this file after use
// Access: https://casalux.zincolo.com/_diag.php?key=diag2026
if (($_GET['key'] ?? '') !== 'diag2026') { http_response_code(403); exit('forbidden'); }

chdir(__DIR__);
$cfg = require __DIR__ . '/config/config.php';

// DB
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};port={$cfg['db']['port']};dbname={$cfg['db']['name']};charset={$cfg['db']['charset']}",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ DB connected: " . $cfg['db']['name'] . "\n";
} catch (Throwable $e) {
    die("❌ DB error: " . $e->getMessage() . "\n");
}

// Settings
$rows = $pdo->query("SELECT k,v FROM settings WHERE k IN ('sheetdb_enabled','sheetdb_url','sheetdb_token')")->fetchAll(PDO::FETCH_KEY_PAIR);
echo "\n--- SheetDB Settings ---\n";
echo "sheetdb_enabled = [" . ($rows['sheetdb_enabled'] ?? 'MISSING') . "]\n";
echo "sheetdb_url     = [" . ($rows['sheetdb_url']     ?? 'MISSING') . "]\n";
echo "sheetdb_token   = [" . ($rows['sheetdb_token']   ?? 'MISSING') . "]\n";

if (($rows['sheetdb_enabled'] ?? '') !== '1') {
    echo "\n⚠️  sheetdb_enabled is not '1' — fixing now...\n";
    $pdo->exec("UPDATE settings SET v='1' WHERE k='sheetdb_enabled'");
    echo "✅ Fixed.\n";
}
if (empty($rows['sheetdb_token'])) {
    echo "\n⚠️  token is empty — fixing now...\n";
    $pdo->exec("UPDATE settings SET v='c4pbl6r3lwr8r0bossphcnv02tpic1dqlp40ifla' WHERE k='sheetdb_token'");
    echo "✅ Fixed.\n";
}

// Test actual POST to SheetDB
$url   = $rows['sheetdb_url']   ?? '';
$token = $rows['sheetdb_token'] ?? '';
if (!$token) $token = 'c4pbl6r3lwr8r0bossphcnv02tpic1dqlp40ifla';

$testPayload = ['data' => [[
    'date'         => date('Y-m-d H:i:s'),
    'destinataire' => 'TEST-DIAG',
    'telephone'    => '0600000000',
    'ville'        => '-',
    'adresse'      => 'test address',
    'prix'         => '249',
    'produit'      => 'Pant-TEST',
    'id_intern'    => '',
    'change'       => '0',
    'ouvrir_colis' => '1',
    'essayage'     => '1',
    'quantity'     => '1',
    'color'        => 'test-color',
    'size'         => 'test-size',
    'createdAt'    => date('d/m/Y') . ' à ' . date('H:i:s'),
    'montant'      => '249',
    'status'       => 'en cours',
    'trafic'       => 'Organique',
]]];

echo "\n--- SheetDB POST Test ---\n";
echo "URL: $url\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($testPayload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo "❌ cURL error: $curlErr\n";
} elseif ($httpCode >= 200 && $httpCode < 300) {
    echo "✅ SheetDB responded HTTP $httpCode\n";
    echo "Response: $resp\n";
    echo "\n⚠️  A test row was inserted — delete it manually from the sheet.\n";
} else {
    echo "❌ SheetDB HTTP $httpCode\n";
    echo "Response: $resp\n";
}
