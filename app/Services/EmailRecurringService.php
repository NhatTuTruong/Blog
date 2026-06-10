<?php

namespace App\Services;

use App\Models\EmailRecurringSchedule;
use App\Models\EmailSendLog;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailRecurringService
{
    public ?string $lastError = null;

    public function __construct(
        protected TemplatedEmailService $emailService,
    ) {}

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, string>  $variableValues
     * @param  array<int, string>  $extraAttachmentPaths
     */
    public function createSchedule(
        ?EmailTemplate $template,
        array $recipients,
        array $variableValues,
        string $subject,
        string $body,
        array $extraAttachmentPaths,
        int $intervalHours,
        ?User $sender = null,
    ): EmailRecurringSchedule {
        $persistedPaths = $this->persistExtraAttachments($extraAttachmentPaths);

        return EmailRecurringSchedule::query()->create([
            'email_template_id' => $template?->id,
            'template_name' => $template?->name ?? 'Gửi thủ công',
            'recipients' => $recipients,
            'variable_values' => $variableValues !== [] ? $variableValues : null,
            'subject' => $subject,
            'body' => $body,
            'extra_attachment_paths' => $persistedPaths !== [] ? $persistedPaths : null,
            'interval_hours' => max(1, min(8760, $intervalHours)),
            'next_send_at' => now()->addHours(max(1, min(8760, $intervalHours))),
            'send_count' => 0,
            'user_id' => $sender?->id,
        ]);
    }

    public function linkLogToSchedule(EmailSendLog $log, EmailRecurringSchedule $schedule): void
    {
        $log->update(['email_recurring_schedule_id' => $schedule->id]);

        $schedule->update([
            'send_count' => 1,
            'last_sent_at' => now(),
        ]);
    }

    public function stopSchedule(EmailRecurringSchedule $schedule): void
    {
        $schedule->stop();
    }

    public function processDueSchedules(): int
    {
        $processed = 0;

        EmailRecurringSchedule::query()
            ->whereNull('stopped_at')
            ->where('next_send_at', '<=', now())
            ->orderBy('next_send_at')
            ->each(function (EmailRecurringSchedule $schedule) use (&$processed): void {
                if ($this->processSchedule($schedule)) {
                    $processed++;
                }
            });

        return $processed;
    }

    public function processSchedule(EmailRecurringSchedule $schedule): bool
    {
        $this->lastError = null;

        if (! $schedule->isActive()) {
            return false;
        }

        if ($schedule->next_send_at->isFuture()) {
            return false;
        }

        $result = $this->resendFromSchedule($schedule);

        $schedule->refresh();

        if ($result['sent'] === 0) {
            $schedule->update(['stopped_at' => now()]);
            $this->lastError = $this->emailService->lastError ?? 'Gửi lại thất bại — đã dừng lịch.';

            return true;
        }

        $schedule->update([
            'send_count' => $schedule->send_count + 1,
            'last_sent_at' => now(),
            'next_send_at' => now()->addHours(max(1, (int) $schedule->interval_hours)),
        ]);

        return true;
    }

    /**
     * @return array{sent: int, failed: int, errors: array<int, string>, log: EmailSendLog}
     */
    public function resendFromSchedule(EmailRecurringSchedule $schedule): array
    {
        $template = $schedule->email_template_id
            ? EmailTemplate::query()->find($schedule->email_template_id)
            : null;

        $templatePaths = $template?->attachmentStoragePaths() ?? [];
        $extraPaths = $schedule->extraAttachmentStoragePaths();

        $sender = $schedule->user;

        $result = $this->emailService->sendRendered(
            recipients: EmailSendLog::normalizeArray($schedule->recipients),
            subject: (string) $schedule->subject,
            body: (string) $schedule->body,
            templateName: (string) $schedule->template_name,
            templateId: $schedule->email_template_id,
            variableValues: is_array($schedule->variable_values) ? $schedule->variable_values : [],
            sender: $sender,
            attachmentStoragePaths: $this->emailService->mergeAttachmentStoragePaths($templatePaths, $extraPaths),
            recurringScheduleId: $schedule->id,
        );

        return $result;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    protected function persistExtraAttachments(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $directory = 'email-recurring/'.Str::uuid();
        $persisted = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            if (! Storage::disk('local')->exists($path)) {
                continue;
            }

            $target = $directory.'/'.basename($path);
            Storage::disk('local')->copy($path, $target);
            $persisted[] = $target;
        }

        return $persisted;
    }

    public function deleteScheduleAttachments(EmailRecurringSchedule $schedule): void
    {
        $paths = $schedule->extraAttachmentStoragePaths();

        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }
}
