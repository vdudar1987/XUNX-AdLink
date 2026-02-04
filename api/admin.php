<?php

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/response.php';
require __DIR__ . '/../lib/settings.php';

require_role('admin');
$conn = db();
$action = $_GET['action'] ?? 'overview';

if ($action === 'overview') {
    $counts = [];

    $result = $conn->query('SELECT COUNT(*) AS total FROM users');
    $counts['users'] = (int) ($result->fetch_assoc()['total'] ?? 0);

    $result = $conn->query("SELECT COUNT(*) AS total FROM publishers WHERE moderation_status = 'pending'");
    $counts['publishers_pending'] = (int) ($result->fetch_assoc()['total'] ?? 0);

    $result = $conn->query("SELECT COUNT(*) AS total FROM campaigns WHERE moderation_status = 'pending'");
    $counts['campaigns_pending'] = (int) ($result->fetch_assoc()['total'] ?? 0);

    $result = $conn->query("SELECT COUNT(*) AS total FROM payout_requests WHERE status = 'pending'");
    $counts['payouts_pending'] = (int) ($result->fetch_assoc()['total'] ?? 0);

    json_response(['counts' => $counts]);
}

if ($action === 'users') {
    $result = $conn->query('SELECT id, role, name, email, status, created_at FROM users ORDER BY created_at DESC');
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    json_response(['users' => $users]);
}

if ($action === 'update_user') {
    $input = read_json();
    $userId = (int) ($input['id'] ?? 0);
    $status = trim((string) ($input['status'] ?? ''));
    $role = trim((string) ($input['role'] ?? ''));

    if ($userId <= 0 || $status === '' || $role === '') {
        json_response(['error' => 'Неверные данные пользователя.'], 400);
    }

    $stmt = $conn->prepare('UPDATE users SET status = ?, role = ? WHERE id = ?');
    $stmt->bind_param('ssi', $status, $role, $userId);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true]);
}

if ($action === 'publishers') {
    $result = $conn->query(
        'SELECT id, name, site_domain, site_url, category, region_code, moderation_status, verification_status, active, created_at
         FROM publishers
         ORDER BY created_at DESC'
    );
    $sites = [];
    while ($row = $result->fetch_assoc()) {
        $sites[] = $row;
    }
    json_response(['sites' => $sites]);
}

if ($action === 'update_publisher') {
    $input = read_json();
    $publisherId = (int) ($input['id'] ?? 0);
    $moderation = trim((string) ($input['moderation_status'] ?? ''));
    $verification = trim((string) ($input['verification_status'] ?? ''));
    $active = isset($input['active']) ? (int) (bool) $input['active'] : 1;

    if ($publisherId <= 0 || $moderation === '' || $verification === '') {
        json_response(['error' => 'Неверные данные площадки.'], 400);
    }

    $stmt = $conn->prepare(
        'UPDATE publishers SET moderation_status = ?, verification_status = ?, active = ? WHERE id = ?'
    );
    $stmt->bind_param('ssii', $moderation, $verification, $active, $publisherId);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true]);
}

if ($action === 'campaigns') {
    $result = $conn->query(
        'SELECT c.id, c.name, c.cpc, c.moderation_status, c.active, c.created_at, a.name AS advertiser_name
         FROM campaigns c
         JOIN advertisers a ON a.id = c.advertiser_id
         ORDER BY c.created_at DESC'
    );
    $campaigns = [];
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    json_response(['campaigns' => $campaigns]);
}

if ($action === 'update_campaign') {
    $input = read_json();
    $campaignId = (int) ($input['id'] ?? 0);
    $moderation = trim((string) ($input['moderation_status'] ?? ''));
    $active = isset($input['active']) ? (int) (bool) $input['active'] : 1;

    if ($campaignId <= 0 || $moderation === '') {
        json_response(['error' => 'Неверные данные кампании.'], 400);
    }

    $stmt = $conn->prepare('UPDATE campaigns SET moderation_status = ?, active = ? WHERE id = ?');
    $stmt->bind_param('sii', $moderation, $active, $campaignId);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true]);
}

if ($action === 'payouts') {
    $result = $conn->query(
        "SELECT pr.id, pr.amount, pr.method, pr.status, pr.created_at, p.name AS publisher_name, p.site_domain
         FROM payout_requests pr
         JOIN publishers p ON p.id = pr.publisher_id
         WHERE pr.status = 'pending'
         ORDER BY pr.created_at DESC"
    );
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    json_response(['payouts' => $requests]);
}

if ($action === 'update_payout') {
    $input = read_json();
    $requestId = (int) ($input['id'] ?? 0);
    $status = trim((string) ($input['status'] ?? ''));

    if ($requestId <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
        json_response(['error' => 'Неверный статус выплаты.'], 400);
    }

    $stmt = $conn->prepare(
        'SELECT publisher_id, amount FROM payout_requests WHERE id = ? AND status = ?'
    );
    $pending = 'pending';
    $stmt->bind_param('is', $requestId, $pending);
    $stmt->execute();
    $stmt->bind_result($publisherId, $amount);
    $stmt->fetch();
    $stmt->close();

    if (!$publisherId) {
        json_response(['error' => 'Заявка не найдена.'], 404);
    }

    if ($status === 'approved') {
        $stmt = $conn->prepare('SELECT balance FROM publishers WHERE id = ?');
        $stmt->bind_param('i', $publisherId);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();

        if ((float) $balance < (float) $amount) {
            json_response(['error' => 'Недостаточно средств на балансе.'], 400);
        }

        $stmt = $conn->prepare('UPDATE publishers SET balance = balance - ? WHERE id = ?');
        $stmt->bind_param('di', $amount, $publisherId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            'INSERT INTO transactions (publisher_id, amount, type, description)
             VALUES (?, ?, ?, ?)'
        );
        $type = 'payout_paid';
        $desc = 'Выплата по заявке #' . $requestId;
        $stmt->bind_param('idss', $publisherId, $amount, $type, $desc);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare(
        'UPDATE payout_requests SET status = ?, processed_at = NOW() WHERE id = ?'
    );
    $stmt->bind_param('si', $status, $requestId);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true]);
}

if ($action === 'settings') {
    $settings = get_settings($conn);
    json_response(['settings' => $settings]);
}

if ($action === 'update_settings') {
    $input = read_json();
    if (!is_array($input['settings'] ?? null)) {
        json_response(['error' => 'Некорректные настройки.'], 400);
    }

    $pairs = [];
    foreach ($input['settings'] as $key => $value) {
        $pairs[$key] = $value;
    }
    update_settings($conn, $pairs);
    json_response(['success' => true]);
}

json_response(['error' => 'Неизвестное действие.'], 400);
