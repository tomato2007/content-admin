<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posting_history', function (Blueprint $table): void {
            $table->foreignId('planned_post_id')
                ->nullable()
                ->after('platform_account_id')
                ->constrained('planned_posts')
                ->nullOnDelete();
            $table->string('attempt_type')->default('manual')->after('status');
            $table->foreignId('triggered_by')->nullable()->after('provider_message_id')->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->after('triggered_by');

            $table->index(['planned_post_id', 'created_at']);
            $table->index(['idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('posting_history', function (Blueprint $table): void {
            $table->dropIndex(['planned_post_id', 'created_at']);
            $table->dropIndex(['idempotency_key']);
            $table->dropConstrainedForeignId('planned_post_id');
            $table->dropConstrainedForeignId('triggered_by');
            $table->dropColumn(['attempt_type', 'idempotency_key']);
        });
    }
};
