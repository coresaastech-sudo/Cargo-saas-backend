<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEbarimt;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPInstBrch;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Ad\Entities\AdEbarimtActionCode;
use Modules\Tr\Entities\TrJournal;
use Modules\Tr\Entities\LnTxn;
use Modules\Tr\Entities\DpTxn;
use Modules\Cr\Entities\Views\VwCrCustAllAcntList;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Gp\Entities\Views\VwGPInstTxnFeeList;
use Modules\Gp\Entities\GPInstFeeType;
use Modules\Tr\Entities\IaDeTxn;
use Illuminate\Support\Str;

use Exception;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\GpctionCode;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\EBarimtJob;
use Modules\Ln\Entities\LnAccount;
use Modules\Ln\Entities\LnAccountHist;

class AdEbarimtService
{

    // public $pos_api_address = 'http://10.0.2.8';
    // public $pos_api_port = '5000';
    public $providerConfig;
    public $provider;
    public $connection;
    public $instid;
    public $user;

    public function __construct($instid, $user)
    {
        $this->user = $user;

        $tmpprovider = VwGPProviderConf::where("code", '6')->where("instid", $instid)->first();
        if ($tmpprovider) {
            $this->provider = $tmpprovider;
            $this->providerConfig = json_decode($tmpprovider->config, true);
            $connConfig = VwGPConnConf::where("id", $this->provider->connid)->first();
            if ($connConfig) {
                $this->connection = json_decode($connConfig->config, true);
                $this->instid = $instid;
            } else {
                throw new MeException("RC000174");
            }
        } else {
            throw new MeException("RC000173", [
                'inst' => $instid,
                'code' => '6'
            ]);
        }
    }

    public function ActionCode()
    {
        return $this->providerConfig['ebarimt_notification_pc'];
    }

    public function checkApi()
    {
        $soFileName = $this->providerConfig['soFileName'] ?? '';
        return Http::get($this->connection['pos_api_address'] . ":" . $this->connection['pos_api_port'] . "/checkApi?lib=$soFileName")->json();
    }

