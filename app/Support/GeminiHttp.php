<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GeminiHttp
{
    /**
     * HTTP client for Google Generative Language API.
     * Prefer IPv4 — some datacenters get HTTP 400 "location not supported" over IPv6.
     */
    public static function client(): PendingRequest
    {
        $request = Http::acceptJson()->asJson();

        if (! config('gemini.force_ipv4', true)) {
            return $request;
        }

        if (! defined('CURLOPT_IPRESOLVE') || ! defined('CURL_IPRESOLVE_V4')) {
            return $request;
        }

        return $request->withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ]);
    }
}
