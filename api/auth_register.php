<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/response.php';

$input = read_json();
$role = trim((string) ($input['role'] ?? ''));
$name = trim((string) ($input['name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($role === '' || $name === '' || $email === '' || $password === '') {
    json_response(['error' => 'Заполните все поля.'], 400);
}

if (!in_array($role, ['advertiser', 'publisher'], true)) {
    json_response(['error' => 'Некорректная роль.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Некорректный email.'], 400);
}

if (mb_strlen($password) < 6) {
    json_response(['error' => 'Пароль должен быть не короче 6 символов.'], 400);
}

$conn = db();

$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    json_response(['error' => 'Пользователь с таким email уже существует.'], 409);
}
$stmt->close();

$hash = password_hash($password, PASSWORD_DEFAULT);
$status = 'pending';

$stmt = $conn->prepare('INSERT INTO users (role, name, email, password_hash, status) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssss', $role, $name, $email, $hash, $status);
if (!$stmt->execute()) {
    $stmt->close();
    json_response(['error' => 'Не удалось создать пользователя.'], 500);
}
$userId = $stmt->insert_id;
$stmt->close();

if ($role === 'advertiser') {
    $stmt = $conn->prepare('INSERT INTO advertisers (id, name, email, balance, status) VALUES (?, ?, ?, 0.00, ?)');
    $active = 'active';
    $stmt->bind_param('isss', $userId, $name, $email, $active);
    $stmt->execute();
    $stmt->close();
}

if ($role === 'publisher') {
    $stmt = $conn->prepare('INSERT INTO publishers (user_id, name, site_domain, site_url, category, region_code) VALUES (?, ?, ?, ?, ?, ?)');
    $domain = '';
    $url = '';
    $category = '';
    $region = null;
    $stmt->bind_param('isssss', $userId, $name, $domain, $url, $category, $region);
    $stmt->execute();
    $stmt->close();
}

json_response(['success' => true, 'user_id' => $userId]);
