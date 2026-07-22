<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Gp\Services\NavigationService;

class NavigationController extends Controller
{
    public function menu(Request $request, NavigationService $navigation): array
    {
        return $navigation->build();
    }
}
