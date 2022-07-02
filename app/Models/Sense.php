<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sense extends Model
{
    use HasFactory;

    public function children()
    {
        return $this->belongsToMany(Child::class);
    }

    public function health_centers()
    {
        return $this->hasMany(HealthCenter::class)
            ->withPivot(['area', 'quantity']);
    }
}