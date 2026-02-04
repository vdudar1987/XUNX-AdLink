<?php

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/response.php';

require_auth();

json_response([
    'notifications' => [],
]);
