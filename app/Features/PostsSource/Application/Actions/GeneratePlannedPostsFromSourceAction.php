<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Application\Actions;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Features\PostsSource\Application\Services\SourcePoolService;
use App\Models\AdminAuditLog;
use App\Models\PlannedPost;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GeneratePlannedPostsFromSourceAction
{
    public function __construct(
        private readonly SourcePoolService $sourcePoolService,
    ) {}

    /**
     * @return array{created:int, skipped:int, no_candidates:int, created_ids:list<int>}
     */
    public function execute(PlatformAccount $platformAccount, User $actor, int $limit = 10, bool $preferMedia = false): array
    {
        $limit = max(1, $limit);
        $createdIds = [];
        $skipped = 0;
        $noCandidates = 0;

        for ($index = 0; $index < $limit; $index++) {
            $candidate = $this->sourcePoolService->pickCandidate($preferMedia, CarbonImmutable::now('UTC'));

            if ($candidate === null) {
                $noCandidates++;
                break;
            }

            $plannedPost = DB::transaction(function () use ($platformAccount, $candidate): PlannedPost {
                return PlannedPost::query()->create([
                    'platform_account_id' => $platformAccount->getKey(),
                    'source_type' => 'posts_source',
                    'source_id' => (string) $candidate->sourcePost->id,
                    'content_snapshot' => [
                        'source_post_id' => $candidate->sourcePost->id,
                        'source_media_url' => $candidate->sourcePost->mediaUrl,
                        'source_published_at' => $candidate->sourcePost->publishedAt?->toIso8601String(),
                        'cleaned_content' => $candidate->cleanedContent,
                        'generated_from' => 'posts_source',
                    ],
                    'content_text' => $candidate->cleanedContent,
                    'status' => PlannedPostStatus::Draft,
                    'moderation_status' => ModerationStatus::PendingReview,
                ]);
            });

            $createdIds[] = $plannedPost->getKey();
        }

        AdminAuditLog::logAction(
            action: 'planned_posts_generated_from_source',
            userId: $actor->getKey(),
            platformAccountId: $platformAccount->getKey(),
            entityType: PlatformAccount::class,
            entityId: $platformAccount->getKey(),
            before: null,
            after: [
                'created' => count($createdIds),
                'skipped' => $skipped,
                'no_candidates' => $noCandidates,
                'created_ids' => $createdIds,
                'source_type' => 'posts_source',
                'batch_limit' => $limit,
            ],
        );

        return [
            'created' => count($createdIds),
            'skipped' => $skipped,
            'no_candidates' => $noCandidates,
            'created_ids' => $createdIds,
        ];
    }
}
