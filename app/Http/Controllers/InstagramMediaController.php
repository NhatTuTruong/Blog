<?php

namespace App\Http\Controllers;

use App\Models\InstagramQueueItem;
use App\Services\InstagramPostImageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstagramMediaController extends Controller
{
    public function show(Request $request, InstagramQueueItem $item, InstagramPostImageService $images): BinaryFileResponse
    {
        $expected = $images->mediaAccessToken($item);
        if (! hash_equals($expected, (string) $request->query('t', ''))) {
            abort(403);
        }

        $absolutePath = $images->resolveMediaAbsolutePath($item);

        return response()->file($absolutePath, [
            'Content-Type' => $images->mediaContentType($item),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
