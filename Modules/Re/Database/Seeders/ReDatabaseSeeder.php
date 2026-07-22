<?php

namespace Modules\Re\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Model;

class ReDatabaseSeeder extends Seeder
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
