<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    protected $guarded = ['id'];

    protected function casts() {
        return [
            'inventory' => 'array',
            'abilities' => 'array',
            'features' => 'array',
        ];
    }
}
