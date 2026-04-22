<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table): void {
            $table->text('telegram_bot_token')->nullable()->after('credentials_ref');
            $table->unsignedBigInteger('telegram_bot_user_id')->nullable()->after('telegram_bot_token');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_user_id');
            $table->string('telegram_bot_name')->nullable()->after('telegram_bot_username');
            $table->timestamp('telegram_bot_connected_at')->nullable()->after('telegram_bot_name');
        });
    }

    public function down(): void
    {
        Schema::table('platform_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_bot_token',
                'telegram_bot_user_id',
                'telegram_bot_username',
                'telegram_bot_name',
                'telegram_bot_connected_at',
            ]);
        });
    }
};
