<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/response.php';

$input = read_json();
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['error' => 'Введите email и новый пароль.'], 400);
}

if (mb_strlen($password) < 6) {
    json_response(['error' => 'Пароль должен быть не короче 6 символов.'], 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$conn = db();
$stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
$stmt->bind_param('ss', $hash, $email);
$stmt->execute();
$stmt->close();

json_response(['success' => true]);
