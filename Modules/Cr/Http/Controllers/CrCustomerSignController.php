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
use Modules\Gp\Entities\GPInstConst;

class CrCustomerSignController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC cr010006
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'nullable',
            'custno' => 'nullable'
        ],);

        $user = auth()->user();
        $validated['instid'] = $user->instid;
        $consttable = with(new GPInstConst())->getTable();
        $sql = CrCustSign::select(['cr_cust_sign.*', $consttable . '.name as sign_levelname'])
            ->leftJoin($consttable, function ($join) use ($consttable) {
                $join->on($consttable . '.value', '=', DB::raw(' cast(cr_cust_sign.sign_level as varchar)'));
                $join->on($consttable . '.parent_code', '=', DB::raw("'" . 'sign_level' . "'"));
            })
            ->where('cr_cust_sign.statusid', '<>', -1)
            ->where('cr_cust_sign.instid', $validated['instid']);

        if (isset($validated['custid'])) {
            $cust = VwCrCustList::where('id', $validated['custid'])
                ->where('instid', $validated['instid'])->where('statusid', 1)->first();
        } else if (isset($validated['custno'])) {
            $cust = VwCrCustList::where('custno', $validated['custno'])
                ->where('instid', $validated['instid'])->where('statusid', 1)->first();
        }
        if (!$cust) {
            $this->error("RC000082");
        }
        $sql = $sql->where('custid', $cust->id);
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CrCustSignRequest $request)
    {
        $validated = $request->validated();
        $cust = VwCrCustList::where('id', $validated['custid'])->first();
        if ($cust) {
            $dupl = CrCustSign::where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid'])
                ->where('statusid', 1)
                ->where('sign_level', $validated['sign_level'])
                ->first();
            if ($dupl) {
                $this->error("RC000086", ['field' => $validated['sign_level']]);
            }

            $validated['custtypecode'] = $cust->custtypecode;
            $validated['statusid'] = 1;
            $validated['instid'] = auth()->user()->instid;
            $validated['created_by'] = auth()->user()->id;
            $validated['updated_by'] = auth()->user()->id;
            CrCustSign::create($validated);
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
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $instid = auth()->user()->instid;
        $GPinst = CrCustSign::where('id', $validate['id'])
            ->where('instid', $instid)
            ->where('statusid', '<>', -1)->first();
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
    public function update(CrCustSignRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $dupl = CrCustSign::where('instid', auth()->user()->instid)
            ->where('custid', $validated['custid'])
            ->where('statusid', 1)
            ->where('sign_level', $validated['sign_level'])
            ->where('id', '!=', $validated['id'])
            ->first();
        if ($dupl) {
            $this->error("RC000086", ['field' => $validated['sign_level']]);
        }
        $validated['updated_by'] = auth()->user()->id;
        $inst = CrCustSign::where('statusid', '<>', -1)
            ->where('instid', auth()->user()->instid)
            ->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $dtl = CrCustSign::where('instid', auth()->user()->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