    public function put($data, $jrno, $moduleid, $txndate, $curcode, $txncode, $repleca = null, $consumerNo = null)
    {
        try {
            DB::beginTransaction();

            if ($repleca === null) {
                $repleca = [];
                foreach ($data['data'] as $field => $value) {
                    $fieldlower = strtolower($field);
                    $repleca[$fieldlower] = $value;
                }

                $repleca['created_at'] = getNow();
                $repleca['instid'] = $this->instid;
                $repleca['jrno'] = $jrno;
                $repleca['moduleid'] = $moduleid;
                $repleca['txndate'] = $txndate;
                $repleca['curcode'] = $curcode;
                $repleca['txncode'] = $txncode;
                $repleca['res_success'] = 0;
                $repleca['created_by'] = $this->user->id;
                $tax = AdEbarimt::create($repleca);
            } else {
                $tax = $repleca;
            }

            // Assuming that $tax is an Eloquent model, and not an array. If it's not, the next block will catch the exception.
            if (!$tax instanceof AdEbarimt) {
                throw new Exception("Failed to create AdEbarimt record.");
            }

            $soFileName = $this->providerConfig['soFileName'] ?? '';
            $version = $this->providerConfig['version'] ?? 2;

            if ($version == 2) {
                $response = Http::post(
                    $this->connection['pos_api_address'] . ":" . $this->connection['pos_api_port'] . "/put?lib=$soFileName",
                    $data
                );
                $response_array = (array) $response->json();

                foreach ($tax->response_fields as $field) {
                    if (array_key_exists(substr($field, 4), $response_array)) {
                        $fieldunder = strtolower($field);
                        $tax->{$fieldunder} = $response_array[substr($field, 4)];

                        if (substr($field, 4) == "warningmsg" || substr($field, 4) == "lotterywarningmsg") {
                            $this->sendData();
                            unset($data["id"]);
                            $data["prev_id"] = $tax->id;
                            // $this->put($data); // Recursion? Be cautious with recursive calls as they can lead to infinite loops or maximum execution time exceeded errors.
                        }
                    }
                }
            } else {
                $cust = $data['data']['cust'];
                $data = $data['data'];

                // Log::debug('$data');
                // Log::debug($data);
                if (!empty($cust)) {
                    $customerTin = null;
                    if ($cust->custtypecode == 0) {
                        $billType = 'B2C_RECEIPT';
                    } elseif ($cust->custtypecode == 1) {
                        $billType = 'B2B_RECEIPT';
                        $customerTin = $cust->tin;
                    }

                    $items = array();
                    foreach ($data['stocks'] as $key => $stock) {
                        $items[] = [
                            "name" => $stock['code_name'],
                            "barCodeType" => "UNDEFINED",
                            "classificationCode" => $stock['classification_code'],
                            "measureUnit" => $stock['measureUnit'],
                            "qty" => $stock['qty'],
                            "unitPrice" => $stock['unitPrice'],
                            "totalAmount" => $stock['totalAmount'],
                            "totalVAT" => @$this->providerConfig['taxType'] == "VAT_ABLE" ?  $stock['vat'] : 0,
                            "totalCityTax" => $stock['cityTax'],
                            "taxProductCode" =>  @$this->providerConfig['taxProductCode'] ?? '405', //Зээл олгох үйлчилгээ
                        ];
                    }

                    $numberbrch = extractNumber($this->user->brchno);

                    if ($cust->custtypecode == 1) {
                        if (empty($customerTin)) {
                            $response = Http::get($this->connection['apiGetTin'] . $cust->id1);
                            if ($response->ok()) {
                                $response_array = (array) $response->json();
                                if ($response_array['status'] == 200) {
                                    $customerTin = $response_array['data'];
                                }
                            }
                        }
                    }

                    $data = [
                        "totalAmount" => $data['amount'],
                        "totalVAT" => @$this->providerConfig['taxType'] == "VAT_ABLE" ? $data['vat'] : 0,
                        "totalCityTax" => $data['cityTax'],
                        "districtCode" => $data['districtCode'],
                        "merchantTin" => $this->providerConfig['merchantTin'] ?? "",
                        "posNo" => $this->providerConfig['posNo'] ?? "",
                        "customerTin" => $cust->custtypecode == 0 ? '' : (string) $customerTin,
                        "consumerNo" => $consumerNo ?? '',
                        "type" => $billType,
                        "branchNo" => ($numberbrch > 999 ? 999 : ($numberbrch == 0 ? 999 : $numberbrch)) . "",
                        "receipts" => [
                            [
                                "totalAmount" => $data['amount'],
                                "totalVAT" =>  @$this->providerConfig['taxType'] == "VAT_ABLE" ? $data['vat'] : 0,
                                "totalCityTax" => $data['cityTax'],
                                "taxType" => @$this->providerConfig['taxType'] ?? 'VAT_FREE',
                                "merchantTin" => $this->providerConfig['merchantTin'] ?? "",
                                "items" => $items
                            ]
                        ],
                        "payments" => [
                            [
                                "code" => "CASH",
                                "paidAmount" => $data['amount'],
                                "status" => "PAID",
                            ]
                        ]
                    ];
                }
                $startTime = Carbon::now()->getTimestampMs();
                $r = new GPLogRequestList();
                $r->userid = 1;
                $r->url = $this->connection['pos_apiv3_address'] . "/rest/receipt";
                $r->method = 'POST';
                $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
                $r->save();
                try {
                    $response = Http::post(
                        $r->url,
                        $data
                    );

                    $response_array = (array) $response->json();
                    $original_qrdata = $response_array['qrData'] ?? '';
                    $original_lottery = $response_array['lottery'] ?? '';
                    $response_array['qrData'] = '***';
                    $response_array['lottery'] = '***';
                    $r->response = json_encode($response_array, JSON_UNESCAPED_UNICODE);
                    $r->responsecode = $response->status();
                    $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
                    $r->save();
                    if ($response_array['status'] == 'SUCCESS') {
                        $tax->res_success = 1;
                        $tax->res_qrdata = $original_qrdata;
                        $tax->res_lottery = $original_lottery;
                    } else {
                        $tax->res_warningmsg = $response_array['message'];
                        $tax->res_success = 0;
                        $tax->res_errorcode = $response->status();
                    }
                    $tax->res_billid = $response_array['id'] ?? null;
                    $tax->res_date = $response_array['date'];
                } catch (\Throwable $th) {
                    Log::debug($th);

                    $r->response = $th->getMessage();
                    $r->responsecode = 500;
                    $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
                    $r->save();
                }
            }

            $tax->save();

            DB::commit();

            return [
                'tax' => [
                    'id' => $tax->id,
                    'jrno' => $tax->jrno,
                    'moduleid' => $tax->moduleid,
                    'txndate' => $tax->txndate,
                    'consumerNo' => $tax->consumerNo,
                    'amount' => $tax->amount,
                    'vat' => $tax->vat,
                    'baseAmount' => $tax->amount - $tax->vat,
                    'cashAmount' => $tax->cashamount,
                    'nonCashAmount' => $tax->noncashamount,
                    'billType' => $tax->billtype,
                    'taxType' => $tax->taxtype,
                    'res_billid' => $tax->res_billid,
                    'res_qrdata' => $tax->res_qrdata,
                    'res_internalcode' => $tax->res_internalcode,
                    'res_date' => $tax->res_date,
                    'res_lottery' => $tax->res_lottery,
                    'res_lotteryWarningMsg' => $tax->res_lotterywarningmsg,
                    'prev_id' => $tax->prev_id,
                ],
                'response' => $response->json()
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage()); // It's a good idea to log errors.
            // Re-throw the exception or return a meaningful error response.
            throw $e;
        }
    }

