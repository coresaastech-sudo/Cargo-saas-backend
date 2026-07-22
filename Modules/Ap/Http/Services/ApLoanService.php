<?php

namespace Modules\Ap\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Ad\Http\Services\AdCorporateGatewayKhanService;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApTxnJournal;
use Modules\Ap\Http\Controllers\ApInstController;
use Modules\Gp\Entities\GPInstFeeTypeCur;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\GpctionCode;
use Modules\Gp\Entities\GPProviderConf;
use Modules\Gp\Entities\Views\VwGPInstFeeList;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Controllers\TrJournalController;
use Modules\Tr\Http\Services\DpTxnService;
use Modules\Ap\Enums\ApAccountTypeEnum;
use Modules\Cr\Entities\CrCustInd;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Jobs\EBarimtJob;
use Modules\Ln\Entities\LnAccountType;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\Views\VwGPProviderConf;

class ApLoanService
{
    public function getLoanSaving($data)
    {
        $lnService = null;

        try {
            $stpservice = new ApStopService();
            $resp = $stpservice->checkStopSrevice([
                'instid' => $data['instid'],
                'serviceCode' => '10000002'
            ]);
            if ($resp['status'] != 1) {
                throw new MeException($resp['message']);
            }


            $polaris = new PolarisApiRequestService($data['instid']);
            $acntService = new ApAcntService();
            $intRate = 0;
            $cgwBank = $this->getCallCgwBankCode($data);
            $cgwBankCode = $cgwBank;
            $acntService->getTdAccountDetail($data['txnAcntCode'], $data['instid']);
            $intdetail = $acntService->getAccountInt($data['txnAcntCode'], $data['instid'], ApAccountTypeEnum::td);

            foreach ($intdetail as $key => $value) {
                $value = json_decode(json_encode($value));
                // Log::debug($value->intTypeCode);
                if ($value->intTypeCode == 'SIMPLE_INT') {
                    if (empty($value->intRate)) {
                        throw new MeException('Хадгаламжийн дансны хүүний мэдээлэл алдаатай байна.');
                    }
                    $intRate = $value->intRate;
                    break;
                }
            }

            $acnt = ApAcntDp::where('acnt_code', $data['txnAcntCode'])
                ->where('instid', $data['instid'])->first();
            if (empty($acnt)) {
                throw new MeException($data['txnAcntCode'] . ' дугаартай хадгаламжийн данс олдсонгүй.');
            }
            if ($acnt->cur_code != 'MNT') {
                throw new MeException($acnt->cur_code . ' системд зөвшөөрөгдөөгүй валют.');
            }
            $user = auth()->user();
            $cust = ApCustomer::where('instid', $data['instid'])
                ->where('regno', $user->regno)->where('statusid', '1')->first();
            if (empty($cust)) {
                throw new MeException('RC000015');
            }

            $contaccountno = '';
            if ($polaris->is_use_cust_susp_acnt == 1 || $polaris->is_use_cust_susp_acnt == '1') {
                $casaAcnt = ApAcntDp::where('prod_code', $polaris->susp_acnt_prod_code)
                    ->whereIn('status', ['O', '4', '1'])->where('instid', $data['instid'])
                    ->where('cust_code', $cust->cif)
                    ->orderBy('acnt_code', 'desc')->first();

                if (empty($casaAcnt)) {
                    throw new MeException('RC000209');
                }
                $contaccountno = $casaAcnt->acnt_code;
            } else {
                $contaccountno = $polaris->internalAccount;
            }
            if (empty($contaccountno)) {
                throw new MeException('RC000209');
            }
            // Хадгаламжийн данс барьцаалж зээл олгох шимтгэл
            if (!isset($polaris->fee->fee_td)) {
                throw new MeException('RC000188');
            }
            $fee = VwGPInstFeeList::where('feecode', $polaris->fee->fee_td)
                ->where('instid', $data['instid'])
                ->where('statusid', 1)->first();

            // $fee = DicFee::where('operation', 13610265)
            //     ->where('prodcode', $polaris->savingLoan->loanAcnt->prodCode)
            //     ->where('instid', $data['instid'])->first();

            if (empty($fee)) {
                throw new MeException('RC000188');
            }

            $feeconf = GPInstFeeTypeCur::where('feecode', $fee->feecode)
                ->where('instid', $data['instid'])
                ->where('statusid', 1)->first();
            if (empty($feeconf) || empty($feeconf->formula)) {
                throw new MeException('RC000188');
            }
            $fee_config = json_decode($feeconf->formula, true);
            $req_amount = $data['amount'];
            $calc_amounts_resp = $this->getCalcAmounts($fee_config, $data['amount']);
            $calc_amounts = [];
            $calc_amounts = $calc_amounts_resp;
            $calc_fee_amount = $calc_amounts['calc_fee_amount'];
            $adv_amount = $calc_amounts['adv_amount'];
            $loan_amount = $calc_amounts['loan_amount'];
            if ($calc_fee_amount > $data['amount']) {
                throw new MeException('RC000189');
            }
            // Тухайн байгууллагын ямар дансан дээр гүйлгээ хийгдэхээс шалтгаалан дотоодын данс авдаг болов.
            $pp = GPProviderConf::where("code", $cgwBankCode)
                ->where('statusid', 1)->where('instid', $data['instid'])->first();
            if (empty($pp)) {
                throw new MeException("$cgwBankCode дугаартай банкны тохиргоо хийгдээгүй байна.");
            }
            $providerBank = json_decode($pp->config, true);
            if (!isset($providerBank['internal_bank_account_no'])) {
                throw new MeException('Зээл олголтын дотоодын данс тохируулагдаагүй байна.');
            }
            $onlineteller = CoreService::getInstGp($data['instid'], 'ONLINETELLERNUMBER');

            // Зээлийн гүйлгээний мэдээлэл үүсгэх
            $lnService = new ApTxnJournal();
            $lnService->txn_acnt_code = $data['txnAcntCode'];
            $lnService->cur_code = $acnt->cur_code;
            // $lnService->tran_amt = $data['amount'];
            // $lnService->tran_cur_code = $acnt->cur_code;
            $lnService->identity_type = "MANUAL";
            $lnService->rate = 1;
            $lnService->internal_cont_acnt_code = $contaccountno;
            $lnService->cont_amount = $data['amount'];
            $lnService->txn_amount = $data['amount'];
            $lnService->cont_cur_code = 'MNT';
            $lnService->cont_rate = 1;
            $lnService->txn_desc = $polaris->savingLoan->txnDesc;
            $lnService->tcust_name = $cust->fname;
            $lnService->tcust_addr = $cust->address ?? "";
            $lnService->tcust_register = $cust->regno;
            $lnService->tcust_register_mask = $cust->register_mask_code;
            $lnService->tcust_contact = $cust->phone;
            $lnService->source_type = "OI";
            $lnService->is_tmw = 1;
            $lnService->is_preview = 0;
            $lnService->is_preview_fee = 0;
            $lnService->cont_acnt_code = $data['contAcntCode'];
            $lnService->cont_bank_code = $data['contBankCode'];
            $lnService->created_at = Carbon::now();
            $lnService->userid = $user->id;
            $lnService->created_by = $onlineteller ?? 1;
            $lnService->statusid = 0;
            $lnService->txn_type = 1;
            $lnService->instid = $data['instid'];
            $lnService->fee_id = $fee->id;
            $lnService->fee_inst_amount = $calc_amounts['calc_instfee_amount'];
            $lnService->fee_sys_amount = $calc_amounts['calc_sysfee_amount'];
            $lnService->oper_code = 13610265;
            $lnService->prodcode = $polaris->savingLoan->loanAcnt->prodCode;
            $lnService->save();
            $sysDate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $data['instid']]));

            $to = Carbon::createFromFormat('Y-m-d', $sysDate);
            $from = Carbon::createFromFormat('Y-m-d', formatDate($acnt->maturity_date, false));
            $termLen = $to->diffInMonths($from);
            $acntname = ($cust->fname ?? '') . ' ' . ($cust->lname ?? '');
            $acntname2 = empty($cust->shortname2) ? $cust->fname2 : $cust->shortname2;
            $providertype = CoreService::getInstGp($data['instid'], 'MEAPPPROVIDER');
            if ($providertype == 'MECORE') {
                $lnService->oper_code = 'ln902021';
                $crCust = CrCustInd::where('custno', $cust->cif)->where('instid', $data['instid'])->where('statusid', '<>', -1)->first();

                if ($crCust && $crCust->bl == 1) {
                    throw new MeException('RC000263');
                }

                $tmpuser = auth()->user();

                $user = GPInstUser::where('instid', $data['instid'])->find(
                    $onlineteller
                );
                if (empty($user)) {
                    throw new MeException('RC000201');
                }
                Auth::setUser($user);

                // Бүтээгдэхүүний хугацааны нэгж шалгаж үзэх
                $prod = LnAccountType::where('instid', $data['instid'])
                    ->where('prodcode', $polaris->savingLoan->loanAcnt->prodCode)
                    ->where('statusid', 1)
                    ->first();
                if ($prod) {
                    switch ($prod->termbasis) {
                        case 'D':
                            $termLen = $to->diffInDays($from);
                            break;
                        case 'M':
                            $termLen = $to->diffInMonths($from);
                            break;
                        case 'Y':
                            $termLen = $to->diffInYears($from);
                            break;

                        default:
                            $termLen = $to->diffInMonths($from);
                            break;
                    }
                }

                // Зээлийн данс шинээр үүсгэх ln010200
                $process = GpctionCode::where('ACTION_CODE', 'ln010200')->first();
                $route = $process->controller . '@' . $process->function;
                request()->merge([
                    "brchno" => $polaris->brchCode,
                    "custno" => $cust->cif,
                    "prodcode" => $polaris->savingLoan->loanAcnt->prodCode,
                    "name" => $acntname,
                    "name2" => $acntname2,
                    "curcode" => "MNT",
                    "loantype" => "01", // зээлийн төрөл
                    "purpcode" => $polaris->savingLoan->loanAcnt->purpose, // зээлийн зориулалт
                    "subpurpcode" => $polaris->savingLoan->loanAcnt->subPurpose, // зээлийн зориулалт
                    "lnsubtype" => $polaris->savingLoan->loanAcnt->lnsubtype, // ZMS зээлийн төрөл
                    "segcode" => $cust->segment,
                    "intrate" => $polaris->savingLoan->loanAcnt->marginRate === 0 ? $prod->intrate : $polaris->savingLoan->loanAcnt->marginRate + $intRate,
                    "termlen" => $termLen,
                    "termbasis" => $prod->termbasis,
                    "begdate" => $sysDate,
                    "enddate" => $acnt->maturity_date,
                    "approvdate" => $sysDate,
                    "approvamount" => $loan_amount,
                    "openeddate" => $sysDate,
                    "sourcecode" => 2,
                    "statusid" => 5,
                    "capfreq" => "S",
                    "repayacntno" => $contaccountno,
                    // "repayacntcur" => "MNT, төлбөр хийх данс
                ]);

                $txndata = App::call($route);
                // Log::debug('Зээлийн данс');
                // Log::debug($txndata);
                $new_loan_acnt = $txndata;
                // Барьцаа хөрөнгө холбох ln010203
                $process = GpctionCode::where('ACTION_CODE', 'ln010203')->first();
                $route = $process->controller . '@' . $process->function;

                request()->merge([
                    "acntno" => $txndata,
                    "collacntno" => $data['txnAcntCode'],
                    "curdate" => $sysDate,
                    // "morno" => $polaris->savingLoan->collAcnt->morno,
                    "perlon" => 100,
                    "releaseorder" => 1,
                    "registered_by" => "02",
                    "registered_date" => $sysDate,
                    "calcbaltype" => 1, // 1 - approvamount, 2 - advamount, 3 - princbal
                ]);

                $txndata = App::call($route);
                // Log::debug('Барьцаа хөрөнгө');
                // Log::debug($txndata);

                // Зээлийн данс зөвшөөрөх ln800001
                $process = GpctionCode::where('ACTION_CODE', 'ln800001')->first();
                $route = $process->controller . '@' . $process->function;

                request()->merge([
                    "acntno" => $new_loan_acnt,
                ]);

                $txndata = App::call($route);
                // Log::debug('Зээлийн данс зөвшөөрөх');
                // Log::debug($txndata);

                // Зээлийн данс олголт хийх ln902021
                $process = GpctionCode::where('ACTION_CODE', 'ln902021')->first();
                $route = $process->controller . '@' . $process->function;

                request()->merge([
                    "acntno" => $new_loan_acnt,
                    "contacntcurcode" => "MNT",
                    "curcode" => "MNT",
                    "contacntno" => $contaccountno,
                    "ispreview" => 0,
                    "rate" => 0,
                    "rtypecode" => 1,
                    "txnamount" => $loan_amount,
                    "txndesc" => $lnService->txn_desc,
                    "sourcecode" => "6",
                ]);

                $txndata = App::call($route);
                // Log::debug('Зээл олголт');
                // Log::debug($txndata);


                // Extract Core fee from ln902021 transaction
                $core_fee_amount = 0;
                if (!empty($txndata['feesPreview']) && isset($txndata['feesPreview'][0]['contamount'])) {
                    $core_fee_amount = $txndata['feesPreview'][0]['contamount'];
                }


                // Recalculate adv_amount accounting for both App fee and Core fee
                $adv_amount = $adv_amount - $core_fee_amount;

                // Байгууллага тохируулсан шимтгэлийг гүйлгээн дээр бичдэг болов.
                $lnService->fee_inst_amount = $core_fee_amount;
                // төлбөрийн хуваарь тооцоолол хийх ln010504
                $process = GpctionCode::where('ACTION_CODE', 'ln010504')->first();
                $route = $process->controller . '@' . $process->function;

                request()->merge([
                    "acntno" => $new_loan_acnt,
                    "calcbaltype" => 2,
                    "payfreq" => "N",
                    "repaytype" => 1,
                    "startdate" => $sysDate,
                ]);

                $nrsdata = App::call($route);

                // төлбөрийн хуваарь хадгалах ln010204
                $process = GpctionCode::where('ACTION_CODE', 'ln010204')->first();
                $route = $process->controller . '@' . $process->function;
                request()->merge([
                    "acntno" => $new_loan_acnt,
                    "calcbaltype" => 2,
                    "payfreq" => "N",
                    "repaytype" => 1,
                    "startdate" => $sysDate,
                    "repaymentdata" => $nrsdata
                ]);
                App::call($route);


                $lnService->statusid = 2;
                $lnService->core_jrno = $txndata['txnJrno'];
                $lnService->is_supervisor = $txndata['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $txndata['jrItemNoAndIncr'] ?? 0;
                $lnService->err_desc = "Банк дээрх гүйлгээг шалгах хэрэгтэй.";
                $lnService->txn_date = $sysDate;
                $lnService->txn_acnt_code = $new_loan_acnt;
                $lnService->save();
                $trjournal = new TrJournalController();
                $iscorr = false;

                try {
                    // шимтгэлийн гүйлгээ хийх.
                    $fee_respdata = $this->casaToInternalAcntT([
                        "txnAcntCode" => $contaccountno,
                        "txnAmount" => $calc_fee_amount,
                        "rate" => 1,
                        "contAcntCode" => $fee_config['fee_account'],
                        "contAmount" => $calc_fee_amount,
                        "contRate" => 1,
                        "txnDesc" => !empty($fee_config['fee_txn_desc'])
                            ? $fee_config['fee_txn_desc'] : 'Шимтгэл',
                        "instid" => $data['instid'],
                        "parent_jrno" => $lnService->core_jrno,
                        "txn_date" => $sysDate
                    ], true);

                    $fee_resp = $fee_respdata['data'];
                    if (!empty($fee_resp)) {
                        $fee_tran = ApTxnJournal::where('core_jrno', $fee_resp['txnJrno'])
                            ->where('instid', $data['instid'])->first();
                    }

                    try {
                        // Зээл олголт банк дотоод данс.
                        $tran3_data = $this->casaToInternalAcntT([
                            "txnAcntCode" => $contaccountno,
                            "txnAmount" => $adv_amount,
                            "rate" => 1,
                            "contAcntCode" => $providerBank['internal_bank_account_no'],
                            "contAmount" => $adv_amount,
                            "contRate" => 1,
                            "txnDesc" => $providerBank['txndesc'] ?? 'Зээл олголт',
                            "instid" => $data['instid'],
                            "parent_jrno" => $lnService->core_jrno,
                            "txn_date" => $sysDate
                        ]);
                        if ($tran3_data['status'] == 200) {
                            $tran3_resp = $tran3_data['data'];

                            $inter_tran = ApTxnJournal::where('core_jrno', $tran3_resp['txnJrno'])
                                ->where('instid', $data['instid'])->first();
                        }
                        Auth::setUser(ApCustUser::find($tmpuser->id));

                        // Corprate gateway дээр хүсэлт илгээх хэсэг
                        // Test server дээр банкны гүйлгээ хийдэггүй болов.
                        if (config('app.env') != 'production') {
                            $lnService->txn_jrno = 0;
                            $lnService->statusid = 1;
                            $lnService->save();
                            if (!empty($fee_resp)) {
                                $fee_tran->err_desc = '';
                                $fee_tran->statusid = 1;
                                $fee_tran->save();
                            }
                            $inter_tran->err_desc = '';
                            $inter_tran->statusid = 1;
                            $inter_tran->save();
                            $contService = new ApContractService();
                            $contService->storeCustContract([
                                'account_no' => $new_loan_acnt,
                                'acnt_code' => $data['txnAcntCode'],
                                'prod_code' => $polaris->savingLoan->loanAcnt->prodCode,
                                'operation' => $lnService->oper_code,
                                'txn_jrno' => $lnService->core_jrno,
                                'cust_cif' => $cust->cif,
                                'cust_name' => $cust->shortname,
                                'amount' => $req_amount,
                                'type_id' => ApAccountTypeEnum::td,
                                'bank_acnt_code' => $data['contAcntCode'],
                                'int_rate' => $polaris->savingLoan->loanAcnt->marginRate === 0 ? $prod->intrate : $polaris->savingLoan->loanAcnt->marginRate + $intRate,
                                'bank_code' => $cgwBankCode,
                                'instid' => $data['instid'],
                                'sign_image_id' => $data['sign_image_id'] ?? null,
                            ], '10000002', null);
                            return "Зээл олголт амжилттай хийгдлээ.";
                        }
                        $data['amount'] = $adv_amount;

                        try {
                            $lnService->err_desc = "";
                            $resp = $this->corporateTransaction($cgwBankCode, $data, $acnt);
                            $lnService->txn_jrno = $resp['journal_no'] ?? ($resp['journalNo'] ?? 0);
                            $lnService->statusid = 1;
                            $lnService->err_desc = '';
                            $lnService->save();
                            if (!empty($fee_resp)) {
                                $fee_tran->statusid = 1;
                                $fee_tran->save();
                            }
                            $inter_tran->statusid = 1;
                            $inter_tran->save();

                            $contService = new ApContractService();
                            $contService->storeCustContract([
                                'account_no' => $new_loan_acnt,
                                'acnt_code' => $data['txnAcntCode'],
                                'prod_code' => $polaris->savingLoan->loanAcnt->prodCode,
                                'operation' => $lnService->oper_code,
                                'txn_jrno' => $lnService->core_jrno,
                                'cust_cif' => $cust->cif,
                                'cust_name' => $cust->shortname,
                                'amount' => $req_amount,
                                'type_id' => ApAccountTypeEnum::td,
                                'bank_acnt_code' => $data['contAcntCode'],
                                'int_rate' => $polaris->savingLoan->loanAcnt->marginRate === 0 ? $prod->intrate : $polaris->savingLoan->loanAcnt->marginRate + $intRate,
                                'bank_code' => $cgwBankCode,
                                'instid' => $data['instid'],
                                'sign_image_id' => $data['sign_image_id'],
                            ], '10000001', null);
                            return "Зээл олголт амжилттай хийгдлээ.";
                        } catch (\Throwable $th) {
                            if (!$iscorr) {
                                if (!empty($fee_resp)) {
                                    try {
                                        $respdata = $trjournal->insertCorrTran(
                                            $fee_resp['txnJrno'],
                                            'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                            CoreService::getTxnDate($data['instid'])
                                        )->jsonSerialize();
                                        $fee_tran->statusid = 3;
                                        $fee_tran->core_corr_jrno = $respdata['txnJrno'];
                                        $fee_tran->err_desc = $respdata['txnJrno'];
                                        $fee_tran->save();
                                    } catch (\Throwable $th) {
                                        $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                                        $fee_tran->save();
                                    }
                                }

                                // Дотоод зээл олголтын буцаалт
                                $req_data = [
                                    'orgJrno' => $tran3_resp['txnJrno'],
                                    'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                                ];

                                try {
                                    $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                                    $inter_tran->statusid = 3;
                                    $inter_tran->core_corr_jrno = $respdata['txnJrno'];
                                    $inter_tran->err_desc = 'Гүйлгээ амжилтгүй болсон учир буцаалт хийв.';
                                    $inter_tran->save();
                                } catch (\Throwable $th) {
                                    Log::debug($th);
                                    $inter_tran->err_desc = 'Core системийн буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $tran3_resp['txnJrno'];
                                    $inter_tran->save();
                                }

                                try {
                                    $respdata = $trjournal->insertCorrTran(
                                        $txndata['txnJrno'],
                                        'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                        CoreService::getTxnDate($data['instid'])
                                    )->jsonSerialize();
                                    $lnService->statusid = 3;
                                    $lnService->core_corr_jrno = $respdata['txnJrno'];
                                    $lnService->err_desc = $respdata['txnJrno'];
                                    $lnService->save();
                                } catch (\Throwable $th) {
                                    $lnService->err_desc = 'Core системийн гүйлгээний
                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $txndata['txnJrno'];
                                    $lnService->save();
                                }
                                $iscorr = true;
                                throw $th;
                            }
                        }
                    } catch (\Throwable $th) {
                        Log::error($th);
                        if (!$iscorr) {
                            if (!empty($fee_resp)) {
                                try {
                                    $respdata = $trjournal->insertCorrTran(
                                        $fee_resp['txnJrno'],
                                        'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                        CoreService::getTxnDate($data['instid'])
                                    )->jsonSerialize();
                                    $fee_tran->statusid = 3;
                                    $fee_tran->core_corr_jrno = $respdata['txnJrno'];
                                    $fee_tran->err_desc = $respdata['txnJrno'];
                                    $fee_tran->save();
                                } catch (\Throwable $th) {
                                    $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                                    $fee_tran->save();
                                }
                            }

                            try {
                                $respdata = $trjournal->insertCorrTran(
                                    $txndata['txnJrno'],
                                    'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                    CoreService::getTxnDate($data['instid'])
                                )->jsonSerialize();
                                $lnService->statusid = 3;
                                $lnService->core_corr_jrno = $respdata['txnJrno'];
                                $lnService->err_desc = $respdata['txnJrno'];
                                $lnService->save();
                            } catch (\Throwable $th) {
                                $lnService->err_desc = 'Core системийн гүйлгээний
                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $txndata['txnJrno'];
                                $lnService->save();
                            }
                            $iscorr = true;
                            throw new MeException('Уучлаарай, зээл олголт шимтгэлийн гүйлгээ амжилтгүй боллоо.');
                        }
                    }
                } catch (\Throwable $th) {
                    if (!$iscorr) {
                        try {
                            $respdata = $trjournal->insertCorrTran(
                                $txndata['txnJrno'],
                                'ME APP гүйлгээний буцаалт',
                                CoreService::getTxnDate($data['instid'])
                            )->jsonSerialize();
                            $lnService->statusid = 3;
                            $lnService->core_corr_jrno = $respdata['txnJrno'];
                            $lnService->err_desc = $respdata['txnJrno'];
                            $lnService->save();
                        } catch (\Throwable $th) {
                            $lnService->err_desc = 'Core системийн гүйлгээний
                    буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $txndata['txnJrno'];
                            $lnService->save();
                        }
                        $iscorr = true;
                    }

                    Log::debug($th);
                    throw new MeException('Уучлаарай, зээл олголт шимтгэлийн гүйлгээ амжилтгүй боллоо.');
                }

                // Зээл олголт банк дотоод данс.
                Auth::setUser(ApCustUser::find($tmpuser->id));
                return 'Зээл олголт амжилттай хийгдлээ.';
            } else {
                $req_data = [
                    "txnAmount" => $loan_amount,
                    "curCode" => $acnt->cur_code,
                    "rate" => 1,
                    "contAcntCode" => $contaccountno,
                    "contAmount" => $loan_amount,
                    "contCurCode" => "MNT",
                    "contRate" => 1,
                    // "rateTypeId" => 5,
                    "txnDesc" => $lnService->txn_desc,
                    "sourceType" => "OI",
                    "isPreview" => 0,
                    "isPreviewFee" => 0,
                    "isBlockInt" => 0,
                    "collAcnt" => [
                        "name" => $acntname,
                        "name2" => $acntname2,
                        "custCode" => $cust->cif,
                        "prodCode" => $polaris->savingLoan->collAcnt->prodCode,
                        "prodType" => "COLL",
                        "collType" => "4",
                        "brchCode" => $polaris->brchCode,
                        "status" => "N",
                        "key2SysNo" => "1306",
                        "key2" => $data['txnAcntCode'], // Барьцаалж байгаа хадгаламжийн данс
                        "price" => $loan_amount,
                        "curCode" => "MNT"
                    ],
                    "loanAcnt" => [
                        "custCode" => $cust->cif,
                        "name" => $acntname,
                        "name2" => $acntname2,
                        "prodCode" => $polaris->savingLoan->loanAcnt->prodCode,
                        "curCode" => "MNT",
                        "approvAmount" => $loan_amount,
                        "approvDate" => $sysDate,
                        "startDate" => $sysDate,
                        "termLen" => $termLen,
                        "endDate" => $acnt->maturity_date, // Хадгаламжийн дансны дуусах хугацаа
                        "purpose" => $polaris->savingLoan->loanAcnt->purpose,
                        "subPurpose" => $polaris->savingLoan->loanAcnt->subPurpose,
                        "isNotAutoClass" => 0,
                        "comRevolving" => 0,
                        "dailyBasisCode" => $polaris->savingLoan->loanAcnt->dailyBasisCode,
                        "acntManager" => $polaris->savingLoan->loanAcnt->acntManager,
                        "brchCode" => $polaris->brchCode,
                        "isGetBrchFromOutside" => 0,
                        "segCode" => $cust->segment,
                        "status" => "N",
                        "slevel" => 1,
                        "classNoTrm" => 1,
                        "classNoQlt" => 1,
                        "classNo" => 1,
                        "repayAcntCode" => null,
                        "isBrowseAcntOtherCom" => 0,
                        "repayPriority" => 0,
                        "losMultiAcnt" => 0,
                        "impairmentPer" => 0,
                        "validLosAcnt" => 1,
                        "prodType" => "LOAN",
                        "secType" => 0
                    ],
                    "acntNrs" => [
                        "startDate" => $sysDate, // now date
                        "calcAmt" => $loan_amount,
                        "payType" => "1",
                        "payFreq" => "E",
                        "payDay1" => 20,
                        "holidayOption" => "2",
                        "shiftPartialPay" => 0,
                        "termFreeTimes" => 0,
                        "intTypeCode" => "SIMPLE_INT",
                        "endDate" => $acnt->maturity_date // Хадгаламжийн дансны дуусан хугацаа
                    ],
                    "acntInt" => [
                        "intTypeCode" => "SIMPLE_INT",
                        "intRate" => $polaris->savingLoan->loanAcnt->marginRate + $intRate
                    ]
                ];
                // return getSystemResp($req_data, 200);
                // Log::debug('$req_data');
                // Log::debug($req_data);
                try {
                    $respdata = $polaris->sendRequest($lnService->oper_code, [$req_data], $data['instid']);
                } catch (\Throwable $th) {
                    Log::error($th);
                    $lnService->err_desc = @$respdata ?? $th->getMessage();
                    $lnService->statusid = 2;
                    $lnService->save();
                    throw new MeException('Уучлаарай, зээл олголт амжилтгүй боллоо.');
                }
                $nesresp = $respdata;
                $lnService->statusid = 2;
                $lnService->core_jrno = $nesresp['txnJrno'];
                $lnService->is_supervisor = $nesresp['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $nesresp['jrItemNoAndIncr'] ?? 0;
                $new_loan_acnt = $nesresp['acntCode'] ?? 0;
                $lnService->err_desc = "Банк дээрх гүйлгээг шалгах хэрэгтэй.";
                $lnService->txn_date = $sysDate;
                $lnService->txn_acnt_code = $new_loan_acnt;
                $lnService->save();
                try {
                    // Log::debug('$calc_fee_amount');
                    // Log::debug($calc_fee_amount);
                    // шимтгэлийн гүйлгээ хийх.
                    $fee_respdata = $this->casaToInternalAcntT([
                        "txnAcntCode" => $contaccountno,
                        "txnAmount" => $calc_fee_amount,
                        "rate" => 1,
                        "contAcntCode" => $fee_config['fee_account'],
                        "contAmount" => $calc_fee_amount,
                        "contRate" => 1,
                        "txnDesc" => $fee_config['fee_txn_desc'] ?? 'Шимтгэл',
                        "instid" => $data['instid'],
                        "parent_jrno" => $lnService->core_jrno,
                        "txn_date" => $sysDate
                    ], true);
                } catch (\Throwable $th) {
                    Log::debug($th);
                    $req_data = [
                        'orgJrno' => $nesresp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $lnService->statusid = 3;
                        $lnService->core_corr_jrno = $respdata['txnJrno'];
                        $lnService->err_desc = 'Шимтгэлийн гүйлгээ амжилтгүй болсон учир буцаалт хийв.';
                        $lnService->save();
                    } catch (\Throwable $th) {
                        Log::debug($th);
                        $lnService->err_desc = 'Core системийн гүйлгээний
                    буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $nesresp['txnJrno'];
                        $lnService->save();
                    }
                    throw new MeException('Уучлаарай, зээл олголт шимтгэлийн гүйлгээ амжилтгүй боллоо.');
                }

                $fee_resp = $fee_respdata['data'];
                if (!empty($fee_resp)) {
                    $fee_tran = ApTxnJournal::where('core_jrno', $fee_resp['txnJrno'])
                        ->where('instid', $data['instid'])->first();
                }

                try {
                    // Зээл олголт банк дотоод данс.
                    $tran3_data = $this->casaToInternalAcntT([
                        "txnAcntCode" => $contaccountno,
                        "txnAmount" => $adv_amount,
                        "rate" => 1,
                        "contAcntCode" => $providerBank['internal_bank_account_no'],
                        "contAmount" => $adv_amount,
                        "contRate" => 1,
                        "txnDesc" => $providerBank['txndesc'] ?? 'Зээл олголт',
                        "instid" => $data['instid'],
                        "parent_jrno" => $lnService->core_jrno,
                        "txn_date" => $sysDate
                    ]);
                } catch (\Throwable $th) {
                    Log::error($th);
                    if (!empty($fee_resp)) {
                        $req_data = [
                            'orgJrno' => $fee_resp['txnJrno'],
                            'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                        ];
                        try {
                            $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                            $fee_tran->statusid = 3;
                            $fee_tran->core_corr_jrno = $respdata;
                            $fee_tran->err_desc = 'Зээл олголт банк дотоод данс гүйлгээ амжилтгүй учир гүйлгээ буцаав.';
                            $fee_tran->save();
                        } catch (\Throwable $th) {
                            //throw $th;
                            $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                            $fee_tran->save();
                        }
                    }
                    $req_data = [
                        'orgJrno' => $nesresp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $lnService->statusid = 3;
                        $lnService->core_corr_jrno = $respdata;
                        $lnService->err_desc = '';
                        $lnService->save();
                    } catch (\Throwable $th) {
                        //throw $th;
                        $lnService->err_desc = 'Core системийн гүйлгээний
                    буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $nesresp['txnJrno'];
                        $lnService->save();
                    }
                    throw new MeException('Уучлаарай, зээл олголт амжилтгүй боллоо.');
                }

                $tran3_resp = $tran3_data['data'];
                $inter_tran = ApTxnJournal::where('core_jrno', $tran3_resp['txnJrno'])
                    ->where('instid', $data['instid'])->first();


                // Corprate gateway дээр хүсэлт илгээх хэсэг
                // Test server дээр банкны гүйлгээ хийдэггүй болов.
                if (config('app.env') != 'production') {
                    $lnService->txn_jrno = 0;
                    $lnService->statusid = 1;
                    $lnService->save();
                    if (!empty($fee_resp)) {
                        $fee_tran->err_desc = '';
                        $fee_tran->statusid = 1;
                        $fee_tran->save();
                    }
                    $inter_tran->err_desc = '';
                    $inter_tran->statusid = 1;
                    $inter_tran->save();
                    $contService = new ApContractService();
                    $contService->storeCustContract([
                        // Шинээр үүссэн зээлийн дансны дугаар
                        'account_no' => $new_loan_acnt,
                        // Хадгаламжийн данс
                        'acnt_code' => $data['txnAcntCode'],
                        'prod_code' => $polaris->savingLoan->loanAcnt->prodCode,
                        'operation' => $lnService->oper_code,
                        'txn_jrno' => $lnService->core_jrno,
                        'cust_cif' => $cust->cif,
                        'cust_name' => $cust->shortname,
                        'amount' => $req_amount,
                        'type_id' => ApAccountTypeEnum::td,
                        'bank_acnt_code' => $data['contAcntCode'],
                        'int_rate' => $polaris->savingLoan->loanAcnt->marginRate + $intRate,
                        'bank_code' => $cgwBankCode,
                        'instid' => $data['instid'],
                        'sign_image_id' => $data['sign_image_id'],
                    ], '10000002', null);
                    return "Зээл олголт амжилттай хийгдлээ.";
                }
                $data['amount'] = $adv_amount;
                $lnService->err_desc = "";
                try {
                    $resp = $this->corporateTransaction($cgwBankCode, $data, $acnt);
                    $lnService->txn_jrno = $resp['journal_no'] ?? ($resp['journalNo'] ?? 0);
                    $lnService->statusid = 1;
                    $lnService->err_desc = '';
                    $lnService->save();
                    if (!empty($fee_resp)) {
                        $fee_tran->err_desc = '';
                        $fee_tran->statusid = 1;
                        $fee_tran->save();
                    }
                    $inter_tran->err_desc = '';
                    $inter_tran->statusid = 1;
                    $inter_tran->save();
                    $contService = new ApContractService();
                    $contService->storeCustContract([
                        // Шинээр үүссэн зээлийн дансны дугаар
                        'account_no' => $new_loan_acnt,
                        'acnt_code' => $data['txnAcntCode'],
                        'prod_code' => $polaris->savingLoan->loanAcnt->prodCode,
                        'operation' => $lnService->oper_code,
                        'txn_jrno' => $lnService->core_jrno,
                        'cust_cif' => $cust->cif,
                        'cust_name' => $cust->shortname,
                        'amount' => $req_amount,
                        'type_id' => ApAccountTypeEnum::td,
                        'bank_acnt_code' => $data['contAcntCode'],
                        'int_rate' => $polaris->savingLoan->loanAcnt->marginRate + $intRate,
                        'bank_code' => $cgwBankCode,
                        'instid' => $data['instid'],
                        'sign_image_id' => $data['sign_image_id'],
                    ], '10000002', null);
                    return "Зээл олголт амжилттай хийгдлээ.";
                } catch (\Throwable $th) {
                    Log::error($th);
                    // Corprate gateway гүйлгээ амжилтгүй болсон учир буцаалт хийнэ.

                    // Дотоод зээл олголтын буцаалт
                    $req_data = [
                        'orgJrno' => $tran3_resp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $inter_tran->statusid = 3;
                        $inter_tran->core_corr_jrno = $respdata;
                        $inter_tran->err_desc = $respdata;
                        $inter_tran->save();
                    } catch (\Throwable $th) {
                        $inter_tran->err_desc = 'Core системийн буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $tran3_resp['txnJrno'];
                        $inter_tran->save();
                    }

                    if (!empty($fee_resp)) {
                        $req_data = [
                            'orgJrno' => $fee_resp['txnJrno'],
                            'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                        ];
                        try {
                            $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                            $fee_tran->statusid = 3;
                            $fee_tran->core_corr_jrno = $respdata;
                            $fee_tran->err_desc = 'Зээл олголт банк дотоод данс гүйлгээ амжилтгүй учир гүйлгээ буцаав.';
                            $fee_tran->save();
                        } catch (\Throwable $th) {
                            //throw $th;
                            $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                            $fee_tran->save();
                        }
                    }
                    $req_data = [
                        'orgJrno' => $nesresp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $lnService->statusid = 3;
                        $lnService->core_corr_jrno = $respdata;
                        $lnService->err_desc = '';
                        $lnService->save();
                    } catch (\Throwable $th) {
                        //throw $th;
                        $lnService->err_desc = 'Core системийн гүйлгээний
                    буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $nesresp['txnJrno'];
                        $lnService->save();
                    }
                    throw new MeException('Уучлаарай, зээл олголтын гүйлгээ амжилтгүй боллоо.');
                }
            }
        } catch (MeException $e) {
            if ($lnService) {
                $lnService->err_desc = $e->getMessage();
                $lnService->save();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($lnService) {
                $lnService->err_desc = $e->getMessage();
                $lnService->save();
            }
            throw new MeException($e->getMessage());
        }
    }

    public function oppositeTran($req_data, $polaris, $instid)
    {
        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
            $req_data['jrno'] = $req_data['orgJrno'];
            $req_data['txndesc'] = $req_data['txnDesc'];
            $onlineteller = CoreService::getInstGp($instid, 'ONLINETELLERNUMBER');

            $tmpuser = auth()->user();
            $user = GPInstUser::where('instid', $instid)->find(
                $onlineteller
            );
            Auth::setUser($user);
            $data = (new TrJournalController())->tr909999((new Request())->merge($req_data));
            if (!empty($tmpuser) && gettype($tmpuser) != 'string') {
                Auth::setUser(ApCustUser::find($tmpuser->id));
            }

            return $data;
        } else {
            $res = $polaris->sendRequest(13619998, [$req_data], $instid);
            return ['txnJrno' => $res];
        }
    }

    public function getCallCgwBankCode($data)
    {
        $polaris = new PolarisApiRequestService($data['instid']);
        $cgwBankCode = '';
        $cgw = $polaris->cgw;
        if (isset($cgw->configAll) && isset($cgw->configAll->isUse)) {
            if ($cgw->configAll->isUse) {
                $cgwBankCode = $cgw->configAll->bankCode;
            } else {
                if (isset($cgw->generalConfig)) {
                    foreach ($cgw->generalConfig as $key => $value) {
                        if ($key == $data['contBankCode']) {
                            // $cgwBankCode = $value . "$key" . $data['contBankCode'];
                            $cgwBankCode = $value;
                            break;
                        }
                    }
                    if ($cgwBankCode == '') {
                        if (isset($cgw->generalConfig->default)) {
                            $cgwBankCode = $cgw->generalConfig->default;
                        } else {
                            // Системийн ерөнхий тохиргоо дээр generalConfig default тохиргоо хийгдээгүй байна.
                            throw new MeException('Cgw generalConfig default тохиргоо хийгдээгүй байна.');
                        }
                    }
                    // if (isset()) {}
                } else {
                    // Системийн ерөнхий тохиргоо дээр configAll.isUse = false үед generalConfig тохиргоо хийгдээгүй байна.
                    throw new MeException('Системийн ерөнхий тохиргоо хийгдээгүй байна.');
                }
            }
            return $cgwBankCode;
        } else {
            throw new MeException('Cgw configAll тохиргоо хийгдээгүй байна.');
        }
    }

    public function giveLoan($data)
    {
        $lnService = null;
        try {
            $stpservice = new ApStopService();
            $resp = $stpservice->checkStopSrevice([
                'instid' => $data['instid'],
                'serviceCode' => '10000001',
                'acntCode' => $data['txnAcntCode'],
            ]);
            if ($resp['status'] != 1) {
                throw new MeException($resp['message']);
            }
            $polaris = new PolarisApiRequestService($data['instid']);
            $acnt = ApAcntLn::where('acnt_code', $data['txnAcntCode'])->where('instid', $data['instid'])->first();
            $cgwBankCode = $this->getCallCgwBankCode($data);
            if (empty($acnt)) {
                throw new MeException($data['txnAcntCode'] . ' дугаартай данс олдсонгүй.');
            }
            if ($acnt->cur_code != 'MNT') {
                throw new MeException($acnt->cur_code . ' системд зөвшөөрөгдөөгүй валют.');
            }

            $user = auth()->user();
            $cust = ApCustomer::where('instid', $data['instid'])
                ->where('regno', $user->regno)->where('statusid', '1')->first();
            if (empty($cust)) {
                throw new MeException('Харилцагчийн мэдээлэл системд бүртгэлгүй байна.');
            }

            $crCust = CrCustInd::where('custno', $cust->cif)->where('instid', $data['instid'])->where('statusid', '<>', -1)->first();

            if ($crCust && $crCust->bl == 1) {
                throw new MeException('RC000263');
            }

            $contaccountno = '';
            if ($polaris->is_use_cust_susp_acnt == 1 || $polaris->is_use_cust_susp_acnt == '1') {
                $casaAcnt = ApAcntDp::where('prod_code', $polaris->susp_acnt_prod_code)
                    ->whereIn('status', ['O', '4', '1'])->where('instid', $data['instid'])
                    ->where('cust_code', $cust->cif)
                    ->orderBy('acnt_code', 'desc')->first();

                if (empty($casaAcnt)) {
                    throw new MeException('Харилцагч дээр түр дансны бүртгэл хийгдээгүй байна.');
                }
                $contaccountno = $casaAcnt->acnt_code;
            } else {
                $contaccountno = $polaris->internalAccount;
            }
            if (empty($contaccountno)) {
                throw new MeException('Түр дансны бүртгэл хийгдээгүй байна.');
            }
            if (empty($contaccountno)) {
                throw new MeException('Түр дансны бүртгэл хийгдээгүй байна.');
            }

            // Зээл- Зээл депозит дансаар олгох
            // $fee = DicFee::where('operation', 13610262)->where('prodcode', $acnt->prod_code)
            //     ->where('instid', $data['instid'])->first();
            if (!isset($polaris->fee->fee_loan)) {
                throw new MeException('RC000188');
            }
            $fee = VwGPInstFeeList::where('feecode', $polaris->fee->fee_loan)
                ->where('instid', $data['instid'])
                ->where('statusid', 1)->first();

            if (empty($fee)) {
                throw new MeException('RC000188');
            }

            $feeconf = GPInstFeeTypeCur::where('feecode', $fee->feecode)
                ->where('instid', $data['instid'])
                ->where('statusid', 1)->first();
            if (empty($feeconf)) {
                throw new MeException('RC000188');
            }
            $fee_config = json_decode($feeconf->formula, true);
            if (empty($fee)) {
                throw new MeException('Шимтгэлийн тохиргоо хийгдээгүй байна. (13610262 - ' . $acnt->prod_code . ')');
            }
            $calc_amounts_resp = $this->getCalcAmounts($fee_config, $data['amount']);
            $calc_amounts = [];
            $calc_amounts = $calc_amounts_resp;
            $calc_fee_amount = $calc_amounts['calc_fee_amount'];
            $adv_amount = $calc_amounts['adv_amount'];
            $loan_amount = $calc_amounts['loan_amount'];
            if ($calc_fee_amount > $data['amount']) {
                throw new MeException('RC000189');
            }

            if ($adv_amount <= 0) {
                throw new MeException('RC000189');
            }
            // Тухайн байгууллагын ямар дансан дээр гүйлгээ хийгдэхээс шалтгаалан дотоодын данс авдаг болов.
            $pp = GPProviderConf::where("code", $cgwBankCode)
                ->where('statusid', 1)->where('instid', $data['instid'])->first();
            $providerBank = json_decode($pp->config, true);
            if (!isset($providerBank['internal_bank_account_no']) && !isset($provider['internal_bank_account_no_line'])) {
                throw new MeException('Зээл олголтын дотоодын данс тохируулагдаагүй байна.');
            }

            $internal_bank_acnt_code = $providerBank['internal_bank_account_no_line'] ?? $providerBank['internal_bank_account_no'];

            $onlineteller = CoreService::getInstGp($data['instid'], 'ONLINETELLERNUMBER');
            // Зээлийн гүйлгээний мэдээлэл үүсгэх
            $lnService = new ApTxnJournal();
            $lnService->txn_acnt_code = $data['txnAcntCode'];
            $lnService->cur_code = $acnt->cur_code;
            // $lnService->tran_amt = $adv_amount;
            // $lnService->tran_cur_code = $acnt->cur_code;
            $lnService->identity_type = "MANUAL";
            $lnService->rate = 1;
            $lnService->internal_cont_acnt_code = $contaccountno;
            $lnService->cont_amount = $loan_amount;
            $lnService->txn_amount = $loan_amount;
            $lnService->cont_cur_code = 'MNT';
            $lnService->cont_rate = 1;
            $lnService->txn_desc = "Зээл олголтын гүйлгээ";
            $lnService->tcust_name = $cust->fname;
            $lnService->tcust_addr = $cust->address ?? "";
            $lnService->tcust_register = $cust->regno;
            $lnService->tcust_register_mask = $cust->register_mask_code;
            $lnService->tcust_contact = $cust->phone;
            $lnService->source_type = "OI";
            $lnService->is_tmw = 1;
            $lnService->is_preview = 0;
            $lnService->is_preview_fee = 0;
            $lnService->cont_acnt_code = $data['contAcntCode'];
            $lnService->cont_bank_code = $data['contBankCode'];
            $lnService->created_at = Carbon::now();
            $lnService->userid = $user->id;
            $lnService->created_by = $onlineteller ?? 1;
            $lnService->statusid = 0;
            $lnService->txn_type = 1;
            $lnService->instid = $data['instid'];
            $lnService->fee_id = $fee->id;
            $lnService->oper_code = 13610262;
            $lnService->fee_inst_amount = $calc_amounts['calc_instfee_amount'];
            $lnService->fee_sys_amount = $calc_amounts['calc_sysfee_amount'];
            $lnService->prodcode = $acnt->prod_code;
            $lnService->save();

            $sysDate = (new ApInstController())->oi000180((new Request())->merge(['instid' => $data['instid']]));
            $providertype = CoreService::getInstGp($data['instid'], 'MEAPPPROVIDER');
            if ($providertype == 'MECORE') {
                $crCust = CrCustInd::where('custno', $cust->cif)->where('instid', $data['instid'])->where('statusid', '<>', -1)->first();

                if ($crCust && $crCust->bl == 1) {
                    throw new MeException('RC000263');
                }
                // Зээлийн данс олголт хийх
                $requestData = [
                    'acntno' => $data['txnAcntCode'],
                    'txnamount' => $loan_amount,
                    'curcode' => 'MNT',
                    'txndesc' => $lnService->txn_desc,
                    'rtypecode' => 1,
                    'contacntno' => $contaccountno,
                    'sourcecode' => "6",
                    // Add more attributes as needed
                ];

                $process = GpctionCode::where('ACTION_CODE', 'ln902021')->first();
                $route = $process->controller . '@' . $process->function;
                request()->merge($requestData);
                $tmpuser = auth()->user();
                $user = GPInstUser::where('instid', $data['instid'])->find(
                    $onlineteller
                );
                if (empty($user)) {
                    throw new MeException('Онлайн теллерийн дугаар буруу тохируулагдсан байна.');
                }
                Auth::setUser($user);
                $txndata = App::call($route);

                // Extract Core fee from ln902021 transaction
                $core_fee_amount = 0;
                if (!empty($txndata['feesPreview']) && isset($txndata['feesPreview'][0]['contamount'])) {
                    $core_fee_amount = $txndata['feesPreview'][0]['contamount'];
                }

                // Recalculate adv_amount accounting for both App fee and Core fee
                $adv_amount = $adv_amount - $core_fee_amount;

                // Байгууллага тохируулсан шимтгэлийг гүйлгээн дээр бичдэг болов.
                $lnService->fee_inst_amount = $core_fee_amount;
                $lnService->statusid = 2;
                $lnService->core_jrno = $txndata['txnJrno'];
                $lnService->is_supervisor = $txndata['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = 0;
                $lnService->err_desc = "Банк дээрх гүйлгээг шалгах хэрэгтэй.";
                $lnService->txn_date = $sysDate;
                $lnService->save();
                $trjournal = new TrJournalController();
                $iscorr = false;

                try {
                    // $trTxnRequest = new TrTxnRequest($requestData);
                    // $txndata = $lncontroller->ln902021();
                    // шимтгэлийн гүйлгээ хийх.
                    $fee_respdata = $this->casaToInternalAcntT([
                        "txnAcntCode" => $contaccountno,
                        "txnAmount" => $calc_fee_amount,
                        "rate" => 1,
                        "contAcntCode" => $fee_config['fee_account'],
                        "contAmount" => $calc_fee_amount,
                        "contRate" => 1,
                        "txnDesc" => !empty($fee_config['fee_txn_desc'])
                            ? $fee_config['fee_txn_desc'] : 'Шимтгэл',
                        "instid" => $data['instid'],
                        "parent_jrno" => $lnService->core_jrno,
                        "txn_date" => $sysDate
                    ], true);

                    // Log::debug($fee_respdata);
                    $fee_resp = $fee_respdata['data'];
                    if (!empty($fee_resp)) {
                        $fee_tran = ApTxnJournal::where('core_jrno', $fee_resp['txnJrno'])
                            ->where('instid', $data['instid'])->first();
                    }
                    try {
                        // Зээл олголт банк дотоод данс.
                        $tran3_data = $this->casaToInternalAcntT([
                            "txnAcntCode" => $contaccountno,
                            "txnAmount" => $adv_amount,
                            "rate" => 1,
                            "contAcntCode" => $internal_bank_acnt_code,
                            "contAmount" => $adv_amount,
                            "contRate" => 1,
                            "txnDesc" => $providerBank['txndesc'] ?? 'Зээл олголт',
                            "instid" => $data['instid'],
                            "parent_jrno" => $lnService->core_jrno,
                            "txn_date" => $sysDate
                        ]);
                        if ($tran3_data['status'] == 200) {
                            $tran3_resp = $tran3_data['data'];

                            $inter_tran = ApTxnJournal::where('core_jrno', $tran3_resp['txnJrno'])
                                ->where('instid', $data['instid'])->first();
                        }
                        // Corprate gateway дээр хүсэлт илгээх хэсэг
                        // Test server дээр банкны гүйлгээ хийдэггүй болов.
                        if (config('app.env') != 'production') {
                            $lnService->txn_jrno = 0;
                            $lnService->statusid = 1;
                            $lnService->save();
                            $fee_tran->statusid = 1;
                            $fee_tran->save();
                            $inter_tran->statusid = 1;
                            $inter_tran->save();
                            $contService = new ApContractService();
                            $contService->storeCustContract([
                                'account_no' => $data['txnAcntCode'],
                                'acnt_code' => $data['txnAcntCode'],
                                'prod_code' => $acnt->prod_code,
                                'operation' => 13610262,
                                'txn_jrno' => $lnService->core_jrno,
                                'cust_cif' => $cust->cif,
                                'cust_name' => $cust->shortname,
                                'amount' => $data['amount'],
                                'type_id' => ApAccountTypeEnum::line,
                                'bank_acnt_code' => $data['contAcntCode'],
                                'int_rate' => '0.15',
                                'bank_code' => $cgwBankCode,
                                'instid' => $data['instid'],
                                'sign_image_id' => $data['sign_image_id'] ?? null,
                            ], '10000001', null);
                            return "Зээл олголт амжилттай хийгдлээ.";
                        }
                        $data['amount'] = $adv_amount;

                        try {
                            $lnService->err_desc = "";
                            $resp = $this->corporateTransaction($cgwBankCode, $data, $acnt);
                            $lnService->txn_jrno = $resp['journal_no'] ?? ($resp['journalNo'] ?? 0);
                            $lnService->statusid = 1;
                            $lnService->err_desc = '';
                            $lnService->save();
                            if (!empty($fee_resp)) {
                                $fee_tran->statusid = 1;
                                $fee_tran->save();
                            }
                            $inter_tran->statusid = 1;
                            $inter_tran->save();

                            // Log::debug('$inter_tran');
                            // Log::debug($inter_tran);

                            $contService = new ApContractService();
                            $contService->storeCustContract([
                                'account_no' => $data['txnAcntCode'],
                                'acnt_code' => $data['txnAcntCode'],
                                'prod_code' => $acnt->prod_code,
                                'operation' => 13610262,
                                'txn_jrno' => $lnService->core_jrno,
                                'cust_cif' => $cust->cif,
                                'cust_name' => $cust->shortname,
                                'amount' => $data['amount'],
                                'type_id' => ApAccountTypeEnum::line,
                                'bank_acnt_code' => $data['contAcntCode'],
                                'int_rate' => '0.15',
                                'bank_code' => $cgwBankCode,
                                'instid' => $data['instid'],
                                'sign_image_id' => $data['sign_image_id'],
                            ], '10000001', null);
                            return "Зээл олголт амжилттай хийгдлээ.";
                        } catch (\Throwable $th) {
                            if (!$iscorr) {
                                if (!empty($fee_resp)) {
                                    try {
                                        $respdata = $trjournal->insertCorrTran(
                                            $fee_resp['txnJrno'],
                                            'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                            CoreService::getTxnDate($data['instid'])
                                        )->jsonSerialize();
                                        $fee_tran->statusid = 3;
                                        $fee_tran->core_corr_jrno = $respdata['txnJrno'];
                                        $fee_tran->err_desc = $respdata['txnJrno'];
                                        $fee_tran->save();
                                    } catch (\Throwable $th) {
                                        $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                                        $fee_tran->save();
                                    }
                                }

                                try {
                                    // Дотоод зээл олголтын буцаалт
                                    $respdata = $trjournal->insertCorrTran(
                                        $tran3_resp['txnJrno'],
                                        'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                        CoreService::getTxnDate($data['instid'])
                                    )->jsonSerialize();
                                    $inter_tran->statusid = 3;
                                    $inter_tran->core_corr_jrno = $respdata['txnJrno'];
                                    $inter_tran->err_desc = $respdata['txnJrno'];
                                    $inter_tran->save();
                                } catch (\Throwable $th) {
                                    $inter_tran->err_desc = 'Core системийн буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                                    $inter_tran->save();
                                }

                                try {
                                    $respdata = $trjournal->insertCorrTran(
                                        $txndata['txnJrno'],
                                        'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                        CoreService::getTxnDate($data['instid'])
                                    )->jsonSerialize();
                                    $lnService->statusid = 3;
                                    $lnService->core_corr_jrno = $respdata['txnJrno'];
                                    $lnService->err_desc = $respdata['txnJrno'];
                                    $lnService->save();
                                } catch (\Throwable $th) {
                                    $lnService->err_desc = 'Core системийн гүйлгээний
                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $txndata['txnJrno'];
                                    $lnService->save();
                                }
                                $iscorr = true;
                                throw $th;
                            }
                        }
                    } catch (\Throwable $th) {
                        Log::error($th);
                        if (!$iscorr) {
                            if (!empty($fee_resp)) {
                                try {
                                    $respdata = $trjournal->insertCorrTran(
                                        $fee_resp['txnJrno'],
                                        'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                        CoreService::getTxnDate($data['instid'])
                                    )->jsonSerialize();
                                    $fee_tran->statusid = 3;
                                    $fee_tran->core_corr_jrno = $respdata['txnJrno'];
                                    $fee_tran->err_desc = $respdata['txnJrno'];
                                    $fee_tran->save();
                                } catch (\Throwable $th) {
                                    $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                                    $fee_tran->save();
                                }
                            }

                            try {
                                $respdata = $trjournal->insertCorrTran(
                                    $txndata['txnJrno'],
                                    'ME APP Буцаалт - Зээл авах хүсэлт амжилтгүй болов.',
                                    CoreService::getTxnDate($data['instid'])
                                )->jsonSerialize();
                                $lnService->statusid = 3;
                                $lnService->core_corr_jrno = $respdata['txnJrno'];
                                $lnService->err_desc = $respdata['txnJrno'];
                                $lnService->save();
                            } catch (\Throwable $th) {
                                $lnService->err_desc = 'Core системийн гүйлгээний
                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $txndata['txnJrno'];
                                $lnService->save();
                            }
                            $iscorr = true;
                            throw new MeException('Уучлаарай, зээл олголт шимтгэлийн гүйлгээ амжилтгүй боллоо.');
                        }
                    }
                } catch (\Throwable $th) {
                    if (!$iscorr) {
                        try {
                            $respdata = $trjournal->insertCorrTran(
                                $txndata['txnJrno'],
                                'ME APP гүйлгээний буцаалт',
                                CoreService::getTxnDate($data['instid'])
                            )->jsonSerialize();
                            $lnService->statusid = 3;
                            $lnService->core_corr_jrno = $respdata['txnJrno'];
                            $lnService->err_desc = $respdata['txnJrno'];
                            $lnService->save();
                        } catch (\Throwable $th) {
                            $lnService->err_desc = 'Core системийн гүйлгээний
                    буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $txndata['txnJrno'];
                            $lnService->save();
                        }
                        $iscorr = true;
                    }

                    Log::debug($th);
                    throw new MeException('Уучлаарай, зээл олголт шимтгэлийн гүйлгээ амжилтгүй боллоо.');
                }

                // Зээл олголт банк дотоод данс.
                Auth::setUser(ApCustUser::find($tmpuser->id));
                return 'Зээл олголт амжилттай хийгдлээ.';
            } else {
                $req_data = [
                    "txnAcntCode" => $data['txnAcntCode'],
                    "txnAmount" => $loan_amount,
                    "curCode" => $acnt->cur_code,
                    "tranAmt" => $loan_amount,
                    "tranCurCode" => $acnt->cur_code,
                    "identityType" => $lnService->identity_type,
                    "rate" => 1,
                    "contAcntCode" => $contaccountno,
                    "contAmount" => $loan_amount,
                    "contCurCode" => "MNT",
                    "contRate" => 1,
                    // "rateTypeId" => 5,
                    "txnDesc" => $lnService->txn_desc,
                    "tcustName" => $cust->fname,
                    "tcustAddr" => $cust->address ?? "",
                    "tcustRegister" => $cust->regno,
                    "tcustRegisterMask" => $cust->register_mask_code,
                    "tcustContact" => $cust->phone,
                    "sourceType" => "OI",
                    "isTmw" => 1,
                    "isPreview" => 0,
                    "isPreviewFee" => 0
                ];
                try {
                    $nesresp = $polaris->sendRequest($lnService->oper_code, [$req_data], $data['instid']);
                } catch (\Throwable $th) {
                    Log::error($th);
                    $lnService->err_desc = @$nesresp['data'];
                    $lnService->statusid = 2;
                    $lnService->save();
                    throw new MeException('Уучлаарай, зээл олголт гүйлгээ амжилтгүй боллоо.');
                }

                $lnService->statusid = 2;
                $lnService->core_jrno = $nesresp['txnJrno'];
                $lnService->is_supervisor = $nesresp['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $nesresp['jrItemNoAndIncr'] ?? 0;
                $lnService->err_desc = "Банк дээрх гүйлгээг шалгах хэрэгтэй.";
                $lnService->txn_date = $sysDate;
                $lnService->save();

                try {
                    $fee_respdata = $this->casaToInternalAcntT([
                        "txnAcntCode" => $contaccountno,
                        "txnAmount" => $calc_fee_amount,
                        "rate" => 1,
                        "contAcntCode" => $fee_config['fee_account'],
                        "contAmount" => $calc_fee_amount,
                        "contRate" => 1,
                        "txnDesc" => $fee_config['fee_txn_desc'] ?? 'Шимтгэл',
                        "instid" => $data['instid'],
                        "parent_jrno" => $lnService->core_jrno,
                        "txn_date" => $sysDate
                    ], true);
                } catch (\Throwable $th) {
                    Log::error($th);
                    $req_data = [
                        'orgJrno' => $nesresp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $lnService->statusid = 3;
                        $lnService->core_corr_jrno = $respdata;
                        $lnService->err_desc = $respdata;
                        $lnService->save();
                    } catch (\Throwable $th) {
                        Log::error($th);
                        $lnService->err_desc = 'Core системийн гүйлгээний
                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $nesresp['txnJrno'];
                        $lnService->save();
                    }
                    throw new MeException('Уучлаарай, зээл олголт шимтгэлийн гүйлгээ амжилтгүй боллоо.');
                }
                // шимтгэлийн гүйлгээ хийх.

                if ($fee_respdata['status'] == 200) {
                    $fee_resp = $fee_respdata['data'];
                    if (!empty($fee_resp)) {
                        $fee_tran = ApTxnJournal::where('core_jrno', $fee_resp['txnJrno'])
                            ->where('instid', $data['instid'])->first();
                    }
                }

                try {
                    // Зээл олголт банк дотоод данс.
                    $tran3_data = $this->casaToInternalAcntT([
                        "txnAcntCode" => $contaccountno,
                        "txnAmount" => $adv_amount,
                        "rate" => 1,
                        "contAcntCode" => $internal_bank_acnt_code,
                        "contAmount" => $adv_amount,
                        "contRate" => 1,
                        "txnDesc" => $providerBank['txndesc'] ?? 'Зээл олголт',
                        "instid" => $data['instid'],
                        "parent_jrno" => $lnService->core_jrno,
                        "txn_date" => $sysDate
                    ]);
                } catch (\Throwable $th) {
                    //throw $th;
                }

                if ($tran3_data['status'] == 200) {
                    $tran3_resp = $tran3_data['data'];
                    $inter_tran = ApTxnJournal::where('core_jrno', $tran3_resp['txnJrno'])->where('instid', $data['instid'])->first();
                } else {
                    if (!empty($fee_resp)) {
                        $req_data = [
                            'orgJrno' => $fee_resp['txnJrno'],
                            'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                        ];
                        try {
                            $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                            $fee_tran->statusid = 3;
                            $fee_tran->core_corr_jrno = $respdata;
                            $fee_tran->err_desc = $respdata;
                            $fee_tran->save();
                        } catch (\Throwable $th) {
                            Log::error($th);
                            $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                            $fee_tran->save();
                        }
                    }

                    $req_data = [
                        'orgJrno' => $nesresp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $lnService->statusid = 3;
                        $lnService->core_corr_jrno = $respdata;
                        $lnService->err_desc = $respdata;
                        $lnService->save();
                    } catch (\Throwable $th) {
                        Log::error($th);
                        $lnService->err_desc = 'Core системийн гүйлгээний
                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $nesresp['txnJrno'];
                        $lnService->save();
                    }
                    throw new MeException('Уучлаарай, зээл олголт шимтгэлийн гүйлгээ амжилтгүй боллоо.');
                }
                // Corprate gateway дээр хүсэлт илгээх хэсэг
                // Test server дээр банкны гүйлгээ хийдэггүй болов.
                if (config('app.env') != 'production') {
                    $lnService->txn_jrno = 0;
                    $lnService->statusid = 1;
                    $lnService->save();
                    $fee_tran->statusid = 1;
                    $fee_tran->save();
                    $inter_tran->statusid = 1;
                    $inter_tran->save();
                    $contService = new ApContractService();
                    $contService->storeCustContract([
                        'account_no' => $data['txnAcntCode'],
                        'acnt_code' => $data['txnAcntCode'],
                        'prod_code' => $acnt->prod_code,
                        'operation' => 13610262,
                        'txn_jrno' => $lnService->core_jrno,
                        'cust_cif' => $cust->cif,
                        'cust_name' => $cust->shortname,
                        'amount' => $data['amount'],
                        'type_id' => ApAccountTypeEnum::line,
                        'bank_acnt_code' => $data['contAcntCode'],
                        'int_rate' => '0.15',
                        'bank_code' => $cgwBankCode,
                        'instid' => $data['instid'],
                        'sign_image_id' => $data['sign_image_id'],
                    ], '10000001', null);
                    return "Зээл олголт амжилттай хийгдлээ.";
                }
                $data['amount'] = $adv_amount;

                try {
                    $resp = $this->corporateTransaction($cgwBankCode, $data, $acnt);
                    $lnService->err_desc = "";
                    $lnService->txn_jrno = $resp['journal_no'] ?? ($resp['journalNo'] ?? 0);
                    $lnService->statusid = 1;
                    $lnService->err_desc = '';
                    $lnService->save();
                    if (!empty($fee_resp)) {
                        $fee_tran->statusid = 1;
                        $fee_tran->save();
                    }
                    $inter_tran->statusid = 1;
                    $inter_tran->save();
                    $contService = new ApContractService();
                    $contService->storeCustContract([
                        'account_no' => $data['txnAcntCode'],
                        'acnt_code' => $data['txnAcntCode'],
                        'prod_code' => $acnt->prod_code,
                        'operation' => 13610262,
                        'txn_jrno' => $lnService->core_jrno,
                        'cust_cif' => $cust->cif,
                        'cust_name' => $cust->shortname,
                        'amount' => $data['amount'],
                        'type_id' => ApAccountTypeEnum::line,
                        'bank_acnt_code' => $data['contAcntCode'],
                        'int_rate' => '0.15',
                        'bank_code' => $cgwBankCode,
                        'instid' => $data['instid'],
                        'sign_image_id' => $data['sign_image_id'],
                    ], '10000001', null);
                    return "Зээл олголт амжилттай хийгдлээ.";
                } catch (\Throwable $th) {
                    Log::error('Уучлаарай, зээл олголт амжилтгүй боллоо.----->');
                    Log::error($th->getMessage());
                    if (!empty($fee_resp)) {
                        // Corprate gateway гүйлгээ амжилтгүй болсон учир буцаалт хийнэ.
                        $req_data = [
                            'orgJrno' => $fee_resp['txnJrno'],
                            'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                        ];
                        try {
                            // Шимтгэлийн гүйлгээний буцаалт
                            $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                            $fee_tran->statusid = 3;
                            $fee_tran->core_corr_jrno = $respdata;
                            $fee_tran->err_desc = $respdata;
                            $fee_tran->save();
                        } catch (\Throwable $th) {
                            Log::error($th);
                            $fee_tran->err_desc = 'Core системийн шитгэлийн гүйлгээний буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                            $fee_tran->save();
                        }
                    }
                    // Дотоод зээл олголтын буцаалт
                    $req_data = [
                        'orgJrno' => $tran3_resp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $inter_tran->statusid = 3;
                        $inter_tran->core_corr_jrno = $respdata;
                        $inter_tran->err_desc = $respdata;
                        $inter_tran->save();
                    } catch (\Throwable $th) {
                        Log::error($th);
                        $inter_tran->err_desc = 'Core системийн буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $fee_resp['txnJrno'];
                        $inter_tran->save();
                    }

                    $req_data = [
                        'orgJrno' => $nesresp['txnJrno'],
                        'txnDesc' => 'Буцаалт - Зээл авах хүсэлт амжилтгүй болов.'
                    ];
                    try {
                        $respdata = $this->oppositeTran($req_data, $polaris, $data['instid']);
                        $lnService->statusid = 3;
                        $lnService->core_corr_jrno = $respdata;
                        $lnService->err_desc = $respdata;
                        $lnService->save();
                    } catch (\Throwable $th) {
                        Log::error($th);
                        $lnService->err_desc = 'Банк дээрх гүйлгээ амжилтгүй болсон үед Core системийн гүйлгээний
                        буцаалт амжилтгүй болов. Core системийн гүйлгээний дугаар: ' . $nesresp['txnJrno'];
                        $lnService->save();
                    }
                    throw new MeException('Уучлаарай, зээл олголт амжилтгүй боллоо.');
                }
            }
        } catch (MeException $e) {
            if ($lnService) {
                $lnService->err_desc = $e->getMessage();
                $lnService->save();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($lnService) {
                $lnService->err_desc = $e->getMessage();
                $lnService->save();
            }
            throw new MeException($e->getMessage());
        }
    }

    public function corporateTransaction($bankCode, $data, $acnt)
    {
        switch ($bankCode) {
            case '04':
                $corp = new CorpGatewayTdbService($data['instid']);
                break;
            case '05':
                $corp = new AdCorporateGatewayKhanService($data['instid'], auth()->user()->id);
                break;

            default:
                $corp = new CorpGatewayTdbService($data['instid']);
                break;
        }
        if (Str::startsWith($data['contBankCode'], $bankCode)) {
            // банк доторх гүйлгээ
            $transferid = random_number();
            $senddata = [
                "fromAccount" => "string",
                "toAccount" => $data['contAcntCode'],
                "toCurrency" => $acnt->cur_code,
                "amount" => $data['amount'],
                "toBank" => $data['contBankCode'],
                "toAccountName" => $data['contAcntName'],
                "description" => "ME: Зээл олголтын гүйлгээ. Данс: "
                    . $data['txnAcntCode'] . " CODE: $transferid",
                "currency" => $acnt->cur_code,
                "transferid" => $transferid,
                "acnttype" => $acnt->acnt_type
            ];
            // Log::debug(['senddata' => $senddata]);
            $resp = $corp->transactionDemostic(
                $senddata
            );
        } else {
            // Бусад банкны данс руу гүйлгээ
            $transferid = random_number();
            $resp = $corp->transInterBank(
                [
                    "fromAccount" => "string",
                    "toAccount" => $data['contAcntCode'],
                    "toCurrency" => $acnt->cur_code,
                    "toAccountName" => $data['contAcntName'],
                    "toBank" => $data['contBankCode'],
                    "amount" => $data['amount'],
                    "description" => "ME: Зээл олголтын гүйлгээ. Данс: "
                        . $data['txnAcntCode'] . " CODE: $transferid",
                    "currency" => $acnt->cur_code,
                    "transferid" => $transferid,
                    "acnttype" => $acnt->acnt_type
                ]
            );
        }
        return $resp;
    }

    public function paymentLoan($instid, $qpay, $txn_acnt, $contbankcode = '', $contbankacnt = '', $othrs = [])
    {
        $polaris = new PolarisApiRequestService($instid);
        $cuser = ApCustUser::where('id', $qpay->created_by)->first();

        $onlineteller = CoreService::getInstGp($instid, 'ONLINETELLERNUMBER');
        $lnService = new ApTxnJournal();
        $lnService->txn_acnt_code = $qpay->to_account;
        $lnService->cur_code = $qpay->cur_code ?? 'MNT';
        // $lnService->tran_amt = $qpay->amount;
        // $lnService->tran_cur_code = $qpay->cur_code ?? 'MNT';
        $lnService->identity_type = "MANUAL";
        $lnService->rate = 1;
        $lnService->cont_amount = $qpay->amount;
        $lnService->txn_amount = $qpay->amount;
        $lnService->cont_cur_code = 'MNT';
        $lnService->cont_rate = 1;
        $lnService->txn_desc = "Зээл төлөлтийн гүйлгээ";
        $lnService->source_type = "OI";
        $lnService->is_tmw = 1;
        $lnService->is_preview = 0;
        $lnService->is_preview_fee = 0;
        $lnService->internal_cont_acnt_code = $txn_acnt;
        $lnService->cont_acnt_code = $contbankacnt;
        $lnService->cont_bank_code = $contbankcode;
        $lnService->tcust_name = $cuser->firstname;
        $lnService->tcust_register = $cuser->regno;
        $lnService->created_at = Carbon::now();
        $lnService->created_by = $qpay->created_by;
        $lnService->created_by = $onlineteller ?? 1;
        $lnService->statusid = 0;
        $lnService->instid = $instid;
        $lnService->parent_jrno = $othrs['parent_jrno'] ?? '';
        $lnService->txn_date = $othrs['txn_date'] ?? '';
        $lnService->txn_type = 0;

        $prodcode = null;
        $acnt = ApAcntLn::where('acnt_code', $qpay->to_account)->where('instid', $instid)->first();

        if (isset($acnt)) {
            $prodcode = $acnt->prod_code;
        }
        $lnService->prodcode = $prodcode;
        $lnService->save();


        $providertype = CoreService::getInstGp($instid, 'MEAPPPROVIDER');
        $respdata = [];
        if ($providertype == "MECORE") {
            try {
                $tmpuser = auth()->user();
                $consumerNo = $tmpuser->ebarimt_consumerno ?? null;

                $user = GPInstUser::where('instid', $instid)->find(
                    $onlineteller
                );
                Auth::setUser($user);
                if ($qpay->typeid == 1 || $qpay->typeid == '1') {
                    $req_data = [
                        "acntno" => $txn_acnt,
                        "curcode" => $qpay->cur_code,
                        "rate" => 1,
                        "rtypecode" => "1",
                        "contacntno" => $qpay->to_account,
                        "txnamount" => $qpay->amount,
                        "txndesc" => $lnService->txn_desc,
                        "ispreview" => 0
                    ];
                    $lnService->oper_code = 'ln902091';
                    $lnService->save();
                    // Зээлийн данс хаах(бэлэн бус)
                    $process = GpctionCode::where('ACTION_CODE', 'ln902091')->first();
                    $route = $process->controller . '@' . $process->function;
                    request()->merge($req_data);
                    $respdata = App::call($route);
                } else {

                    $req_data = [
                        "acntno" => $txn_acnt,
                        "curcode" => $qpay->cur_code,
                        "rate" => 1,
                        "rtypecode" => "1",
                        "contacntno" => $qpay->to_account,
                        "txnamount" => $qpay->amount,
                        "txndesc" => $lnService->txn_desc,
                        "ispreview" => 0
                    ];
                    $lnService->oper_code = 'ln902011';
                    $lnService->save();
                    // Зээлийн данс төлөлт хийх (бэлэн бус)
                    $process = GpctionCode::where('ACTION_CODE', 'ln902011')->first();
                    $route = $process->controller . '@' . $process->function;
                    request()->merge($req_data);
                    $respdata = App::call($route);
                }

                try {
                    EBarimtJob::dispatch($process->ACTION_CODE, $respdata, $user, $consumerNo)->onQueue("sendVAT");
                } catch (Exception $ex) {
                    Log::error('Апп - User');
                    Log::error($user);
                    Log::error('Апп - Зээл төлөлтийн гүйлгээ ибаримт үүсгэхэд алдаа гарлаа.');
                    Log::error($ex);
                }
                if (!empty($tmpuser) && gettype($tmpuser) != 'string') {
                    Auth::setUser(ApCustUser::find($tmpuser->id));
                }

                $lnService->statusid = 1;
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->err_desc = "";
                $lnService->save();
            } catch (Exception $ex) {
                $lnService->statusid = 2;
                $lnService->err_desc = $ex->getMessage();
                Log::error($ex);
                $lnService->save();
                throw $ex;
            }

            return $respdata;

            // }
        } else {
            $req_data = [
                "txnAcntCode" => $qpay->to_account,
                "txnAmount" => $qpay->amount,
                "curCode" => "MNT",
                "rate" => 1,
                "contAcntCode" => $txn_acnt,
                "contAmount" => $qpay->amount,
                "contRate" => 1,
                "contCurCode" => "MNT",
                "txnDesc" => $lnService->txn_desc,
                "sourceType" => "OI",
                "isPreview" => 0,
                "isPreviewFee" => 0,
                "isTmw" => 1
            ];
            try {
                if ($qpay->typeid == 1 || $qpay->typeid == '1') {
                    // Зээлийн данс хаах(бэлэн бус)
                    $req_data['addParams'] = [
                        [
                            'contAcntType' => 'CASA',
                            'chkAcntInt' => 'Y'
                        ]
                    ];
                    $lnService->oper_code = 13610267;
                    $lnService->save();
                    $respdata = $polaris->sendRequest($lnService->oper_code, [$req_data], $instid);
                } else {
                    // NES зээлийн дансны төлөлт хийх хүсэлт илгээх
                    $lnService->oper_code = 13610250;
                    $lnService->save();
                    $respdata = $polaris->sendRequest($lnService->oper_code, [$req_data], $instid);
                }

                $lnService->statusid = 1;
                // $lnService->txn_jrno = $nesresp['txnJrno'];
                $lnService->core_jrno = $respdata['txnJrno'];
                $lnService->is_supervisor = $respdata['isSupervisor'] ?? 0;
                $lnService->jr_item_no_and_incr = $respdata['jrItemNoAndIncr'] ?? 0;
                $lnService->save();
            } catch (Exception $ex) {
                Log::error($ex);
                $lnService->statusid = 2;
                $lnService->err_desc = $ex->getMessage();
                $lnService->save();
                throw $ex;
            }
        }

        return $respdata;
    }

    public function getCalcAvialBalTd($data, $polaris)
    {
        $acntService = new ApAcntService();
        $maxAmount = 0;
        $tddata = $acntService->getTdAccountDetail($data['txnAcntCode'], $data['instid']);

        if ($tddata) {
            $maxAmount = $tddata->avail_bal * $polaris->savingLoan->giveLoanMaxRate / 100;
        }

        return $maxAmount;
    }

    public function getLoanInfoTdAcnt($data)
    {
        $polaris = new PolarisApiRequestService($data['instid']);
        $acntService = new ApAcntService();
        $intRate = 0;
        $maxAmount = 0;
        $tddata = null;

        try {
            $tddata = $acntService->getTdCollInfo($data['txnAcntCode'], $data['instid']);
        } catch (Exception $ex) {
            Log::error($ex);
        }

        if (isset($tddata)) {
            $maxAmount = $this->getCalcAvialBalTd($data, $polaris) - $tddata->utilized;
            if ($maxAmount < 0) {
                $maxAmount = 0;
            }
        } else {
            $maxAmount = $this->getCalcAvialBalTd($data, $polaris);
        }

        $intdetail = $acntService->getAccountInt($data['txnAcntCode'], $data['instid'], ApAccountTypeEnum::td);
        $prod = LnAccountType::where('instid', $data['instid'])
            ->where('prodcode', $polaris->savingLoan->loanAcnt->prodCode)
            ->where('statusid', 1)->first();

        if ($intdetail) {
            foreach ($intdetail as $key => $value) {
                $value = json_decode(json_encode($value));
                if ($value->intTypeCode == 'SIMPLE_INT') {
                    if (!isset($value->intRate)) {
                        throw new MeException("RC000204");
                    }
                    $intRate = $value->intRate;
                    break;
                }
            }
            return [
                'maxAmount' => $maxAmount,
                'intRate' => round($polaris->savingLoan->loanAcnt->marginRate === 0 ? $prod->intrate : $polaris->savingLoan->loanAcnt->marginRate + $intRate, 2)
            ];
        } else {
            throw new MeException("RC000204");
        }
    }

    /**
     * Зээлийн данс хаах(бэлэн бус)
     *
     * @param  mixed $data
     * @return void
     */
    public function closeLoanAcnt($data)
    {
        // $polaris = new PolarisApiRequestService($data['instid']);
        // $req_data = [
        //     "txnAcntCode" => "110013000058",
        //     "txnAmount" => 116321.03,
        //     "curCode" => "MNT",
        //     "rate" => 1,
        //     "rateTypeId" => "4",
        //     "contAcntCode" => "1100CA000016",
        //     "contAmount" => 116321.03,
        //     "contRate" => 1,
        //     "contCurCode" => "MNT",
        //     "txnDesc" => "loan closing",
        //     "sourceType" => "OI",
        //     "isPreview" => 0,
        //     "isPreviewFee" => null,
        //     "isTmw" => 1
        // ];
        // return $polaris->sendRequest(13610267, [$req_data], $data['instid']);
    }

    public function casaToInternalAcntT($data, $isFeeTran = false)
    {
        if ($data['txnAmount'] == 0) {
            return [
                'data' => [],
                'status' => 200
            ];
        }
        $req_data = [
            [
                "txnAcntCode" => $data['txnAcntCode'],
                "txnAmount" => $data['txnAmount'],
                "rate" => $data['rate'],
                "contAcntCode" => $data['contAcntCode'],
                "contAmount" => $data['contAmount'],
                "contRate" => $data['contRate'],
                "txnDesc" => $data['txnDesc'],
                "txnDefCode" => null,
                "sourceType" => "OI",
                "isPreview" => 0,
                "isPreviewFee" => null,
                "isTmw" => 1
            ]
        ];
        $user = auth()->user();

        $onlineteller = CoreService::getInstGp($data['instid'], 'ONLINETELLERNUMBER');
        // Шимтгэлийн гүйлгээний мэдээлэл үүсгэх
        $lnService = new ApTxnJournal();
        $lnService->txn_acnt_code = $data['txnAcntCode'];
        $lnService->cur_code = 'MNT';
        // $lnService->tran_amt = $data['txnAmount'];
        // $lnService->tran_cur_code = 'MNT';
        $lnService->identity_type = "MANUAL";
        $lnService->rate = 1;
        $lnService->cont_amount = $data['txnAmount'];
        $lnService->txn_amount = $data['txnAmount'];
        $lnService->cont_cur_code = 'MNT';
        $lnService->cont_rate = 1;
        $lnService->txn_desc = $data['txnDesc'];
        $lnService->source_type = "OI";
        $lnService->is_tmw = 1;
        $lnService->is_preview = 0;
        $lnService->is_preview_fee = 0;
        $lnService->cont_acnt_code = $data['contAcntCode'];
        // $lnService->cont_bank_code = $data['contBankCode'];
        $lnService->created_at = Carbon::now();
        $lnService->userid = $user->id;
        $lnService->created_by = $onlineteller ?? 1;
        $lnService->statusid = 0;
        $lnService->txn_type = $isFeeTran ? 2 : 1;
        $lnService->instid = $data['instid'];
        $lnService->parent_jrno = $data['parent_jrno'] ?? '';
        $lnService->txn_date = $data['txn_date'] ?? '';
        $lnService->oper_code = 13610053;

        $prodcode = null;
        if (!$isFeeTran) {
            $acnt = ApAcntLn::where('acnt_code', $data['txnAcntCode'])->where('instid', $data['instid'])->first();

            if (isset($acnt)) {
                $prodcode = $acnt->prod_code;
            }
        }

        $lnService->prodcode = $prodcode;
        $lnService->save();

        $providertype = CoreService::getInstGp($data['instid'], 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
            $service = new DpTxnService();
            $tP = new TxnJrnlEntity();
            $tP->setTxnAcntCode($data['txnAcntCode']);
            $tP->setContAcntCode($data['contAcntCode']);
            $tP->setTxnAmount($data['txnAmount']);
            $tP->setContAmount($data['txnAmount']);
            $tP->setContCurCode('MNT');
            $tP->setRate(1);
            $tP->setContRate(1);
            $tP->setCurCode('MNT');
            $tP->setSourcecode(2);
            $tP->setInstid($data['instid']);
            $tP->setPostdate(getNow());
            $tP->setUserid($onlineteller);
            $tP->setTxndate(CoreService::getTxnDate($data['instid']));
            $tP->setTxnDesc($data['txnDesc'] ?? 'ME APP');

            $response = $service->doNonCashToIaCreditTxn($tP)->jsonSerialize();
            $respdata = [
                'status' => 200,
                'data' => $response
            ];

            $apTxn = ApTxnJournal::where('id', $lnService->id)->where('instid', $lnService->instid)->first();
            $apTxn->statusid = 2;
            $apTxn->core_jrno = $response['txnJrno'];
            $apTxn->is_supervisor = $response['isSupervisor'] ?? 0;
            $apTxn->jr_item_no_and_incr = $response['jrItemNoAndIncr'] ?? 0;
            $apTxn->err_desc = "";
            $apTxn->save();
        } else {
            $polaris = new PolarisApiRequestService($data['instid']);
            $nesresp = $polaris->sendRequest($lnService->oper_code, $req_data, $data['instid']);
            $lnService->statusid = 2;
            $lnService->core_jrno = $nesresp['txnJrno'];
            $lnService->is_supervisor = $nesresp['isSupervisor'] ?? 0;
            $lnService->jr_item_no_and_incr = $nesresp['jrItemNoAndIncr'] ?? 0;
            $lnService->err_desc = "";
            $lnService->save();
            $respdata = [
                'status' => 200,
                'data' => $nesresp
            ];
        }
        return $respdata;
    }

    public function getCalcAmounts($fee_config, $amount)
    {
        // App-s орж ирж буй дүн
        $req_amount = $amount;
        // Байгууллагын шимтгэлийн томьёог авах
        $calcinstfee_str = $fee_config['expressions'][$fee_config['calc_inst_fee'] ?? ''];
        // Системийн шимтгэлийн томьёог авах
        $calcsysfee_str = $fee_config['expressions'][$fee_config['calc_system_fee'] ?? ''];
        if (empty($calcinstfee_str) || empty($calcsysfee_str)) {
            throw new MeException('Шимтгэл тооцоолох тохиргоо хийгдээгүй байна.');
        }
        $adv_amount_expr = $fee_config['expressions'][$fee_config['adv_expression'] ?? ''];
        $loan_amount_expr = $fee_config['expressions'][$fee_config['loan_expression'] ?? ''];
        if (!isset($adv_amount_expr)) {
            throw new MeException('Шимтгэлийн эх үүсвэр тохиргоо хийгдээгүй байна./Гарт олгох/');
        }

        if (!isset($loan_amount_expr)) {
            throw new MeException('Шимтгэлийн эх үүсвэр тохиргоо хийгдээгүй байна./Зээлийн дүн/');
        }

        if (!isset($fee_config['fee_info'])) {
            throw new MeException('Шимтгэлийн fee_info тохиргоо хийгдээгүй байна.');
        }
        // Байгууллагын шимтгэлийн дүн
        $calc_instfee_amount = eval($calcinstfee_str) * 1;
        // Системийн шимтгэлийн дүн
        $calc_sysfee_amount = eval($calcsysfee_str) * 1;
        // Нийт шимтгэлийн дүн
        $calc_fee_amount = $calc_instfee_amount + $calc_sysfee_amount;
        // Гарт олгох
        $adv_amount = eval($adv_amount_expr) * 1;
        // Зээлийн дүн
        $loan_amount = eval($loan_amount_expr) * 1;
        // Зээлийн мэдээлэл
        $fee_info = $fee_config['fee_info'];
        // Allow mathematical expressions in parentheses, e.g., ($adv_amount - 5000)
        $fee_info = preg_replace_callback('/\((.*?)\)/', function ($matches) use ($adv_amount, $calc_fee_amount, $loan_amount, $calc_instfee_amount, $calc_sysfee_amount) {
            if (strpos($matches[1], '$') !== false) {
                try {
                    return eval("return $matches[1];");
                } catch (\Throwable $th) {
                    return $matches[0];
                }
            }
            return $matches[0];
        }, $fee_info);
        eval("\$fee_info = \"$fee_info\";");
        return [
            'calc_fee_amount' => $calc_fee_amount,
            'adv_amount' => $adv_amount,
            'loan_amount' => $loan_amount,
            'fee_info' => $fee_info,
            'calc_instfee_amount' => $calc_instfee_amount,
            'calc_sysfee_amount' => $calc_sysfee_amount,
        ];
    }

    /**
     * Хугацаагүй хадгаламжийн данснаас зарлага гаргах (oi000810)
     */
    public function withdrawDpAcnt($data)
    {
        $user = auth()->user();

        $dpAcnt = ApAcntDp::where('acnt_code', $data['acntno'])
            ->whereIn('status', ['O', '4', '1'])
            ->where('acnt_type', 'DP')
            ->first();

        if (empty($dpAcnt)) {
            throw new MeException('RC000034', ['mainacntno' => $data['acntno']]);
        }

        $instid = $dpAcnt->instid;

        $cust = ApCustomer::where('instid', $instid)
            ->where('regno', $user->regno)
            ->where('statusid', 1)
            ->first();

        if (empty($cust)) {
            throw new MeException('RC000176');
        }

        if ($dpAcnt->cust_code !== $cust->cif) {
            throw new MeException('RC000034', ['mainacntno' => $data['acntno']]);
        }

        $provider = VwGPProviderConf::where('code', '2')->where('instid', $instid)->first();
        $sharedCapitalProdCodes = [];
        if (isset($provider)) {
            $providerConfig = json_decode($provider->config, true);
            if (isset($providerConfig['savingLoan']['sharedCapitalProdCodes'])) {
                $sharedCapitalProdCodes = $providerConfig['savingLoan']['sharedCapitalProdCodes'];
            }
        }

        if (in_array($dpAcnt->prod_code, $sharedCapitalProdCodes)) {
            throw new MeException('Хувь нийлүүлсэн хөрөнгийн дансаас зарлага гаргах боломжгүй.');
        }

        $availBal = floatval($dpAcnt->avail_bal ?? 0);
        $amount = floatval($data['amount']);

        if ($availBal < $amount) {
            throw new MeException('Дансны үлдэгдэл хүрэлцэхгүй байна.');
        }

        $receiveAcnt = ApCustBankAccount::where('acnt_code', $data['receive_acntno'])
            ->where('cust_user_id', $user->id)
            ->where('statusid', '>', 0)
            ->first();

        if (empty($receiveAcnt)) {
            throw new MeException('Хүлээн авах данс бүртгэлгүй байна.');
        }

        $receiveBankCode = $receiveAcnt->bank_code;
        $receiveAcntName = $receiveAcnt->acnt_name;

        $cgwBankCode = $this->getCallCgwBankCode([
            'instid' => $instid,
            'contBankCode' => $receiveBankCode,
        ]);

        if (isset($provider)) {
            if (isset($providerConfig['cgw']['limit']['otherBankTxnLimit'])) {
                if ($amount > $providerConfig['cgw']['limit']['otherBankTxnLimit']) {
                    $bank_name = $cgwBankCode;

                    $bank = GPInstConst::where('parent_code', 'bank')->where('value', $cgwBankCode)->first();
                    if ($bank) {
                        $bank_name = $bank->name;
                    }
                    throw new MeException('RC000214', [
                        'amount' => number_format($providerConfig['cgw']['limit']['otherBankTxnLimit'], 2, '.', ','),
                        'bank_name' => $bank_name
                    ]);
                }
            }
        }

        if (config('app.env') != 'production') {
            return 'Хадгаламжийн дансны зарлага амжилттай хийгдлээ.';
        }

        try {
            $resp = $this->corporateTransaction($cgwBankCode, [
                'instid'  => $instid,
                'txnAcntCode'  => $data['acntno'],
                'amount' => $amount,
                'contAcntCode' => $data['receive_acntno'],
                'contBankCode' => $receiveBankCode,
                'contAcntName' => $receiveAcntName,
            ], $dpAcnt);
        } catch (Exception $e) {
            throw new MeException('Зарлагын гүйлгээ амжилтгүй боллоо. ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Хадгаламжийн дансны зарлага амжилттай хийгдлээ.',
        ];
    }
}
