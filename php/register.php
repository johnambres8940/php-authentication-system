<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

require_method('POST');

$username = clean($_POST['username'] ?? '');
$email    = strtolower(clean($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm  = (string)($_POST['confirm'] ?? '');

$errors = [];
if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) $errors[] = 'Username must be 3-30 letters, numbers or underscores.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email address.';
if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'Password must be 8-72 characters.';
if ($password !== $confirm) $errors[] = 'Passwords do not match.';
if ($errors) respond(['success' => false, 'message' => implode(' ', $errors)], 422);

try {
    $pdo = mysql();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(username) = LOWER(?) OR email = ? LIMIT 1');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) respond(['success' => false, 'message' => 'Username or email already registered.'], 409);

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();

    profiles()->insertOne([
        'user_id'    => $userId,
        'name'       => '',
        'age'        => null,
        'dob'        => '',
        'contact'    => '',
        'bio'        => '',
        'interests'  => [],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ]);

    respond(['success' => true, 'message' => 'Registration successful. Please log in.'], 201);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}