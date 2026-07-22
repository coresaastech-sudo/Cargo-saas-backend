<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Cr\Entities\CrCustSecret;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustSecretRequest;
use Modules\Gp\Entities\GPInstConst;

class CrCustomerSecretController extends Controller
{
    /**
     * Display a listing of the resource.
     * AC cr010004
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
        $sql = CrCustSecret::select(['cr_cust_secret.*', $consttable . '.name as questiontypename'])
            ->leftJoin($consttable, function ($join) use ($consttable) {
                $join->on($consttable . '.value', '=', DB::raw(' cast(cr_cust_secret.questiontypecode as varchar)'));
                $join->on($consttable . '.parent_code', '=', DB::raw("'" . 'secret_question' . "'"));
            })
            ->where('cr_cust_secret.statusid', 1)
            ->where('cr_cust_secret.instid', auth()->user()->instid)
            ->where('cr_cust_secret.custid', $validated['custid']);
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CrCustSecretRequest $request)
    {
        $validated = $request->validated();
        $cust = VwCrCustList::where('id', $validated['custid'])->first();
        if ($cust) {

            if (empty($validated['is_inputquestion'])) {
                $validated['is_inputquestion'] = 0;
            }

            if (
                $validated['is_inputquestion'] == true
                || $validated['is_inputquestion'] == 1
            ) {
                $validated['questiontypecode'] = 0;
            }
            $validated['custtypecode'] = $cust->custtypecode;
            $validated['statusid'] = 1;
            $validated['instid'] = auth()->user()->instid;
            $validated['created_by'] = auth()->user()->id;
            $validated['updated_by'] = auth()->user()->id;
            CrCustSecret::create($validated);
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
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = CrCustSecret::where('id', $validated['id'])->where('statusid', '<>', -1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(CrCustSecretRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }

        if (empty($validated['is_inputquestion'])) {
            $validated['is_inputquestion'] = 0;
            $validated['question'] = null;
        }
        if (
            $validated['is_inputquestion'] == true
            || $validated['is_inputquestion'] == 1
        ) {
            $validated['questiontypecode'] = 0;
            $validated['question'] = null;
        }
        $validated['updated_by'] = auth()->user()->id;
        $inst = CrCustSecret::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $dtl = CrCustSecret::where('instid', auth()->user()->instid)->where('id', $validated['id'])->where('statusid', '<>', -1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
