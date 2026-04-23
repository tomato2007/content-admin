<?php

declare(strict_types=1);

namespace App\Features\PostsSource\Application\Services;

use App\Enums\ModerationStatus;
use App\Enums\PlannedPostStatus;
use App\Features\PostsSource\Application\Contracts\PostsRepository;
use App\Features\PostsSource\Application\Support\SourcePoolCandidate;
use App\Features\PostsSource\Application\Support\SourcePoolQuietHours;
use App\Features\PostsSource\Application\Support\SourcePostCandidateValidator;
use App\Features\PostsSource\Application\Support\SourcePostTextCleaner;
use App\Models\PlannedPost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SourcePoolService
{
    public function __construct(
        private readonly PostsRepository $postsRepository,
        private readonly SourcePostTextCleaner $textCleaner,
        private readonly SourcePostCandidateValidator $candidateValidator,
        private readonly SourcePoolQuietHours $quietHours,
    ) {}

    public function isWithinQuietHours(CarbonImmutable $nowUtc): bool
    {
        return $this->quietHours->isQuietPeriod(
            $nowUtc,
            (string) config('posts.scheduler.quiet_hours_start_utc', '00:00'),
            (string) config('posts.scheduler.quiet_hours_end_utc', '06:00'),
        );
    }

    public function pickCandidate(bool $preferMedia = false, ?CarbonImmutable $nowUtc = null): ?SourcePoolCandidate
    {
        $nowUtc ??= CarbonImmutable::now('UTC');

        if ($this->isWithinQuietHours($nowUtc)) {
            return null;
        }

        return DB::connection(config('posts.source.connection'))->transaction(function () use ($preferMedia): ?SourcePoolCandidate {
            $excludeSourceIds = $this->queuedSourceIds();

            $candidate = $this->pickEligibleCandidate(requireMedia: $preferMedia, excludeSourceIds: $excludeSourceIds);

            if ($candidate !== null) {
                return $candidate;
            }

            if (! $preferMedia) {
                return null;
            }

            return $this->pickEligibleCandidate(requireMedia: false, excludeSourceIds: $excludeSourceIds);
        });
    }

    /**
     * @param  list<int>  $excludeSourceIds
     */
    private function pickEligibleCandidate(bool $requireMedia, array $excludeSourceIds): ?SourcePoolCandidate
    {
        $attempted = $excludeSourceIds;

        while (true) {
            $post = $this->postsRepository->pickUnpublished($requireMedia, $attempted);

            if ($post === null) {
                return null;
            }

            $attempted[] = $post->id;
            $cleaned = $this->textCleaner->clean($post->content);

            if (! $this->candidateValidator->isEligible($cleaned)) {
                continue;
            }

            return new SourcePoolCandidate(
                sourcePost: $post,
                cleanedContent: $cleaned,
                hasMedia: $post->mediaUrl !== null,
            );
        }
    }

    /**
     * @return list<int>
     */
    private function queuedSourceIds(): array
    {
        return PlannedPost::query()
            ->where('source_type', 'posts_source')
            ->where(function ($query): void {
                $query->where('moderation_status', ModerationStatus::PendingReview)
                    ->orWhere(function ($scheduled): void {
                        $scheduled
                            ->where('moderation_status', ModerationStatus::Approved)
                            ->where('status', PlannedPostStatus::Scheduled);
                    });
            })
            ->whereNotNull('source_id')
            ->pluck('source_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();
    }
}
