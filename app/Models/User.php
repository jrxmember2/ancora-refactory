<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'theme_preference',
        'is_active',
        'is_protected',
        'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_protected' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(SystemModule::class, 'user_module_permissions', 'user_id', 'module_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function accessibleModuleSlugs(): array
    {
        if ($this->isSuperadmin()) {
            return SystemModule::enabled()->pluck('slug')->all();
        }

        return $this->modules()->pluck('slug')->all();
    }
}
