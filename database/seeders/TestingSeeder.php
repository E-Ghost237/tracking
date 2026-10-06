<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data every feature test needs: roles, carriers, pricing, cities and templates.
 */
class TestingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ReferenceDataSeeder::class, NotificationTemplateSeeder::class]);
    }
}