    public function put2($data)
    {
        try {
            DB::beginTransaction();

            $tax = new AdEbarimt();
            foreach ($tax->fillable as $field) {
                if (array_key_exists($field, $data["data"])) {
                    $tax->$field = $data["data"][$field];
                }
            }
            $tax->created_at = getNow();
            if (empty($tax->clientid)) {
                $tax->clientid = auth()->user() ? auth()->user()->clientid : 1;
            }
            $tax->save();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        $soFileName = $this->providerConfig['soFileName'] ?? '';

        $response = Http::post(
            $this->connection['pos_api_address'] . ":" . $this->connection['pos_api_port'] . "/put?lib=$soFileName",
            $data
        );

        $response_array = (array) $response->json();

        foreach ($tax->response_fields as $field) {
            if (array_key_exists(substr($field, 4), $response_array)) {
                $tax->$field = $response_array[substr($field, 4)];
                if (substr($field, 4) == "warningMsg" || substr($field, 4) == "lotteryWarningMsg") {
                    $this->sendData();
                    unset($data["id"]);
                    $data["prev_id"] = $tax->id;
                    // $this->put($data);
                }
            }
        }
        $tax->save();
        DB::commit();
        return $tax;
    }

    public function sendData()
    {
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();
        $r->userid = 1;
        $r->method = 'GET';
        if ($this->providerConfig['version'] == 2) {
            $soFileName = $this->providerConfig['soFileName'] ?? '';
            $r->url = $this->connection['pos_api_address'] . ":" . $this->connection['pos_api_port'] . "/sendData?lib=$soFileName";
        } else {
            $r->url = $this->connection['pos_apiv3_address'] . "/rest/sendData";
        }
        $r->save();
        $response = Http::get($r->url);
        $r->response = json_encode($response ?? [], JSON_UNESCAPED_UNICODE);
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();
        $response = $response->json();
        return $response;
    }

    public function getInfo()
    {
        $soFileName = $this->providerConfig['soFileName'] ?? '';
        return Http::get($this->connection['pos_api_address'] . ":" . $this->connection['pos_api_port'] . "/getInformation?lib=$soFileName")->json();
    }

    public function returnBill($data)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();
        $r->userid = 1;
        $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
        $r->method = 'POST';
        if ($this->providerConfig['version'] == 2) {
            $soFileName = $this->providerConfig['soFileName'] ?? '';
            $r->url = $this->connection['pos_api_address'] . ":" . $this->connection['pos_api_port'] . "/returnBill?lib=$soFileName";
            $r->save();
            $response = Http::post($r->url, $data);
        } else {
            $r->url = $this->connection['pos_apiv3_address'] . "/rest/receipt";
            $r->save();
            $response = Http::delete($r->url, $data['data']);
        }
        $r->response = json_encode($response->json() ?? [], JSON_UNESCAPED_UNICODE);
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();
        return $response;
    }

    public function getActionCodeList($AC)
    {
        return AdEbarimtActionCode::where("parent_ACTION_CODE", $AC)->where("instid", $this->instid)->where("statusid", 1)->distinct()->pluck('ACTION_CODE')->toArray();
    }

    public function getTransactionList($txncodelist, $jrno)
    {
        $journal = TrJournal::where("instid", $this->instid)->where("jrno", $jrno)->orderby('jritemno', 'ASC')->first();

        $feeTxnCode = [];
        if ($journal) {
            $ConnTxnFees = VwGPInstTxnFeeList::where("ACTION_CODE", $journal->parenttxncode)->where('statusid', 1)->where('instid', $this->instid)->get();
            if ($ConnTxnFees->isNotEmpty()) {
                foreach ($ConnTxnFees as $txnFee) {
                    $fee = GPInstFeeType::where('feecode', $txnFee->feecode)->where('instid', $this->instid)->where('statusid', 1)->first();
                    if ($fee && $fee->sendvat == 1) {
                        $feeTxnCode[] = $fee->txncode;
                    }
                }
            }
        }

        $journalEntries = TrJournal::select("jrno", "txnamount", "curcode", "txncode", "txndesc", "retailacntno", "parenttxncode")->where('retailacntmod', 'DP')->where("instid", $this->instid)->where("jrno", $jrno)->where(function ($query) use ($txncodelist, $feeTxnCode) {
            $query->whereIn("txncode", $txncodelist)
                ->orWhereIn("parenttxncode", $feeTxnCode);
        });

        // DP Данснаас гарсан гүйлгээг шүүж гарган ибаримт үүсгэв.
        $dptxn = DpTxn::select("jrno", "txnamount", "curcode", "txncode", "txndesc", "acntno as retailacntno", "parenttxncode")->where("instid", $this->instid)->where("jrno", $jrno)->where(function ($query) use ($txncodelist, $feeTxnCode) {
            $query->whereIn("txncode", $txncodelist)
                ->orWhereIn("parenttxncode", $feeTxnCode);
        });



        $entries = $journalEntries->union($dptxn)->get();

        return $entries;
    }

