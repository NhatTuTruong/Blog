<?php

namespace App\Http\Controllers;

use App\Support\PublicStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class BlogContentMediaUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg'])
                    ->max(102400),
            ],
        ]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $isVideo = str_starts_with($mime, 'video/');
        $directory = $isVideo ? 'blog-content/videos' : 'blog-content';

        $path = PublicStorage::storeUploadedFile($file, $directory, $file->hashName());
        PublicStorage::syncUploadedPath($path);

        return response()->json([
            'url' => PublicStorage::url($path),
            'contentType' => $mime,
        ]);
    }
}
