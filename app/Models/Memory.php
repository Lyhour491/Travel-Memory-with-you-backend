<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Memory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trip_id',
        'user_id',
        'title',
        'note',
        'date_time',
        'place',
        'memory_date',
        'location_name',
        'latitude',
        'longitude',
        'address',
        'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'memory_date' => 'date:Y-m-d',
            'date_time' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_favorite' => 'boolean',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MemoryPhoto::class)->orderBy('photo_order');
    }
}
