<?php

namespace App\Jobs;

use App\Services\IndexNowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitIndexNowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $urlList;

    public function __construct(array $urlList)
    {
        $this->urlList = $urlList;
    }

    public function handle(IndexNowService $indexNow)
    {
        $indexNow->submit($this->urlList);
    }
}
