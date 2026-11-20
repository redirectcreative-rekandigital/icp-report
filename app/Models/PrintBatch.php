<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintBatch extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enrollment_ids' => 'array',
            'failed' => 'array',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function dir(): string
    {
        return "batches/{$this->id}";
    }

    /** @return array<string, mixed> state for the progress dialog */
    public function state(?string $current = null): array
    {
        $failed = collect($this->failed ?? [])->filter(fn ($f) => ($f['attempts'] ?? 0) >= 3)->values();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'done' => $this->done,
            'failed' => $failed->map(fn ($f) => ['name' => $f['name'], 'error' => $f['error']])->all(),
            'current' => $current,
            'mode' => $this->mode,
            'paper' => $this->paper,
            'download_url' => $this->status === 'completed' ? route('reports.batch.download', $this) : null,
        ];
    }
}
