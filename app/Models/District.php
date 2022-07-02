<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    public function health_centers()
    {
        return $this->hasMany(HealthCenter::class);
    }

    public function coordinate()
    {
        return $this->hasOne(Coordinate::class);
    }
}