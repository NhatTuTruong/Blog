<?php



namespace App\Http\Controllers;



use App\Models\Blog;

use Illuminate\Http\Response;

use Illuminate\Support\Facades\Cache;



class SitemapController extends Controller

{

    /**

     * Generate sitemap.xml for SEO.

     */

    public function index(): Response

    {

        $xml = Cache::remember('site.sitemap.xml', 3600, function (): string {

            return $this->buildSitemapXml();

        });



        return response($xml, 200, [

            'Content-Type' => 'application/xml',

            'Cache-Control' => 'public, max-age=3600',

        ]);

    }



    protected function buildSitemapXml(): string

    {

        $base = rtrim(config('app.url'), '/');



        $urls = [

            ['loc' => $base.'/', 'changefreq' => 'daily', 'priority' => '1.0'],

            ['loc' => $base.'/review', 'changefreq' => 'daily', 'priority' => '0.9'],

            ['loc' => $base.'/blogs', 'changefreq' => 'daily', 'priority' => '0.9'],

            ['loc' => $base.'/categories', 'changefreq' => 'weekly', 'priority' => '0.8'],

            ['loc' => $base.'/deals', 'changefreq' => 'daily', 'priority' => '0.85'],

            ['loc' => $base.'/about', 'changefreq' => 'monthly', 'priority' => '0.5'],

            ['loc' => $base.'/contact', 'changefreq' => 'monthly', 'priority' => '0.5'],

            ['loc' => $base.'/privacy', 'changefreq' => 'monthly', 'priority' => '0.4'],

            ['loc' => $base.'/cookie-policy', 'changefreq' => 'monthly', 'priority' => '0.4'],

            ['loc' => $base.'/terms', 'changefreq' => 'monthly', 'priority' => '0.4'],

        ];



        Blog::query()

            ->where('is_published', true)

            ->orderByDesc('updated_at')

            ->select(['slug', 'post_type', 'updated_at'])

            ->chunk(200, function ($posts) use (&$urls): void {

                foreach ($posts as $post) {

                    $urls[] = [

                        'loc' => $post->publicUrl(),

                        'changefreq' => 'weekly',

                        'priority' => '0.8',

                        'lastmod' => $post->updated_at?->toAtomString(),

                    ];

                }

            });



        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";



        foreach ($urls as $u) {

            $xml .= '  <url>'."\n";

            $xml .= '    <loc>'.htmlspecialchars($u['loc']).'</loc>'."\n";

            if (! empty($u['lastmod'])) {

                $xml .= '    <lastmod>'.$u['lastmod'].'</lastmod>'."\n";

            }

            $xml .= '    <changefreq>'.($u['changefreq'] ?? 'weekly').'</changefreq>'."\n";

            $xml .= '    <priority>'.($u['priority'] ?? '0.5').'</priority>'."\n";

            $xml .= '  </url>'."\n";

        }



        $xml .= '</urlset>';



        return $xml;

    }

}


