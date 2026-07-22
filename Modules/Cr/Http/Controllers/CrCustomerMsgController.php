<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustMsg;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustMsgRequest;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstUser;

class CrCustomerMsgController extends Controller
{

    public function getCustMsg(int $custId, bool $returnBuilder = false)
    {
        $constTbl = (new GPInstConst)->getTable();   // GP_inst_const
        $userTbl  = (new GPInstUser)->getTable();    // GP_inst_user

        $q = CrCustMsg::query()
            ->from('cr_cust_msg')
            ->select([
                'cr_cust_msg.*',
                'type.name as msgtypename',
                'note.name as msgnotename',
                "$userTbl.name as created_name",
            ])

            ->leftJoin("$constTbl as type", function ($join) {
                $join->on('type.value', '=', DB::raw('cr_cust_msg.msgtypecode::varchar'))
                    ->whereIn('type.instid', [auth()->user()->instid, 1])
                    ->where('type.statusid', 1)
                    ->where('type.parent_code', 'msg_type');
            })

            ->leftJoin("$constTbl as note", function ($join) {
                $join->on('note.value', '=', DB::raw('cr_cust_msg.msgnotecode::varchar'))
                    ->whereIn('note.instid', [auth()->user()->instid, 1])
                    ->where('note.parent_code', 'customer_note_const');
            })

            ->leftJoin($userTbl, "$userTbl.id", '=', 'cr_cust_msg.created_by')

            ->where('cr_cust_msg.statusid', '<>', -1)
            ->where('cr_cust_msg.instid', auth()->user()->instid)
            ->where('cr_cust_msg.custid', $custId);

        return $returnBuilder ? $q : $q->get();
    }
    /**
     * Display a listing of the resource.
     * @AC cr010008
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);
        return $this->getGridData(
            $request,
            $this->getCustMsg($validated['custid'], true),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @AC cr010208
     * @param Request $request
     * @return Response
     */
    public function store(CrCustMsgRequest $request)
    {
        $validated = $request->validated();
        if (isset($validated['custid']) && !empty($validated['custid'])) {
            $field = 'id';
            $id = $validated['custid'];
        } else if (isset($validated['custno']) && !empty($validated['custno'])) {
            $field = 'custno';
            $id = $validated['custno'];
        }


        $cust = VwCrCustList::where($field, $id)->first();
        if ($cust) {
            $validated['custid'] = $cust->id;
            $validated['custtypecode'] = $cust->custtypecode;
            $validated['statusid'] = 1;
            $validated['instid'] = auth()->user()->instid;
            $validated['created_by'] = auth()->user()->id;
            $validated['updated_by'] = auth()->user()->id;
            CrCustMsg::create($validated);
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
        $instid = auth()->user()->instid;
        $GPinst = CrCustMsg::where('id', $validate['id'])
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
    public function update(CrCustMsgRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        $inst = CrCustMsg::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->find($validate['id']);
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
        $dtl = CrCustMsg::where('instid', auth()->user()->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
