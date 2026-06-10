<?php

namespace App\Http\Controllers;

use App\Models\PinterestQueueItem;
use App\Services\PinterestPostMediaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PinterestMediaController extends Controller
{
    public function show(Request $request, PinterestQueueItem $item, PinterestPostMediaService $media): BinaryFileResponse
    {
        $expected = $media->mediaAccessToken($item);
        if (! hash_equals($expected, (string) $request->query('t', ''))) {
            abort(403);
        }

        $path = $media->ensureStoredJpegForItem($item);
        $absolutePath = $media->absolutePath($path);

        return response()->file($absolutePath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
