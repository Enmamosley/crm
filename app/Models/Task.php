<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'title', 'description', 'status', 'priority',
        'due_at', 'completed_at',
        'client_id', 'lead_id', 'assigned_to', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at'       => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const STATUSES = ['todo', 'in_progress', 'done'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    public const STATUS_LABELS = [
        'todo'        => 'Pendiente',
        'in_progress' => 'En progreso',
        'done'        => 'Completada',
    ];

    public const PRIORITY_LABELS = [
        'low'    => 'Baja',
        'medium' => 'Media',
        'high'   => 'Alta',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->status !== 'done'
            && $this->due_at->isPast();
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['todo', 'in_progress']);
    }
}
