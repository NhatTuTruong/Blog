<?php

namespace App\Http\Controllers;

use App\Support\SitemapGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Generate sitemap.xml for SEO.
     */
    public function index(SitemapGenerator $generator): Response
    {
        $xml = Cache::remember('site.sitemap.xml', 3600, function () use ($generator): string {
            return $generator->contentsForHttp();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
