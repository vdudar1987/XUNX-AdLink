<?php

require __DIR__ . '/../lib/auth.php';

$user = current_user();
if (!$user) {
    json_response(['authenticated' => false]);
}

json_response([
    'authenticated' => true,
    'user' => $user,
]);
