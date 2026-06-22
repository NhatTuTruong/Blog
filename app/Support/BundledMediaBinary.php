<?php

namespace App\Support;

use Symfony\Component\Process\Process;

class BundledMediaBinary
{
    public function mediaEncoderPath(): ?string
    {
        $configured = trim((string) config('social_media_video.media_encoder_binary', ''));

        if ($configured !== '') {
            return $this->isRunnable($configured) ? $configured : null;
        }

        foreach ($this->encoderCandidates() as $candidate) {
            if ($this->isRunnable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isEncoderAvailable(): bool
    {
        return $this->mediaEncoderPath() !== null;
    }

    /**
     * @return array<int, string>
     */
    protected function encoderCandidates(): array
    {
        $candidates = [
            base_path('bin/ffmpeg'),
            base_path('bin/linux/ffmpeg'),
            base_path('vendor/mathiasgrimm/laravel-cloud-binaries/bin/ffmpeg'),
        ];

        if (PHP_OS_FAMILY !== 'Windows') {
            $candidates[] = base_path('vendor/bin/ffmpeg');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = 'ffmpeg';
        }

        return $candidates;
    }

    protected function isRunnable(string $path): bool
    {
        if (! $this->looksLikePath($path) && ! str_contains($path, DIRECTORY_SEPARATOR)) {
            return $this->canExecuteCommand($path);
        }

        if (! is_file($path)) {
            return false;
        }

        if (PHP_OS_FAMILY !== 'Windows' && ! is_executable($path)) {
            return false;
        }

        return $this->canExecuteCommand($path);
    }

    protected function looksLikePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    protected function canExecuteCommand(string $command): bool
    {
        try {
            $process = new Process([$command, '-version']);
            $process->setTimeout(15);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
