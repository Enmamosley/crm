<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['session_id', 'service_id', 'quantity'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
