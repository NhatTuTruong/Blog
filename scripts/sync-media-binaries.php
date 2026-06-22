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
        continue;
    }

    if (! is_file($to) || filesize($to) !== filesize($from)) {
        copy($from, $to);
    }

    @chmod($to, 0755);
}
