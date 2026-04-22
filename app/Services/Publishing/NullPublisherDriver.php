<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Services\Publishing\Contracts\PublisherDriverInterface;
use App\Services\Publishing\Data\DryRunResult;
use App\Services\Publishing\Data\PublishRequest;
use App\Services\Publishing\Data\PublishResult;
use Carbon\CarbonImmutable;

class NullPublisherDriver implements PublisherDriverInterface
{
    public function dryRun(PublishRequest $request): DryRunResult
    {
        $text = trim((string) $request->plannedPost->content_text);

        if ($text === '') {
            return new DryRunResult(
                eligible: false,
                mode: 'none',
                cleanedText: '',
                reason: 'Planned post has no content text.',
            );
        }

        return new DryRunResult(
            eligible: true,
            mode: 'text',
            cleanedText: $text,
            meta: [
                'driver' => $this->driverKey(),
                'platform' => $request->platformAccount->platform->driver ?? null,
                'force' => $request->force,
            ],
        );
    }

    public function publish(PublishRequest $request): PublishResult
    {
        $dryRun = $this->dryRun($request);

        if (! $dryRun->eligible) {
            return new PublishResult(
                success: false,
                status: 'failed',
                payload: [
                    'cleaned_text' => $dryRun->cleanedText,
                ],
                error: $dryRun->reason,
                mode: $dryRun->mode,
            );
        }

        return new PublishResult(
            success: true,
            status: 'sent',
            providerMessageId: 'stub-'.bin2hex(random_bytes(6)),
            payload: [
                'cleaned_text' => $dryRun->cleanedText,
                'attempt_type' => $request->attemptType,
                'force' => $request->force,
            ],
            response: [
                'driver' => $this->driverKey(),
                'message' => $this->successMessage(),
            ],
            sentAt: CarbonImmutable::now(),
            mode: $dryRun->mode,
            externalReference: $request->idempotencyKey,
        );
    }

    protected function driverKey(): string
    {
        return 'null';
    }

    protected function successMessage(): string
    {
        return 'Stub publish completed. Replace NullPublisherDriver with a real platform driver.';
    }
}
