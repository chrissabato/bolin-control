<?php
/**
 * Bolin EXU-230NX – PTZ Proxy
 *
 * Forwards requests to the camera and injects the Cookie auth header.
 * Called by index.html as:  proxy.php?path=/apiv2/<endpoint>
 *
 * Requires: PHP with cURL extension (standard on most LAMP stacks).
 */

header('Content-Type: application/json');

// ── Validate path ────────────────────────────────────────────────────────────
$path  = $_GET['path']  ?? '';
$query = $_GET['query'] ?? '';   // optional query string for CGI GET requests

$isApi = preg_match('#^/apiv2/[a-zA-Z]+$#', $path);
$isCgi = preg_match('#^/cgi-bin/[a-zA-Z0-9._/-]+\.cgi$#', $path);

if (!$isApi && !$isCgi) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing path parameter']);
    exit;
}

// Sanitise CGI query string: allow only pelco-style key=value pairs
if ($query && !preg_match('#^[a-zA-Z0-9._]+=\d+(&[a-zA-Z0-9._]+=\d+)*$#', $query)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid query parameter']);
    exit;
}

// ── Camera config from custom request headers ────────────────────────────────
$camHost  = $_SERVER['HTTP_X_CAM_HOST']     ?? '';
$camPort  = (int)($_SERVER['HTTP_X_CAM_PORT'] ?? 80);
$token    = $_SERVER['HTTP_X_CAM_TOKEN']    ?? '';
$username = $_SERVER['HTTP_X_CAM_USERNAME'] ?? 'admin';
$camPass  = $_SERVER['HTTP_X_CAM_PASS']     ?? '';

if (!$camHost) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing X-Cam-Host header']);
    exit;
}

// Basic input sanitisation
$camHost = preg_replace('/[^a-zA-Z0-9.\-]/', '', $camHost);
$camPort = max(1, min(65535, $camPort));

// ── Forward request ──────────────────────────────────────────────────────────
$targetUrl = "http://{$camHost}:{$camPort}{$path}";
if ($query) { $targetUrl .= '?' . $query; }
$body      = file_get_contents('php://input');

$curlHeaders = ['Content-Type: application/json'];
if ($token) {
    $safeToken    = preg_replace('/[^a-zA-Z0-9]/', '', $token);
    $safeUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '', $username);
    $curlHeaders[] = "Cookie: Username={$safeUsername};Token={$safeToken}";
}

$ch = curl_init($targetUrl);
if ($isCgi) {
    // CGI endpoints use plain GET with HTTP Basic auth
    $safeUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $username);
    $safePass = preg_replace('/[^a-zA-Z0-9!@#$%^&*()_+\-=\[\]{};\':",.<>?]/', '', $camPass);
    $opts = [
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];
    if ($safePass) {
        $opts[CURLOPT_USERPWD]   = "{$safeUser}:{$safePass}";
        $opts[CURLOPT_HTTPAUTH]  = CURLAUTH_BASIC;
    }
    curl_setopt_array($ch, $opts);
} else {
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Camera unreachable: ' . $curlError]);
    exit;
}

http_response_code($httpCode ?: 502);
echo $response;
