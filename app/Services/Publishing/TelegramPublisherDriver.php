<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Services\Publishing\Contracts\PublisherDriverInterface;
use App\Services\Publishing\Data\DryRunResult;
use App\Services\Publishing\Data\PublishRequest;
use App\Services\Publishing\Data\PublishResult;
use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TelegramPublisherDriver implements PublisherDriverInterface
{
    public function __construct(
        private readonly TelegramBotApiClient $telegramBotApiClient,
    ) {}

    public function dryRun(PublishRequest $request): DryRunResult
    {
        if ($this->usesDirectBotApi($request)) {
            return $this->dryRunViaBotApi($request);
        }

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
        if ($this->usesDirectBotApi($request)) {
            return $this->publishViaBotApi($request);
        }

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

    private function dryRunViaBotApi(PublishRequest $request): DryRunResult
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

        try {
            $targetChatId = $this->directTargetChatId($request);
        } catch (RuntimeException $exception) {
            return new DryRunResult(
                eligible: false,
                mode: 'none',
                cleanedText: $text,
                reason: $exception->getMessage(),
                meta: [
                    'driver' => 'telegram_bot_api',
                ],
            );
        }

        return new DryRunResult(
            eligible: true,
            mode: 'text',
            cleanedText: $text,
            meta: [
                'driver' => 'telegram_bot_api',
                'target' => $targetChatId,
                'bot_username' => $request->platformAccount->telegram_bot_username,
                'force' => $request->force,
            ],
        );
    }

    private function publishViaBotApi(PublishRequest $request): PublishResult
    {
        $dryRun = $this->dryRunViaBotApi($request);

        if (! $dryRun->eligible) {
            return new PublishResult(
                success: false,
                status: 'failed',
                payload: [
                    'cleaned_text' => $dryRun->cleanedText,
                    'target' => $dryRun->meta['target'] ?? null,
                ],
                response: [
                    'driver' => 'telegram_bot_api',
                ],
                error: $dryRun->reason,
                mode: $dryRun->mode,
            );
        }

        try {
            $message = $this->telegramBotApiClient->sendMessage(
                $this->botToken($request),
                $this->directTargetChatId($request),
                $dryRun->cleanedText,
            );
        } catch (RuntimeException $exception) {
            return new PublishResult(
                success: false,
                status: 'failed',
                payload: [
                    'cleaned_text' => $dryRun->cleanedText,
                    'target' => $dryRun->meta['target'] ?? null,
                ],
                response: [
                    'driver' => 'telegram_bot_api',
                    'error' => $exception->getMessage(),
                ],
                error: $exception->getMessage(),
                mode: $dryRun->mode,
            );
        }

        return new PublishResult(
            success: true,
            status: 'sent',
            providerMessageId: (string) $message['message_id'],
            payload: [
                'cleaned_text' => $dryRun->cleanedText,
                'mode' => $dryRun->mode,
                'target' => $dryRun->meta['target'] ?? null,
            ],
            response: [
                'driver' => 'telegram_bot_api',
                'message_id' => $message['message_id'],
                'chat' => $message['chat'],
            ],
            sentAt: CarbonImmutable::now(),
            mode: $dryRun->mode,
            externalReference: (string) $message['message_id'],
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
            $this->scriptPath(),
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

        $process = new Process($command, base_path(), $this->runtimeEnvironment(), null, 120);
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

    private function usesDirectBotApi(PublishRequest $request): bool
    {
        return $request->platformAccount->hasConnectedTelegramBot();
    }

    private function botToken(PublishRequest $request): string
    {
        $token = trim((string) $request->platformAccount->telegram_bot_token);

        if ($token === '') {
            throw new RuntimeException('Telegram bot token is not connected for this platform account.');
        }

        return $token;
    }

    private function directTargetChatId(PublishRequest $request): string
    {
        $targetChatId = trim((string) ($request->platformAccount->settings['target_chat_id'] ?? ''));

        if ($targetChatId === '') {
            throw new RuntimeException('Missing settings.target_chat_id for direct Telegram bot publishing.');
        }

        return $targetChatId;
    }

    protected function scriptPath(): string
    {
        $scriptPath = (string) config('services.telegram.publish_script_path', '');

        if ($scriptPath === '') {
            throw new RuntimeException('Telegram publish script path is not configured. Set TELEGRAM_PUBLISH_SCRIPT_PATH or keep TELEGRAM_PUBLISHER_DRIVER=null for local development.');
        }

        if (! is_file($scriptPath)) {
            throw new RuntimeException(sprintf('Telegram publish script not found at [%s].', $scriptPath));
        }

        return $scriptPath;
    }

    /**
     * @return array<string, string>
     */
    protected function runtimeEnvironment(): array
    {
        $connectionName = (string) config('services.telegram.runtime_connection', 'telegram_runtime');
        $connection = config(sprintf('database.connections.%s', $connectionName));

        if (! is_array($connection)) {
            throw new RuntimeException(sprintf('Telegram runtime database connection [%s] is not configured.', $connectionName));
        }

        $environment = array_filter([
            'PGHOST' => $connection['host'] ?? null,
            'PGPORT' => isset($connection['port']) ? (string) $connection['port'] : null,
            'PGDATABASE' => $connection['database'] ?? null,
            'PGUSER' => $connection['username'] ?? null,
            'PGPASSWORD' => $connection['password'] ?? null,
            'PGSSLMODE' => $connection['sslmode'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $searchPath = $connection['search_path'] ?? null;

        if (is_string($searchPath) && $searchPath !== '') {
            $environment['PGOPTIONS'] = sprintf('-c search_path=%s', $searchPath);
        }

        return $environment;
    }
}
