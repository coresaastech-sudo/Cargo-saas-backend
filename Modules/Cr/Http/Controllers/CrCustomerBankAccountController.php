<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Http\Services\AdCorporateGatewayKhanService;
use Modules\Cr\Entities\CrCustBankAccount;
use Modules\Cr\Entities\Views\VwCrCustBankAccount;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustBankAccountRequest;


class CrCustomerBankAccountController extends Controller
{

    /**
     * @AC cr010513 Банкны данс шалгах
     */
    public function cr010513(Request $request) // custom FormRequest‑ийг шууд ашиглав
    {
        // validated() дотор чинь аль хэдийнээ sanitised дата байна
        $data = $this->validate($request, [
            'custid' => 'required',
            'acnt_code' => 'required',
            'cust_name' => 'required',
            'bank_code' => 'required',
        ], [
            'custid.required' => "RC000082",
            'acnt_code.required' => "RC000082",
            'cust_name.required' => "RC000082",
            'bank_code.required' => "RC000082",
        ]);
        $user = auth()->user();
        $service = new AdCorporateGatewayKhanService($user->instid, $user->id);

        $isValid = $service->checkBankAccount(
            cust: (object)[
                'custid' => $data['custid'],
                'name' => $data['cust_name']
            ],
            acntno: $data['acnt_code'],
            bankcode: $data['bank_code'],
        );

        return $isValid;
    }



    /**
     * Display a listing of the resource.
     * @AC cr010013
     * @return Response
     */
    public function cr010013(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required',
        ], [
            'custid.required' => "RC000082",
        ]);
        return $this->getGridData(
            $request,
            VwCrCustBankAccount::where('statusid', 1)
                ->where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid']),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @AC cr010213
     * @param Request $request
     * @return Response
     */
    public function cr010213(CrCustBankAccountRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();

        $cust = VwCrCustList::where('instid', $user->instid)
            ->where('id', $validated['custid'])
            ->first();
        if ($cust) {
            $validated['custid'] = $cust->id;
            $validated['custno'] = $cust->custno;
            $validated['statusid'] = 1;
            $validated['instid'] = $user->instid;
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;
            CrCustBankAccount::create($validated);
        } else {
            $this->error('RC000015');
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function cr010113(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $instid = auth()->user()->instid;
        $GPinst = CrCustBankAccount::where('id', $validate['id'])
            ->where('instid', $instid)
            ->where('statusid', 1)->first();
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
    public function cr010313(CrCustBankAccountRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        $validate['updated_by'] = $user->id;
        $cust = VwCrCustList::where('instid', $user->instid)
            ->where('id', $validate['custid'])
            ->first();
        if ($cust) {
            $validate['custno'] = $cust->custno;
        }
        $inst = CrCustBankAccount::where('instid', $user->instid)->where('statusid', 1)->find($validate['id']);
        $inst->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function cr010413(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $dtl = CrCustBankAccount::where('instid',  $user->instid)->where('id', $validate['id'])->where('statusid', 1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' =>  $user->id,
        ]);
    }
}
