<?php

namespace Modules\Ap\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ap\Entities\ApCustIncome;
use Modules\Ap\Http\Requests\ApCustIncomeRequest;
// use Modules\Gp\Enums\ResponseCodeEnum;

class ApCustIncomeController extends Controller
{
    /**
     * ap070000 - Харилцагчийн орлого жагсаалт
     */
    public function ap070000(Request $request)
    {
        $validated = $this->validateMe($request, [
            'instid' => 'nullable',
        ]);
        $query = ApCustIncome::where('statusid', 1);
        if (!empty($validated['instid'])) {
            $query->where('instid', $validated['instid']);
        }

        return $this->getGridData(
            $request,
            $query,
            [['field' => 'created_at', 'dir' => 'DESC']]
        );
    }


    /**
     * ap070200 - Харилцагчийн орлого бүртгэх
     */
    public function ap070200(ApCustIncomeRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        $regnoUpper = mb_strtoupper(trim($validated['regno']));

        $income = ApCustIncome::create([
            'instid' => $user->instid,
            'regno' => $regnoUpper,
            'cif' => $validated['cif'],
            'cust_userid' => $validated['cust_userid'],
            'type' => $validated['type'],
            'source_name' => $validated['source_name'] ?? null,
            'year' => $validated['year'],
            'month' => $validated['month'],
            'amount' => $validated['amount'],
            'fee' => $validated['fee'] ?? 0,
            'net_income' => $validated['net_income'],
            'statusid' => 1,
            'created_by' => $user->id,
        ]);

        return $income;
    }

    /**
     * ap070400 - Харилцагчийн орлого устгах
     */
    public function ap070400(Request $request)
    {
        $validated = $this->validateMe($request, [
            'regno' => 'required|string',
            'type' => 'nullable|string|in:sales,salary,real',
        ]);
        $user = auth()->user();
        $regnoUpper = mb_strtoupper(trim($validated['regno']));

        ApCustIncome::where('regno', $regnoUpper)
            ->where('statusid', 1)
            ->when($validated['type'] ?? null, function ($query, $type) {
                return $query->where('type', $type);
            })
            ->update([
                'statusid'   => -1,
                'updated_by' => $user->id,
            ]);
    }
}