<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Modules\Ad\Entities\AdAutoJob;
use Modules\Ad\Entities\AdEbarimt;
use Modules\Ad\Entities\Views\VwAdEbarimt;
use Modules\Ad\Entities\Views\VwAdNotificationUsers;
use Modules\Ad\Http\Requests\AdEbarimtRequest;
use Modules\Ad\Http\Services\AdAutoJobService;
use Modules\Ad\Http\Services\AdEbarimtService;
use Modules\Ad\Http\Services\AdNotificationService;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Http\Services\CoreService;

class AdEbarimtController extends Controller
{
    /**
     * Display a listing of the resource.
     * ad051000
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdEbarimt::where('instid', auth()->user()->instid)->where('statusid', '<>', -1),
            [['field' => 'created_at', 'dir' => 'DESC']]
        );
    }

    /**
     * Show the specified resource.
     * ad051100
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $instid = auth()->user()->instid;
        $validated = $request->validate([
            'id' => 'required',
        ]);

        $ebarimt = AdEbarimt::where('instid', $instid)->where("id", $validated['id'])->first();

        if (!empty($ebarimt)) {
            $ebarimt_service = new AdEbarimtService($instid, auth()->user());
            $txnList = $ebarimt_service->getTransactionList($ebarimt_service->getActionCodeList($ebarimt->txncode), $ebarimt->jrno);

            if (!empty($txnList)) {
                $list = [];

                foreach ($txnList as $item) {
                    $amount = $ebarimt_service->getAmount($item['txnamount'], $item['curcode']);
                    $list[] = [
                        "code" => $item['txncode'],
                        "name" => $item['txndesc'],
                        "measureUnit" => 'Ш',
                        "qty" => '1.00',
                        "unitPrice" => $amount['formattedAmount'] * 1,
                        "totalAmount" => $amount['formattedAmount'] * 1,
                        "vat" => $amount['formattedVatAmount'] * 1,
                        "cityTax" => '0.00' * 1,
                    ];
                }
                $ebarimt->children = $list;
            }

            return $ebarimt;
        }
    }

    /**
     * Store a newly created resource in storage.
     * ad051200
     * @param AdEbarimtRequest $request
     * @return Response
     */
    public function store(AdEbarimtRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if (!isset($validated['issendmail'])) {
            $validated['issendmail'] = false;
        }

        $ebarimt_service = new AdEbarimtService($user->instid, $user);
        $generated_vat = null;
        if ($validated['issendmail']) {
            $generated_vat = $ebarimt_service->sendEbarimtEmail($validated['id'], $validated['jrno'], $validated['txncode']);
        } else {
            $txnList = $ebarimt_service->getTransactionList($ebarimt_service->getActionCodeList($validated['txncode']), $validated['jrno']);
            $generated_vat = $ebarimt_service->generateVat($validated['txncode'], $txnList);
        }

        if ($generated_vat) {
            $tax = $generated_vat['tax'];
            $cust = $generated_vat['cust'];
            $cust_info = null;

            if ($cust->custtypecode === 0) {
                $cust_info = CrCustInd::where("custno", $cust->custno)->where("instid", $user->instid)->where("statusid", "<>", -1)->first();
            } else {
                $cust_info = CrCustOrg::where("custno", $cust->custno)->where("instid", $user->instid)->where("statusid", "<>", -1)->first();
            }

            if (!empty($cust_info) && !empty($cust_info->email)) {
                //Log::debug($cust_info->email);
                $autojobService = new AdAutoJobService();
                $autojob = $autojobService->checkAutoJobActionCode($validated['txncode'], $cust_info, $tax);
                //Log::debug($autojob);
            }
        }
    }

    /**
     * Return a Tax bill.
     * ad051300
     * @param Request $request
     * @return Response
     */
    public function returnbill(Request $request)
    {
        // {
        //     "returnBillId":,
        //     "date":
        // }
        $validated = $request->validate([
            'id' => 'required',
        ]);
        $user = auth()->user();
        $ebarimt_service = new AdEbarimtService($user->instid, $user);
        $response = $ebarimt_service->rebillVat($validated['id']);

        return $response;
    }

    /**
     * Delete a Tax bill.
     * ad051400
     * @return Response
     */
    public function ad051400(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required'
        ]);
        AdEbarimt::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
    }
}
