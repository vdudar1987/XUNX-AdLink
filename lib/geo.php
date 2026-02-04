<?php

function get_region_code(): ?string
{
    $header = $_SERVER['HTTP_X_RU_REGION'] ?? '';
    $param = $_GET['region'] ?? $_POST['region'] ?? '';
    $region = strtoupper(trim($header ?: $param));

    if ($region === '') {
        return null;
    }

    return $region;
}

function is_region_allowed(?string $region): bool
{
    if ($region === null) {
        return false;
    }

    $config = require __DIR__ . '/../config.php';
    return in_array($region, $config['geo']['allowed_regions'], true);
}
