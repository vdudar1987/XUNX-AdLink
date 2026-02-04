<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function current_user(): ?array
{
    start_session();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $conn = db();
    $stmt = $conn->prepare('SELECT id, role, name, email, status FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return null;
    }

    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['error' => 'Требуется авторизация.'], 401);
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_auth();
    if ($user['role'] !== $role) {
        json_response(['error' => 'Недостаточно прав.'], 403);
    }
    return $user;
}
