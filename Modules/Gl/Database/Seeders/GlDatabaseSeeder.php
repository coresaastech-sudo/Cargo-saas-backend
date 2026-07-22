<?php

namespace Modules\Gl\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Model;

class GlDatabaseSeeder extends Seeder
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
