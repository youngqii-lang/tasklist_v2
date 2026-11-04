<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * Seed the application's divisions.
     */
    public function run(): void
    {
        $divisions = [
            ['code_division' => 'IT', 'name' => 'Teknologi Informasi'],
            ['code_division' => 'HRD', 'name' => 'Sumber Daya Manusia'],
            ['code_division' => 'FIN', 'name' => 'Keuangan'],
            ['code_division' => 'MKT', 'name' => 'Pemasaran'],
        ];

        foreach ($divisions as $division) {
            Division::updateOrCreate(['code_division' => $division['code_division']], $division);
        }
    }
}
