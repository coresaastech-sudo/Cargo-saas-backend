<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustSign;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustSignRequest;
use Modules\Cr\Http\Services\CustomerService;
use Modules\Gp\Entities\GPInstConst;

class CrCustomerBatchController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function cr013011(Request $request)
    {
        $validated = $this->validate($request, [
            'custids' => 'required|array',
            'withAddress' => 'nullable',
        ], [
            'custids.required' => "RC000082"
        ]);

        return (new CustomerService())->getCustomerDetails($validated['custids'], auth()->user()->instid, @$validated['withAddress'] ? true : false);
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function cr013012(Request $request)
    {
        $validated = $this->validate($request, [
            'custids' => 'required|array',
        ], [
            'custids.required' => "RC000082"
        ]);

        return (new CustomerService())->getCustomerAddresses($validated['custids'], auth()->user()->instid);
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function cr013013(Request $request)
    {
        $validated = $this->validate($request, [
            'data' => 'required|array',
            'type' => 'required|in:cr,crorg,ln,gp,ia',
        ], [
            'custids.required' => "RC000082"
        ]);

        return (new CustomerService())->getAdditionals($validated['type'], $validated['data'], auth()->user()->instid);
    }
}
