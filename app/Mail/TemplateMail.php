<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $attachmentPaths  Absolute paths on disk
     */
    public function __construct(
        public string $mailSubject,
        public string $htmlBody,
        public array $attachmentPaths = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.template',
            with: [
                'htmlBody' => $this->htmlBody,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->attachmentPaths as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $attachments[] = Attachment::fromPath($path);
        }

        return $attachments;
    }
}
