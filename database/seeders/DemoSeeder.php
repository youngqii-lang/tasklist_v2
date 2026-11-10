<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed demo accounts and tasks for local development. Never run this in production.
     */
    public function run(): void
    {
        $this->call([
            DemoAccountSeeder::class,
            DemoTaskSeeder::class,
        ]);
    }
}
