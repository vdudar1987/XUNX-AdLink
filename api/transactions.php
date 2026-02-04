<?php

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/response.php';

$user = require_auth();
$conn = db();

if ($user['role'] === 'advertiser') {
    $stmt = $conn->prepare(
        'SELECT amount, type, description, created_at
         FROM transactions
         WHERE advertiser_id = ?
         ORDER BY created_at DESC
         LIMIT 50'
    );
    $stmt->bind_param('i', $user['id']);
} else {
    $stmt = $conn->prepare(
        'SELECT amount, type, description, created_at
         FROM transactions
         WHERE publisher_id IN (SELECT id FROM publishers WHERE user_id = ?)
         ORDER BY created_at DESC
         LIMIT 50'
    );
    $stmt->bind_param('i', $user['id']);
}

$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

json_response(['transactions' => $transactions]);
