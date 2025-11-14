<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexNowService
{
    protected $endpoint = 'https://api.indexnow.org/indexnow';

    public function submit(array $urls): array
    {
        $key = env('INDEXNOW_KEY');
        $host = env('INDEXNOW_HOST');
        $siteUrl = rtrim(env('APP_URL'), '/');

        if (!$key || !$host) {
            throw new \RuntimeException('IndexNow config missing.');
        }

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => "{$siteUrl}/{$key}.txt",
            'urlList' => array_values(array_unique($urls)),
        ];

        Log::info('IndexNow submit payload', ['count' => count($payload['urlList'])]);

        $response = Http::timeout(20)->post($this->endpoint, $payload);

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'ok' => $response->successful(),
        ];
    }

    /**
     * Submit all URLs discovered in a sitemap to the IndexNow API.
     */
    public function submitSitemap(string $sitemapUrl): array
    {
        $urls = $this->getUrlsFromSitemap($sitemapUrl);

        if (empty($urls)) {
            throw new \RuntimeException('No URLs found in sitemap.');
        }

        return $this->submit($urls);
    }

    /**
     * Fetch and parse a sitemap, returning a unique list of URLs.
     */
    public function getUrlsFromSitemap(string $sitemapUrl): array
    {
        Log::info('IndexNow fetching sitemap', ['url' => $sitemapUrl]);

        $response = Http::timeout(30)->get($sitemapUrl);

        if (!$response->ok()) {
            throw new \RuntimeException("Failed to fetch sitemap. Status: {$response->status()}");
        }

        $doc = new \DOMDocument();
        $libxmlPreviousState = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($response->body());
        libxml_use_internal_errors($libxmlPreviousState);

        if (!$loaded) {
            throw new \RuntimeException('Failed to parse sitemap XML.');
        }

        $urls = [];

        foreach ($doc->getElementsByTagName('loc') as $loc) {
            $url = trim($loc->nodeValue);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }
}