    public function generateSingleVat($AC, $data, $consumerNo = null)
    {
        $pc_list = $this->getActionCodeList($AC);
        $stock = [];
        $sum = 0;
        $acntno = null;
        $loanAcntno = null;
        $jrno = @$data['txnJrno'];
        $txndate = null;
        $curcode = null;

        if (@$this->providerConfig['enableEOD'] == '1' || @$this->providerConfig['enableEOD'] == 1) {
            return null;
        }

        $lnTransactionList = LnTxn::where('jrno', $data['txnJrno'])->where('corr', 0)->whereIn('txncode', $pc_list)->where('instid', $this->instid)->where('statusid', 1)->get();

        $dpTransactionList = DpTxn::where('jrno', $data['txnJrno'])->where('corr', 0)->where('instid', $this->instid)->where('statusid', 1)->get();

        if ($lnTransactionList->isNotEmpty()) {
            $loanAcntno = $lnTransactionList->first()->acntno;
        }

        if (Str::startsWith($AC, 'ln') && $dpTransactionList->isNotEmpty()) {
            $acntno = $dpTransactionList->first()->acntno;
        }

        // 2 жагсаалтыг нэгтгэж 1 жагсаалт үүсгэх
        $transactionList = $lnTransactionList->concat($dpTransactionList);

        foreach ($transactionList as $item) {
            if (in_array($item['txncode'], $pc_list)) {
                if ($loanAcntno == null) {
                    $loanAcntno = $item['acntno'];
                }
                /// Бэлэн төлөх гүйлгээн дээр txnacntmod орж ирэхгүй байна.
                if ($acntno == null) {
                    $acntno = $item['acntno'];
                } else {
                    // Худалдсан зээл дээр ибаримт үүсгэхгүй
                    if (@$this->providerConfig['disableSaleTxn'] == 1 || @$this->providerConfig['disableSaleTxn'] == '1') {
                        $account = LnAccount::where('acntno', $loanAcntno)->where('instid', $this->instid)->where('statusid', 8)->first();
                        if ($account) {
                            return null;
                        }
                    }
                }
                $classification_code = "7113900";
                $pccode = AdEbarimtActionCode::where("ACTION_CODE", $item['txncode'])->where('statusid', '<>', -1)->where('instid', $this->instid)->first();
                if ($pccode) {
                    $classification_code = $pccode->classification_code;
                }

                $amount = $this->getAmount($item['txnamount'], $item['curcode']);
                $sum += floatval($amount['formattedAmount']);
                $stock[] = [
                    "code" => $item['txncode'],
                    "name" => $item['txndesc'],
                    "measureUnit" => 'Ш',
                    "qty" => '1.00',
                    "unitPrice" => $amount['formattedAmount'],
                    "totalAmount" => $amount['formattedAmount'],
                    "vat" => $amount['formattedVatAmount'],
                    "cityTax" => '0.00',
                    "code_name" => (GpctionCode::where('ACTION_CODE', $item['txncode'])
                        ->where('statusid', 1)->first())->name,
                    "classification_code" => $classification_code,
                ];

                $txndate = $item['txndate'];
                $jrno = $item['jrno'];
                $curcode = $item['curcode'];
            } else {
                if (empty($acntno)) {
                    $acntno = $item['acntno'];
                }
                // $data['txnPreview'] = collect($data['txnPreview'])->where('txnacntmod', 'DP')->values();

                /// Зээл олголтын шимтгэл байвал гүйлгээг DP-ээр шалгана.
                $ConnTxnFees = VwGPInstTxnFeeList::where("ACTION_CODE", $AC)->where('statusid', 1)->where('instid', $this->instid)->get();

                if ($ConnTxnFees->isNotEmpty()) {
                    foreach ($ConnTxnFees as $txnFee) {
                        $fee = GPInstFeeType::where('feecode', $txnFee->feecode)->where('instid', $this->instid)->where('statusid', 1)->first();
                        if ($fee && $fee->sendvat == 1 && $item['parenttxncode'] == $fee->txncode) {
                            $amount = $this->getAmount($item['txnamount'], $item['curcode']);
                            $sum += floatval($amount['formattedAmount']);

                            $stock[] = [
                                "code" => $fee->txncode,
                                "name" => $fee->feecode,
                                "measureUnit" => 'Ш',
                                "qty" => '1.00',
                                "unitPrice" => $amount['formattedAmount'],
                                "totalAmount" => $amount['formattedAmount'],
                                "vat" => $amount['formattedVatAmount'],
                                "cityTax" => '0.00',
                                "code_name" => $fee->name,
                                "classification_code" => $fee->classification_code,
                            ];

                            $txndate = $item['txndate'];
                            $jrno = $item['jrno'];
                            $curcode = $item['curcode'];
                        }
                    }
                }
            }
        }


        if (count($stock) > 0 && ($acntno !== null && $sum !== 0)) {
            $amount = $this->getAmount($sum, 'MNT');
            $retailacnt = VwCrCustAllAcntList::where("instid", $this->instid)->where("acntno", $acntno)->where("statusid", "<>", -1)->first();
            $region = VwGPInstBrch::select('taxregion', 'taxsubregion')->where('statusid', '<>', -1)->where('brchno', $this->user->brchno)->where('instid', $this->instid)->first();

            if (!empty($region)) {
                $version = $this->providerConfig['version'] ?? 2;
                if ($version == 2) {
                    $districtCode = $region->taxsubregion;
                } else {
                    $districtCode = $region->taxregion . $region->taxsubregion;
                }
            } else {
                $districtCode = "";
            }

            // Оны эхний өдрийг тодорхойлох
            $firstDayOfYear = Carbon::now()->startOfYear();

            $txndate = CoreService::getEodSysdate($this->instid);
            $txndateCarbon = Carbon::parse($txndate);

            // 2 огноог харьцуулах
            $firstDayOfYearFormatted = $firstDayOfYear->format('Y-m-d');
            if ($txndateCarbon->isSameDay($firstDayOfYear)) {

                $lnAcntHist = LnAccount::where('instid', $this->instid)
                    ->where('acntno', $acntno)
                    ->first();
            } else {
                // Зээлийн дансны түүхэн мэдээлэл авах
                $lnAcntHist = LnAccountHist::where('instid', $this->instid)
                    ->whereDate('txndate', $firstDayOfYearFormatted)
                    ->where('acntno', $acntno)
                    ->first();
            }

            // Татварын татгалзсан дүнг тооцоолох
            // tmp_capbint + tmp_acrbint (үндсэн хүү)
            // tmp_capfint + tmp_acrfint (нэмэлт хүү)
            // tmp_capcint + tmp_acrcint (бусад хүү)
            $refusedAmount = 0;
            if ($lnAcntHist) {
                $refusedAmount = ($lnAcntHist->tmp_capbint ?? 0)
                    + ($lnAcntHist->tmp_acrbint ?? 0)
                    + ($lnAcntHist->tmp_capfint ?? 0)
                    + ($lnAcntHist->tmp_acrfint ?? 0)
                    + ($lnAcntHist->tmp_capcint ?? 0)
                    + ($lnAcntHist->tmp_acrcint ?? 0);
            }

            // Log::debug('$refusedAmount');
            // Log::debug($refusedAmount);
            // Татгалзсан дүнтэй ибаримтуудыг авах
            $ebarimtList = AdEbarimt::where('instid', $this->instid)
                ->where('acntno', $acntno)
                ->whereNotNull('refused_amount')
                ->get();

            // Татгалзсан дүнгийн нийт дүнг тооцоолох
            $totalRefusedAmount =  $refusedAmount - $ebarimtList->sum('refused_amount') ?? 0;

            // Log::debug('$totalRefusedAmount');
            // Log::debug($totalRefusedAmount);
            if ($totalRefusedAmount < 0) {
                $totalRefusedAmount = 0;
            }
            if ($sum < $totalRefusedAmount) {
                $amount =  $this->getAmount($sum, 'MNT');
            } else {
                $amount =  $this->getAmount($sum - $totalRefusedAmount, 'MNT');
            }


            // Log::debug('$amount');
            // Log::debug($amount);

            if ($totalRefusedAmount > 0) {

                $refused = [];

                $refused['created_at'] = getNow();
                $refused['instid'] = $this->instid;
                $refused['jrno'] = (int)($jrno . '0000000');
                $refused['moduleid'] = 'ln';
                $refused['txndate'] = $txndate;
                $refused['curcode'] = $curcode;
                $refused['txncode'] = $AC;
                $refused['res_success'] = 9;
                $refused['created_by'] = $this->user->id;
                $refused['refused_amount'] = $sum < $totalRefusedAmount ? $sum : $totalRefusedAmount;
                $refused['acntno'] = $acntno;
                $refused['amount'] =  $sum < $totalRefusedAmount ? $sum : $totalRefusedAmount;
                $refused['noncashamount'] = $amount['formattedAmount'];
                $refused['cashamount'] = '0.00';
                $refused['vat'] =  $amount['formattedVatAmount'];

                AdEbarimt::create($refused);


                // Log::debug('$$sum - $totalRefusedAmount');
                // Log::debug($sum - $totalRefusedAmount);
                if (($sum - $totalRefusedAmount) > 0) {
                    $balstock[] = [
                        "code" => $stock[0]['code'],
                        "name" => $stock[0]['name'],
                        "measureUnit" => 'Ш',
                        "qty" => '1.00',
                        "unitPrice" => $amount['formattedAmount'],
                        "totalAmount" => $amount['formattedAmount'],
                        "vat" => $amount['formattedVatAmount'],
                        "cityTax" => '0.00',
                        "code_name" => $stock[0]['code_name'],
                        "classification_code" => $stock[0]['classification_code']
                    ];

                    $stock = $balstock;
                } else {
                    return null;
                }
            }

            if (!empty($retailacnt)) {
                $cust = VwCrCustList::where("instid", $this->instid)->where("custno", $retailacnt->custno)->where("statusid", "<>", -1)->first();

                if (!empty($cust)) {
                    $putinfo = null;
                    if ($cust->custtypecode === 0) {
                        $data = [
                            'amount' => $amount['formattedAmount'],
                            'nonCashAmount' => $amount['formattedAmount'],
                            'vat' => $amount['formattedVatAmount'],
                            "billType" => "1",
                            'cashAmount' => "0.00",
                            "customerNo" => '',
                            'cityTax' => "0.00",
                            "districtCode" => $districtCode,
                            "stocks" => $stock,
                            'cust' => $cust,
                            "brchno" => $this->user->brchno,
                            "acntno" => $retailacnt->acntno,
                        ];

                        $moduleid = substr($AC, 0, 2);
                        $putinfo = $this->put(["data" => $data], $jrno, $moduleid, $txndate, $curcode, $AC, null, $consumerNo);
                    } elseif ($cust->custtypecode === 1) {
                        $data = [
                            'amount' => $amount['formattedAmount'],
                            'nonCashAmount' => $amount['formattedAmount'],
                            'vat' => $amount['formattedVatAmount'],
                            "billType" => "3",
                            'cashAmount' => "0.00",
                            "customerNo" => '',
                            'cityTax' => "0.00",
                            "districtCode" => $districtCode,
                            "stocks" => $stock,
                            'cust' => $cust,
                            "brchno" => $this->user->brchno,
                            "acntno" => $retailacnt->acntno,
                        ];
                        $moduleid = substr($AC, 0, 2);
                        $putinfo = $this->put(["data" => $data], $jrno, $moduleid, $txndate, $curcode, $AC, null, $consumerNo);
                    }

                    if ($putinfo) {
                        return [
                            'tax' => $putinfo['tax'],
                            'response' => $putinfo['response'],
                            'cust' => $cust
                        ];
                    }
                } else {
                    Log::error('Cust not found: ' . $retailacnt->custno . ' jrno: ' . $jrno . ' inst: ' . $this->instid);
                }
            } else {
                Log::error('Retail acntno not found: ' . $acntno . ' jrno: ' . $jrno . ' inst: ' . $this->instid);
            }
        } else {
            Log::error('Ebarimt acntno not found: ' . $acntno . ' jrno: ' . $jrno . ' inst: ' . $this->instid);
        }
    }

