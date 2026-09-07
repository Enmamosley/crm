<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /** Caché por petición de los permisos resueltos. */
    private ?array $effectivePermissions = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSales(): bool
    {
        return $this->role === 'sales';
    }

    public function isAccounting(): bool
    {
        return $this->role === 'accounting';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Permisos efectivos: los que el admin marcó para este usuario o, si no
     * tiene ninguno, los de su rol. Se resuelve una vez por petición.
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        return $this->effectivePermissions ??= (function (): array {
            $own = $this->permissions()->pluck('permission')->all();

            return $own ?: Permission::defaultsForRole($this->role);
        })();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->effectivePermissions(), true);
    }

    /** ¿Puede ver el recurso, sea todo o sólo lo asignado? */
    public function canViewAny(string $resource): bool
    {
        return $this->hasPermission("{$resource}.view_all")
            || $this->hasPermission("{$resource}.view_own");
    }

    /**
     * ¿Sólo ve lo que tiene asignado? Se cumple cuando el admin le dio
     * explícitamente el permiso restringido y no el amplio.
     */
    public function seesOnlyAssigned(string $resource): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        return $this->hasPermission("{$resource}.view_own")
            && !$this->hasPermission("{$resource}.view_all");
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class)->latest();
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
