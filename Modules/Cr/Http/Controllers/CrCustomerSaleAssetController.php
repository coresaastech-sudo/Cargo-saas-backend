<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Cr\Entities\CrCustSaleAsset;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustSaleAssetRequest;

class CrCustomerSaleAssetController extends Controller
{
    /**
     * Display a listing of the resource.
     * index
     * @return Response
     */
    public function cr010012(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);
        return $this->getGridData(
            $request,
            CrCustSaleAsset::where('statusid', 1)
                ->where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid']),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function cr010212(CrCustSaleAssetRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $cust = VwCrCustList::where('id', $validated['custid'])->first();
        if ($cust) {
            $validated['custtypecode'] = $cust->custtypecode;
            $validated['statusid'] = 1;
            $validated['instid'] = $user->instid;
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;
            CrCustSaleAsset::create($validated);
        } else {
            $this->error('RC000015');
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function cr010112(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $instid = auth()->user()->instid;
        $GPinst = CrCustSaleAsset::where('id', $validate['id'])
            ->where('instid', $instid)->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function cr010312(CrCustSaleAssetRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        $validate['updated_by'] = $user->id;
        $inst = CrCustSaleAsset::where('instid', $user->instid)
            ->where('statusid', 1)->find($validate['id']);
        $inst->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function cr010412(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $dtl = CrCustSaleAsset::where('instid', $user->instid)
            ->where('id', $validate['id'])->where('statusid', 1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => $user->id,
        ]);
    }
}
