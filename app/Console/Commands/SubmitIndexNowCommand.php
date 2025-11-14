<?php

namespace App\Console\Commands;

use App\Services\IndexNowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubmitIndexNowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'indexnow:submit 
                            {--sitemap=https://urlmg.com/sitemap.xml : The sitemap URL to fetch}
                            {--urls= : Comma-separated list of URLs to submit (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit URLs to IndexNow API from sitemap or provided URLs';

    /**
     * Execute the console command.
     */
    public function handle(IndexNowService $service): int
    {
        $this->info('Starting IndexNow submission...');

        $urls = [];

        // If URLs are provided via option, use them
        if ($this->option('urls')) {
            $urls = array_filter(array_map('trim', explode(',', $this->option('urls'))));
            $this->info("Using provided URLs: " . count($urls));
        } else {
            // Otherwise, fetch from sitemap
            $sitemapUrl = $this->option('sitemap');
            $this->info("Fetching sitemap from: {$sitemapUrl}");

            try {
                $urls = $service->getUrlsFromSitemap($sitemapUrl);
                $this->info("Found " . count($urls) . " URLs in sitemap");
            } catch (\Throwable $e) {
                $this->error("Error fetching sitemap: " . $e->getMessage());
                Log::error('IndexNow sitemap fetch failed', [
                    'url' => $sitemapUrl,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return Command::FAILURE;
            }
        }

        if (empty($urls)) {
            $this->warn('No URLs found to submit.');
            Log::warning('IndexNow: no URLs found to submit');
            return Command::FAILURE;
        }

        // Remove duplicates
        $urls = array_values(array_unique($urls));
        $this->info("Submitting " . count($urls) . " unique URLs to IndexNow...");

        // IndexNow API accepts up to 10,000 URLs per request, but we'll chunk to 1000 for reliability
        $chunks = array_chunk($urls, 1000);
        $totalChunks = count($chunks);
        $successCount = 0;
        $failCount = 0;

        $bar = $this->output->createProgressBar($totalChunks);
        $bar->start();

        foreach ($chunks as $index => $batch) {
            try {
                $result = $service->submit($batch);

                if ($result['ok']) {
                    $successCount++;
                    $this->newLine();
                    $this->line("Batch " . ($index + 1) . "/{$totalChunks}: Successfully submitted " . count($batch) . " URLs");
                } else {
                    $failCount++;
                    $this->newLine();
                    $this->warn("Batch " . ($index + 1) . "/{$totalChunks}: Failed with status {$result['status']}");
                    Log::warning('IndexNow batch submission failed', [
                        'batch' => $index + 1,
                        'count' => count($batch),
                        'status' => $result['status'],
                        'response' => $result['body']
                    ]);
                }
            } catch (\Throwable $e) {
                $failCount++;
                $this->newLine();
                $this->error("Batch " . ($index + 1) . "/{$totalChunks}: Error - " . $e->getMessage());
                Log::error('IndexNow batch submission error', [
                    'batch' => $index + 1,
                    'count' => count($batch),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Submission complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total URLs', count($urls)],
                ['Batches', $totalChunks],
                ['Successful Batches', $successCount],
                ['Failed Batches', $failCount],
            ]
        );

        Log::info('IndexNow submission complete', [
            'total_urls' => count($urls),
            'total_batches' => $totalChunks,
            'successful_batches' => $successCount,
            'failed_batches' => $failCount
        ]);

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
