<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoutePermission extends Model
{
    protected $table = 'route_permissions';

    protected $fillable = [
        'group_key',
        'route_name',
        'label',
    ];

    public $timestamps = false;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_route_permissions', 'route_permission_id', 'user_id');
    }
}
