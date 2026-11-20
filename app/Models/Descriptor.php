<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Descriptor extends Model
{
    protected $fillable = ['level', 'skill', 'can_do', 'next_step'];
}
