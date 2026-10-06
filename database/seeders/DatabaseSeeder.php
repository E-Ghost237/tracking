<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            NotificationTemplateSeeder::class,
            ContentSeeder::class,
        ]);

        if (app()->environment(['local', 'testing', 'staging'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
