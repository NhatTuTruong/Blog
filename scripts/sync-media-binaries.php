<?php

$sourceDir = dirname(__DIR__).'/vendor/mathiasgrimm/laravel-cloud-binaries/bin';
$targetDir = dirname(__DIR__).'/bin';

if (! is_dir($sourceDir)) {
    exit(0);
}

if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
    fwrite(STDERR, "Cannot create bin directory.\n");
    exit(1);
}

foreach (['ffmpeg', 'ffprobe'] as $name) {
    $from = $sourceDir.'/'.$name;
    $to = $targetDir.'/'.$name;

    if (! is_file($from)) {
        fwrite(STDERR, "Missing bundled binary: {$from}\n");
        continue;
    }

    if (! is_file($to) || filesize($to) !== filesize($from)) {
        if (! copy($from, $to)) {
            fwrite(STDERR, "Failed to copy {$from} -> {$to}\n");
            continue;
        }
    }

    @chmod($to, 0755);
}
