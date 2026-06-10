<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\EmailSendLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TemplatedEmailService
{
    public ?string $lastError = null;

    /**
     * @param  array<string, string>  $variableValues
     * @param  array<int, string>|null  $attachmentStoragePaths  Paths relative to storage disk (Filament FileUpload)
     * @return array{sent: int, failed: int, errors: array<int, string>, log: EmailSendLog, subject: string, body: string, recipients: array<int, string>}
     */
    public function send(
        ?EmailTemplate $template,
        string|array $recipientsInput,
        array $variableValues = [],
        ?User $sender = null,
        ?string $customSubject = null,
        ?string $customBody = null,
        ?array $attachmentStoragePaths = null,
        string $attachmentDisk = 'local',
        ?int $recurringScheduleId = null,
    ): array {
        $recipients = $this->parseRecipients($recipientsInput);

        if ($recipients === []) {
            $this->lastError = 'Chưa có địa chỉ email người nhận hợp lệ.';

            return [
                'sent' => 0,
                'failed' => 0,
                'errors' => [$this->lastError],
                'log' => new EmailSendLog(),
                'subject' => '',
                'body' => '',
                'recipients' => [],
            ];
        }

        if ($template) {
            $subject = $template->renderSubject($variableValues);
            $body = $template->renderBody($variableValues);
            $templateName = $template->name;
            $templateId = $template->id;
        } else {
            $subject = trim((string) $customSubject);
            $body = EmailTemplate::formatBodyForEmail(trim((string) $customBody));
            $templateName = 'Gửi thủ công';
            $templateId = null;
            $variableValues = [];
        }

        $mergedStoragePaths = $this->mergeAttachmentStoragePaths(
            $template?->attachmentStoragePaths() ?? [],
            $attachmentStoragePaths ?? [],
        );

        return $this->sendRendered(
            recipients: $recipients,
            subject: $subject,
            body: $body,
            templateName: $templateName,
            templateId: $templateId,
            variableValues: $variableValues,
            sender: $sender,
            attachmentStoragePaths: $mergedStoragePaths,
            attachmentDisk: $attachmentDisk,
            recurringScheduleId: $recurringScheduleId,
        );
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, string>  $variableValues
     * @param  array<int, string>  $attachmentStoragePaths
     * @return array{sent: int, failed: int, errors: array<int, string>, log: EmailSendLog, subject: string, body: string, recipients: array<int, string>}
     */
    public function sendRendered(
        array $recipients,
        string $subject,
        string $body,
        string $templateName,
        ?int $templateId,
        array $variableValues = [],
        ?User $sender = null,
        array $attachmentStoragePaths = [],
        string $attachmentDisk = 'local',
        ?int $recurringScheduleId = null,
    ): array {
        $attachmentPaths = $this->resolveAttachmentPaths($attachmentStoragePaths, $attachmentDisk);
        $attachmentNames = $this->attachmentNamesForLog($attachmentStoragePaths);

        if ($sender?->id) {
            MailSettings::applyForUser($sender->id);
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new TemplateMail($subject, $body, $attachmentPaths));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $message = $email.': '.$e->getMessage();
                $errors[] = $message;
                Log::warning('TemplatedEmailService send failed', [
                    'email' => $email,
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $log = EmailSendLog::query()->create([
            'email_template_id' => $templateId,
            'template_name' => $templateName,
            'recipients' => $recipients,
            'variable_values' => $variableValues !== [] ? $variableValues : null,
            'subject' => $subject,
            'body' => $body,
            'attachments' => $attachmentNames !== [] ? $attachmentNames : null,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'errors' => $errors !== [] ? $errors : null,
            'user_id' => $sender?->id,
            'email_recurring_schedule_id' => $recurringScheduleId,
        ]);

        if ($sent === 0 && $failed > 0) {
            $this->lastError = 'Không gửi được email nào. '.implode(' | ', array_slice($errors, 0, 3));
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
            'log' => $log,
            'subject' => $subject,
            'body' => $body,
            'recipients' => $recipients,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function parseRecipients(string|array $input): array
    {
        $parts = is_array($input)
            ? $input
            : (preg_split('/[\s,;]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $emails = [];
        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param  array<int, string>  $templatePaths
     * @param  array<int, string>  $sendPaths
     * @return array<int, string>
     */
    public function mergeAttachmentStoragePaths(array $templatePaths, array $sendPaths): array
    {
        return collect(array_merge($templatePaths, $sendPaths))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $storagePaths
     * @return array<int, string>
     */
    public function resolveAttachmentPaths(?array $storagePaths, string $disk = 'local'): array
    {
        if ($storagePaths === null || $storagePaths === []) {
            return [];
        }

        $paths = [];

        foreach ($storagePaths as $relativePath) {
            if (! is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $fullPath = Storage::disk($disk)->path($relativePath);

            if (is_file($fullPath)) {
                $paths[] = $fullPath;
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, string>|null  $storagePaths
     * @return array<int, string>
     */
    public function attachmentNamesForLog(?array $storagePaths): array
    {
        if ($storagePaths === null || $storagePaths === []) {
            return [];
        }

        return collect($storagePaths)
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => basename($path))
            ->values()
            ->all();
    }
}
