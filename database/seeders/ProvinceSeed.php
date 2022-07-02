<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $provs = [
            ['name' => 'Tete', 'lat' => '-16,03185982', 'lng' => '33,59422403'],
            ['name' => 'Niassa'],
            ['name' => 'Cabo Delgado'],
            ['name' => 'Zambézia'],
            ['name' => 'Manica'],
            ['name' => 'Sofala'],
            ['name' => 'Gaza'],
            ['name' => 'Maputo Província'],
            ['name' => 'Maputo Cidade'],
            ['name' => 'Nampula'],
            ['name' => 'Inhambane'],
        ];

        foreach ($provs as $p) {
            Province::create($p);
        }
    }
}

// 
// 
// 
// 
// 
// 
// 
// 
// Gaza
// 
// 