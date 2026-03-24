<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentControl extends Model
{
    protected $fillable = ['channel', 'action', 'reason', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function isAgentPaused(string $channel = 'general'): bool
    {
        $last = static::where('channel', $channel)
            ->latest()
            ->first();

        return $last && $last->action === 'paused';
    }

    public static function pauseAgent(string $channel = 'general', ?string $reason = null, ?int $userId = null): self
    {
        return static::create([
            'channel' => $channel,
            'action' => 'paused',
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }

    public static function reactivateAgent(string $channel = 'general', ?string $reason = null, ?int $userId = null): self
    {
        return static::create([
            'channel' => $channel,
            'action' => 'reactivated',
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }
}
