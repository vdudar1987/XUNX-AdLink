<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/response.php';

$input = read_json();
$email = trim((string) ($input['email'] ?? ''));

if ($email === '') {
    json_response(['error' => 'Укажите email.'], 400);
}

$conn = db();
$stmt = $conn->prepare('UPDATE users SET status = ? WHERE email = ?');
$status = 'active';
$stmt->bind_param('ss', $status, $email);
$stmt->execute();
$stmt->close();

json_response(['success' => true]);
