<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// SEO: robots.txt (dynamic so Sitemap URL matches app.url)
Route::get('/robots.txt', function () {
    $sitemap = url('/sitemap.xml');
    $body = "User-agent: *\nAllow: /\n\nSitemap: {$sitemap}\n";
    return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

// SEO: sitemap.xml
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// Simple health check endpoint
Route::get('/health', function () {
    $status = [
        'app' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ];

    try {
        DB::connection()->getPdo();
        $status['db'] = 'ok';
    } catch (\Throwable $e) {
        $status['db'] = 'fail';
    }

    return response()->json($status, $status['db'] === 'ok' ? 200 : 500);
})->name('health');

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/blog', [App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [App\Http\Controllers\BlogController::class, 'show'])->name('blog.show')->where('slug', '[a-z0-9\-]+');

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Legal Pages
Route::get('/about', function () {
    return view('legal.about');
})->name('legal.about');

Route::get('/contact', function () {
    return view('legal.contact');
})->name('legal.contact');

Route::get('/privacy', function () {
    return view('legal.privacy');
})->name('legal.privacy');

Route::post('/admin/blog/upload-image', [App\Http\Controllers\BlogImageUploadController::class, 'upload'])
    ->middleware(['web', 'auth'])
    ->name('blog.upload-image');

Route::middleware(['web', 'auth'])->prefix('admin/received-emails')->group(function () {
    Route::get('{receivedEmail}/attachments/{attachment}', [App\Http\Controllers\ReceivedEmailAttachmentController::class, 'show'])
        ->name('admin.received-emails.attachments.show');
    Route::get('{receivedEmail}/attachments/{attachment}/download', [App\Http\Controllers\ReceivedEmailAttachmentController::class, 'download'])
        ->name('admin.received-emails.attachments.download');
});

Route::get('/instagram/media/{item}', [App\Http\Controllers\InstagramMediaController::class, 'show'])
    ->name('instagram.media');

Route::get('/facebook/media/{item}', [App\Http\Controllers\FacebookMediaController::class, 'show'])
    ->name('facebook.media');

Route::get('/terms', function () {
    return view('legal.terms');
})->name('legal.terms');

Route::get('/cookie-policy', function () {
    return view('legal.cookie');
})->name('legal.cookie');
