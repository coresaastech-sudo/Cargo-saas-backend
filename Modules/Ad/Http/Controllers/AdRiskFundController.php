<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdResAccountBal;
use Modules\Ad\Entities\Views\VwAdResAccountBal;
use Modules\Ad\Entities\Views\VwAdResAccountBalCalc;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\RiskFundTxnJob;
use Modules\Gp\Http\Controllers\GPController;
use BeyondCode\LaravelWebSockets\Dashboard\Http\Controllers\AuthenticateDashboard;
use Modules\Ad\Entities\Views\VwAdIaResAccountBalCalc;
use Modules\Ad\Entities\Views\VwAdRecResAccountBalCalc;

class AdRiskFundController extends Controller
{
    /**
     * Өмчлөх бусад хөрөнгийн эрсдэлийн сангийн жагсаалт
     */
    public function ad019072(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdIaResAccountBalCalc::where('instid', auth()->user()->instid),
            [
                ['field' => 'acntno', 'dir' => 'asc']
            ]
        );
    }
    /**
     * Авлагын эрсдэлийн сангийн жагсаалт
     */
    public function ad019071(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdRecResAccountBalCalc::where('instid', auth()->user()->instid),
            [
                ['field' => 'recpayno', 'dir' => 'asc']
            ]
        );
    }
    /**
     * Зээлийн эрсдэлийн сангийн жагсаалт
     */
    public function ad019070(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdResAccountBalCalc::where('instid', auth()->user()->instid),
            [
                ['field' => 'acntno', 'dir' => 'asc']
            ]
        );
    }
    /**
     * Өмчлөх бусад хөрөнгийн эрсдэлийн сангийн түүх
     */
    public function ad019172(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdResAccountBal::where('instid', auth()->user()->instid)->where('acnttype', '=', 'IA'),
            [
                ['field' => 'resdate', 'dir' => 'desc']
            ]
        );
    }
    /**
     * Авлагын эрсдэлийн сангийн түүх
     */
    public function ad019171(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdResAccountBal::where('instid', auth()->user()->instid)->where('acnttype', '=', 'R'),
            [
                ['field' => 'resdate', 'dir' => 'desc']
            ]
        );
    }
    /**
     * Зээлийн эрсдэлийн сангийн түүх
     */
    public function ad019170(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdResAccountBal::where('instid', auth()->user()->instid)->where('acnttype', '=', 'LN'),
            [
                ['field' => 'resdate', 'dir' => 'desc']
            ]
        );
    }
    /**
     * Бүх эрсдэлийн сан байгуулах Job
     */
    public function doRiskFundtran($v, $acnttype)
    {
        if ($this->isOnEodJob()) {
            $this->error('RC000196');
        }
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        foreach ($v['txns'] as $txn) {
            $acntno = "";
            if ($acnttype == 'R') {
                $acntno = $txn['recpayno'];
            } else {
                $acntno = $txn['acntno'];
            }
            $iscreated = AdResAccountBal::where('acntno', $acntno)
                ->where('acnttype', $acnttype)
                ->where('instid', $instid)
                ->where('statusid', 0)
                ->first();
            if (empty($txn['res_acntno'])) {
                $this->error("RC000065");
            }
            if ($iscreated) {
                AdResAccountBal::where('acntno', $acntno)
                    ->where('acnttype', $acnttype)
                    ->where('instid', $instid)
                    ->where('statusid', 0)->update([
                        'acntno' => $acntno,
                        'acnttype' => $acnttype,
                        'balance' => $acnttype == 'R' ? $txn['recbalance'] : ($acnttype == 'IA' ? $txn['currentbal'] : $txn['princbal']),
                        'clscode' => $txn['clscode'],
                        'resdate' => CoreService::getTxnDate($instid),
                        'resbal' => $txn['newresbal'],
                        'rescur' => $txn['curcode'],
                        'res_acntno' => $txn['res_acntno'],
                        'res_acnttype' => $txn['res_acnttype'],
                        'cont_acntno' => $txn['cont_acntno'],
                        'cont_acnttype' => $txn['cont_acnttype'],
                        'amount' => $txn['amount'],
                        'rescls' => $txn['rescls'],
                        'errordesc' => null,
                        'statusid' => 0,
                        'updated_by' => auth()->user()->id,
                    ]);
            } else {
                AdResAccountBal::create([
                    'acntno' => $acntno,
                    'acnttype' => $acnttype,
                    'balance' => $acnttype == 'R' ? $txn['recbalance'] : ($acnttype == 'IA' ? $txn['currentbal'] : $txn['princbal']),
                    'clscode' => $txn['clscode'],
                    'resdate' => CoreService::getTxnDate($instid),
                    'resbal' => $txn['newresbal'],
                    'rescur' => $txn['curcode'],
                    'res_acntno' => $txn['res_acntno'],
                    'res_acnttype' => $txn['res_acnttype'],
                    'cont_acntno' => $txn['cont_acntno'],
                    'cont_acnttype' => $txn['cont_acnttype'],
                    'amount' => $txn['amount'],
                    'rescls' => $txn['rescls'],
                    'errordesc' => null,
                    'statusid' => 0,
                    'instid' => $instid,
                    'created_by' => $userid,
                    'updated_by' => $userid,
                ]);
            }
        }

        RiskFundTxnJob::dispatch(
            $userid,
            $instid,
            $acnttype
        )->onQueue('RiskFundTxnJob');

        return 'Гүйлгээ PENDING төлөвт бүртгэгдлээ.';
    }

    /**
     * Авлагын эрсдэлийн сангийн гүйлгээ хийх
     *
     * @param  mixed $request
     * @return string
     */
    public function ad019971(Request $request)
    {

        $v = $this->validate($request, [
            'txns' => 'required|array',
            'txns.*.res_acnttype' => 'required',
            'txns.*.res_acntno' => 'required',
            'txns.*.cont_acnttype' => 'required',
            'txns.*.cont_acntno' => 'required',
            'txns.*.curcode' => 'required',
            'txns.*.amount' => 'required',
            'txns.*.recpayno' => 'required',
            'txns.*.clscode' => 'required',
            'txns.*.recbalance' => 'required',
            'txns.*.newresbal' => 'required',
            'txns.*.resacnttype' => 'nullable',
            'txns.*.rescls' => 'nullable',
        ], [
            'txns.*.res_acnttype.required' => ResponseCodeEnum::required,
            'txns.*.res_acntno.required' => ResponseCodeEnum::required,
            'txns.*.cont_acnttype.required' => ResponseCodeEnum::required,
            'txns.*.cont_acntno.required' => ResponseCodeEnum::required,
            'txns.*.curcode.required' => ResponseCodeEnum::required,
            'txns.*.amount.required' => ResponseCodeEnum::required,
            'txns.*.recpayno.required' => ResponseCodeEnum::required,
            'txns.*.clscode.required' => ResponseCodeEnum::required,
            'txns.*.recbalance.required' => ResponseCodeEnum::required,
            'txns.*.newresbal.required' => ResponseCodeEnum::required,
            'txns.required' => ResponseCodeEnum::required,
            'txns.array' => ResponseCodeEnum::array
        ]);
        return $this->doRiskFundtran($v, 'R');
    }

    /**
     * Зээлийн эрсдэлийн сангийн гүйлгээ хийх
     *
     * @param  mixed $request
     * @return string
     */
    public function ad019970(Request $request)
    {

        $v = $this->validate($request, [
            'txns' => 'required|array',
            'txns.*.res_acnttype' => 'required',
            'txns.*.res_acntno' => 'required',
            'txns.*.cont_acnttype' => 'required',
            'txns.*.cont_acntno' => 'required',
            'txns.*.curcode' => 'required',
            'txns.*.amount' => 'required',
            'txns.*.acntno' => 'required',
            'txns.*.clscode' => 'required',
            'txns.*.princbal' => 'required',
            'txns.*.newresbal' => 'required',
            'txns.*.resacnttype' => 'nullable',
            'txns.*.rescls' => 'nullable',
        ], [
            'txns.*.res_acnttype.required' => ResponseCodeEnum::required,
            'txns.*.res_acntno.required' => ResponseCodeEnum::required,
            'txns.*.cont_acnttype.required' => ResponseCodeEnum::required,
            'txns.*.cont_acntno.required' => ResponseCodeEnum::required,
            'txns.*.curcode.required' => ResponseCodeEnum::required,
            'txns.*.amount.required' => ResponseCodeEnum::required,
            'txns.*.acntno.required' => ResponseCodeEnum::required,
            'txns.*.clscode.required' => ResponseCodeEnum::required,
            'txns.*.princbal.required' => ResponseCodeEnum::required,
            'txns.*.newresbal.required' => ResponseCodeEnum::required,
            'txns.required' => ResponseCodeEnum::required,
            'txns.array' => ResponseCodeEnum::array
        ]);
        return $this->doRiskFundtran($v, 'LN');
    }

    /**
     * Өмчлөх бусад хөрөнгийн эрсдэлийн сангийн гүйлгээ хийх
     *
     * @param  mixed $request
     * @return string
     */
    public function ad019972(Request $request)
    {

        $v = $this->validate($request, [
            'txns' => 'required|array',
            'txns.*.res_acnttype' => 'required',
            'txns.*.res_acntno' => 'required',
            'txns.*.cont_acnttype' => 'required',
            'txns.*.cont_acntno' => 'required',
            'txns.*.curcode' => 'required',
            'txns.*.amount' => 'required',
            'txns.*.acntno' => 'required',
            'txns.*.clscode' => 'required',
            'txns.*.currentbal' => 'required',
            'txns.*.newresbal' => 'required',
            'txns.*.resacnttype' => 'nullable',
            'txns.*.rescls' => 'nullable',
        ], [
            'txns.*.res_acnttype.required' => ResponseCodeEnum::required,
            'txns.*.res_acntno.required' => ResponseCodeEnum::required,
            'txns.*.cont_acnttype.required' => ResponseCodeEnum::required,
            'txns.*.cont_acntno.required' => ResponseCodeEnum::required,
            'txns.*.curcode.required' => ResponseCodeEnum::required,
            'txns.*.amount.required' => ResponseCodeEnum::required,
            'txns.*.acntno.required' => ResponseCodeEnum::required,
            'txns.*.clscode.required' => ResponseCodeEnum::required,
            'txns.*.currentbal.required' => ResponseCodeEnum::required,
            'txns.*.newresbal.required' => ResponseCodeEnum::required,
            'txns.required' => ResponseCodeEnum::required,
            'txns.array' => ResponseCodeEnum::array
        ]);
        return $this->doRiskFundtran($v, 'IA');
    }

    public function isOnEodJob()
    {
        return app(\App\Services\QueueJobInspector::class)
            ->has('RiskFundTxnJob', RiskFundTxnJob::class, auth()->user()->instid);
    }

    /**
     * WebSocket - эрх шалгах
     */
    public function ad019960(Request $request)
    {
        $GPcontroller = new GPController();
        if (!$GPcontroller->checkActionCode('ad019960')) {
            $this->error("SR0023", ['AC' => 'ad019960']);
        }
        $authcontroller = new AuthenticateDashboard();
        $token = $authcontroller->__invoke($request);
        return $token;
    }
}
