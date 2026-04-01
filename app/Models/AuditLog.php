<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = ['user_id', 'user_email', 'action', 'entity_type', 'entity_id', 'details', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
