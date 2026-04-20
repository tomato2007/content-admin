<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Services\Publishing\Contracts\PublisherDriverInterface;
use App\Services\Publishing\Data\DryRunResult;
use App\Services\Publishing\Data\PublishRequest;
use App\Services\Publishing\Data\PublishResult;
use Carbon\CarbonImmutable;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TelegramPublisherDriver implements PublisherDriverInterface
{
    private const SCRIPT_PATH = '/home/serg/.openclaw/workspace/scripts/publish_planned_telegram_post.py';

    public function dryRun(PublishRequest $request): DryRunResult
    {
        $payload = $this->runScript($request, true);

        return new DryRunResult(
            eligible: (bool) ($payload['eligible'] ?? false),
            mode: (string) ($payload['mode'] ?? 'none'),
            cleanedText: (string) ($payload['cleaned_text'] ?? ''),
            reason: $payload['reason'] ?? (($payload['eligible'] ?? false) ? null : ($payload['error'] ?? 'dry_run_failed')),
            meta: is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
        );
    }

    public function publish(PublishRequest $request): PublishResult
    {
        $payload = $this->runScript($request, false);
        $success = ($payload['status'] ?? null) === 'posted';

        return new PublishResult(
            success: $success,
            status: $success ? 'sent' : 'failed',
            providerMessageId: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
            payload: [
                'cleaned_text' => $payload['cleaned_text'] ?? null,
                'mode' => $payload['mode'] ?? null,
                'target' => $payload['target'] ?? null,
            ],
            response: $payload,
            error: $success ? null : (string) ($payload['error'] ?? $payload['reason'] ?? 'telegram_publish_failed'),
            sentAt: $success ? CarbonImmutable::now() : null,
            mode: (string) ($payload['mode'] ?? 'none'),
            externalReference: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function runScript(PublishRequest $request, bool $dryRun): array
    {
        $platformAccount = $request->platformAccount->loadMissing('platform');
        $plannedPost = $request->plannedPost;
        $channelKey = (string) ($platformAccount->settings['channel_key'] ?? $platformAccount->external_id);
        $command = [
            'python3',
            self::SCRIPT_PATH,
            '--channel-key', $channelKey,
            '--text', (string) $plannedPost->content_text,
        ];

        $sourceChat = $plannedPost->content_snapshot['source_chat']
            ?? $platformAccount->settings['source_chat']
            ?? null;
        $sourceMessageId = $plannedPost->content_snapshot['source_message_id']
            ?? null;

        if (is_string($sourceChat) && $sourceChat !== '') {
            $command[] = '--source-chat';
            $command[] = $sourceChat;
        }

        if ($sourceMessageId !== null && $sourceMessageId !== '') {
            $command[] = '--source-message-id';
            $command[] = (string) $sourceMessageId;
        }

        if ($dryRun) {
            $command[] = '--dry-run';
        }

        if ($request->force) {
            $command[] = '--force';
        }

        $env = array_filter([
            'PGHOST' => env('TELEGRAM_RUNTIME_DB_HOST', env('DB_HOST')),
            'PGPORT' => env('TELEGRAM_RUNTIME_DB_PORT', env('DB_PORT')),
            'PGDATABASE' => env('TELEGRAM_RUNTIME_DB_DATABASE', env('DB_DATABASE')),
            'PGUSER' => env('TELEGRAM_RUNTIME_DB_USERNAME', env('DB_USERNAME')),
            'PGPASSWORD' => env('TELEGRAM_RUNTIME_DB_PASSWORD', env('DB_PASSWORD')),
        ], static fn ($value) => $value !== null && $value !== '');

        $process = new Process($command, base_path(), $env, null, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());

            $decodedStdout = json_decode($stdout, true);
            if (is_array($decodedStdout)) {
                return array_merge($decodedStdout, [
                    'stderr' => $stderr,
                ]);
            }

            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return [
                'status' => 'failed',
                'error' => 'Invalid JSON from telegram publish script.',
                'raw_output' => $output,
            ];
        }

        return $decoded;
    }
}
