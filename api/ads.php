<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/geo.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

$publisherId = (int) ($input['publisher_id'] ?? 0);
$keywords = $input['keywords'] ?? [];

if ($publisherId <= 0 || !is_array($keywords) || $keywords === []) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_params']);
    exit;
}

$region = get_region_code();
if (!is_region_allowed($region)) {
    echo json_encode(['ads' => []]);
    exit;
}

$clean = [];
foreach ($keywords as $keyword) {
    $keyword = mb_strtolower(trim((string) $keyword));
    if ($keyword === '') {
        continue;
    }
    $clean[$keyword] = true;
}
$clean = array_keys($clean);
$clean = array_slice($clean, 0, 50);

if ($clean === []) {
    echo json_encode(['ads' => []]);
    exit;
}

$conn = db();

$stmt = $conn->prepare('SELECT id FROM publishers WHERE id = ? AND active = 1');
$stmt->bind_param('i', $publisherId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo json_encode(['ads' => []]);
    exit;
}
$stmt->close();

$placeholders = implode(',', array_fill(0, count($clean), '?'));
$types = str_repeat('s', count($clean));

$sql = "
    SELECT
        k.id AS keyword_id,
        k.keyword,
        c.id AS campaign_id,
        c.cpc,
        c.title,
        c.landing_url
    FROM keywords k
    JOIN campaigns c ON c.id = k.campaign_id
    WHERE c.active = 1
      AND k.keyword IN ($placeholders)
      AND (c.region_code IS NULL OR c.region_code = ?)
    ORDER BY c.cpc DESC
    LIMIT 3
";

$stmt = $conn->prepare($sql);
$params = array_merge($clean, [$region]);
$types .= 's';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$ads = [];
while ($row = $result->fetch_assoc()) {
    $ads[] = [
        'campaign_id' => (int) $row['campaign_id'],
        'keyword_id' => (int) $row['keyword_id'],
        'keyword' => $row['keyword'],
        'cpc' => (float) $row['cpc'],
        'title' => $row['title'],
        'landing_url' => $row['landing_url'],
    ];
}

$stmt->close();

echo json_encode(['ads' => $ads], JSON_UNESCAPED_UNICODE);
