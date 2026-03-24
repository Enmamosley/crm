<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name', 'email', 'phone', 'business',
        'project_description', 'status', 'source',
        'assigned_to', 'estimated_value',
    ];

    public const STATUSES = ['nuevo', 'contactado', 'cotizado', 'cerrado', 'perdido'];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->orderByDesc('created_at');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function updateStatus(string $newStatus, string $changedBy = 'admin'): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);

        LeadStatusHistory::create([
            'lead_id' => $this->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
        ]);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
