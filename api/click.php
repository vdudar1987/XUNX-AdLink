<?php

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/geo.php';
require __DIR__ . '/../lib/fraud.php';

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
$fingerprint = trim((string) ($input['fingerprint'] ?? ''));
$timeOnPageMs = (int) ($input['time_on_page_ms'] ?? 0);

if ($publisherId <= 0 || $campaignId <= 0 || $keywordId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Отсутствуют обязательные параметры.']);
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
    'fingerprint' => $fingerprint,
    'time_on_page_ms' => $timeOnPageMs,
];

[$isFraud, $reason] = detect_fraud($conn, $data);

$allowed = false;
$counted = false;

if (!$isFraud) {
    $config = require __DIR__ . '/../config.php';
    $commissionRate = (float) $config['finance']['commission_rate'];
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'SELECT c.cpc, c.active, c.daily_limit, c.total_limit, c.spent_today, c.spent_today_date, c.spent_total,
                c.advertiser_id, a.balance AS advertiser_balance, a.status AS advertiser_status,
                p.balance AS publisher_balance, p.active AS publisher_active
         FROM campaigns c
         JOIN advertisers a ON a.id = c.advertiser_id
         JOIN publishers p ON p.id = ?
         WHERE c.id = ?
         FOR UPDATE'
    );
    $stmt->bind_param('ii', $publisherId, $campaignId);
    $stmt->execute();
    $campaign = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($campaign && (int) $campaign['active'] === 1 && (int) $campaign['publisher_active'] === 1 && $campaign['advertiser_status'] === 'active') {
        $cpc = (float) $campaign['cpc'];
        $advertiserBalance = (float) $campaign['advertiser_balance'];

        $spentToday = (float) $campaign['spent_today'];
        $spentTodayDate = $campaign['spent_today_date'];
        if ($spentTodayDate !== date('Y-m-d')) {
            $spentToday = 0.0;
        }

        $dailyLimit = $campaign['daily_limit'] !== null ? (float) $campaign['daily_limit'] : null;
        $totalLimit = $campaign['total_limit'] !== null ? (float) $campaign['total_limit'] : null;
        $spentTotal = (float) $campaign['spent_total'];

        if ($advertiserBalance >= $cpc) {
            $withinDaily = $dailyLimit === null || ($spentToday + $cpc) <= $dailyLimit;
            $withinTotal = $totalLimit === null || ($spentTotal + $cpc) <= $totalLimit;

            if ($withinDaily && $withinTotal) {
                $publisherIncome = round($cpc * (1 - $commissionRate), 2);
                $commission = round($cpc - $publisherIncome, 2);

                $stmt = $conn->prepare('UPDATE advertisers SET balance = balance - ? WHERE id = ?');
                $stmt->bind_param('di', $cpc, $campaign['advertiser_id']);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare('UPDATE publishers SET balance = balance + ? WHERE id = ?');
                $stmt->bind_param('di', $publisherIncome, $publisherId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    'UPDATE campaigns
                     SET spent_total = spent_total + ?, spent_today = ?, spent_today_date = CURDATE()
                     WHERE id = ?'
                );
                $newSpentToday = $spentToday + $cpc;
                $stmt->bind_param('ddi', $cpc, $newSpentToday, $campaignId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    'INSERT INTO transactions (advertiser_id, campaign_id, amount, type, description)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $type = 'advertiser_debit';
                $desc = 'Списание за клик';
                $stmt->bind_param('iidss', $campaign['advertiser_id'], $campaignId, $cpc, $type, $desc);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    'INSERT INTO transactions (publisher_id, campaign_id, amount, type, description)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $type = 'publisher_credit';
                $desc = 'Начисление за клик';
                $stmt->bind_param('iidss', $publisherId, $campaignId, $publisherIncome, $type, $desc);
                $stmt->execute();
                $stmt->close();

                if ($commission > 0) {
                    $stmt = $conn->prepare(
                        'INSERT INTO transactions (campaign_id, amount, type, description)
                         VALUES (?, ?, ?, ?)'
                    );
                    $type = 'commission';
                    $desc = 'Комиссия сети';
                    $stmt->bind_param('idss', $campaignId, $commission, $type, $desc);
                    $stmt->execute();
                    $stmt->close();
                }

                $allowed = true;
                $counted = true;
            }
        }
    }

    $conn->commit();
}

$stmt = $conn->prepare(
    'INSERT INTO clicks (campaign_id, keyword_id, publisher_id, ip, user_agent, referrer, fingerprint, time_on_page_ms, region_code, page_url, is_fraud, fraud_reason, counted, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$fraudFlag = $isFraud ? 1 : 0;
$countedFlag = $counted ? 1 : 0;
$stmt->bind_param(
    'iiissssissisi',
    $campaignId,
    $keywordId,
    $publisherId,
    $data['ip'],
    $data['user_agent'],
    $data['referrer'],
    $data['fingerprint'],
    $timeOnPageMs,
    $region,
    $pageUrl,
    $fraudFlag,
    $reason,
    $countedFlag
);
$stmt->execute();
$stmt->close();

echo json_encode([
    'allowed' => $allowed,
    'counted' => $counted,
]);
