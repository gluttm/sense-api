<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthCenter extends Model
{
    use HasFactory;

    public function coordinate()
    {
        return $this->hasOne(Coordinate::class);
    }
}