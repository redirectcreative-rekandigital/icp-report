<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelMeta extends Model
{
    protected $table = 'level_meta';

    protected $primaryKey = 'level';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['level', 'short_name', 'name', 'cambridge', 'overall_comment', 'color_hex', 'sort'];
}
