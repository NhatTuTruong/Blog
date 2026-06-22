<?php

$artisan = dirname(__DIR__).'/artisan';

if (! is_file($artisan)) {
    exit(0);
}

passthru(PHP_BINARY.' '.escapeshellarg($artisan).' social:media-encoder-install --if-missing', $exitCode);
exit($exitCode);
