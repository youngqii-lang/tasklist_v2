<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * The platforms a task can belong to.
     *
     * @var list<string>
     */
    public const array PLATFORMS = [
        'CEREBRUM',
        'JADIASN',
        'JADIBUMN',
        'JADIPOLISI',
        'JADIPRAJURIT',
        'JADISEKDIN',
        'JADIBEASISWA',
        'PSIKOTESKERJA',
        'JAGOTPA',
        'JADIPPPK',
        'JADIOJK',
        'JADIPCPM',
        'TOEFLACADEMY',
    ];

    /**
     * Seed the task platforms.
     */
    public function run(): void
    {
        foreach (self::PLATFORMS as $name) {
            Platform::updateOrCreate(['slug' => strtolower($name)], ['name' => $name]);
        }
    }
}
