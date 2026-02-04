<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/response.php';

session_start();

$input = read_json();
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['error' => 'Введите email и пароль.'], 400);
}

$conn = db();
$stmt = $conn->prepare('SELECT id, role, name, password_hash, status FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response(['error' => 'Неверный email или пароль.'], 401);
}

if ($user['status'] !== 'active') {
    json_response(['error' => 'Подтвердите email перед входом.'], 403);
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['role'] = $user['role'];

$stmt = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->close();

json_response([
    'success' => true,
    'user' => [
        'id' => (int) $user['id'],
        'role' => $user['role'],
        'name' => $user['name'],
        'email' => $email,
    ],
]);
