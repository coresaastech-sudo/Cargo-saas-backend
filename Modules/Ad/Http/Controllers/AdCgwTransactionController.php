<?php

namespace Modules\Ad\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCgwTransaction;

class AdCgwTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function ad070001(Request $request)
    {
        return $this->getGridData(
            $request,
            AdCgwTransaction::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)
        );
    }

    /**
     * Show the specified resource.
     * @param int $id
     */
    public function ad070101(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $cgwTransaction = AdCgwTransaction::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->first();

        if ($cgwTransaction) {
            return $cgwTransaction;
        } else {
            $this->error("RC000010", $validated);
        }
    }
}
