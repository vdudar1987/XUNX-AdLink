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
$keywords = $input['keywords'] ?? [];

if ($publisherId <= 0 || !is_array($keywords) || $keywords === []) {
    http_response_code(400);
    echo json_encode(['error' => 'Отсутствуют обязательные параметры.']);
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

$stmt = $conn->prepare(
    "SELECT id FROM publishers WHERE id = ? AND active = 1 AND moderation_status = 'approved'"
);
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
        c.advertiser_id,
        c.cpc,
        c.title,
        c.landing_url,
        c.ad_type,
        c.teaser_text,
        c.image_url
    FROM keywords k
    JOIN campaigns c ON c.id = k.campaign_id
    JOIN advertisers a ON a.id = c.advertiser_id
    WHERE c.active = 1
      AND c.moderation_status = 'approved'
      AND a.status = 'active'
      AND a.balance >= c.cpc
      AND k.keyword IN ($placeholders)
      AND (c.region_code IS NULL OR c.region_code = ?)
      AND (c.starts_at IS NULL OR c.starts_at <= NOW())
      AND (c.ends_at IS NULL OR c.ends_at >= NOW())
      AND (c.total_limit IS NULL OR c.spent_total < c.total_limit)
      AND (
        c.daily_limit IS NULL
        OR (c.spent_today_date = CURDATE() AND c.spent_today < c.daily_limit)
        OR (c.spent_today_date IS NULL OR c.spent_today_date <> CURDATE())
      )
      AND NOT EXISTS (
        SELECT 1 FROM campaign_sites cs
        WHERE cs.campaign_id = c.id
          AND cs.publisher_id = ?
          AND cs.list_type = 'blacklist'
      )
      AND (
        NOT EXISTS (
          SELECT 1 FROM campaign_sites cs
          WHERE cs.campaign_id = c.id
            AND cs.list_type = 'whitelist'
        )
        OR EXISTS (
          SELECT 1 FROM campaign_sites cs
          WHERE cs.campaign_id = c.id
            AND cs.publisher_id = ?
            AND cs.list_type = 'whitelist'
        )
      )
    ORDER BY c.cpc DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
$params = array_merge($clean, [$region, $publisherId, $publisherId]);
$types .= 'sii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$ads = [];
$seenAdvertisers = [];
while ($row = $result->fetch_assoc()) {
    $advertiserId = (int) $row['advertiser_id'];
    if (isset($seenAdvertisers[$advertiserId])) {
        continue;
    }
    $seenAdvertisers[$advertiserId] = true;
    $ads[] = [
        'campaign_id' => (int) $row['campaign_id'],
        'advertiser_id' => $advertiserId,
        'keyword_id' => (int) $row['keyword_id'],
        'keyword' => $row['keyword'],
        'cpc' => (float) $row['cpc'],
        'title' => $row['title'],
        'landing_url' => $row['landing_url'],
        'ad_type' => $row['ad_type'],
        'teaser_text' => $row['teaser_text'],
        'image_url' => $row['image_url'],
    ];
    if (count($ads) >= 3) {
        break;
    }
}

$stmt->close();

echo json_encode(['ads' => $ads], JSON_UNESCAPED_UNICODE);
