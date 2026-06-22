<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BundledMediaBinary
{
    /** @var array<int, string> */
    public array $lastProbeNotes = [];

    public function mediaEncoderPath(): ?string
    {
        $this->lastProbeNotes = [];

        $configured = trim((string) config('social_media_video.media_encoder_binary', ''));

        if ($configured !== '') {
            return $this->resolveCandidate($configured, 'config');
        }

        foreach ($this->encoderCandidates() as $candidate) {
            $resolved = $this->resolveCandidate($candidate, 'auto');
            if ($resolved !== null) {
                return $resolved;
            }
        }

        Log::warning('BundledMediaBinary media encoder not found', [
            'notes' => $this->lastProbeNotes,
            'proc_open' => function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
        ]);

        return null;
    }

    public function isEncoderAvailable(): bool
    {
        return $this->mediaEncoderPath() !== null;
    }

    /**
     * @return array<int, string>
     */
    public function encoderCandidates(): array
    {
        $candidates = [
            base_path('bin/ffmpeg'),
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

    /**
     * @return array<int, array{path: string, exists: bool, executable: bool, runnable: bool, size: int|null, note: string}>
     */
    public function probeAllCandidates(): array
    {
        $results = [];

        $configured = trim((string) config('social_media_video.media_encoder_binary', ''));
        $paths = $configured !== '' ? [$configured] : $this->encoderCandidates();

        foreach ($paths as $path) {
            $beforeNotes = count($this->lastProbeNotes);
            $exists = $this->looksLikePath($path) || str_contains($path, DIRECTORY_SEPARATOR)
                ? is_file($path)
                : true;
            $size = ($exists && ($this->looksLikePath($path) || str_contains($path, DIRECTORY_SEPARATOR)) && is_file($path))
                ? (int) filesize($path)
                : null;
            $executable = is_file($path) ? is_executable($path) : false;
            $runnable = $this->canExecuteCommand($path);
            $notes = array_slice($this->lastProbeNotes, $beforeNotes);

            $results[] = [
                'path' => $path,
                'exists' => $exists,
                'executable' => $executable,
                'runnable' => $runnable,
                'size' => $size,
                'note' => implode(' | ', $notes),
            ];
        }

        return $results;
    }

    protected function resolveCandidate(string $path, string $source): ?string
    {
        if (! $this->looksLikePath($path) && ! str_contains($path, DIRECTORY_SEPARATOR)) {
            if ($this->canExecuteCommand($path)) {
                $this->lastProbeNotes[] = "{$source}: PATH command `{$path}` OK";

                return $path;
            }

            $this->lastProbeNotes[] = "{$source}: PATH command `{$path}` failed";

            return null;
        }

        if (! is_file($path)) {
            $this->lastProbeNotes[] = "{$source}: missing `{$path}`";

            return null;
        }

        $this->ensureExecutable($path);

        if ($this->canExecuteCommand($path)) {
            $this->lastProbeNotes[] = "{$source}: runnable `{$path}`";

            return $path;
        }

        if ($this->canTrustBundledBinary($path)) {
            $this->lastProbeNotes[] = "{$source}: trusted bundled binary `{$path}` (version probe skipped)";

            return $path;
        }

        $this->lastProbeNotes[] = "{$source}: present but not runnable `{$path}`";

        return null;
    }

    protected function ensureExecutable(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }

        if (is_executable($path)) {
            return;
        }

        @chmod($path, 0755);
    }

    protected function canTrustBundledBinary(string $path): bool
    {
        if ($this->canUseProcessProbe()) {
            return false;
        }

        $size = filesize($path);

        return is_int($size) && $size > 1_000_000;
    }

    protected function canUseProcessProbe(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        return ! in_array('proc_open', $disabled, true);
    }

    protected function looksLikePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    protected function canExecuteCommand(string $command): bool
    {
        if (! $this->canUseProcessProbe()) {
            return false;
        }

        try {
            $process = new Process([$command, '-version']);
            $process->setTimeout(15);
            $process->run();

            if ($process->isSuccessful()) {
                return true;
            }

            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            if ($error !== '') {
                $this->lastProbeNotes[] = "probe error for `{$command}`: {$error}";
            }

            return false;
        } catch (\Throwable $e) {
            $this->lastProbeNotes[] = "probe exception for `{$command}`: {$e->getMessage()}";

            return false;
        }
    }
}
