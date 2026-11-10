<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * The application's divisions, keyed by division code.
     *
     * @var array<string, string>
     */
    public const array DIVISIONS = [
        'SEO' => 'SEO',
        'MKT' => 'Marketing',
        'SLS' => 'Sales',
        'CRT' => 'Creative',
    ];

    /**
     * Seed the application's divisions.
     */
    public function run(): void
    {
        foreach (self::DIVISIONS as $code => $name) {
            Division::updateOrCreate(['code_division' => $code], ['name' => $name]);
        }
    }
}
