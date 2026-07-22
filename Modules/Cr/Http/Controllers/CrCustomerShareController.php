<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustShare;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustShareRequest;
use Modules\Gp\Entities\GPInstConst;

class CrCustomerShareController extends Controller
{
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
        $consttable = with(new GPInstConst())->getTable();
        $customer = with(new VwCrCustList())->getTable();
        $sql = CrCustShare::select(['cr_cust_shareholder.*',
            $consttable . '.name as sharetypename',
            DB::raw($customer . '.lname || '."' '".' || ' . $customer . '.name as custid2name'),
            DB::raw($customer . '.lname2 || '."' '".' || ' . $customer . '.name2 as custid2name2'),
            $customer . '.custno as custid2custno',
            ])
            ->leftJoin($consttable, function ($join) use ($consttable) {
                $join->on($consttable . '.value', '=', DB::raw(' cast(cr_cust_shareholder.sharetypecode as varchar)'));
                $join->on($consttable . '.parent_code', '=', DB::raw("'" . 'shareholder_type' . "'"));
            })
            ->leftJoin($customer, $customer . '.id', '=', 'cr_cust_shareholder.custid2')
            ->where('cr_cust_shareholder.statusid', '<>', -1)
            ->where('cr_cust_shareholder.instid', auth()->user()->instid)
            ->where('cr_cust_shareholder.custid', $validated['custid']);
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CrCustShareRequest $request)
    {
        $validated = $request->validated();
        $cust = VwCrCustList::where('id', $validated['custid'])->first();
        if ($cust) {
            $relcust = VwCrCustList::where('id', $validated['custid2'])->first();
            if ($relcust) {
                $validated['custid2typecode'] = $relcust->custtypecode;
            } else {
                $this->error('RC000016');
            }
            $validated['custtypecode'] = $cust->custtypecode;
            $validated['statusid'] = 1;
            $validated['instid'] = auth()->user()->instid;
            $validated['brchno'] = auth()->user()->brchno;
            $validated['created_by'] = auth()->user()->id;
            $validated['updated_by'] = auth()->user()->id;
            CrCustShare::create($validated);
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
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $customer = with(new VwCrCustList())->getTable();
        $instid = auth()->user()->instid;
        $GPinst = CrCustShare::select([
            'cr_cust_shareholder.*',
            DB::raw($customer . '.lname || '."' '".' || ' . $customer . '.name as custid2name'),
            DB::raw($customer . '.lname2 || '."' '".' || ' . $customer . '.name2 as custid2name2'),
            $customer . '.custno as custid2custno',
        ])->leftJoin($customer, $customer . '.id', '=', 'cr_cust_shareholder.custid2')
            ->where('cr_cust_shareholder.id', $validate['id'])
            ->where('cr_cust_shareholder.instid', $instid)
            ->where('cr_cust_shareholder.statusid', '<>', -1)->first();
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
    public function update(CrCustShareRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        $inst = CrCustShare::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->find($validate['id']);
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
        $dtl = CrCustShare::where('instid', auth()->user()->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
