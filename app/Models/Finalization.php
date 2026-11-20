<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finalization extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['classroom_id', 'action', 'reason', 'config_snapshot', 'ip', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['config_snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(FinalResult::class);
    }
}
