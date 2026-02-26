<?php

declare(strict_types=1);

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['file'])) {
    $requested = basename((string) $_GET['file']);
    $path = $storageDir . DIRECTORY_SEPARATOR . $requested;

    if (!$requested || !is_file($path)) {
        http_response_code(404);
        exit('Файл не найден');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($requested) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody ?: '{}', true);

$url = trim((string)($data['url'] ?? ''));
$format = strtolower(trim((string)($data['format'] ?? 'mp4')));

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    jsonResponse(['ok' => false, 'error' => 'Некорректная ссылка'], 422);
}

$host = parse_url($url, PHP_URL_HOST) ?: '';
$allowedHosts = [
    'ok.ru', 'www.ok.ru',
    'vk.com', 'www.vk.com',
    'rutube.ru', 'www.rutube.ru',
    'dzen.ru', 'www.dzen.ru',
    'yandex.ru', 'yandex.by', 'ya.ru', 'www.ya.ru',
];

$allowed = false;
foreach ($allowedHosts as $allowedHost) {
    if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    jsonResponse(['ok' => false, 'error' => 'Источник не поддерживается'], 422);
}

if (!in_array($format, ['mp4', 'mp3'], true)) {
    $format = 'mp4';
}

$ytDlpPath = trim((string)shell_exec('command -v yt-dlp'));
$ffmpegPath = trim((string)shell_exec('command -v ffmpeg'));

if ($ytDlpPath === '') {
    jsonResponse(['ok' => false, 'error' => 'yt-dlp не найден на сервере'], 500);
}
if ($ffmpegPath === '') {
    jsonResponse(['ok' => false, 'error' => 'ffmpeg не найден на сервере'], 500);
}

$token = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$template = $storageDir . '/' . $token . '_%(title).80B.%(ext)s';

if ($format === 'mp3') {
    $cmd = sprintf(
        '%s --no-playlist --no-warnings --restrict-filenames -x --audio-format mp3 -o %s %s 2>&1',
        escapeshellarg($ytDlpPath),
        escapeshellarg($template),
        escapeshellarg($url)
    );
} else {
    $cmd = sprintf(
        '%s --no-playlist --no-warnings --restrict-filenames -f "bv*+ba/b" --merge-output-format mp4 -o %s %s 2>&1',
        escapeshellarg($ytDlpPath),
        escapeshellarg($template),
        escapeshellarg($url)
    );
}

$output = [];
$code = 0;
exec($cmd, $output, $code);

if ($code !== 0) {
    jsonResponse([
        'ok' => false,
        'error' => 'Ошибка yt-dlp: ' . implode("\n", array_slice($output, -5)),
    ], 500);
}

$files = glob($storageDir . '/' . $token . '_*');
if (!$files) {
    jsonResponse(['ok' => false, 'error' => 'Файл не найден после скачивания'], 500);
}

usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
$file = basename($files[0]);

jsonResponse([
    'ok' => true,
    'filename' => $file,
    'download_url' => 'api.php?file=' . rawurlencode($file),
]);
