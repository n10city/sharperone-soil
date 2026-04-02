<?php
// session_update.php
// The Sharper ONE™ — Session Updater (customer completion)
// Deploy to: /var/www/[site-id]/public_html/intake/session_update.php

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
if (!$data || empty($data['token'])) {
    http_response_code(400); echo json_encode(['error' => 'Missing token']); exit;
}

$token = preg_replace('/[^a-zA-Z0-9_]/', '', $data['token']);
$sessionFile = __DIR__ . '/sessions/' . $token . '.json';
if (!file_exists($sessionFile)) {
    http_response_code(404); echo json_encode(['error' => 'Session not found']); exit;
}

$session = json_decode(file_get_contents($sessionFile), true);
if (!$session) {
    http_response_code(500); echo json_encode(['error' => 'Corrupt session']); exit;
}

if (time() > $session['expiresAt']) {
    http_response_code(410); echo json_encode(['error' => 'Session expired']); exit;
}

// ── Update customer-filled fields ──
if (isset($data['lastName'])) {
    $session['lastName'] = strtoupper(preg_replace('/[^a-zA-Z\s\-]/', '', $data['lastName']));
}
if (isset($data['bladeTypes']) && is_array($data['bladeTypes'])) {
    $allowed = ['Kitchen Knife','Straight Razor','Pocket Knife','Field Blade','Garden / Landscape','Other Tool'];
    $session['bladeTypes'] = array_values(array_intersect($data['bladeTypes'], $allowed));
}
if (isset($data['consent']) && in_array($data['consent'], ['text','email','none'])) {
    $session['consent'] = $data['consent'];
}
if (isset($data['email'])) {
    $email = trim($data['email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254) {
        $session['email'] = strtolower($email);
    }
}
if (isset($data['phone'])) {
    $phone = preg_replace('/[^0-9]/', '', $data['phone']);
    if (strlen($phone) === 10) {
        $session['phone'] = $phone;
    }
}
if (isset($data['email'])) {
    $email = trim($data['email']);
    // Basic email validation
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254) {
        $session['email'] = strtolower($email);
    }
}

$session['status']      = 'completed';
$session['completedAt'] = time();

file_put_contents($sessionFile, json_encode($session, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'token' => $token]);
