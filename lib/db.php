<?php

function db(): mysqli
{
    static $conn;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $config = require __DIR__ . '/../config.php';
    $db = $config['db'];

    $conn = new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], $db['port']);
    if ($conn->connect_error) {
        http_response_code(500);
        exit('DB connection error');
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}
