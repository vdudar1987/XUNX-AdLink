<?php

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/response.php';

$user = require_auth();
$conn = db();

$input = read_json();
$name = trim((string) ($input['name'] ?? $user['name']));
$email = trim((string) ($input['email'] ?? $user['email']));
$password = (string) ($input['password'] ?? '');

if ($name === '' || $email === '') {
    json_response(['error' => 'Имя и email обязательны.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Некорректный email.'], 400);
}

$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
$stmt->bind_param('si', $email, $user['id']);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    json_response(['error' => 'Email уже используется.'], 409);
}
$stmt->close();

if ($password !== '') {
    if (mb_strlen($password) < 6) {
        json_response(['error' => 'Пароль должен быть не короче 6 символов.'], 400);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?');
    $stmt->bind_param('sssi', $name, $email, $hash, $user['id']);
} else {
    $stmt = $conn->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
    $stmt->bind_param('ssi', $name, $email, $user['id']);
}
if (!$stmt->execute()) {
    $stmt->close();
    json_response(['error' => 'Не удалось обновить профиль.'], 500);
}
$stmt->close();

json_response(['success' => true]);
