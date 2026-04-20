<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_account_id')->constrained()->cascadeOnDelete();
            $table->string('source_type')->default('manual');
            $table->string('source_id')->nullable();
            $table->json('content_snapshot')->nullable();
            $table->text('content_text')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('moderation_status')->default('pending_review');
            $table->foreignId('replace_of_id')->nullable()->constrained('planned_posts')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delete_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delete_confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['platform_account_id', 'status', 'scheduled_at']);
            $table->index(['platform_account_id', 'moderation_status']);
            $table->index(['replace_of_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_posts');
    }
};
