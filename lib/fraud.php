<?php

function detect_fraud(mysqli $conn, array $data): array
{
    $ip = $data['ip'];
    $campaignId = (int) $data['campaign_id'];
    $publisherId = (int) $data['publisher_id'];
    $userAgent = trim($data['user_agent']);
    $referrer = trim($data['referrer']);

    if ($ip === '' || $userAgent === '') {
        return [true, 'missing_client_data'];
    }

    $publisherDomain = '';
    $stmt = $conn->prepare('SELECT site_domain FROM publishers WHERE id = ? AND active = 1');
    $stmt->bind_param('i', $publisherId);
    $stmt->execute();
    $stmt->bind_result($publisherDomain);
    $stmt->fetch();
    $stmt->close();

    if ($publisherDomain === '') {
        return [true, 'publisher_not_found'];
    }

    if ($referrer !== '' && stripos($referrer, $publisherDomain) === false) {
        return [true, 'referrer_mismatch'];
    }

    $config = require __DIR__ . '/../config.php';
    $limit = (int) $config['fraud']['max_clicks_per_ip_campaign_10min'];

    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM clicks WHERE ip = ? AND campaign_id = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)'
    );
    $stmt->bind_param('si', $ip, $campaignId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ((int) $count >= $limit) {
        return [true, 'too_many_clicks'];
    }

    return [false, ''];
}
