<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_account_id')->constrained()->cascadeOnDelete();
            $table->string('timezone')->default('UTC');
            $table->time('quiet_hours_from')->nullable();
            $table->time('quiet_hours_to')->nullable();
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('platform_account_id');
            $table->index(['platform_account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_plans');
    }
};
