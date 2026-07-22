<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustAddr;
use Modules\Cr\Entities\Views\VwCrCustAllAddressDetail;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustAddrRequest;
use Modules\Gp\Entities\GPInstConst;

class CrCustomerAddrController extends Controller
{
    /**
     * Харилцагчийн нийт хаягийн дэлгэрэнгүй
     * @return Response
     */
    public function cr012003(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);
        $sql = VwCrCustAllAddressDetail::where('instid', auth()->user()->instid)
            ->where('custid', $validated['custid'])
            ->where('statusid', 1);
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);
        $consttable = with(new GPInstConst)->getTable();
        $sql = CrCustAddr::select(['cr_cust_address.*', $consttable . '.name as addrtypename'])
            ->leftJoin($consttable, function ($join) use ($consttable) {
                $join->on($consttable . '.value', '=', DB::raw('cr_cust_address.addrtypecode::varchar'))
                    ->on($consttable . '.parent_code', '=', DB::raw("'addr_type'"))
                    ->where($consttable . '.statusid', '=', 1)
                    ->whereIn($consttable . '.instid', [auth()->user()->instid, 1]);
            })
            ->where('cr_cust_address.statusid', 1)
            ->where('cr_cust_address.instid', auth()->user()->instid)
            ->where('cr_cust_address.custid', $validated['custid']);
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CrCustAddrRequest $request)
    {
        $validated = $request->validated();
        $cust = VwCrCustList::where('id', $validated['custid'])->first();
        if ($cust) {
            $validated['custtypecode'] = $cust->custtypecode;
            $validated['statusid'] = 1;
            $validated['instid'] = auth()->user()->instid;
            $validated['created_by'] = auth()->user()->id;
            $validated['updated_by'] = auth()->user()->id;
            CrCustAddr::create($validated);
        } else {
            $this->error('RC000015');
        }
    }

    /**
     * Show the specified resource.
     * AC cr010102
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $instid = auth()->user()->instid;
        $GPinst = CrCustAddr::where('id', $validate['id'])
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
    public function update(CrCustAddrRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        $inst = CrCustAddr::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->find($validate['id']);
        $inst->update($validate);
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
        $dtl = CrCustAddr::where('instid', auth()->user()->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
