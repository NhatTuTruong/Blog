<?php

namespace App\Http\Controllers;

use App\Support\PublicStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogImageUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = PublicStorage::storeUploadedFile($file, 'blog-content', $file->hashName());
        PublicStorage::syncUploadedPath($path);

        return response()->json([
            'url' => PublicStorage::url($path),
        ]);
    }
}
