<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Enums\PlatformAccountRole;
use App\Enums\PostingHistoryStatus;
use App\Features\Publishing\Application\Orchestration\PublishPlannedPostOrchestrator;
use App\Models\PlannedPost;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingHistory;
use App\Models\User;
use App\Services\Publishing\Data\PublishRequest;
use App\Services\Publishing\PublishingService;
use App\Services\Publishing\TelegramBotApiClient;
use App\Services\Publishing\TelegramConfigValidator;
use App\Services\Publishing\TelegramPublisherDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_records_history_and_marks_post_as_published(): void
    {
        $user = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $account->users()->attach($user->getKey(), ['role' => PlatformAccountRole::Owner->value]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'scheduled_at' => now()->addHour(),
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        $result = app(PublishingService::class)->publish($post, $user->getKey(), false, 'manual', 'pub-test-1');

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);

        $post->refresh();
        $this->assertSame(PlannedPostStatus::Published, $post->status);

        $history = PostingHistory::query()->where('planned_post_id', $post->getKey())->first();
        $this->assertNotNull($history);
        $this->assertSame(PostingHistoryStatus::Sent, $history->status);
        $this->assertSame('manual', $history->attempt_type);
        $this->assertSame('pub-test-1', $history->idempotency_key);
        $this->assertSame($user->getKey(), $history->triggered_by);
    }

    public function test_publish_is_idempotent_for_same_key(): void
    {
        [$user, $post] = $this->makePublishablePost();

        $first = app(PublishPlannedPostOrchestrator::class)->execute($post, $user->getKey(), false, 'manual', 'pub-test-2');
        $second = app(PublishPlannedPostOrchestrator::class)->execute($post->fresh(), $user->getKey(), false, 'manual', 'pub-test-2');

        $this->assertTrue($first->success);
        $this->assertTrue($second->success);
        $this->assertSame($first->providerMessageId, $second->providerMessageId);
        $this->assertSame(1, PostingHistory::query()->where('planned_post_id', $post->getKey())->count());
    }

    public function test_publish_orchestrator_blocks_parallel_attempts_for_same_planned_post(): void
    {
        [$user, $post] = $this->makePublishablePost();
        $lock = Cache::lock('publishing:planned-post:'.$post->getKey(), 30);

        $this->assertTrue($lock->get());

        try {
            app(PublishPlannedPostOrchestrator::class)->execute($post, $user->getKey(), false, 'manual', 'pub-test-locked');
            $this->fail('Publish orchestrator should block while the lock is held.');
        } catch (RuntimeException $e) {
            $this->assertSame('Another publish attempt is already running for this planned post.', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_telegram_driver_can_parse_dry_run_json(): void
    {
        $driver = new class(app(TelegramBotApiClient::class)) extends TelegramPublisherDriver
        {
            public function __construct(TelegramBotApiClient $telegramBotApiClient)
            {
                parent::__construct($telegramBotApiClient);
            }

            protected function runScript(PublishRequest $request, bool $dryRun): array
            {
                return [
                    'status' => 'dry_run',
                    'eligible' => true,
                    'cleaned_text' => 'Clean text',
                    'mode' => 'text-card',
                    'meta' => ['target' => '@anecdots_mems'],
                ];
            }
        };

        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'status' => PlannedPostStatus::Draft,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        $result = $driver->dryRun(new PublishRequest($post, $account));

        $this->assertTrue($result->eligible);
        $this->assertSame('text-card', $result->mode);
        $this->assertSame('Clean text', $result->cleanedText);
    }

    public function test_telegram_driver_uses_connected_bot_for_dry_run_without_script(): void
    {
        config()->set('services.telegram.publish_script_path', null);
        config()->set('services.telegram.publisher_driver', 'real');

        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['target_chat_id' => '@target_channel'],
            'telegram_bot_token' => '123456:ABCDEF',
            'telegram_bot_user_id' => 123456789,
            'telegram_bot_username' => 'content_admin_bot',
            'telegram_bot_name' => 'Content Admin Bot',
            'telegram_bot_connected_at' => now(),
        ]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'status' => PlannedPostStatus::Draft,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        $result = app(PublishingService::class)->dryRun($post);

        $this->assertTrue($result->eligible);
        $this->assertSame('text', $result->mode);
        $this->assertSame('telegram_bot_api', $result->meta['driver'] ?? null);
        $this->assertSame('@target_channel', $result->meta['target'] ?? null);
    }

    public function test_telegram_driver_requires_configured_script_path_for_real_driver(): void
    {
        config()->set('services.telegram.publish_script_path', null);
        config()->set('services.telegram.publisher_driver', 'real');

        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'status' => PlannedPostStatus::Draft,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Telegram publish script path is not configured.');

        app(PublishingService::class)->dryRun($post);
    }

    public function test_telegram_driver_publishes_via_connected_bot_token(): void
    {
        config()->set('services.telegram.publish_script_path', null);
        config()->set('services.telegram.publisher_driver', 'real');

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 777,
                    'chat' => [
                        'id' => -1001234567890,
                        'title' => 'Target Channel',
                        'type' => 'channel',
                    ],
                ],
            ]),
        ]);

        [$user, $post] = $this->makePublishablePost([
            'settings' => ['target_chat_id' => '@target_channel'],
            'telegram_bot_token' => '123456:ABCDEF',
            'telegram_bot_user_id' => 123456789,
            'telegram_bot_username' => 'content_admin_bot',
            'telegram_bot_name' => 'Content Admin Bot',
            'telegram_bot_connected_at' => now(),
        ]);

        $result = app(PublishingService::class)->publish($post, $user->getKey(), false, 'manual', 'telegram-bot-publish-1');

        $this->assertTrue($result->success);
        $this->assertSame('777', $result->providerMessageId);
        $this->assertSame('telegram_bot_api', $result->response['driver'] ?? null);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bot123456:ABCDEF/sendMessage'
                && $request['chat_id'] === '@target_channel'
                && $request['text'] === 'Test publish body';
        });

        $history = PostingHistory::query()->where('planned_post_id', $post->getKey())->first();
        $this->assertNotNull($history);
        $this->assertSame(PostingHistoryStatus::Sent, $history->status);
        $this->assertSame('777', $history->provider_message_id);
    }

    public function test_vk_platform_uses_vk_stub_driver_even_when_telegram_real_driver_is_enabled(): void
    {
        config()->set('services.telegram.publish_script_path', null);
        config()->set('services.telegram.publisher_driver', 'real');

        $platform = Platform::query()->create([
            'key' => 'vk',
            'name' => 'VK',
            'driver' => 'vk',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'VK page',
            'external_id' => 'vk-page',
            'handle' => '@vk_page',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'VK post body',
            'status' => PlannedPostStatus::Draft,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        $result = app(PublishingService::class)->dryRun($post);

        $this->assertTrue($result->eligible);
        $this->assertSame('text', $result->mode);
        $this->assertSame('vk_stub', $result->meta['driver'] ?? null);
        $this->assertSame('vk', $result->meta['platform'] ?? null);
    }

    public function test_x_platform_uses_x_stub_driver(): void
    {
        $platform = Platform::query()->create([
            'key' => 'x',
            'name' => 'X',
            'driver' => 'x',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'X account',
            'external_id' => 'x-account',
            'handle' => '@x_account',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'X post body',
            'status' => PlannedPostStatus::Draft,
            'moderation_status' => ModerationStatus::PendingReview,
        ]);

        $result = app(PublishingService::class)->dryRun($post);

        $this->assertTrue($result->eligible);
        $this->assertSame('text', $result->mode);
        $this->assertSame('x_stub', $result->meta['driver'] ?? null);
        $this->assertSame('x', $result->meta['platform'] ?? null);
    }

    public function test_telegram_config_validator_uses_configured_runtime_connection_and_table(): void
    {
        $runtimeConnection = config('database.default');
        $runtimeTable = 'telegram_channel_configs_test';

        config()->set('services.telegram.runtime_connection', $runtimeConnection);
        config()->set('services.telegram.channel_config_table', $runtimeTable);

        Schema::create($runtimeTable, function ($table): void {
            $table->string('channel_key')->primary();
            $table->string('title')->nullable();
            $table->boolean('active')->default(true);
            $table->string('publish_mode')->nullable();
            $table->string('quiet_hours_start')->nullable();
            $table->string('quiet_hours_end')->nullable();
            $table->string('style_mode')->nullable();
        });

        DB::table($runtimeTable)->insert([
            'channel_key' => 'humor-story-mems-v2',
            'title' => '@anecdots_mems',
            'active' => true,
            'publish_mode' => 'text-card',
            'quiet_hours_start' => '00:00',
            'quiet_hours_end' => '06:00',
            'style_mode' => 'humor',
        ]);

        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ]);

        $result = app(TelegramConfigValidator::class)->validate($account->loadMissing('platform'));

        $this->assertSame('humor-story-mems-v2', $result['channel_key']);
        $this->assertSame('@anecdots_mems', $result['target']);
        $this->assertSame('text-card', $result['publish_mode']);
        $this->assertSame('humor', $result['style_mode']);
    }

    public function test_telegram_config_validator_supports_direct_bot_api_mode(): void
    {
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['target_chat_id' => '@target_channel'],
            'credentials_ref' => 'telegram-bot:content_admin_bot',
            'telegram_bot_token' => '123456:ABCDEF',
            'telegram_bot_user_id' => 123456789,
            'telegram_bot_username' => 'content_admin_bot',
            'telegram_bot_name' => 'Content Admin Bot',
            'telegram_bot_connected_at' => now(),
        ]);

        $result = app(TelegramConfigValidator::class)->validate($account->loadMissing('platform'));

        $this->assertSame('telegram_bot_api', $result['mode']);
        $this->assertSame('@target_channel', $result['target']);
        $this->assertSame('content_admin_bot', $result['bot_username']);
    }

    /**
     * @return array{0: User, 1: PlannedPost}
     */
    private function makePublishablePost(array $accountOverrides = []): array
    {
        $user = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create(array_merge([
            'platform_id' => $platform->getKey(),
            'title' => 'Channel',
            'external_id' => '@channel',
            'handle' => '@channel',
            'is_enabled' => true,
            'settings' => ['channel_key' => 'humor-story-mems-v2'],
        ], $accountOverrides));

        $account->users()->attach($user->getKey(), ['role' => PlatformAccountRole::Owner->value]);

        $post = PlannedPost::query()->create([
            'platform_account_id' => $account->getKey(),
            'source_type' => 'manual',
            'content_text' => 'Test publish body',
            'scheduled_at' => now()->addHour(),
            'status' => PlannedPostStatus::Scheduled,
            'moderation_status' => ModerationStatus::Approved,
        ]);

        return [$user, $post];
    }
}
