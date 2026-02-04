<?php

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/response.php';

$user = require_role('publisher');
$conn = db();
$action = $_GET['action'] ?? 'overview';

if ($action === 'overview') {
    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(balance), 0) AS balance
         FROM publishers
         WHERE user_id = ?'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $balance = (float) $stmt->get_result()->fetch_assoc()['balance'];
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS sites
         FROM publishers
         WHERE user_id = ?'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $sites = (int) $stmt->get_result()->fetch_assoc()['sites'];
    $stmt->close();

    json_response([
        'balance' => $balance,
        'sites' => $sites,
    ]);
}

if ($action === 'sites') {
    $stmt = $conn->prepare(
        'SELECT id, site_domain, site_url, category, region_code, moderation_status, verification_status
         FROM publishers
         WHERE user_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $sites = [];
    while ($row = $result->fetch_assoc()) {
        $sites[] = $row;
    }
    $stmt->close();
    json_response(['sites' => $sites]);
}

if ($action === 'create_site') {
    $input = read_json();
    $domain = trim((string) ($input['site_domain'] ?? ''));
    $url = trim((string) ($input['site_url'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $region = trim((string) ($input['region_code'] ?? ''));

    if ($domain === '' || $url === '' || $category === '') {
        json_response(['error' => 'Заполните все поля сайта.'], 400);
    }

    if ($region === '') {
        $region = null;
    }

    $stmt = $conn->prepare(
        'INSERT INTO publishers (user_id, name, site_domain, site_url, category, region_code)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssss', $user['id'], $user['name'], $domain, $url, $category, $region);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['error' => 'Не удалось добавить сайт.'], 500);
    }
    $siteId = $stmt->insert_id;
    $stmt->close();

    json_response(['success' => true, 'site_id' => $siteId]);
}

if ($action === 'transactions') {
    $stmt = $conn->prepare(
        'SELECT amount, type, description, created_at
         FROM transactions
         WHERE publisher_id IN (SELECT id FROM publishers WHERE user_id = ?)
         ORDER BY created_at DESC
         LIMIT 50'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
    json_response(['transactions' => $transactions]);
}

if ($action === 'payout') {
    $input = read_json();
    $amount = (float) ($input['amount'] ?? 0);
    $method = trim((string) ($input['method'] ?? ''));

    if ($amount <= 0 || $method === '') {
        json_response(['error' => 'Укажите сумму и способ вывода.'], 400);
    }

    $stmt = $conn->prepare(
        'INSERT INTO transactions (publisher_id, amount, type, description)
         VALUES ((SELECT id FROM publishers WHERE user_id = ? ORDER BY created_at ASC LIMIT 1), ?, ?, ?)'
    );
    $type = 'payout_request';
    $desc = 'Запрос на вывод: ' . $method;
    $stmt->bind_param('idss', $user['id'], $amount, $type, $desc);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true]);
}

json_response(['error' => 'Неизвестное действие.'], 400);
