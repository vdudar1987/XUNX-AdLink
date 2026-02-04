<?php

require_once __DIR__ . '/db.php';

function settings_defaults(): array
{
    $config = require __DIR__ . '/../config.php';

    return [
        'site' => [
            'name' => 'XUNX AdLink',
            'url' => '',
        ],
        'fraud' => [
            'max_clicks_per_ip_campaign_10min' => (int) ($config['fraud']['max_clicks_per_ip_campaign_10min'] ?? 5),
            'max_clicks_per_fingerprint_10min' => (int) ($config['fraud']['max_clicks_per_fingerprint_10min'] ?? 8),
            'min_time_on_page_ms' => (int) ($config['fraud']['min_time_on_page_ms'] ?? 2000),
        ],
        'finance' => [
            'commission_rate' => (float) ($config['finance']['commission_rate'] ?? 0.2),
            'currency' => (string) ($config['finance']['currency'] ?? 'RUB'),
        ],
    ];
}

function settings_table_available(mysqli $conn): bool
{
    $result = $conn->query("SHOW TABLES LIKE 'settings'");
    if (!$result) {
        return false;
    }
    $hasTable = $result->num_rows > 0;
    $result->free();
    return $hasTable;
}

function apply_setting_value(array $settings, string $key, string $value): array
{
    $parts = explode('.', $key, 2);
    if (count($parts) !== 2) {
        return $settings;
    }
    [$group, $name] = $parts;
    if (!isset($settings[$group]) || !is_array($settings[$group])) {
        return $settings;
    }

    if (is_numeric($settings[$group][$name] ?? null)) {
        if (str_contains($value, '.')) {
            $settings[$group][$name] = (float) $value;
        } else {
            $settings[$group][$name] = (int) $value;
        }
    } else {
        $settings[$group][$name] = $value;
    }

    return $settings;
}

function get_settings(mysqli $conn): array
{
    $settings = settings_defaults();

    if (!settings_table_available($conn)) {
        return $settings;
    }

    $result = $conn->query('SELECT setting_key, setting_value FROM settings');
    if (!$result) {
        return $settings;
    }

    while ($row = $result->fetch_assoc()) {
        $settings = apply_setting_value($settings, (string) $row['setting_key'], (string) $row['setting_value']);
    }
    $result->free();

    return $settings;
}

function update_settings(mysqli $conn, array $pairs): void
{
    if (!settings_table_available($conn)) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($pairs as $key => $value) {
        $key = (string) $key;
        $value = (string) $value;
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }

    $stmt->close();
}
