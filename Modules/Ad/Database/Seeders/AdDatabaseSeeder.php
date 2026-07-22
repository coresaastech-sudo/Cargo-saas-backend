<?php

namespace Modules\Ad\Database\Seeders;

use App\Models\Model;
use Illuminate\Database\Seeder;

class AdDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        // $this->call("OthersTableSeeder");
    }
}
