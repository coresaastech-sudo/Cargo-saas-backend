<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCreditInfoBueroDetail;
use Modules\Ad\Entities\AdZmsInquiry;
use Modules\Ad\Entities\Views\VwAdCreditInfoBuero;
use Modules\Ad\Entities\Views\VwAdCreditInfoBueroDetail;
use Modules\Ad\Entities\Views\VwAdZmsInquiryDetail;
use Modules\Ad\Http\Services\AdCreditInfoBueroService;
use Modules\Ad\Http\Services\AdZmsService;
use Modules\Gp\Entities\GPInstFeeType;
use Modules\Gp\Entities\GPInstFormula;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ln\Entities\LnAccount;
use Modules\Tr\Entities\TxnItemEntity;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Controllers\TxnCoreController;

class AdCreditInfoBueroController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validateMe($request, [
            'acntno' => 'nullable'
        ]);
        $query =  VwAdCreditInfoBuero::where('instid', auth()->user()->instid)->where('statusid', '<>', -1);

        if (!empty($validated['acntno'])) {
            $query->where('acntno', $validated['acntno']);
        }

        return $this->getGridData($request, $query, [['field' => 'created_at', 'dir' => 'DESC']]
        );
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

        $adCreditInfoBuero = VwAdCreditInfoBuero::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)
            ->first();

        $details = AdCreditInfoBueroDetail::where('buero_id', $adCreditInfoBuero->id)->where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->get();

        $adCreditInfoBuero->details = $details;

        return $adCreditInfoBuero;
    }

    /**
     * Send credit informations to ZMS .
     * @param Request $request
     * @return Response
     */
    public function send(Request $request)
    {
        $validated = $this->validateMe($request, [
            'addjob' => 'nullable'
        ]);

        $addjob = 1;

        if (isset($validated['addjob'])) {
            $addjob = $validated['addjob'];
        }

        $service = new AdCreditInfoBueroService(auth()->user()->instid, auth()->user()->id);
        if ($service->isOnSendZMSJob()) {
            $this->error('RC000221');
        }
        $creditInfoList = $service->sendData($addjob);

        return $creditInfoList;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Response
     */
    public function ad080001(Request $request)
    {
        $instid = auth()->user()->instid;

        $formula = GPInstFormula::where('name2', 'ErrorDesc')
            ->where('instid', 1)
            ->where('statusid', '>', 0)
            ->first();

        $query = VwAdCreditInfoBueroDetail::from('vw_ad_credit_buero_detail as cb')
            ->select('cb.*');

        if ($formula) {
            $formulaSql = preg_replace('/AND\s+d\.acntno\s*=\s*:acntno/i', '', $formula->formula);
            $formulaSql = preg_replace('/:instid\b/', intval($instid), $formulaSql);
            $formulaSql = rtrim(trim($formulaSql), ';');

            $query->leftJoinSub(
                DB::table(DB::raw("($formulaSql) as err")),
                'err',
                'err.acntno',
                '=',
                'cb.acntno'
            )
            ->select('cb.*', 'err.error_code', 'err.error_desc');
        } else {
            $query->selectRaw('NULL as error_code, NULL as error_desc');
        }

        $query->where('cb.instid', $instid)
            ->where('cb.statusid', '<>', -1);

        return $this->getGridData($request, $query);
    }

    /**
     * Resend credit informations to ZMS .
     * @param Request $request
     * @return Response
     */
    public function ad080501(Request $request)
    {
        $validated = $this->validateMe($request, [
            'acntno' => 'required'
        ], [
            'acntno.required' => "RC000011"
        ]);
        $service = new AdCreditInfoBueroService(auth()->user()->instid, auth()->user()->id);

        if ($service->isOnSendZMSJob()) {
            $this->error('RC000221');
        }

        $creditInfoList = $service->sendData(1, $validated['acntno']);

        return $creditInfoList;
    }

    public function ad080502(Request $request)
    {
        $validated = $this->validateMe($request, [
            'acntno' => 'required'
        ], [
            'acntno.required' => "RC000011"
        ]);

        $service = new AdCreditInfoBueroService(auth()->user()->instid, auth()->user()->id);

        if ($service->isOnSendZMSJob()) {
            $this->error('RC000221');
        }

        $result = $service->sendApprovedLoan($validated['acntno']);

        return $result;
    }
    /**
     * Get payment method and product list from ZMS.
     * @param Request $request
     * @return Response
     */
    public function ad081001(Request $request)
    {
        $validated = $this->validateMe($request, [
            'custregno' => 'nullable',
            'productno' => 'required_with:custregno'
        ], [
            'productno.required_with' => ResponseCodeEnum::required,
        ]);

        if (isset($validated['custregno'])) {
            $senddata = [
                'custregno' => $validated['custregno'],
                'productno' => $validated['productno']
            ];
        } else {
            $senddata = [];
        }

        $service = new AdZmsService(auth()->user()->instid, auth()->user()->id);
        $response = $service->post($senddata, 'co9510');

        if (isset($validated['productno'])) {
            if (!isset($response['response'])) {
                $this->error('RC000228');
            }

            $res = $response['response'];
            if (!isset($res['productno'], $res['custregno'], $res['price'], $res['discountamount'])) {
                $this->error('RC000228');
            }

            return $res;
        } else {
            $filtered = [];
            try {
                foreach ($response['response'] as $item) {
                    if (!isset($item['productno'], $item['inquiry_typeid'], $item['acnttypeid'])) {
                        $this->error('RC000228');
                    }

                    $productNo = $item['productno'];

                    if (!isset($filtered[$productNo]) || $filtered[$productNo]['acnttypeid'] !== '02') {
                        if ($item['acnttypeid'] === '02') {
                            $filtered[$productNo] = $item;
                        } elseif ($item['acnttypeid'] === '01' && !isset($filtered[$productNo])) {
                            $filtered[$productNo] = $item;
                        }
                    }
                }
            } catch (Exception $e) {
                $this->error('RC000228');
                $filtered = [];
            }

            return array_values($filtered);
        }
    }


    /**
     * Get inquiry from ZMS.
     * @param Request $request
     * @return Response
     */
    public function ad081000(Request $request)
    {
        $decoded_statement_inquiry = json_decode($request->input('statement_inquiry'), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $request->merge(['statement_inquiry' => $decoded_statement_inquiry]);
        }

        $validated = $this->validateMe($request, [
            'price' => 'required',

            'custtypeid' => 'required',
            'custregno' => 'required',
            'origin' => 'required',
            'purptypeid' => 'required',
            'productno' => 'required',
            'productname' => 'required',
            'acnttypeid' => 'required',

            'car_val_inquiry' => 'nullable|array',
            'car_val_inquiry.brand' => 'required_with:car_val_inquiry',
            'car_val_inquiry.condition' => 'required_with:car_val_inquiry',
            'car_val_inquiry.imported_year' => 'required_with:car_val_inquiry',
            'car_val_inquiry.manufactured_year' => 'required_with:car_val_inquiry',
            'car_val_inquiry.model' => 'required_with:car_val_inquiry',

            'statement_inquiry' => 'nullable|array',
            'statement_inquiry.businesstype' => 'required_with:statement_inquiry',
            'statement_inquiry.custregno' => 'required_with:statement_inquiry',
            'statement_inquiry.custtypeid' => 'required_with:statement_inquiry',
            'statement_inquiry.reportname' => 'required_with:statement_inquiry',
            'files' => 'required_with:statement_inquiry'
        ], [
            'price' => ResponseCodeEnum::required,

            'custtypeid.required' => ResponseCodeEnum::required,
            'custregno.required' => ResponseCodeEnum::required,
            'origin.required' => ResponseCodeEnum::required,
            'purptypeid.required' => ResponseCodeEnum::required,
            'productno.required' => ResponseCodeEnum::required,
            'productname.required' => ResponseCodeEnum::required,
            'acnttypeid.required' => ResponseCodeEnum::required,

            'car_val_inquiry.brand.required_with' => ResponseCodeEnum::required,
            'car_val_inquiry.condition.required_with' => ResponseCodeEnum::required,
            'car_val_inquiry.imported_year.required_with' => ResponseCodeEnum::required,
            'car_val_inquiry.manufactured_year.required_with' => ResponseCodeEnum::required,
            'car_val_inquiry.model.required_with' => ResponseCodeEnum::required,

            'statement_inquiry.businesstype.required_with' => ResponseCodeEnum::required,
            'statement_inquiry.custregno.required_with' => ResponseCodeEnum::required,
            'statement_inquiry.custtypeid.required_with' => ResponseCodeEnum::required,
            'statement_inquiry.reportname.required_with' => ResponseCodeEnum::required,
            'files.required_with' => ResponseCodeEnum::required,
        ]);


        $user = auth()->user();
        $service = new AdZmsService($user->instid, $user->id);

        try {
            DB::beginTransaction();
            $txnController = new TxnCoreController();
            $p = new TxnJrnlEntity();
            $p->setInstid($user->instid);
            $p->setUserid($user->id);
            $pfeecode = $service->getFeeCode();
            $price = $validated['price'];
            $fee = GPInstFeeType::where('feecode', $pfeecode)
                ->where('instid', $p->getInstid())
                ->where('statusid', 1)->first();
            $feeamount = 0;
            $feeacntno = '';
            if ($fee) {
                $p->setTxncode($fee->ACTION_CODE);
                $p->setSourcecode(2);
                $p->setAcntbrchno($user->brchno);
                $p->setContAcntbrchno($user->brchno);
                $p->setTxnAmount($price);
                $p->setContAmount($price);
                $p->setCurCode('MNT');
                $p->setContCurCode('MNT');
                $p->setTxndate(CoreService::getTxnDate($p->getInstid()));
                $p->setJrno(CoreService::getNextJrno());
                $p = $txnController->initFee($p, $fee);
                $jrItem = new TxnItemEntity();
                if (count($p->getFeeinfos()) > 0) {
                    foreach ($p->getFeeinfos() as $feeinfo) {
                        $feeamount = $feeamount + $feeinfo['contamount'];
                        $feeacntno = $feeinfo['contacntno'];
                    }
                }
                $jrItem = $txnController->doFeeTxn($p, $jrItem);
            }

            // Авто машины лавлагаа
            if (isset($validated['car_val_inquiry'])) {

                $validated['car_val_inquiry']['productno'] = $validated['productno'];
                $fetch_response = $service->post($validated['car_val_inquiry'], 'in1020');

                if ($fetch_response && isset($fetch_response['response'])) {
                    if ($fetch_response['response_code'] == 'SR0000') {
                        $send_data = [
                            'acnttypeid' => $validated['acnttypeid'],
                            'inquiry_id' => $fetch_response['response']['serviceid']
                        ];
                        $response = $service->post($send_data, 'in1080');
                    } else if ($fetch_response['response_code'] == 'SR1221') {
                        $this->error('RC000232');
                    } else {
                        $this->error('RC000228');
                    }
                } else {
                    $this->error('RC000228');
                }

                // Дансны тайлан лавлагаа
            } else if (isset($validated['statement_inquiry'])) {
                $files = $request->file('files');

                $validated['statement_inquiry']['productno'] = $validated['productno'];
                $validated['statement_inquiry']['acnttypeid'] = $validated['acnttypeid'];

                $fetch_response = $service->post($validated['statement_inquiry'], 'in9414', true, $files, 'files');
                if ($fetch_response && isset($fetch_response['response']) && $fetch_response['response_code'] == 'SR0000') {
                    AdZmsInquiry::create([
                        'productno' => $validated['productno'],
                        'productname' => $validated['productname'],
                        'purptypeid' => $validated['purptypeid'],
                        'acnttypeid' => $validated['acnttypeid'],
                        'custtypeid' => $validated['custtypeid'],
                        'custregno' => $validated['custregno'],
                        'origin' => $validated['origin'],
                        'price' => $price,
                        'fee' => $feeamount,
                        'fee_acntno' => $feeacntno,
                        'instid' => $user->instid,
                        'stmt_id' => $fetch_response['response']['stmt_id'],
                        'statusid' => 1,
                        'created_by' => $user->id,
                    ]);

                    return;
                }

                // Бусад лавлагаа
            } else {
                $response = $service->post($validated, 'in9410');
            }

            if ($response && $response['response']['pdf']) {
                AdZmsInquiry::create([
                    'productno' => $validated['productno'],
                    'productname' => $validated['productname'],
                    'purptypeid' => $validated['purptypeid'],
                    'acnttypeid' => $validated['acnttypeid'],
                    'custtypeid' => $validated['custtypeid'],
                    'custregno' => $validated['custregno'],
                    'origin' => $validated['origin'],
                    'price' => $price,
                    'fee' => $feeamount,
                    'fee_acntno' => $feeacntno,
                    'pdf' => $response['response']['pdf'],
                    'instid' => $user->instid,
                    'statusid' => 1,
                    'created_by' => $user->id,
                ]);
                DB::commit();
                return ($validated['origin'] === 1) 
                    ? $response['response']['pdf'] 
                    : $response['response'];
            }
            DB::rollBack();
            $this->error('RC000228');
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Get ZMS inquiry list.
     * @param Request $request
     * @return Response
     */
    public function ad081003(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdZmsInquiryDetail::where('instid', auth()->user()->instid)->where('statusid', '<>', -1),
            [['field' => 'created_at', 'dir' => 'DESC']]
        );
    }

    /**
     * Get car model list.
     * @param Request $request
     * @return Response
     */
    public function ad081004(Request $request)
    {
        $user = auth()->user();
        $service = new AdZmsService($user->instid, $user->id);

        $response = $service->post([], 'in1070');

        if ($response && isset($response['response']) && $response['response_code'] == 'SR0000') {
            return $response['response'];
        } else {
            $this->error('RC000229');
        }
    }

    /**
     * Validate statement inquiry's account statement file.
     * @param Request $request
     * @return Response
     */
    public function ad081005(Request $request)
    {
        $this->validateMe($request, [
            'file' => 'required|file|mimes:xls,xlsx|max:2048',
        ], [
            'file.required' => ResponseCodeEnum::required,
        ]);

        $file = $request->file('file');


        $user = auth()->user();
        $service = new AdZmsService($user->instid, $user->id);

        $response = $service->post([], 'in0970', true, [$file], 'file');

        if ($response && isset($response['response']) && $response['response_code'] == 'SR0000') {
            return $response['response'];
        } else {
            $this->error('RC000230');
        }
    }

    /**
     * Get business types from ZMS.
     * @param Request $request
     * @return Response
     */
    public function ad081006(Request $request)
    {
        $user = auth()->user();
        $service = new AdZmsService($user->instid, $user->id);

        $response = $service->post([], 'co9710');

        if ($response && isset($response['response']) && $response['response_code'] == 'SR0000') {
            return $response['response'];
        } else {
            $this->error('RC000231');
        }
    }

    /**
     * Get statement's detail (pdf) from ZMS.
     * @param Request $request
     * @return Response
     */
    public function ad081007(Request $request)
    {
        $validated = $this->validateMe($request, [
            'stmt_id' => 'required'
        ], [
            'stmt_id.required' => ResponseCodeEnum::required
        ]);

        $user = auth()->user();
        $service = new AdZmsService($user->instid, $user->id);

        $response = $service->post(['id' => $validated['stmt_id']], 'in9416');

        if ($response && isset($response['response'])) {
            if ($response['response_code'] == 'SR0000') {
                if (!isset($response['response']['service']['servicecode'])) {
                    $this->error('RC000233');
                }
                $servicecode =  $response['response']['service']['servicecode'];

                $url =  $service->getConnection();
                $cleanUrl = preg_replace('#/api$#', '', $url['url_sainscore']);

                return $cleanUrl . '/front/service/getServicePdf?servicecode=' . $servicecode;
            } else if ($response['response_code'] == 'SR1222') {
                $this->error('RC000234');
            } else {
                $this->error('RC000233');
            }
        } else {
            $this->error('RC000233');
        }
    }

    public function ad081008(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => 'RC000011'
        ]);

        $dtl = VwAdCreditInfoBueroDetail::where('acntno', $validated['id'])->where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->first();

        return  $dtl;
    }

    public function ad081009(Request $request)
    {
        $validate = $this->validateMe($request, [
            'acntno' => 'required'
        ],[
            'acntno.required' => 'RC000011'
        ]);

        $instid = auth()->user()->instid;


        if (!empty($validate['acntno'])) {

            $formula = GPInstFormula::where('name2', 'ErrorDesc')
                ->where('instid', 1)
                ->where('statusid', '>', 0)
                ->first();

            if (!$formula) {
                $this->error('RC000046', ['itemname' => 'ErrorDesc формула']);
            }

            $rows = DB::select(
                $formula->formula,
                [
                    'instid' => $instid,
                    'acntno' => $validate['acntno']
                ]
            );

            return $rows[0] ?? [];
        }
    }
}
