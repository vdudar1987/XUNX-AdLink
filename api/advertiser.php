<?php

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/response.php';

$user = require_role('advertiser');
$conn = db();
$action = $_GET['action'] ?? 'overview';

if ($action === 'overview') {
    $stmt = $conn->prepare('SELECT balance FROM advertisers WHERE id = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $advertiser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS impressions
         FROM impressions i
         JOIN campaigns c ON c.id = i.campaign_id
         WHERE c.advertiser_id = ?'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $impressions = (int) $stmt->get_result()->fetch_assoc()['impressions'];
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS clicks
         FROM clicks cl
         JOIN campaigns c ON c.id = cl.campaign_id
         WHERE c.advertiser_id = ? AND cl.counted = 1'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $clicks = (int) $stmt->get_result()->fetch_assoc()['clicks'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS spent
         FROM transactions
         WHERE advertiser_id = ? AND type = 'advertiser_debit'"
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $spent = (float) $stmt->get_result()->fetch_assoc()['spent'];
    $stmt->close();

    $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
    $cpc = $clicks > 0 ? round($spent / $clicks, 2) : 0;

    json_response([
        'balance' => (float) ($advertiser['balance'] ?? 0),
        'impressions' => $impressions,
        'clicks' => $clicks,
        'ctr' => $ctr,
        'spent' => $spent,
        'cpc' => $cpc,
    ]);
}

if ($action === 'campaigns') {
    $stmt = $conn->prepare(
        'SELECT id, name, title, landing_url, ad_type, cpc, daily_limit, total_limit, active
         FROM campaigns
         WHERE advertiser_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $campaigns = [];
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    $stmt->close();
    json_response(['campaigns' => $campaigns]);
}

if ($action === 'create_campaign') {
    $input = read_json();
    $name = trim((string) ($input['name'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));
    $landingUrl = trim((string) ($input['landing_url'] ?? ''));
    $adType = trim((string) ($input['ad_type'] ?? 'context'));
    $teaserText = trim((string) ($input['teaser_text'] ?? ''));
    $imageUrl = trim((string) ($input['image_url'] ?? ''));
    $cpc = (float) ($input['cpc'] ?? 0);
    $dailyLimit = $input['daily_limit'] !== '' ? (float) ($input['daily_limit'] ?? 0) : 0;
    $totalLimit = $input['total_limit'] !== '' ? (float) ($input['total_limit'] ?? 0) : 0;
    $region = trim((string) ($input['region_code'] ?? ''));
    $keywords = $input['keywords'] ?? [];

    if ($name === '' || $title === '' || $landingUrl === '' || $cpc <= 0) {
        json_response(['error' => 'Заполните обязательные поля кампании.'], 400);
    }

    if (!in_array($adType, ['context', 'banner', 'teaser'], true)) {
        $adType = 'context';
    }

    if ($region === '') {
        $region = null;
    }

    $stmt = $conn->prepare(
        'INSERT INTO campaigns (advertiser_id, name, title, landing_url, ad_type, teaser_text, image_url, cpc, region_code, daily_limit, total_limit)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0))'
    );
    $stmt->bind_param(
        'issssssdsss',
        $user['id'],
        $name,
        $title,
        $landingUrl,
        $adType,
        $teaserText,
        $imageUrl,
        $cpc,
        $region,
        (string) $dailyLimit,
        (string) $totalLimit
    );
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(['error' => 'Не удалось создать кампанию.'], 500);
    }
    $campaignId = $stmt->insert_id;
    $stmt->close();

    if (is_array($keywords)) {
        $stmt = $conn->prepare('INSERT INTO keywords (campaign_id, keyword) VALUES (?, ?)');
        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                continue;
            }
            $stmt->bind_param('is', $campaignId, $keyword);
            $stmt->execute();
        }
        $stmt->close();
    }

    json_response(['success' => true, 'campaign_id' => $campaignId]);
}

if ($action === 'stats_campaigns') {
    $stmt = $conn->prepare(
        'SELECT c.id, c.name,
                COUNT(DISTINCT i.id) AS impressions,
                COUNT(DISTINCT cl.id) AS clicks,
                COALESCE(SUM(t.amount), 0) AS spent
         FROM campaigns c
         LEFT JOIN impressions i ON i.campaign_id = c.id
         LEFT JOIN clicks cl ON cl.campaign_id = c.id AND cl.counted = 1
         LEFT JOIN transactions t ON t.campaign_id = c.id AND t.type = "advertiser_debit"
         WHERE c.advertiser_id = ?
         GROUP BY c.id
         ORDER BY c.created_at DESC'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $impressions = (int) $row['impressions'];
        $clicks = (int) $row['clicks'];
        $spent = (float) $row['spent'];
        $stats[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
            'spent' => $spent,
            'cpc' => $clicks > 0 ? round($spent / $clicks, 2) : 0,
        ];
    }
    $stmt->close();
    json_response(['stats' => $stats]);
}

if ($action === 'stats_sites') {
    $stmt = $conn->prepare(
        'SELECT p.id, p.site_domain,
                COUNT(DISTINCT i.id) AS impressions,
                COUNT(DISTINCT cl.id) AS clicks
         FROM publishers p
         LEFT JOIN impressions i ON i.publisher_id = p.id
         LEFT JOIN clicks cl ON cl.publisher_id = p.id AND cl.counted = 1
         LEFT JOIN campaigns c ON c.id = i.campaign_id OR c.id = cl.campaign_id
         WHERE c.advertiser_id = ?
         GROUP BY p.id
         ORDER BY impressions DESC'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $impressions = (int) $row['impressions'];
        $clicks = (int) $row['clicks'];
        $stats[] = [
            'id' => (int) $row['id'],
            'site' => $row['site_domain'],
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
        ];
    }
    $stmt->close();
    json_response(['stats' => $stats]);
}

if ($action === 'stats_pages') {
    $stmt = $conn->prepare(
        'SELECT i.page_url,
                COUNT(DISTINCT i.id) AS impressions,
                COUNT(DISTINCT cl.id) AS clicks
         FROM impressions i
         JOIN campaigns c ON c.id = i.campaign_id
         LEFT JOIN clicks cl ON cl.page_url = i.page_url AND cl.campaign_id = i.campaign_id AND cl.counted = 1
         WHERE c.advertiser_id = ?
         GROUP BY i.page_url
         ORDER BY impressions DESC
         LIMIT 50'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $impressions = (int) $row['impressions'];
        $clicks = (int) $row['clicks'];
        $stats[] = [
            'page_url' => $row['page_url'],
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
        ];
    }
    $stmt->close();
    json_response(['stats' => $stats]);
}

if ($action === 'stats_keywords') {
    $stmt = $conn->prepare(
        'SELECT k.keyword,
                COUNT(DISTINCT i.id) AS impressions,
                COUNT(DISTINCT cl.id) AS clicks
         FROM keywords k
         JOIN campaigns c ON c.id = k.campaign_id
         LEFT JOIN impressions i ON i.keyword_id = k.id
         LEFT JOIN clicks cl ON cl.keyword_id = k.id AND cl.counted = 1
         WHERE c.advertiser_id = ?
         GROUP BY k.id
         ORDER BY impressions DESC
         LIMIT 50'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $impressions = (int) $row['impressions'];
        $clicks = (int) $row['clicks'];
        $stats[] = [
            'keyword' => $row['keyword'],
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
        ];
    }
    $stmt->close();
    json_response(['stats' => $stats]);
}

json_response(['error' => 'Неизвестное действие.'], 400);