    public function generateVat($AC, $data)
    {
        $pc_list = $this->getActionCodeList($AC);
        $stock = [];
        $sum = 0;
        $acntno = null;
        $jrno = null;
        $txndate = null;
        $curcode = null;

        foreach ($data as $item) {
            if (in_array($item['txncode'], $pc_list)) {
                $classification_code = "7113900";
                $pccode = AdEbarimtActionCode::where("ACTION_CODE", $item['txncode'])->where('statusid', '<>', -1)->where('instid', $this->instid)->first();
                if ($pccode) {
                    $classification_code = $pccode->classification_code;
                }
                $amount = $this->getAmount($item['txnamount'], $item['curcode']);
                $sum += floatval($amount['formattedAmount']);

                $stock[] = [
                    "code" => $item['txncode'],
                    "name" => $item['txndesc'],
                    "measureUnit" => 'Ш',
                    "qty" => '1.00',
                    "unitPrice" => $amount['formattedAmount'],
                    "totalAmount" => $amount['formattedAmount'],
                    "vat" => $amount['formattedVatAmount'],
                    "cityTax" => '0.00',
                    "code_name" => (GpctionCode::where('ACTION_CODE', $item['txncode'])
                        ->where('statusid', 1)->first())->name,
                    "classification_code" => $classification_code,
                ];
                if (empty($acntno)) {
                    if (Str::upper($item['txnacntmod']) == 'DP') {
                        $acntno = $item['retailacntno'];
                    }
                }

                if (empty($acntno)) {
                    $acntno = $item['acntno'];
                }
                $txndate = $item['txndate'];
                $jrno = $item['jrno'];
                $curcode = $item['curcode'];
            } else {
                $ConnTxnFees = VwGPInstTxnFeeList::where("ACTION_CODE", $AC)->where('statusid', 1)->where('instid', $this->instid)->get();
                if ($ConnTxnFees->isNotEmpty()) {
                    foreach ($ConnTxnFees as $txnFee) {
                        $fee = GPInstFeeType::where('feecode', $txnFee->feecode)->where('instid', $this->instid)->where('statusid', 1)->first();
                        if ($fee && $fee->sendvat == 1 && $item['parenttxncode'] == $fee->txncode) {
                            $amount = $this->getAmount($item['txnamount'], $item['curcode']);
                            $sum += floatval($amount['formattedAmount']);
                            $stock[] = [
                                "code" => $fee->txncode,
                                "name" => $fee->feecode,
                                "measureUnit" => 'Ш',
                                "qty" => '1.00',
                                "unitPrice" => $amount['formattedAmount'],
                                "totalAmount" => $amount['formattedAmount'],
                                "vat" => $amount['formattedVatAmount'],
                                "cityTax" => '0.00',
                                "code_name" => $fee->name,
                                "classification_code" => $fee->classification_code,
                            ];

                            $txndate = $item['txndate'];
                            $jrno = $item['jrno'];
                            $curcode = $item['curcode'];
                        }
                    }
                }
            }
        }


        if (count($stock) > 0 && ($acntno !== null && $sum !== 0)) {
            $amount = $this->getAmount($sum, 'MNT');
            $retailacnt = VwCrCustAllAcntList::where("instid", $this->instid)->where("acntno", $acntno)->where("statusid", "<>", -1)->first();
            $region = VwGPInstBrch::select('taxregion', 'taxsubregion')->where('statusid', '<>', -1)->where('brchno', $this->user->brchno)->where('instid', $this->instid)->first();

            if (!empty($region)) {
                $version = $this->providerConfig['version'] ?? 2;

                if ($version == 2) {
                    $districtCode = $region->taxsubregion;
                } else {
                    $districtCode = $region->taxregion . $region->taxsubregion;
                }
            } else {
                $districtCode = "";
            }

            if (!empty($retailacnt)) {
                $cust = VwCrCustList::where("instid", $this->instid)->where("custno", $retailacnt->custno)->where("statusid", "<>", -1)->first();
                if (!empty($cust)) {
                    $putinfo = null;
                    if ($cust->custtypecode === 0) {
                        $data = [
                            'amount' => $amount['formattedAmount'],
                            'nonCashAmount' => $amount['formattedAmount'],
                            'vat' => $amount['formattedVatAmount'],
                            "billType" => "1",
                            'cashAmount' => "0.00",
                            "customerNo" => '',
                            'cityTax' => "0.00",
                            "districtCode" => $districtCode,
                            "stocks" => $stock,
                            'cust' => $cust,
                            "brchno" => $this->user->brchno,
                            "acntno" => $retailacnt->acntno,
                        ];

                        $moduleid = substr($AC, 0, 2);
                        $taxInfo = AdEbarimt::where("instid", $this->instid)->where("jrno", $jrno)->where("txncode", $AC)->first();
                        $putinfo = $this->put(["data" => $data, "classification_code" => $classification_code], $jrno, $moduleid, $txndate, $curcode, $AC, $taxInfo);
                    } elseif ($cust->custtypecode === 1) {
                        $data = [
                            'amount' => $amount['formattedAmount'],
                            'nonCashAmount' => $amount['formattedAmount'],
                            'vat' => $amount['formattedVatAmount'],
                            "billType" => "3",
                            'cashAmount' => "0.00",
                            "customerNo" => '',
                            'cityTax' => "0.00",
                            "districtCode" => $districtCode,
                            "stocks" => $stock,
                            'cust' => $cust,
                            "brchno" => $this->user->brchno,
                            "acntno" => $retailacnt->acntno,
                        ];
                        $moduleid = substr($AC, 0, 2);
                        $taxInfo = AdEbarimt::where("instid", $this->instid)->where("jrno", $jrno)->where("txncode", $AC)->first();
                        $putinfo = $this->put(["data" => $data, "classification_code" => $classification_code], $jrno, $moduleid, $txndate, $curcode, $AC, $taxInfo);
                    }
                    if ($putinfo)
                        return [
                            'tax' => $putinfo['tax'],
                            'response' => $putinfo['response'],
                            'cust' => $cust
                        ];
                }
            }
        }
    }

