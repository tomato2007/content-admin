<?php

declare(strict_types=1);

namespace App\Services\Publishing\Contracts;

use App\Services\Publishing\Data\DryRunResult;
use App\Services\Publishing\Data\PublishRequest;
use App\Services\Publishing\Data\PublishResult;

interface PublisherDriverInterface
{
    public function dryRun(PublishRequest $request): DryRunResult;

    public function publish(PublishRequest $request): PublishResult;
}
