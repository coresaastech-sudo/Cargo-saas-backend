<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            User::query(),
            ['id' => 'id', 'name' => 'name', 'email' => 'email', 'organization_id' => 'organization_id', 'status' => 'status'],
            [['field' => 'name', 'dir' => 'asc']]
        );
    }
}
