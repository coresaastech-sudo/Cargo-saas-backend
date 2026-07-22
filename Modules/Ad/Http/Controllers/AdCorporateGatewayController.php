<?php

namespace Modules\Ad\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCorporateGateway;
use Modules\Ad\Entities\Views\VwAdCorporateGateway;
use Modules\Ad\Http\Services\AdCorporateGatewayGolomtService;
use Modules\Ad\Http\Services\AdCorporateGatewayKhanService;
use Modules\Ad\Http\Services\AdCorporateGatewayService;
use Modules\Ad\Http\Services\AdCorporateGatewayStateService;
use Modules\Ad\Http\Services\AdCorporateGatewayTdbService;
use Modules\Ad\Http\Services\AdCorporateGatewayXacService;
use Modules\Gp\Entities\GPInstSeq;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Http\Services\CoreService;

class AdCorporateGatewayController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdCorporateGateway::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)
        );
    }

    /**
     * Store a newly created resource in storage.
     * @AC ad070200
     * @param Request $request
     */
    public function store(Request $request)
    {

        $validated = $this->validate($request, [
            'statements' => 'required|array', // [1,2]
            'statements.*.bankcode' => 'required',
            'statements.*.banktxndate' => 'required',
            'statements.*.bankjrno' => 'required',
            //Төрийн банкны excel дээр дансны дугаар байхгүй
            'statements.*.bankacntno' => 'required_if:statements.*.bankcode,!34',
            'statements.*.bankfromacntno' => 'nullable',
            'statements.*.txnamount' => 'required',
            'statements.*.sign' => 'required',
            'statements.*.balance' => 'required',
            'statements.*.curcode' => 'required',
            'statements.*.txndesc' => 'required',
            'statements.*.acntno' => 'nullable',
            'statements.*.txn_jrno' => 'nullable',
        ]);

        $list = collect();
        $reslist = collect();
        $duplicatedCount = 0;
        $bankCode = $validated['statements'][0]['bankcode'];
        // $corporateGatewayService = new AdCorporateGatewayKhanService(auth()->user()->instid, auth()->user()->id);

        $provider = VwGPProviderConf::where('code', $bankCode)->where('instid', auth()->user()->instid)->first();
        if (isset($provider)) {
            $providerConfig = json_decode($provider->config, true);
        } else {
            throw new MeException("RC000173", [
                'inst' => auth()->user()->instid,
                'code' => $bankCode
            ]);
        }

        $corporateGatewayService = new AdCorporateGatewayService(auth()->user()->instid, auth()->user()->id, $providerConfig);

        foreach ($validated['statements'] as $statement) {
            $corporateGateway = AdCorporateGateway::where('banktxndate', $statement['banktxndate'])->where('bankjrno', $statement['bankjrno'])->where('bankcode', $statement['bankcode'])->where('instid', auth()->user()->instid)->first();
            if (!isset($corporateGateway)) {
                $txndesc = $statement['txndesc'] ?? '';

                if (isset($txndesc) && Str::length($txndesc) > 75) {
                    $txndesc = Str::substr($txndesc, 0, 74);
                }

                $req = [
                    'bankcode' => $statement['bankcode'],
                    'banktxndate' => Carbon::parse($statement['banktxndate']),
                    'bankjrno' => $statement['bankjrno'],
                    'bankacntno' => $statement['bankacntno'],
                    'bankfromacntno' => @$statement['bankfromacntno'] ?? null,
                    'txnamount' => $statement['txnamount'],
                    'sign' => $statement['sign'],
                    'curcode' => $statement['curcode'],
                    'txndesc' => $txndesc,
                    'balance' => $statement['balance'],
                    'acntno' => $statement['acntno'],
                    'statusid' => 1,
                    'instid' => auth()->user()->instid,
                    'created_at' => getNow(),
                    'created_by' => auth()->user()->id,
                ];

                if ($req['bankfromacntno'] === null) {
                    $providerConf = VwGPProviderConf::where('code', $bankCode)->where('instid', auth()->user()->instid)->first();
                    $providerConfig = json_decode($providerConf->config, true);
                    $req['bankfromacntno'] = $providerConfig['state_bankacntno'];
                }

                try {
                    $corporateGateway = $corporateGatewayService->storeCorporateGateway($req);

                    $res = $corporateGatewayService->processCorporateGateway($corporateGateway, $statement['bankfromacntno']);

                    if ($res != null) {
                        $reslist->push($res);
                    }
                } catch (Exception $ex) {
                    Log::error($ex);
                }

                $list->push($corporateGateway);
            } else {
                $duplicatedCount++;
            }
        }

        return ['totalcount' => count($list), 'successcount' => count($reslist), 'failedcount' => count($list) - count($reslist), 'duplicated' => $duplicatedCount];
    }

    /**
     * Preview statement identification.
     * @AC ad070201
     * @param Request $request
     */
    public function preview(Request $request)
    {
        $validated = $this->validate($request, [
            'statements' => 'required|array',
            'bankcode' => 'required',
        ]);

        $bankCode = $validated['bankcode'];
        $provider = VwGPProviderConf::where('code', $bankCode)->where('instid', auth()->user()->instid)->first();

        if (!isset($provider)) {
            throw new MeException("RC000173", [
                'inst' => auth()->user()->instid,
                'code' => $bankCode
            ]);
        }

        $providerConfig = json_decode($provider->config, true);
        $corporateGatewayService = new AdCorporateGatewayService(auth()->user()->instid, auth()->user()->id, $providerConfig);

        $results = [];
        foreach ($validated['statements'] as $statement) {
            $data = new AdCorporateGateway([
                'txndesc' => $statement['txndesc'] ?? '',
                'instid' => auth()->user()->instid,
                'bankcode' => $bankCode,
                'sign' => $statement['sign'] ?? '+',
            ]);

            // Resolve bankfromacntno if needed
            $bankfromacntno = $statement['bankfromacntno'] ?? $providerConfig['state_bankacntno'] ?? null;

            $preview = $corporateGatewayService->previewCorporateGateway($data, $bankfromacntno);

            if ($preview) {
                $statement['acntno'] = $preview['acntno'];
                $statement['acntname'] = $preview['acntname'];
                $statement['loan_accountno'] = $preview['loan_accountno'];
                $statement['acnttype'] = $preview['acnttype'];
            } else {
                $statement['acntno'] = null;
                $statement['acntname'] = null;
                $statement['loan_accountno'] = null;
                $statement['acnttype'] = null;
            }

            $results[] = $statement;
        }

        return ['list' => $results];
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

        $corporateGateway = VwAdCorporateGateway::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->first();

        if ($corporateGateway) {
            return $corporateGateway;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required',
            'bankacntno' => 'nullable',
            'bankcode' => 'required',
            'bankjrno' => 'required',
            'banktxndate' => 'required',
            'txnamount' => 'required',
            'sign' => 'required',
            'balance' => 'nullable',
            'curcode' => 'required',
            'txndesc' => 'nullable',
            'txn_jrno' => 'nullable',
            'reason' => 'nullable',
        ]);

        if (empty($validated['id'])) {
            $this->error("RC000011");
        }

        if (!empty($validated['reason'])) {
            $validated['statusid'] = 4;
        }

        $validated['updated_by'] = auth()->user()->id;
        AdCorporateGateway::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        AdCorporateGateway::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
    }


    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @AC ad070500
     * @return Response
     */
    public function pullStatement(Request $request)
    {
        $providers = VwGPProviderConf::whereIn('code', ['34', '05', '04', '15', '32'])->where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->get();

        $instid = auth()->user()->instid;
        $eodison = GPInstSeq::where('instid', $instid)
            ->where('seqid', 'EODISON')->where('seqno', 1)->first();

        if ($eodison) {
            throw new MeException("RC000108");
        }

        if ($providers->isNotEmpty()) {
            foreach ($providers as $provider) {
                $config = json_decode($provider->config, true);

                try {
                    if (!empty($config['pull_statement']) && $config['pull_statement'] == 1) {
                        if ($provider['code'] == '05') {
                            // Хаан банк
                            $khanService = new AdCorporateGatewayKhanService(auth()->user()->instid, auth()->user()->id);
                            $accounts = $khanService->getAccountList();

                            if (is_array($accounts['accounts']) && count($accounts['accounts']) > 0) {
                                foreach ($accounts['accounts'] as $account) {
                                    // allow_list хоосон эсвэл байхгүй бол бүх дансыг боловсруулах
                                    // allow_list байгаа бол зөвхөн жагсаалтад байгаа дансыг боловсруулах
                                    $shouldProcess = true;

                                    if (!empty($config['allow_list']) && is_array($config['allow_list']) && count($config['allow_list']) > 0) {
                                        $shouldProcess = in_array($account['number'], $config['allow_list']);
                                    }

                                    if ($shouldProcess) {
                                        // Дансны гүйлгээг боловсруулах
                                        $transactions = $khanService->getAccountStatement($account['number']);
                                        if (is_array($transactions) && $transactions['transactions'] && count($accounts['accounts']) > 0) {
                                            foreach ($transactions['transactions'] as $transaction) {
                                                try {
                                                    $khanService->checkStatement($transaction, $account['number'], $provider['code']);
                                                } catch (MeException $ex) {
                                                    Log::debug($ex);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else if ($provider['code'] == '34') {
                            // Төрийн банк
                            $stateService = new AdCorporateGatewayStateService(auth()->user()->instid, auth()->user()->id);
                            $account = $config['state_acntno'];

                            $date = new Carbon(CoreService::getTxnDate(auth()->user()->instid));
                            $date = $date->format('Y/m/d');
                            $transactions = $stateService->getAccountStatement($account, $date, $date);
                            if (is_array($transactions) && !empty($transactions)) {
                                foreach ($transactions as $transaction) {
                                    try {
                                        $stateService->checkStatement($transaction, $account);
                                    } catch (MeException $ex) {
                                        Log::error($ex);
                                    }
                                }
                            }
                        } else if ($provider['code'] == '15') {
                            // Голомт Банк
                            $golomtService = new AdCorporateGatewayGolomtService(auth()->user()->instid, auth()->user()->id);

                            $date = new Carbon(CoreService::getTxnDate(auth()->user()->instid));
                            $startDate = $date->format('Y-m-d');
                            $endDate = $date->endOfDay()->format('Y-m-d');
                            $accounts = $golomtService->getAccountList();

                            if ($accounts['operAccounts'] && count($accounts['operAccounts']) > 0) {
                                foreach ($accounts['operAccounts'] as $account) {
                                    // allow_list хоосон эсвэл байхгүй бол бүх дансыг боловсруулах
                                    // allow_list байгаа бол зөвхөн жагсаалтад байгаа дансыг боловсруулах
                                    $shouldProcess = true;

                                    if (!empty($config['allow_list']) && is_array($config['allow_list']) && count($config['allow_list']) > 0) {
                                        $shouldProcess = in_array($account['accountNumber'], $config['allow_list']);
                                    }


                                    if ($shouldProcess) {
                                        $transactions = $golomtService->operAccountStatement($account['accountNumber'], $startDate, $endDate);
                                        if (!empty($transactions['statements']) && is_array($transactions['statements']) && $transactions['statements'] && count($transactions['statements']) > 0) {
                                            // tranPostedDate-ээр буурах дарааллаар эрэмбэлэх
                                            usort($transactions['statements'], function ($a, $b) {
                                                try {
                                                    $dateA = isset($a['tranPostedDate']) ? Carbon::parse($a['tranPostedDate'])->timestamp : 0;
                                                    $dateB = isset($b['tranPostedDate']) ? Carbon::parse($b['tranPostedDate'])->timestamp : 0;
                                                } catch (Exception $e) {
                                                    $dateA = isset($a['tranPostedDate']) ? strtotime($a['tranPostedDate']) : 0;
                                                    $dateB = isset($b['tranPostedDate']) ? strtotime($b['tranPostedDate']) : 0;
                                                }
                                                return $dateA - $dateB; // ASC order (хуучин эхэнд)
                                            });

                                            foreach ($transactions['statements'] as $transaction) {
                                                if (!empty($transaction['accNum'])) {
                                                    try {
                                                        $golomtService->checkStatement($transaction, $account['accountNumber']);
                                                    } catch (MeException $ex) {
                                                        Log::error($ex);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else if ($provider['code'] == '04') {
                            // Худалдаа хөгжлийн банк
                            $tdbService = new AdCorporateGatewayTdbService(auth()->user()->instid, auth()->user()->id);
                            $accounts = $tdbService->getAcntList();

                            if ($accounts['success']) {
                                $date = new Carbon(CoreService::getTxnDate(auth()->user()->instid));
                                $date = $date->format('Y/m/d');

                                if (is_array($accounts['data']) && count($accounts['data']) > 0) {
                                    foreach ($accounts['data'] as $account) {
                                        // allow_list хоосон эсвэл байхгүй бол бүх дансыг боловсруулах
                                        // allow_list байгаа бол зөвхөн жагсаалтад байгаа дансыг боловсруулах
                                        $shouldProcess = true;

                                        if (!empty($config['allow_list']) && is_array($config['allow_list']) && count($config['allow_list']) > 0) {
                                            $shouldProcess = in_array($account['ACNTNO'], $config['allow_list']);
                                        }

                                        if ($shouldProcess) {
                                            // Дансны гүйлгээг боловсруулах
                                            $transactions = $tdbService->getAccountStatement($account['ACNTNO'], $date, $date);
                                            if ($transactions['success']) {
                                                if (is_array($transactions) && $transactions['txn'] && count($transactions['txn']) > 0) {
                                                    foreach ($transactions['txn'] as $transaction) {
                                                        try {
                                                            $tdbService->checkStatement($transaction, $account['ACNTNO'], $account['CURCODE']);
                                                        } catch (MeException $ex) {
                                                            //Log::debug($ex);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else if ($provider['code'] == '32') {
                            // Хас банк - allow_list дахь данс бүрээр шууд хуулга татна
                            // (getAccountList() дуудах шаардлагагүй).
                            $xacService = new AdCorporateGatewayXacService(auth()->user()->instid, auth()->user()->id);

                            $allowList = (!empty($config['allow_list']) && is_array($config['allow_list']))
                                ? $config['allow_list']
                                : [];

                            if (!empty($allowList)) {
                                $date = new Carbon(CoreService::getTxnDate(auth()->user()->instid));
                                $date = $date->format('Y-m-d');

                                foreach ($allowList as $accountItem) {
                                    // allow_list-ийн элемент нь string (зөвхөн дансны дугаар)
                                    // эсвэл array (['ACCOUNTID' => ..., 'CURRENCYID' => ...]) байж болно.
                                    if (is_array($accountItem)) {
                                        $accountId = $accountItem['ACCOUNTID'] ?? null;
                                        $currency  = $accountItem['CURRENCYID'] ?? ($accountItem['currency'] ?? 'MNT');
                                    } else {
                                        $accountId = (string) $accountItem;
                                        $currency  = 'MNT';
                                    }

                                    if (empty($accountId)) {
                                        continue;
                                    }

                                    $transactions = $xacService->getAccountStatement($accountId, $date, $date);
                                    if (!is_array($transactions) || empty($transactions['data'])) {
                                        continue;
                                    }
                                    if (($transactions['code'] ?? null) != 100 && ($transactions['code'] ?? null) != 0) {
                                        continue;
                                    }

                                    // TRN_REF_NO ASC, хоосон нь сүүлд
                                    usort($transactions['data'], function ($a, $b) {
                                        $refA = $a['TRN_REF_NO'] ?? '';
                                        $refB = $b['TRN_REF_NO'] ?? '';
                                        if ($refA === '' && $refB === '') return 0;
                                        if ($refA === '') return 1;
                                        if ($refB === '') return -1;
                                        return strcmp($refA, $refB);
                                    });

                                    foreach ($transactions['data'] as $transaction) {
                                        try {
                                            $xacService->checkStatement($transaction, $accountId, $currency);
                                        } catch (MeException $ex) {
                                            // Log::debug($ex);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (Exception $ex) {
                    Log::error($ex);
                }
            }
        }
    }

    private function mapToKhanFormat($txn)
    {
        return [
            'journal' => $txn['JrNo'],
            'relatedAccount' => $txn['ContAcntNo'],
            'description' => $txn['TxnDesc'],
            'amount' => $txn['Amount'],
            'balance' => $txn['Balance'],
            'curcode' => $txn['CurCode'] ?? "MNT",
            'postDate' => $txn['TxnDate'],
            'time' => Carbon::parse($txn['SysDate'])->format('His'),
            'correction' => $txn['Corr'],
        ];
    }
}
