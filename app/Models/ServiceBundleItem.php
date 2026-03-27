<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBundleItem extends Model
{
    protected $fillable = ['service_bundle_id', 'service_id', 'quantity'];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ServiceBundle::class, 'service_bundle_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
