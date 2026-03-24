<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $fillable = ['service_category_id', 'name', 'slug', 'description', 'price', 'active', 'public', 'requires_domain', 'twentyi_package_bundle_id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active'          => 'boolean',
            'public'          => 'boolean',
            'requires_domain' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Service $service) {
            if (empty($service->slug)) {
                $service->slug = Str::slug($service->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('public', true)->where('active', true);
    }

    public function publicUrl(): string
    {
        return url('/buy/' . $this->slug);
    }

    public function priceWithIva(): float
    {
        $ivaRate = (float) Setting::get('iva_percentage', 16) / 100;
        return round((float) $this->price * (1 + $ivaRate), 2);
    }
}
