<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('posting_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('time_local');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['posting_plan_id', 'weekday', 'time_local']);
            $table->index(['posting_plan_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_slots');
    }
};
