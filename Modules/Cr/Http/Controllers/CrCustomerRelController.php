<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustRelation;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustRelRequest;
use Modules\Gp\Entities\GPInstConst;

class CrCustomerRelController extends Controller
{
    /**
     * Display a listing of the resource.
     * AC cr010007
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

        $sql = CrCustRelation::select([
            'cr_cust_relation.*',
            'reltype.name as reltypename',
            'relsubtype.name as relsubtypename',
            DB::raw($customer . '.lname || ' . "' '" . ' || ' . $customer . '.name as custid2name'),
            DB::raw($customer . '.lname2 || ' . "' '" . ' || ' . $customer . '.name2 as custid2name2'),
            $customer . '.custno as custid2custno',
        ])
            ->leftJoin($consttable . ' as reltype', function ($join) {
                $join->on('reltype.value', '=', DB::raw('cast(cr_cust_relation.reltypecode as varchar)'));
                $join->on('reltype.parent_code', '=', DB::raw("'relation_type'"));
            })
            ->leftJoin($consttable . ' as relsubtype', function ($join) {
                $join->on('relsubtype.value', '=', DB::raw('cast(cr_cust_relation.relsubtypecode as varchar)'));
                $join->on('relsubtype.parent_code', '=', DB::raw("'relation_type_' || cr_cust_relation.reltypecode"));
            })
            ->leftJoin($customer, $customer . '.id', '=', DB::raw("case when cr_cust_relation.custid = '" . $validated['custid'] . "' then cr_cust_relation.custid2
                                                                        else cr_cust_relation.custid end"))
            ->where('cr_cust_relation.statusid', '<>', -1)
            ->where('cr_cust_relation.instid', auth()->user()->instid)
            ->where(function ($query) use ($validated) {
                $query->where('cr_cust_relation.custid', $validated['custid'])
                    ->orWhere('cr_cust_relation.custid2', $validated['custid']);
            });

        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * AC cr010207
     * @param Request $request
     * @return Response
     */
    public function store(CrCustRelRequest $request)
    {
        $validated = $request->validated();
        $cust = VwCrCustList::where('id', $validated['custid'])->first();
        if ($validated['custid2'] == $validated['custid']) {
            $this->error('RC000090');
        }
        if ($cust) {
            $relcust = VwCrCustList::where('id', $validated['custid2'])->first();
            if ($relcust) {
                $validated['custid2typecode'] = $relcust->custtypecode;
            } else {
                $this->error('RC000016');
            }
            $dupl = CrCustRelation::where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid'])
                ->where('custid2', $validated['custid2'])
                ->where('statusid', 1)
                ->first();
            if ($dupl) {
                $this->error("RC000086", ['field' => $validated['custid2']]);
            }
            $dupl2 = CrCustRelation::where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid2'])
                ->where('custid2', $validated['custid'])
                ->where('statusid', 1)
                ->first();
            if ($dupl2) {
                $this->error("RC000086", ['field' => $validated['custid2']]);
            }

            $validated['custtypecode'] = $cust->custtypecode;
            $validated['statusid'] = 1;
            $validated['instid'] = auth()->user()->instid;
            $validated['brchno'] = auth()->user()->brchno;
            $validated['created_by'] = auth()->user()->id;
            $validated['updated_by'] = auth()->user()->id;
            CrCustRelation::create($validated);
        } else {
            $this->error('RC000015');
        }
    }

    /**
     * Show the specified resource.
     * AC cr010107
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
            'custid' => 'required'
        ], [
            'custid.required' => "RC000011",
            'id.required' => "RC000011"
        ]);
        $customer = with(new VwCrCustList())->getTable();
        $instid = auth()->user()->instid;
        $hasCustid = CrCustRelation::where('custid', $validate['custid'])
            ->where('id', $validate['id'])
            ->where('statusid', '<>', -1)
            ->where('instid', auth()->user()->instid)
            ->exists();
        $joinField = $hasCustid ? 'custid2' : 'custid';
        $GPinst = CrCustRelation::select([
            'cr_cust_relation.*',
            DB::raw($customer . '.lname || ' . "' '" . ' || ' . $customer . '.name as custid2name'),
            DB::raw($customer . '.lname2 || ' . "' '" . ' || ' . $customer . '.name2 as custid2name2'),
            $customer . '.custno as custid2custno',
        ])->leftJoin($customer, $customer . '.id', '=', DB::raw("cr_cust_relation." . $joinField))
            ->where('cr_cust_relation.id', $validate['id'])
            ->where('cr_cust_relation.instid', $instid)
            ->where('cr_cust_relation.statusid', '<>', -1)->first();
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
    public function update(CrCustRelRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        if ($validate['custid2'] == $validate['custid']) {
            $this->error('RC000090');
        }
        $validate['updated_by'] = auth()->user()->id;
        $inst = CrCustRelation::where('statusid', '<>', -1)
            ->where('instid', auth()->user()->instid)
            ->find($validate['id']);
        if (
            $inst->custid != $validate['custid']
            || $inst->custid2 != $validate['custid2']
            || $inst->reltypecode != $validate['reltypecode']
        ) {
            $dupl = CrCustRelation::where('instid', auth()->user()->instid)
                ->where('custid', $validate['custid'])
                ->where('custid2', $validate['custid2'])
                ->where('statusid', 1)
                ->first();
            if ($dupl) {
                $this->error("RC000086", ['field' => $validate['custid2']]);
            }
            $dupl2 = CrCustRelation::where('instid', auth()->user()->instid)
                ->where('custid', $validate['custid2'])
                ->where('custid2', $validate['custid'])
                ->where('statusid', 1)
                ->first();
            if ($dupl2) {
                $this->error("RC000086", ['field' => $validate['custid2']]);
            }
        }
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
        $dtl = CrCustRelation::where('instid', auth()->user()->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
