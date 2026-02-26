<?php

declare(strict_types=1);

$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

const TMP_FILE_TTL_SECONDS = 3600;

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hostMatches(string $host, string $allowedHost): bool
{
    if ($host === $allowedHost) {
        return true;
    }

    $suffix = '.' . $allowedHost;
    return strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix;
}

function cleanupOldFiles(string $directory, int $ttl): void
{
    $now = time();
    foreach (glob($directory . '/*') ?: [] as $file) {
        if (!is_file($file)) {
            continue;
        }

        $modifiedAt = filemtime($file);
        if ($modifiedAt !== false && ($now - $modifiedAt) > $ttl) {
            @unlink($file);
        }
    }
}

function commandExists(string $command): string
{
    return trim((string) shell_exec('command -v ' . escapeshellarg($command)));
}

function tryResolveDirectUrlViaLibrary(string $url): ?string
{
    if (!class_exists('YT\\YtDlp\\YtDlp')) {
        return null;
    }

    try {
        $yt = new \YT\YtDlp\YtDlp();
        $collection = $yt->getDownloadLinks($url);
        if (is_iterable($collection)) {
            foreach ($collection as $item) {
                if (is_object($item) && method_exists($item, 'getUrl')) {
                    $direct = (string) $item->getUrl();
                    if ($direct !== '') {
                        return $direct;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}

function tryResolveDirectUrlViaCli(string $ytDlpPath, string $url): ?string
{
    $cmd = sprintf(
        '%s --no-playlist --no-warnings -f "bv*+ba/b" --get-url %s 2>&1',
        escapeshellarg($ytDlpPath),
        escapeshellarg($url)
    );

    $output = [];
    $code = 0;
    exec($cmd, $output, $code);

    if ($code !== 0 || !$output) {
        return null;
    }

    foreach ($output as $line) {
        $line = trim((string) $line);
        if (filter_var($line, FILTER_VALIDATE_URL)) {
            return $line;
        }
    }

    return null;
}

function convertToMp3WithPhpFfmpeg(string $inputFile, string $outputFile): bool
{
    if (!class_exists('FFMpeg\\FFMpeg') || !class_exists('FFMpeg\\Format\\Audio\\Mp3')) {
        return false;
    }

    try {
        $ffmpeg = \FFMpeg\FFMpeg::create();
        $audio = $ffmpeg->open($inputFile);
        $format = new \FFMpeg\Format\Audio\Mp3();
        $audio->save($format, $outputFile);
        return is_file($outputFile) && filesize($outputFile) > 0;
    } catch (Throwable $e) {
        return false;
    }
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

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

cleanupOldFiles($storageDir, TMP_FILE_TTL_SECONDS);

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
$url = trim((string) ($data['url'] ?? ''));
$format = strtolower(trim((string) ($data['format'] ?? 'mp4')));
$preferDirectLink = !isset($data['direct']) || (bool) $data['direct'];

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    jsonResponse(['ok' => false, 'error' => 'Некорректная ссылка'], 422);
}

$host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
$allowedHosts = [
    'ok.ru', 'www.ok.ru',
    'vk.com', 'www.vk.com',
    'rutube.ru', 'www.rutube.ru',
    'dzen.ru', 'www.dzen.ru',
    'yandex.ru', 'yandex.by', 'ya.ru', 'www.ya.ru',
];

$allowed = false;
foreach ($allowedHosts as $allowedHost) {
    if (hostMatches($host, $allowedHost)) {
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

$ytDlpPath = commandExists('yt-dlp');
if ($ytDlpPath === '') {
    jsonResponse(['ok' => false, 'error' => 'yt-dlp не найден на сервере'], 500);
}

if ($preferDirectLink && $format === 'mp4') {
    $directUrl = tryResolveDirectUrlViaLibrary($url) ?? tryResolveDirectUrlViaCli($ytDlpPath, $url);
    if ($directUrl !== null) {
        jsonResponse([
            'ok' => true,
            'mode' => 'direct',
            'download_url' => $directUrl,
            'filename' => 'video.mp4',
            'message' => 'Выдана прямая ссылка без сохранения файла на сервере.',
        ]);
    }
}

$token = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$template = $storageDir . '/' . $token . '_%(title).80B.%(ext)s';

if ($format === 'mp3' && class_exists('FFMpeg\\FFMpeg')) {
    $cmd = sprintf(
        '%s --no-playlist --no-warnings --restrict-filenames -f "bestaudio/b" -o %s %s 2>&1',
        escapeshellarg($ytDlpPath),
        escapeshellarg($template),
        escapeshellarg($url)
    );
} elseif ($format === 'mp3') {
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
    jsonResponse(['ok' => false, 'error' => 'Ошибка yt-dlp: ' . implode("\n", array_slice($output, -5))], 500);
}

$files = glob($storageDir . '/' . $token . '_*') ?: [];
if (!$files) {
    jsonResponse(['ok' => false, 'error' => 'Файл не найден после скачивания'], 500);
}

usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
$filePath = $files[0];

if ($format === 'mp3' && class_exists('FFMpeg\\FFMpeg') && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'mp3') {
    $mp3Path = preg_replace('/\.[^.]+$/', '.mp3', $filePath) ?: ($filePath . '.mp3');
    if (convertToMp3WithPhpFfmpeg($filePath, $mp3Path)) {
        @unlink($filePath);
        $filePath = $mp3Path;
    }
}

$file = basename($filePath);

jsonResponse([
    'ok' => true,
    'mode' => 'server',
    'filename' => $file,
    'download_url' => 'api.php?file=' . rawurlencode($file),
    'message' => 'Файл временно сохранён на сервере для скачивания.',
]);