    public function getAmount($amount, $currency)
    {
        $version = $this->providerConfig['version'] ?? 2;

        if ($version == 2) {
            $length = 2;
        } else {
            $length = 4;
        }

        if ($currency === 'MNT') {
            $formattedAmount = $this->formatAsPlainNumber($amount, 2);
            $formattedVatAmount = $this->formatAsPlainNumber(($amount / (100 + $this->providerConfig['vat_percentage'])) * $this->providerConfig['vat_percentage'], $length);
            return ['formattedAmount' => $formattedAmount, 'formattedVatAmount' => $formattedVatAmount];
        } else {
            // currency changing
        }
    }

    public function formatAsPlainNumber($amount, $decimals = 2)
    {
        return sprintf("%.{$decimals}f", $amount);
    }

    public function sendEbarimtEmail($vatid, $jrno, $txncode)
    {
        $tax = AdEbarimt::where("instid", $this->instid)->where("id", $vatid)->first();
        $pc_list = $this->getActionCodeList($txncode);
        $txnList = $this->getTransactionList($pc_list, $jrno);
        $acntno = null;
        foreach ($txnList as $item) {
            if (in_array($item['txncode'], $pc_list)) {
                if (empty($acntno)) {
                    if (Str::upper($item['txnacntmod']) == 'DP') {
                        $acntno = $item['retailacntno'];
                    }
                }

                if (empty($acntno)) {
                    $acntno = $item['acntno'];
                }
            }
            if (!empty($acntno)) {
                break;
            }
        }

        if (empty($acntno)) {
            return null;
        }

        $retailacnt = VwCrCustAllAcntList::where("instid", $this->instid)->where("acntno", $acntno)->where("statusid", "<>", -1)->first();
        if (!empty($retailacnt)) {
            $cust = VwCrCustList::where("instid", $this->instid)
                ->where("custno", $retailacnt->custno)->where("statusid", "<>", -1)->first();
        } else {
            return null;
        }
        return [
            'tax' => [
                'id' => $tax->id,
                'jrno' => $tax->jrno,
                'moduleid' => $tax->moduleid,
                'txndate' => $tax->txndate,
                'consumerNo' => $tax->consumerNo,
                'amount' => $tax->amount,
                'vat' => $tax->vat,
                'baseAmount' => $tax->amount - $tax->vat,
                'cashAmount' => $tax->cashamount,
                'nonCashAmount' => $tax->noncashamount,
                'billType' => $tax->billtype,
                'taxType' => $tax->taxtype,
                'res_billid' => $tax->res_billid,
                'res_qrdata' => $tax->res_qrdata,
                'res_internalcode' => $tax->res_internalcode,
                'res_date' => $tax->res_date,
                'res_lottery' => $tax->res_lottery,
                'res_lotteryWarningMsg' => $tax->res_lotterywarningmsg,
                'prev_id' => $tax->prev_id,
            ],
            'cust' => $cust
        ];
    }

