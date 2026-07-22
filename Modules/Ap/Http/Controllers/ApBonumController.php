<?php

namespace Modules\Ap\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Ap\Http\Services\ApBonumService;
use App\Exceptions\MeException;
use Modules\Ap\Http\Services\ApQpayService;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Ap\Http\Requests\ApBonumCreateCardRequest;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ap\Http\Services\ApAuthService;
use Modules\Ap\Http\Services\PolarisApiRequestService;

class ApBonumController extends Controller
{


    /**
     * ap040200 - Карт бүртгэх /Бонум/
     * @param  mixed $request
     */
    public function ap040200(ApBonumCreateCardRequest $request)
    {
        try {
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->createCard($request->validated());
            if (!$res->successful()) {
                throw new MeException('RC000003');
            }

            $resdetail = $service->getCardDetail($res['cardId']);
            if (!$resdetail->successful()) {
                throw new MeException('RC000003');
            }
            
            return array_merge($res->json(), $resdetail->json());
        } catch (MeException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * ap040100 - Картын дэлгэрэнгүй /Бонум/
     * @param $request
     */
    public function ap040100(Request $request)
    {
        $v = $request->validate([
            'cardId' => 'required|string',
        ], [
            'cardId.required' => ResponseCodeEnum::required
        ]);

        try {
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->getCardDetail($v['cardId']);
            if (!$res->successful()) {
                throw new MeException('RC000003');
            }
            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * ap040000 - Картын жагсаалт авах /Бонум/
     * @param $request
     */
    public function ap040000(Request $request)
    {
        $v = $request->validate([
            'regNo' => 'required|string',
        ], [
            'regNo.required' => ResponseCodeEnum::required
        ]);

        try {
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->getCustCardInfo($v['regNo']);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }
            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * ap040300 - Карт төлөв солих /Бонум/
     * @param $request
     */
    public function ap040300(Request $request)
    {
        $v = $this->validate($request->all(), [
            'cardId' => 'required|string',
            'status' => 'required|string',
        ], [
            'cardId.required' => ResponseCodeEnum::required,
            'status.required' => ResponseCodeEnum::required,
        ]);

        try {
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->setCartStatus($v['cardId'], $v['status']);
            if (!$res->successful()) {
                throw new MeException('RC000003');
            }
            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * ap040400 - Карт пин солих /Бонум/
     * @param $request
     */
    public function ap040400(Request $request)
    {
        $v = $this->validate($request->all(), [
            'cardId' => 'required|string',
            'pinCode' => 'required|string',
        ], [
            'cardId.required' => ResponseCodeEnum::required,
            'pinCode.required' => ResponseCodeEnum::required,
        ]);

        try {
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->setCartPin($v['cardId'], $v['pinCode']);
            if (!$res->successful()) {
                throw new MeException('RC000003');
            }
            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * ap040101 - Картын гүйлгээний жагсаалт авах /Бонум/
     * @param $request
     */
    public function ap040101(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|string',
            'cardId' => 'required|string',
            // 'dateTo' => 'nullable|string',
            'page' => 'nullable',
            'size' => 'nullable',
        ], [
            'date.required' => ResponseCodeEnum::required,
            'cardId.required' => ResponseCodeEnum::required
        ]);

        try {
            $validated['page'] = $validated['page'] ?? 0;
            $validated['size'] = $validated['size'] ?? 10;
            // dateto filter ажиллахгүй байсан
            // $validated['dateTo'] = $validated['dateTo'] ?? null;
            // if ($validated['dateTo'] == null) {
            //     unset($validated['dateTo']);
            // }
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->getCardTransactions($validated);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }
            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    public function getCustDetail(Request $request)
    {
        $v = $request->validate([
            'regNo' => 'required|string',
        ], [
            'regNo.required' => ResponseCodeEnum::required
        ]);
        try {
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->getCustDetail($v['regNo']);
            if (!$res->successful()) {
                throw new MeException('RC000003');
            }
            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    public function getCustBalance(Request $request)
    {
        $v = $this->validate($request->all(), [
            'regNo' => 'required|string',
            'date' => 'required|digits:6', // YYYYMM
        ], [
            'regNo.required' => ResponseCodeEnum::required,
            'date.required' => ResponseCodeEnum::required,
        ]);

        try {
            $instid = auth()->user()->instid;
            $userid = auth()->user()->id;
            $service = new ApBonumService($instid, $userid);
            $res = $service->getCustBalance($v['regNo'], $v['date']);
            if (!$res->successful()) {
                throw new MeException('RC000003');
            }
            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }


    /**
     * oi000660 - Карт жагсаалт /АПП Дуудах/
     * @param $request
     */
    public function oi000660(Request $request)
    {
        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);


        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }

        try {
            $bonumservice = new ApBonumService($connInst->instid, $user->id);
            $res = $bonumservice->getCustCardInfo($user->regno);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }

            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * oi000670 - Карт дэлгэрэнгүй /АПП Дуудах/
     * @param $request
     */
    public function oi000670(Request $request)
    {
        $v = $request->validate([
            'cardId' => 'required|string'
        ], [
            'cardId.required' => ResponseCodeEnum::required
        ]);

        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }

        try {
            $service = new ApBonumService($connInst['instid'], $connInst['cust_userid']);
            $res = $service->getCardDetail($v['cardId']);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }

            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * oi000680 - Карт харилцагч дэлгэрэнгүй /АПП Дуудах/
     * @param $request
     */
    public function oi000680(Request $request)
    {
        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }

        try {
            $service = new ApBonumService($connInst['instid'], $connInst['cust_userid']);
            $res = $service->getCustDetail($user->regno);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }

            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * oi000690 - Карт статус солих /АПП Дуудах/
     * @param $request
     */
    public function oi000690(Request $request)
    {
        $v = $request->validate([
            'cardId' => 'required|string',
            'status' => 'required|string'
        ], [
            'cardId.required' => ResponseCodeEnum::required,
            'status.required' => ResponseCodeEnum::required
        ]);

        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }

        try {
            $service = new ApBonumService($connInst['instid'], $connInst['cust_userid']);
            $res = $service->setCartStatus($v['cardId'], $v['status']);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }

            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }


    /**
     * oi000700 - Карт пин код солих /АПП Дуудах/
     * @param $request
     */
    public function oi000700(Request $request)
    {
        $v = $request->validate([
            'cardId' => 'required|string',
            'pinCode' => 'required|string'
        ], [
            'cardId.required' => ResponseCodeEnum::required,
            'pinCode.required' => ResponseCodeEnum::required
        ]);

        $service = new ApAuthService();
        $app = $service->CheckMobileApp($request);

        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }

        try {
            $service = new ApBonumService($connInst['instid'], $connInst['cust_userid']);
            $res = $service->setCartPin($v['cardId'], $v['pinCode']);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }

            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * oi000710 - Карт үлдэгдэл авах /АПП Дуудах/
     * @param $request
     */
    public function oi000710(Request $request)
    {
        $v = $request->validate([
            'date' => 'required|digits:6'
        ], [
            'date.required' => ResponseCodeEnum::required
        ]);

        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error("RC000015");
        }

        try {
            $service = new ApBonumService($connInst['instid'], $connInst['cust_userid']);
            $res = $service->getCustBalance($user->regno, $v['date']);

            if (!$res->successful()) {
                throw new MeException('RC000003');
            }

            return $res->json();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * oi000720 - Бүтээгдэхүүний төрлийн жагсаалт /АПП Дуудах/
     * @param $request
     */
    public function oi000720(Request $request)
    {
        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }

        try {
            $service = new ApBonumService($connInst['instid'], $connInst['cust_userid']);
            $res = $service->getCardPlans();

            return $res;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * oi000780 - Карт захиалах /АПП Дуудах/
     * @param $request
     */
    public function oi000780(Request $request)
    {
        $v = $request->validate([
            'productId' => 'required|string',
            'aimag' => 'required|string',
            'sum' => 'required|string',
            'address1' => 'required|string',
            'address2' => 'nullable|string',
            'address3' => 'nullable|string',
            'limit' => 'nullable|numeric',
        ]);

        $serviceAuth = new ApAuthService();
        $app = $serviceAuth->checkMobileApp($request);
        $user = auth()->user();

        $connInst = ApInstCustUserLink::where('instid', $app->instid)
            ->where('cust_userid', $user->id)->where('statusid', 1)->first();

        if (!$connInst) {
            $this->error('RC000015');
        }

        try {
            $service = new ApBonumService($connInst['instid'], $connInst['cust_userid']);
            $plans = $service->getCardPlans();
            
            $selectedPlan = null;
            foreach ($plans as $plan) {
                if ($plan['cardPlanId'] == $v['productId']) {
                    $selectedPlan = $plan;
                    break;
                }
            }

            if (!$selectedPlan) {
                throw new MeException('Бүтээгдэхүүн олдсонгүй.');
            }

            $fee = $selectedPlan['cardFee'] ?? 0;

            if ($fee > 0) {
                $polaris = new PolarisApiRequestService($connInst['instid']);
                // Тухайн хэрэглэгчийн deposit дансыг олж авна.
                $depositAccount = ApAcntDp::where('userid', $user->id)
                    ->where('instid', $connInst['instid'])
                    ->where('prod_code', $polaris->susp_acnt_prod_code)
                    ->where('statusid', 1)
                    ->first();

                // Шимтгэлтэй бол QPay нэхэмжлэх үүсгэнэ
                $qpayService = new ApQpayService($connInst['instid']);
                $invoiceData = [
                    'amount' => $fee,
                    'cur_code' => 'MNT',
                    'description' => 'Карт захиалах шимтгэл: ' . $selectedPlan['productName'],
                    'to_account' => $depositAccount ? $depositAccount->acnt_code : '',
                    'contAcntCode' => $depositAccount ? $depositAccount->acnt_code : '',
                    'typeid' => 5,
                    'instid' => $connInst['instid'],
                ];
                
                $resp = $qpayService->createInvoice($invoiceData);
                
                return [
                    'response_code' => 'RC000000',
                    'response' => [
                        'invoice' => $resp['data'],
                        'order' => $v, // Захиалгын мэдээлэл
                        'status' => 'PENDING_PAYMENT'
                    ]
                ];
            } else {
                // Шимтгэлгүй бол шууд захиалга үүсгэх (туршилт байдлаар)
                return [
                    'response_code' => 'RC000000',
                    'response' => [
                        'message' => 'Захиалга амжилттай хүлээн авлаа.',
                        'status' => 'SUCCESS'
                    ]
                ];
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }
}
