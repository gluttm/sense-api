<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $provs = [
            ["province_id" => 1, "name" => "Tete", "lat"    => "-16,08599884", "lng" => "	33,62651465"],
            ["province_id" => 1, "name" => "Tsangano", "lat" =>    "-14,69065175", "lng" => "	34,22222199"],
            ["province_id" => 1, "name" => "Doa", "lat" =>    "-16,52245249", "lng" => "	34,73128791"],
            ["province_id" => 1, "name" => "Mutarara", "lat" =>    "-17,43327872", "lng" => "	35,07719853"],
            ["province_id" => 1, "name" => "Zumbo", "lat"    => "-15,60988232", "lng" => "	30,44210338"],
            ["province_id" => 1, "name" => "Marara", "lat"    => "-15,65473811", "lng" => "	32,99880368"],
            ["province_id" => 1, "name" => "Angonia", "lat"    => "-14,05690419", "lng" => "	34,24888631"],
            ["province_id" => 1, "name" => "Cahora Bassa", "lat"    => "-15,6467516	", "lng" => "31,77368989"],
            ["province_id" => 1, "name" => "Changara", "lat"    => "-16,65269985", "lng" => "	33,27711585"],
            ["province_id" => 1, "name" => "Macanga", "lat"    => "-13,9223117", "lng" => "	33,81091803"],
            ["province_id" => 1, "name" => "Magoe", "lat"    => "-15,78735963", "lng" => "	31,74194812"],
            ["province_id" => 1, "name" => "Moatize", "lat"    => "-16,05733149", "lng" => "	33,71651223"],
            ["province_id" => 1, "name" => "Maravia", "lat"    => "-14,27774552", "lng" => "	31,85932155"],
            ["province_id" => 1, "name" => "Chiuta", "lat"    => "-15,31296935", "lng" => "	33,22525292"],
            ["province_id" => 1, "name" => "Chifunde", "lat"    => "-14,62421913", "lng" => "	32,79127119"]
        ];

        foreach ($provs as $p) {
            District::create($p);
        }
    }
}