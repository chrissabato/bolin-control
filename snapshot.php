<?php
/**
 * Bolin EXU-230NX – Snapshot Handler
 *
 * GET  snapshot.php?preset=N  — fetch JPEG from camera, save to snapshots/preset_N.jpg
 * GET  snapshot.php?preset=N&delete=1 — delete saved snapshot
 */

header('Content-Type: application/json');

$preset = (int)($_GET['preset'] ?? 0);
$delete = !empty($_GET['delete']);

if ($preset < 1 || $preset > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid preset number']);
    exit;
}

$snapDir  = __DIR__ . '/snapshots';
$filename = "preset_{$preset}.jpg";
$filepath = $snapDir . '/' . $filename;

// ── Delete ────────────────────────────────────────────────────────────────────
if ($delete) {
    if (file_exists($filepath)) unlink($filepath);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Save ──────────────────────────────────────────────────────────────────────
$camHost  = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_SERVER['HTTP_X_CAM_HOST']     ?? '');
$camPort  = max(1, min(65535, (int)($_SERVER['HTTP_X_CAM_PORT'] ?? 80)));
$username = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SERVER['HTTP_X_CAM_USERNAME'] ?? 'admin');
$camPass  = preg_replace('/[^a-zA-Z0-9!@#$%^&*()_+\-=\[\]{};\':",.<>?]/', '', $_SERVER['HTTP_X_CAM_PASS'] ?? '');

if (!$camHost) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing X-Cam-Host header']);
    exit;
}

// Try endpoints in order until one returns a JPEG
$endpoints = [
    "http://{$camHost}:{$camPort}/cgi-bin/set.cgi?pelco.controller.snapshot=1",
    "http://{$camHost}:{$camPort}/jpg/image.jpg",
    "http://{$camHost}:{$camPort}/snap.jpg",
];

$image = false; $httpCode = 0; $ct = ''; $curlErr = '';

foreach ($endpoints as $url) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_HTTPGET        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];
    if ($camPass) {
        $opts[CURLOPT_USERPWD]  = "{$username}:{$camPass}";
        $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }
    curl_setopt_array($ch, $opts);

    $image    = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct       = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($image !== false && $httpCode === 200 && strpos($ct, 'image/') === 0) break;
    $image = false; // try next
}

if ($image === false) {
    http_response_code(502);
    echo json_encode(['error' => $curlErr ?: "No snapshot endpoint returned an image (last HTTP {$httpCode}, {$ct})"]);
    exit;
}

if (!is_dir($snapDir)) mkdir($snapDir, 0755, true);
file_put_contents($filepath, $image);

echo json_encode(['url' => "snapshots/{$filename}?t=" . time()]);
