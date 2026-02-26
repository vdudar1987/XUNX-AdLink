<?php

declare(strict_types=1);

$storageDir = dirname(__DIR__) . '/public/storage';
$ttl = isset($argv[1]) ? max(60, (int) $argv[1]) : 3600;

if (!is_dir($storageDir)) {
    fwrite(STDOUT, "storage directory not found: {$storageDir}\n");
    exit(0);
}

$now = time();
$removed = 0;

foreach (glob($storageDir . '/*') ?: [] as $file) {
    if (!is_file($file)) {
        continue;
    }

    $mtime = filemtime($file);
    if ($mtime === false) {
        continue;
    }

    if (($now - $mtime) > $ttl && @unlink($file)) {
        $removed++;
    }
}

fwrite(STDOUT, "Removed {$removed} files older than {$ttl} seconds.\n");
