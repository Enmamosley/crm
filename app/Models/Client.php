<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'lead_id', 'name',
        'billing_type', 'legal_name', 'tax_id', 'tax_system', 'cfdi_use',
        'email', 'phone',
        'address_zip', 'address_street', 'address_exterior', 'address_interior',
        'address_neighborhood', 'address_city', 'address_municipality',
        'address_state', 'address_country',
        'facturapi_customer_id', 'twentyi_package_id', 'domain', 'domain_type', 'cosmotown_registered', 'portal_token', 'portal_active', 'notes',
    ];

    protected function casts(): array
    {
        return ['portal_active' => 'boolean', 'cosmotown_registered' => 'boolean'];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Client $client) {
            if (empty($client->portal_token)) {
                $client->portal_token = Str::uuid()->toString();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function portalUrl(): string
    {
        return url('/portal/' . $this->portal_token);
    }

    /** Construye el objeto address para FacturAPI */
    public function facturApiAddress(): array
    {
        return array_filter([
            'zip'          => $this->address_zip,
            'street'       => $this->address_street,
            'exterior'     => $this->address_exterior,
            'interior'     => $this->address_interior,
            'neighborhood' => $this->address_neighborhood,
            'city'         => $this->address_city,
            'municipality' => $this->address_municipality,
            'state'        => $this->address_state,
            'country'      => $this->address_country,
        ]);
    }
}
