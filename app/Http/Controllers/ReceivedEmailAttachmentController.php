<?php

namespace App\Http\Controllers;

use App\Models\ReceivedEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivedEmailAttachmentController extends Controller
{
    public function show(Request $request, ReceivedEmail $receivedEmail, string $attachment): BinaryFileResponse
    {
        $this->ensureAdmin($request);

        $meta = $receivedEmail->findAttachment($attachment);
        abort_unless($meta !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($meta['path']), 404);

        $filename = $meta['name'] ?? basename($meta['path']);
        $contentType = $meta['content_type'] ?? 'application/octet-stream';

        return response()->file($disk->path($meta['path']), [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
        ]);
    }

    public function download(Request $request, ReceivedEmail $receivedEmail, string $attachment): StreamedResponse
    {
        $this->ensureAdmin($request);

        $meta = $receivedEmail->findAttachment($attachment);
        abort_unless($meta !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($meta['path']), 404);

        $filename = $meta['name'] ?? basename($meta['path']);

        return $disk->download($meta['path'], $filename);
    }

    protected function ensureAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);
    }
}
