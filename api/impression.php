<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/geo.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный JSON.']);
    exit;
}

$publisherId = (int) ($input['publisher_id'] ?? 0);
$campaignId = (int) ($input['campaign_id'] ?? 0);
$keywordId = (int) ($input['keyword_id'] ?? 0);
$pageUrl = trim((string) ($input['page_url'] ?? ''));

if ($publisherId <= 0 || $campaignId <= 0 || $keywordId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Отсутствуют обязательные параметры.']);
    exit;
}

$region = get_region_code();
if (!is_region_allowed($region)) {
    echo json_encode(['stored' => false]);
    exit;
}

$conn = db();

$stmt = $conn->prepare('SELECT id FROM publishers WHERE id = ? AND active = 1');
$stmt->bind_param('i', $publisherId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo json_encode(['stored' => false]);
    exit;
}
$stmt->close();

$stmt = $conn->prepare(
    'INSERT INTO impressions (campaign_id, keyword_id, publisher_id, region_code, page_url, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())'
);
$stmt->bind_param('iiiss', $campaignId, $keywordId, $publisherId, $region, $pageUrl);
$stmt->execute();
$stmt->close();

echo json_encode(['stored' => true]);
