<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_protected' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(SystemModule::class, 'user_module_permissions', 'user_id', 'module_id');
    }

    public function routePermissions(): BelongsToMany
    {
        return $this->belongsToMany(RoutePermission::class, 'user_route_permissions', 'user_id', 'route_permission_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $path = trim((string) $this->avatar_path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $relative = '/' . ltrim($path, '/');
        $absolute = public_path(ltrim($relative, '/'));

        return is_file($absolute) ? asset(ltrim($relative, '/')) : null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = Str::of((string) $this->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $parts !== '' ? $parts : 'U';
    }

    public function accessibleModuleSlugs(): array
    {
        if ($this->isSuperadmin()) {
            return SystemModule::enabled()->pluck('slug')->all();
        }

        return $this->modules()->pluck('slug')->all();
    }

    public function accessibleRouteNames(): array
    {
        if ($this->isSuperadmin()) {
            return [];
        }

        return $this->routePermissions()->pluck('route_name')->all();
    }
}
