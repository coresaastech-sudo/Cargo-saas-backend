<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustSign;
use Modules\Cr\Entities\CrCustSignAcnt;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Entities\Views\VwCrCustSignAcntList;
use Modules\Cr\Http\Requests\CrCustSignAcntRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustomerSignAcntController extends Controller
{

    public function index(Request $request)
    {
        $validate = $this->validateMe($request, [
            'acntno' => 'required',
            'acnt_module' => 'required',
        ], [
            'acnt_module.required' => ResponseCodeEnum::required,
            'acntno.required' => ResponseCodeEnum::required
        ]);

        $link = VwCrCustSignAcntList::where('acntno', $validate['acntno'])
            ->where('acnt_module', $validate['acnt_module'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->get();
        return $link;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function setSignAcnt(CrCustSignAcntRequest $request)
    {
        $validated = $request->validated();
        $cust = VwCrCustList::where('custno', $validated['custno'])
            ->where('instid', auth()->user()->instid)->first();

        if ($cust) {

            $validated['statusid'] = 1;
            $validated['instid'] = auth()->user()->instid;
            $validated['custid'] = $cust->id;
            $validated['updated_by'] = auth()->user()->id;
            try {
                DB::beginTransaction();
                $tmpSign = [];
                foreach ($validated['signids'] as $signid) {
                    $tmpSign[] = $signid;
                    $sign = CrCustSign::where('id', $signid)
                        ->where('instid', auth()->user()->instid)->first();
                    if ($sign) {
                        $validated['signid'] = $signid;
                        $validated['sign_level'] = $sign->sign_level;
                        $cr = CrCustSignAcnt::where('signid', $signid)
                            ->where('acntno',  $validated['acntno'])
                            ->where('acnt_module',  $validated['acnt_module'])
                            ->where('custid', $cust->id)
                            ->where('statusid', 1)
                            ->where('instid', auth()->user()->instid)->first();
                        if ($cr) {
                            $cr->update($validated);
                        } else {
                            $validated['created_by'] = auth()->user()->id;
                            CrCustSignAcnt::create($validated);
                        }
                    } else {
                        $this->error('RC000062');
                    }
                }
                $cr = CrCustSignAcnt::whereNotIn('signid', $tmpSign)
                    ->where('acntno',  $validated['acntno'])
                    ->where('acnt_module',  $validated['acnt_module'])
                    ->where('custid', $cust->id)
                    ->where('statusid', 1)
                    ->where('instid', auth()->user()->instid)->update(['statusid' => -1]);
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        } else {
            $this->error('RC000015');
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'acntno' => 'required',
            'acnt_module' => 'required',
        ], [
            'acnt_module.required' => ResponseCodeEnum::required,
            'acntno.required' => ResponseCodeEnum::required
        ]);

        $link = CrCustSignAcnt::where('acntno', $validate['acntno'])
            ->where('acnt_module', $validate['acnt_module'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->get();
        return $link;
    }

}
