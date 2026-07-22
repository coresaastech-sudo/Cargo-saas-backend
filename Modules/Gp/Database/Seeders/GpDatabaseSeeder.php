<?php

namespace Modules\Gp\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Model;

class GpDatabaseSeeder extends Seeder
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
