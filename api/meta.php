<?php

require __DIR__ . '/../lib/response.php';

$config = require __DIR__ . '/../config.php';
$regions = $config['geo']['allowed_regions'] ?? [];

json_response([
    'regions' => $regions,
    'roles' => [
        ['value' => 'advertiser', 'label' => 'Рекламодатель'],
        ['value' => 'publisher', 'label' => 'Вебмастер'],
    ],
]);
