<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostingPlanValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_plan_rejects_invalid_timezone(): void
    {
        $plan = $this->makePlan();

        $this->assertValidationError(function () use ($plan): void {
            $plan->update([
                'timezone' => 'Mars/Phobos',
            ]);
        }, 'timezone');
    }

    public function test_posting_plan_rejects_partial_quiet_hours(): void
    {
        $plan = $this->makePlan();

        $this->assertValidationError(function () use ($plan): void {
            $plan->update([
                'quiet_hours_from' => '23:00',
                'quiet_hours_to' => null,
            ]);
        }, 'quiet_hours_to');
    }

    public function test_posting_plan_rejects_equal_quiet_hours(): void
    {
        $plan = $this->makePlan();

        $this->assertValidationError(function () use ($plan): void {
            $plan->update([
                'quiet_hours_from' => '23:00',
                'quiet_hours_to' => '23:00',
            ]);
        }, 'quiet_hours_to');
    }

    public function test_posting_plan_allows_valid_overnight_quiet_hours(): void
    {
        $plan = $this->makePlan();

        $plan->update([
            'timezone' => 'Europe/Budapest',
            'quiet_hours_from' => '23:00',
            'quiet_hours_to' => '07:00',
        ]);

        $plan->refresh();

        $this->assertSame('Europe/Budapest', $plan->timezone);
        $this->assertSame('23:00:00', $plan->quiet_hours_from);
        $this->assertSame('07:00:00', $plan->quiet_hours_to);
    }

    public function test_posting_slot_rejects_duplicate_weekday_and_local_time(): void
    {
        $plan = $this->makePlan();

        $plan->postingSlots()->create([
            'weekday' => 1,
            'time_local' => '09:30:00',
            'is_enabled' => true,
        ]);

        $this->assertValidationError(function () use ($plan): void {
            $plan->postingSlots()->create([
                'weekday' => 1,
                'time_local' => '09:30:00',
                'is_enabled' => true,
            ]);
        }, 'time_local');
    }

    public function test_posting_slot_allows_same_time_on_different_weekdays(): void
    {
        $plan = $this->makePlan();

        $plan->postingSlots()->create([
            'weekday' => 1,
            'time_local' => '09:30:00',
            'is_enabled' => true,
        ]);

        $plan->postingSlots()->create([
            'weekday' => 2,
            'time_local' => '09:30:00',
            'is_enabled' => true,
        ]);

        $this->assertSame(2, $plan->postingSlots()->count());
    }

    private function makePlan()
    {
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Validation channel',
            'external_id' => 'validation-channel',
            'handle' => '@validation_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        return $account->postingPlan()->firstOrFail();
    }

    private function assertValidationError(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
