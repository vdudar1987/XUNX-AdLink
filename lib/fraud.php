<?php

require_once __DIR__ . '/settings.php';

function detect_fraud(mysqli $conn, array $data): array
{
    $ip = $data['ip'];
    $campaignId = (int) $data['campaign_id'];
    $publisherId = (int) $data['publisher_id'];
    $userAgent = trim($data['user_agent']);
    $referrer = trim($data['referrer']);
    $fingerprint = trim($data['fingerprint']);
    $timeOnPageMs = (int) $data['time_on_page_ms'];

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

    $settings = get_settings($conn);
    $limit = (int) $settings['fraud']['max_clicks_per_ip_campaign_10min'];
    $fingerprintLimit = (int) $settings['fraud']['max_clicks_per_fingerprint_10min'];
    $minTimeOnPage = (int) $settings['fraud']['min_time_on_page_ms'];

    if ($timeOnPageMs > 0 && $timeOnPageMs < $minTimeOnPage) {
        return [true, 'too_fast_click'];
    }

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

    if ($fingerprint !== '') {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) FROM clicks WHERE fingerprint = ? AND campaign_id = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)'
        );
        $stmt->bind_param('si', $fingerprint, $campaignId);
        $stmt->execute();
        $stmt->bind_result($fpCount);
        $stmt->fetch();
        $stmt->close();

        if ((int) $fpCount >= $fingerprintLimit) {
            return [true, 'fingerprint_limit'];
        }
    }

    return [false, ''];
}
