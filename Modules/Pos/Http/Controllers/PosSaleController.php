<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PosSaleController extends Controller
{
    public function dashboard(Request $request): array
    {
        return [
            'sales_today' => 0,
            'orders_today' => 0,
            'refunds_today' => 0,
        ];
    }
}
