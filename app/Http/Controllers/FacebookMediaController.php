<?php

namespace App\Http\Controllers;

use App\Models\FacebookQueueItem;
use App\Services\FacebookPostMediaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FacebookMediaController extends Controller
{
    public function show(Request $request, FacebookQueueItem $item, FacebookPostMediaService $media): BinaryFileResponse
    {
        $expected = $media->mediaAccessToken($item);
        if (! hash_equals($expected, (string) $request->query('t', ''))) {
            abort(403);
        }

        $absolutePath = $media->resolveMediaAbsolutePath($item);

        return response()->file($absolutePath, [
            'Content-Type' => $media->mediaContentType($item),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
