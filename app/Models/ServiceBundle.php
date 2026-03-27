<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServiceBundle extends Model
{
    protected $fillable = ['name', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceBundleItem::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_bundle_items')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function totalPrice(): float
    {
        return (float) $this->services->sum(fn ($s) => $s->price * $s->pivot->quantity);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
