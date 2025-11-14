<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\IndexNowService;

class IndexNowController extends Controller
{
    protected $service;

    public function __construct(IndexNowService $service)
    {
        $this->service = $service;
    }

    // POST /api/indexnow
    public function submit(Request $request)
    {
        $data = $request->validate([
            'urlList' => 'required|array|min:1',
            'urlList.*' => 'required|string',
        ]);

        // Optional: add auth check for admin or internal use only
        // $this->authorize('submit-indexnow');

        try {
            $result = $this->service->submit($data['urlList']);
            return response()->json(['ok' => true, 'result' => $result], 200);
        } catch (\Throwable $e) {
            \Log::error('IndexNowController error: ' . $e->getMessage(), ['ex' => $e]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // POST /api/indexnow/submit-sitemap
    public function submitSitemap(Request $request)
    {
        $data = $request->validate([
            'sitemapUrl' => 'nullable|url',
        ]);

        $defaultSitemap = env('INDEXNOW_SITEMAP_URL');

        if (!$defaultSitemap) {
            $appUrl = rtrim((string) env('APP_URL', ''), '/');
            if ($appUrl !== '') {
                $defaultSitemap = $appUrl . '/sitemap.xml';
            }
        }

        $sitemapUrl = $data['sitemapUrl'] ?? $defaultSitemap;

        if (!$sitemapUrl) {
            return response()->json([
                'ok' => false,
                'error' => 'No sitemap URL provided. Set INDEXNOW_SITEMAP_URL or send sitemapUrl in the request.',
            ], 422);
        }

        try {
            $result = $this->service->submitSitemap($sitemapUrl);
            return response()->json(['ok' => true, 'result' => $result], 200);
        } catch (\Throwable $e) {
            \Log::error('IndexNowController sitemap error: ' . $e->getMessage(), [
                'sitemap_url' => $sitemapUrl,
                'ex' => $e,
            ]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
