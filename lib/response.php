<?php

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        return [];
    }
    return $input;
}
