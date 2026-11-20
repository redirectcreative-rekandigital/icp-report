<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    public const CATEGORIES = [
        'tes' => 'Instrumen Tes',
        'pedoman' => 'Pedoman Penskoran',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = ['title', 'grade', 'category', 'file_path', 'original_name', 'size', 'mime'];

    protected function casts(): array
    {
        return ['grade' => 'integer', 'size' => 'integer'];
    }
}
