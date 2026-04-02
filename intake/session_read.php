<?php
// session_read.php
// The Sharper ONE™ — Session Reader
// Deploy to: /var/www/[site-id]/public_html/intake/session_read.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sharper.one');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Validate token ──
$token = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['s'] ?? '');
if (!$token || strpos($token, 'ses_') !== 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session token']);
    exit;
}

$sessionFile = __DIR__ . '/sessions/' . $token . '.json';
if (!file_exists($sessionFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Session not found']);
    exit;
}

$session = json_decode(file_get_contents($sessionFile), true);
if (!$session) {
    http_response_code(500);
    echo json_encode(['error' => 'Corrupt session']);
    exit;
}

// ── Check expiry ──
if (time() > $session['expiresAt']) {
    // Clean up expired session and photo
    unlink($sessionFile);
    if ($session['photo']) {
        $photoFull = __DIR__ . $session['photo'];
        if (file_exists($photoFull)) unlink($photoFull);
    }
    http_response_code(410);
    echo json_encode(['error' => 'Session expired']);
    exit;
}

// ── Opportunistic cleanup of other expired sessions ──
$sessionsDir = __DIR__ . '/sessions/';
$files = glob($sessionsDir . 'ses_*.json');
if ($files) {
    foreach ($files as $f) {
        if ($f === $sessionFile) continue;
        $s = json_decode(file_get_contents($f), true);
        if ($s && time() > ($s['expiresAt'] ?? 0)) {
            unlink($f);
            if (!empty($s['photo'])) {
                $p = __DIR__ . $s['photo'];
                if (file_exists($p)) unlink($p);
            }
        }
    }
}

// ── Return full session ──
echo json_encode($session);
