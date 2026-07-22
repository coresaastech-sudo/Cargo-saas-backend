<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Ad\Database\Seeders\AdDatabaseSeeder;
use Modules\Ap\Database\Seeders\ApDatabaseSeeder;
use Modules\Cr\Database\Seeders\CrDatabaseSeeder;
use Modules\Gl\Database\Seeders\GlDatabaseSeeder;
use Modules\Gp\Database\Seeders\GpDatabaseSeeder;
use Modules\Re\Database\Seeders\ReDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GpDatabaseSeeder::class,
            AdDatabaseSeeder::class,
            ApDatabaseSeeder::class,
            CrDatabaseSeeder::class,
            ReDatabaseSeeder::class,
            GlDatabaseSeeder::class,
        ]);
    }
}
