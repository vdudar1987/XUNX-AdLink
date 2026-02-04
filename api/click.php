<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/geo.php';
require __DIR__ . '/../lib/fraud.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

$publisherId = (int) ($input['publisher_id'] ?? 0);
$campaignId = (int) ($input['campaign_id'] ?? 0);
$keywordId = (int) ($input['keyword_id'] ?? 0);
$pageUrl = trim((string) ($input['page_url'] ?? ''));

if ($publisherId <= 0 || $campaignId <= 0 || $keywordId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_params']);
    exit;
}

$region = get_region_code();
if (!is_region_allowed($region)) {
    echo json_encode(['allowed' => false]);
    exit;
}

$conn = db();

$data = [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'campaign_id' => $campaignId,
    'publisher_id' => $publisherId,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
];

[$isFraud, $reason] = detect_fraud($conn, $data);

$stmt = $conn->prepare(
    'INSERT INTO clicks (campaign_id, keyword_id, publisher_id, ip, user_agent, region_code, page_url, is_fraud, fraud_reason, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$fraudFlag = $isFraud ? 1 : 0;
$stmt->bind_param(
    'iiissssis',
    $campaignId,
    $keywordId,
    $publisherId,
    $data['ip'],
    $data['user_agent'],
    $region,
    $pageUrl,
    $fraudFlag,
    $reason
);
$stmt->execute();
$stmt->close();

echo json_encode([
    'allowed' => true,
    'counted' => !$isFraud,
]);
