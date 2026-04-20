<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\PostingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_account_creation_and_plan_update_are_logged(): void
    {
        $user = User::factory()->create();
        $platform = Platform::query()->create([
            'key' => 'telegram',
            'name' => 'Telegram',
            'driver' => 'telegram',
            'is_enabled' => true,
        ]);

        $this->actingAs($user);

        $account = PlatformAccount::query()->create([
            'platform_id' => $platform->getKey(),
            'title' => 'Audit channel',
            'external_id' => 'audit-channel',
            'handle' => '@audit_channel',
            'is_enabled' => true,
            'settings' => [],
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'platform_account_created',
            'platform_account_id' => $account->getKey(),
        ]);

        /** @var PostingPlan $plan */
        $plan = $account->postingPlan()->firstOrFail();
        $plan->update([
            'timezone' => 'Europe/Kyiv',
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'posting_plan_updated',
            'platform_account_id' => $account->getKey(),
            'entity_id' => (string) $plan->getKey(),
        ]);

        $this->assertGreaterThanOrEqual(2, AdminAuditLog::query()->count());
    }
}
