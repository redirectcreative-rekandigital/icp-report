<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const CATEGORIES = [
        'akses' => 'Akses',
        'nilai' => 'Nilai',
        'finalisasi' => 'Finalisasi',
        'data' => 'Data',
        'pengaturan' => 'Pengaturan',
    ];

    protected $fillable = [
        'action', 'category', 'subject_type', 'subject_id', 'description',
        'meta', 'ip', 'device', 'session_short', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
