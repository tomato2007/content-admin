<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Features\Publishing\Application\Actions\DispatchScheduledPublishingAction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RunScheduledPublishingCommand extends Command
{
    protected $signature = 'publishing:run-scheduled
        {--limit=50 : Maximum number of due planned posts to enqueue}
        {--sync : Run publishing jobs inline instead of dispatching them to the queue}
        {--now= : Override the current timestamp for deterministic runs and tests}';

    protected $description = 'Dispatch publishing jobs for approved planned posts whose schedule is due.';

    public function handle(DispatchScheduledPublishingAction $dispatchScheduledPublishingAction): int
    {
        $nowOption = $this->option('now');
        $now = is_string($nowOption) && trim($nowOption) !== ''
            ? CarbonImmutable::parse($nowOption)
            : CarbonImmutable::now();

        $dispatchedCount = $dispatchScheduledPublishingAction->execute(
            $now,
            (int) $this->option('limit'),
            (bool) $this->option('sync'),
        );

        $mode = $this->option('sync') ? 'sync' : 'queued';
        $this->info(sprintf('Dispatched %d scheduled publish job(s) in %s mode.', $dispatchedCount, $mode));

        return self::SUCCESS;
    }
}
