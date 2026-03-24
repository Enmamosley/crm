<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStatusHistory extends Model
{
    protected $fillable = ['lead_id', 'old_status', 'new_status', 'changed_by'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
