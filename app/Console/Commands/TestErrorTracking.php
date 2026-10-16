<?php

namespace App\Console\Commands;

use App\Support\ErrorTracking\SentryReporter;
use Illuminate\Console\Command;

class TestErrorTracking extends Command
{
    protected $signature = 'errors:test';

    protected $description = 'Send one test event to Sentry to check that error tracking is connected';

    public function handle(SentryReporter $reporter): int
    {
        $result = $reporter->sendTest();
        $this->line($result);

        return str_starts_with($result, 'HTTP 2') ? self::SUCCESS : self::FAILURE;
    }
}
