<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    protected $fillable = ['lead_id', 'content', 'author'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
