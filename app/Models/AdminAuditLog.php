<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform_account_id',
        'entity_type',
        'entity_id',
        'action',
        'before',
        'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platformAccount(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function logAction(
        string $action,
        ?int $userId,
        ?int $platformAccountId,
        string $entityType,
        int|string|null $entityId,
        ?array $before = null,
        ?array $after = null,
    ): void {
        if ($userId === null) {
            return;
        }

        self::query()->create([
            'user_id' => $userId,
            'platform_account_id' => $platformAccountId,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
