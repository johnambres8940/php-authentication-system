<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // Logout: POST action=logout
    if ($method === 'POST' && ($_POST['action'] ?? '') === 'logout') {
        $token = request_token();
        if ($token !== '') redis()->del(["session:$token"]);
        respond(['success' => true, 'message' => 'Logged out.']);
    }

    $userId = require_auth();

    if ($method === 'GET') {
        $stmt = mysql()->prepare('SELECT username, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) respond(['success' => false, 'message' => 'User not found'], 404);

        $doc = profiles()->findOne(['user_id' => $userId]);
        $profile = [
            'name'      => $doc['name']    ?? '',
            'age'       => $doc['age']     ?? null,
            'dob'       => $doc['dob']     ?? '',
            'contact'   => $doc['contact'] ?? '',
            'bio'       => $doc['bio']     ?? '',
            'interests' => isset($doc['interests']) ? iterator_to_array($doc['interests']) : [],
        ];
        respond(['success' => true, 'user' => $user, 'profile' => $profile]);
    }

    if ($method === 'POST') {
        $name    = clean($_POST['name'] ?? '');
        $age     = $_POST['age'] ?? '';
        $dob     = clean($_POST['dob'] ?? '');
        $contact = clean($_POST['contact'] ?? '');
        $bio     = clean($_POST['bio'] ?? '');
        $interestsRaw = clean($_POST['interests'] ?? '');

        $errors = [];
        if (mb_strlen($name) > 80) $errors[] = 'Name too long.';
        if ($age !== '' && (!ctype_digit((string)$age) || (int)$age < 1 || (int)$age > 120)) $errors[] = 'Age must be 1-120.';
        if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {$errors[] = 'Invalid date of birth.';}
        if ($contact !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $contact)) $errors[] = 'Invalid contact number.';
        if (mb_strlen($bio) > 500) $errors[] = 'Bio max 500 characters.';
        if ($errors) respond(['success' => false, 'message' => implode(' ', $errors)], 422);

        $interests = array_values(array_unique(array_filter(array_map(
            fn($s) => mb_substr(trim($s), 0, 30), explode(',', $interestsRaw)
        ))));
        $interests = array_slice($interests, 0, 15);

        profiles()->updateOne(
            ['user_id' => $userId],
            ['$set' => [
                'name' => $name, 'age' => $age === '' ? null : (int)$age, 'dob' => $dob,
                'contact' => $contact, 'bio' => $bio, 'interests' => $interests,
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ]],
            ['upsert' => true]
        );
        respond(['success' => true, 'message' => 'Profile updated.']);
    }

    respond(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}
