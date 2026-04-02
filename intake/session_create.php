<?php
// session_create.php
// The Sharper ONE™ — Intake Session Bridge
// Deploy to: /var/www/[site-id]/public_html/intake/session_create.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sharper.one');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit;
}

// ── Validate required fields ──
$required = ['firstName', 'lastInitial', 'bladeCount', 'cardColor', 'cardColorName'];
foreach ($required as $f) {
    if (empty($data[$f])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $f"]);
        exit;
    }
}

// ── Sanitize ──
$firstName     = strtoupper(preg_replace('/[^a-zA-Z\s\-]/', '', $data['firstName']));
$lastInitial   = strtoupper(preg_replace('/[^a-zA-Z]/', '', $data['lastInitial']));
$bladeCount    = max(1, min(99, intval($data['bladeCount'])));
$cardColor     = preg_replace('/[^#a-fA-F0-9]/', '', $data['cardColor']);
$cardColorName = preg_replace('/[^a-zA-Z]/', '', $data['cardColorName']);
date_default_timezone_set('America/Chicago');
$date          = date('M j, Y');
$location      = 'Front of the Farm';

// ── Generate session token ──
$token = 'ses_' . time() . '_' . bin2hex(random_bytes(4));

// ── Handle photo upload ──
$photoPath = null;
if (!empty($data['photo']) && strpos($data['photo'], 'data:image/') === 0) {
    $photoDir = dirname(__DIR__) . '/intake-photos/';
    if (!is_dir($photoDir)) mkdir($photoDir, 0755, true);
    $photoData   = $data['photo'];
    $base64      = substr($photoData, strpos($photoData, ',') + 1);
    $imageData   = base64_decode($base64);
    $photoFile   = $token . '.jpg';
    $photoFull   = $photoDir . $photoFile;
    if ($imageData && file_put_contents($photoFull, $imageData)) {
        $photoPath = '/intake-photos/' . $photoFile;
    }
}

// ── Build session object ──
$session = [
    'token'         => $token,
    'firstName'     => $firstName,
    'lastInitial'   => $lastInitial,
    'bladeCount'    => $bladeCount,
    'cardColor'     => $cardColor,
    'cardColorName' => $cardColorName,
    'date'          => $date,
    'location'      => $location,
    'photo'         => $photoPath,
    'createdAt'     => time(),
    'expiresAt'     => time() + (4 * 60 * 60), // 4 hours
    'status'        => 'active',
    // Customer-filled fields (populated later via session_update.php)
    'lastName'      => null,
    'bladeTypes'    => [],
    'consent'       => null,
    'completedAt'   => null,
];

// ── Write session file ──
$sessionsDir = __DIR__ . '/sessions/';
if (!is_dir($sessionsDir)) mkdir($sessionsDir, 0755, true);
$sessionFile = $sessionsDir . $token . '.json';
if (!file_put_contents($sessionFile, json_encode($session, JSON_PRETTY_PRINT))) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write session']);
    exit;
}

// ── Return token ──
echo json_encode([
    'success' => true,
    'token'   => $token,
    'url'     => 'https://sharper.one/intake-c/?s=' . $token,
]);
