<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

require_method('POST');

$identity = strtolower(clean($_POST['identity'] ?? ''));
$password = (string)($_POST['password'] ?? '');
if ($identity === '' || $password === '') {
    respond(['success' => false, 'message' => 'Enter your username/email and password.'], 422);
}

try {
    // Brute-force protection: 10 failures / 15 min per IP+identity (stored in Redis)
    $rateKey = 'loginfail:' . hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $identity);
    if ((int)redis()->get($rateKey) >= 10) {
        respond(['success' => false, 'message' => 'Too many attempts. Try again in 15 minutes.'], 429);
    }

    $stmt = mysql()->prepare('SELECT id, username, password_hash FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        redis()->incr($rateKey);
        redis()->expire($rateKey, 900);
        respond(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }

    redis()->del([$rateKey]);
    $token = create_session((int)$user['id']);
    respond(['success' => true, 'message' => 'Login successful.', 'token' => $token, 'username' => $user['username']]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}
