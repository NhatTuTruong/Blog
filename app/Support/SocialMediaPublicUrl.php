<?php

namespace App\Support;

class SocialMediaPublicUrl
{
    /**
     * Build a publicly reachable URL for a file under public/storage.
     */
    public static function build(string $publicBaseUrl, string $storageRelativePath): string
    {
        $path = PublicStorage::normalizePath($storageRelativePath);
        PublicStorage::syncFromLegacy($path);

        return rtrim($publicBaseUrl, '/').'/storage/'.$path;
    }
}