    public function rebillVat($vatid)
    {
        $vat = AdEbarimt::where("instid", $this->instid)->where("id", $vatid)->first();

        $returnBill = [
            'returnBillId' => $vat->res_billid,
            'id' => $vat->res_billid,
            'date' => $vat->res_date
        ];

        $response = $this->returnBill(['data' => $returnBill]);

        $response_array = (array) $response->json();
        $success = false;
        if (!empty($response_array)) {
            if (array_key_exists("success", $response_array) && $response_array['success'] === true) {
                $success = true;
            }
        }

        if ($response->ok()) {
            $success = true;
        }

        if ($success) {
            $vat->res_success = -1;
            $vat->res_billid = null;
            $vat->res_qrdata = null;
            $vat->res_internalcode = null;
            $vat->res_date = null;
            $vat->res_lottery = null;
            $vat->res_lotterywarningmsg = null;
            $vat->res_errorcode = null;
            $vat->res_message = null;
            $vat->res_warningmsg = null;
            $vat->save();
        }
    }

    public function resendEbarimt($sysdate, $instid)
    {
        if (@$this->providerConfig['enableEOD'] == '1' || @$this->providerConfig['enableEOD'] == 1) {
            $txnCodes = ['ln902034', 'ln902032', 'ln902030', 'ln902033', 'ln902035', 'ln902031'];

            $results = DB::table('ln_txn as a')
                ->select('a.created_at', 'a.jrno', 'a.acntno', DB::raw("ROUND(SUM(a.txnamount)::numeric, 2) as txndamount"))
                ->where('a.instid', $instid)
                ->where('a.corr', '<>', 1)
                ->where('a.txndate', '=', $sysdate)
                ->whereIn('a.txncode', $txnCodes)
                ->whereNotIn('a.jrno', function ($query) use ($instid) {
                    $query->select('e.jrno')
                        ->from('ad_ebarimt as e')
                        ->where('e.res_success', '1')
                        ->where('e.instid', $instid);
                })
                ->groupBy('a.jrno', 'a.created_at', 'a.acntno')
                ->orderBy('a.created_at')
                ->get();

            foreach ($results as $result) {
                $data = LnTxn::where('jrno', $result->jrno)->where('corr', '<>', 1)->whereIn('txncode', $txnCodes)->where('instid', $instid)->get();

                if ($this->user) {
                    EBarimtJob::dispatch($data[0]['parenttxncode'], ['txnPreview' => $data], $this->user)->onQueue("sendVAT");
                }
            }

            return ['count' => count($results)];
        } else {
            return ['count' => 0];
        }
    }
}
