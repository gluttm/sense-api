<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Child extends Model
{
    use HasFactory;

    public function vacines()
    {
        return $this->belongsToMany(ChildVacine::class)
            ->withPivot(['dosage_nr']);
    }
}