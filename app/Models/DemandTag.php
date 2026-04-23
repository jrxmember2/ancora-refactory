<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DemandTag extends Model
{
    protected $table = 'demand_tags';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'show_on_portal' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_closing' => 'boolean',
            'sla_hours' => 'integer',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class, 'demand_tag_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function publicLabel(): string
    {
        return trim((string) $this->portal_label) !== '' ? (string) $this->portal_label : (string) $this->name;
    }

    public static function default(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();
    }

    public static function defaultForStatus(string $status): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where('status_key', $status)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();
    }

    public static function slaOptions(): array
    {
        return [
            '' => 'Nenhum',
            '2' => '2h',
            '4' => '4h',
            '6' => '6h',
            '8' => '8h',
            '10' => '10h',
            '12' => '12h',
            '24' => '24h',
            '48' => '48h',
            '72' => '72h',
            '96' => '96h',
        ];
    }

    public static function normalizeSlug(string $name, ?string $slug = null): string
    {
        $value = Str::slug(trim((string) $slug) ?: $name);

        return $value !== '' ? $value : Str::random(8);
    }
}
