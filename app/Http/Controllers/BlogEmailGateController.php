<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogPostEmailUnlock;
use App\Services\GeoIpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogEmailGateController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $post = Blog::query()
            ->where('is_published', true)
            ->where('slug', $slug)
            ->ofType(Blog::TYPE_BLOG)
            ->firstOrFail();

        $ip = $request->ip();
        $country = app(GeoIpService::class)->getCountry((string) $ip);

        BlogPostEmailUnlock::query()->firstOrCreate(
            [
                'blog_id' => $post->id,
                'email' => mb_strtolower(trim($validated['email'])),
            ],
            [
                'ip_address' => $ip,
                'country' => $country,
                'user_agent' => (string) $request->userAgent(),
            ]
        );

        return response()->json([
            'ok' => true,
            'post_id' => $post->id,
        ]);
    }
}
