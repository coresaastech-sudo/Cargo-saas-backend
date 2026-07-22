<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCgwTxnDescCombination;
use Modules\Ad\Entities\AdCorporateGateway;
use Modules\Ad\Entities\AdCgwTransaction;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Ap\Entities\ApQpay;
use Modules\Ap\Http\Services\ApQpayService;
use Modules\Cr\Entities\CrCustBankAccount;
use Modules\Cr\Entities\CrCustInd;
use Modules\Dp\Entities\DpAccount;
use Modules\Dp\Entities\Views\VwDpAccountDetail;
use Modules\Gp\Entities\GPInstCurRate;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Gp\Enums\AcntStatusCodeEnum;
use Modules\Gp\Enums\LnStatusCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\EBarimtJob;
use Modules\Ia\Entities\IaAccount;
use Modules\Ln\Entities\LnAccount;
use Modules\Tr\Http\Services\IaTxnService;
use Modules\Tr\Http\Services\LnTxnService;
use Modules\Gp\Entities\GPInstUser;
use Illuminate\Support\Facades\Auth;
use Modules\Cr\Entities\Views\VwCrCustAllAcntList;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Ln\Entities\Views\VwLnAccountAdd;
use PHPUnit\Framework\Constraint\IsFalse;

class AdCorporateGatewayService extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public $providerConfig;
    public $instid;
    public $userid;
    private $is_use_acnt_name;
    private $config;

    public function __construct($instid, $userid, $providerConfig)
    {
        $this->instid = $instid;
        $this->userid = $userid;
        $this->providerConfig = $providerConfig;
        $this->is_use_acnt_name = false;
        $this->config = null;
    }


    /**
     * Corporate Gateway-с ирсэн гүйлгээг боловсруулах үндсэн функц.
     *
     * @param AdCorporateGateway $data Гүйлгээний мэдээлэл.
     * @param string $acntno Байгууллагын (provider) дансны дугаар.
     * @return mixed
     */
    public function processCorporateGateway(AdCorporateGateway $data, $acntno)
    {
        $prodcode = $this->extractProdCode($data['txndesc']);
        $this->config = $this->providerConfig['account'][strval($acntno)] ?? $this->providerConfig['account'] ?? $this->providerConfig;
        $credit_accountno = $this->config['acntno'] ?? null;
        $prodcode = $this->config['prodcode'] ?? $prodcode;

        if (($this->config['disable_qpay'] ?? false) && str_contains(strtoupper($data->txndesc), 'QPAY')) {
            $sender_invoice_no = '';

            // Extract sender_invoice_no from txndesc (format: 'qpay 401001690356346, 2025112723161629')
            // Таслалын дараах утгаас зөвхөн 16 оронтой тоог олох
            if (strpos($data->txndesc, ',') !== false) {
                $parts = explode(',', $data->txndesc);
                $lastPart = trim(end($parts));
                // Таслалын дараах утгаас зөвхөн 16 оронтой тоог олох (жишээ: "2025112723161629 урал баяр..." -> "2025112723161629")
                if (preg_match('/\b\d{16}\b/', $lastPart, $matches)) {
                    $sender_invoice_no = $matches[0];
                } else {
                    // Хэрэв таслалын дараах хэсэгт олдохгүй бол бүх текстээс хайх
                    if (preg_match('/\b\d{16}\b/', $data->txndesc, $matches)) {
                        $sender_invoice_no = $matches[0];
                    }
                }
            } else {
                // Fallback: try to extract 16 digit number using regex
                if (preg_match('/\b\d{16}\b/', $data->txndesc, $matches)) {
                    $sender_invoice_no = $matches[0];
                }
            }

            if (!empty($sender_invoice_no)) {
                $qpay = ApQpay::where('sender_invoice_no', $sender_invoice_no)->where('instid', $data->instid)->where('statusid', 1)->first();
                if ($qpay) {
                    return $this->markAsProcessed($data, 'QPay гүйлгээ хийгдэх үед гүйлгээг хийсэн.');
                } else {
                    $qpayservice = new ApQpayService($qpay->instid);
                    $qpayservice->callBackUrl($sender_invoice_no, true);

                    $qpay = ApQpay::where('sender_invoice_no', $sender_invoice_no)->where('instid', $data->instid)->where('statusid', 1)->first();
                    if ($qpay) {
                        return $this->markAsProcessed($data, 'QPay гүйлгээ хийгдэх үед гүйлгээг хийсэн.');
                    } else {
                        return $this->markAsFailed($data, 'QPay гүйлгээг дахин нягтална уу.');
                    }
                }
            }
        }

        $credit_accountno = $this->resolveCreditAccount($data, $credit_accountno);
        if (!$credit_accountno) {
            return $this->markAsFailed($data, 'Дотоодын дансны тохиргоо олдсонгүй.');
        }

        if (isset($this->config['check_system_date']) && ($this->config['check_system_date'] == '1' || $this->config['check_system_date'] == 1)) {
            if (!$this->isSameSystemDate($data)) {
                return $this->markAsFailed($data, 'Системийн огноо таарахгүй байна.');
            }
        }


        if (isset($this->config['transfer_internal']) && ($this->config['transfer_internal'] == '1' || $this->config['transfer_internal'] == 1)) {
            if ($iaAcnt = $this->resolveInternalAccount($data)) {
                return $this->processInternalTxn($data, $iaAcnt, $credit_accountno);
            }

            $this->markAsFailed($data, 'Данс олдсонгүй /Дотоод/.');
        }

        if ($data['sign'] === '+') {
            $client_acnt = $this->resolveClientAccount($data);

            if ($client_acnt) {
                // Харилцагчийн бүртгэгдсэн данснаас орж ирсэн эсэхийг шалгах
                if (isset($this->config['check_bank_account']) && ($this->config['check_bank_account'] == '1' || $this->config['check_bank_account'] == 1)) {
                    $checked = $this->checkAccount($client_acnt, $data);
                    if (!$checked) {
                        return $this->markAsFailed($data, 'Харилцагчийн бүртгэлтэй банкны данс зөрж байна.');
                    }
                }

                return $this->processIncomingTxn($data, $client_acnt, $credit_accountno);
            } else {
                return $this->markAsFailed($data, 'Данс олдсонгүй.');
            }
        }



        return null;
    }

    /**
     * Гүйлгээний мэдээллээс харилцагчийн дансыг урьдчилан таних.
     *
     * @param array $data Гүйлгээний мэдээлэл.
     * @param string $acntno Байгууллагын (provider) дансны дугаар.
     * @return array|null
     */
    public function previewCorporateGateway($data, $acntno)
    {
        $this->config = $this->providerConfig['account'][strval($acntno)] ?? $this->providerConfig['account'] ?? $this->providerConfig;

        // 1. Зарлага бол дотоод данс хайна
        if ($data['sign'] === '-' && isset($this->config['transfer_internal']) && ($this->config['transfer_internal'] == '1' || $this->config['transfer_internal'] == 1)) {
            if ($iaAcnt = $this->resolveInternalAccount($data)) {
                return [
                    'acntno' => $iaAcnt->acntno,
                    'loan_accountno' => null,
                    'acntname' => $iaAcnt->name,
                    'acnttype' => 'IA',
                ];
            }
        }

        // 2. Орлого бол харилцагчийн данс хайна
        if ($data['sign'] === '+') {
            if ($clientAcnt = $this->resolveClientAccount($data)) {
                return [
                    'acntno' => $clientAcnt->acntno,
                    'loan_accountno' => $clientAcnt->loan_accountno,
                    'acntname' => $clientAcnt->name,
                    'acnttype' => $clientAcnt->loan_accountno ? 'LN' : ($clientAcnt->prodcode ?? 'DP'),
                ];
            }
        }

        return null;
    }

    /**
     * Гүйлгээний утгаас дотоод данс таних.
     * 
     * @param mixed $data
     * @return object|null
     */
    public function resolveInternalAccount($data)
    {
        $descr = $data['txndesc'];
        $inst = GPInstList::where('id', $data->instid)->first();
        
        $regex = '/[0-9]{12,}/u';
        if ($inst && !empty($inst->iaacntbegno)) {
            $regex = '/[0-9]{' . strlen($inst->iaacntbegno) . ',}/u';
        }

        if (preg_match($regex, $descr, $matches)) {
            $acntno = $matches[0];
            $iaAccounts = $this->findAcntIa('acntno', $acntno, $data->instid);
            if (count($iaAccounts) === 1) {
                return $iaAccounts[0];
            }
        }

        return null;
    }



    /**
     * Орлогын гүйлгээг боловсруулах.
     * Энэ функц нь харилцагчийн дансанд орлого хийж, шаардлагатай бол зээлийн төлөлт хийдэг.
     *
     * @param mixed $data Гүйлгээний мэдээлэл.
     * @param mixed $client_acnt Харилцагчийн данс.
     * @param string $credit_accountno Байгууллагын (provider) дотоодын данс.
     * @return AdCorporateGateway|null
     */
    public function processIncomingTxn($data, $client_acnt, $credit_accountno)
    {
        $tP = [
            'txnamount' => abs($data->txnamount),
            'txndesc' => $data->txndesc,
            'curcode' => $data->curcode,
            'acntno' => $credit_accountno,
            'contacntno' => $client_acnt->acntno,
            'rtypecode' => '1',
            'ispreview' => 0,
            'rate' => $this->getRate($data->curcode, '1'),
            'sourcecode' => 7, // Багц гүйлгээ
        ];

        try {
            $resp = $this->depositTxn($tP);
            if (isset($resp['txnJrno'])) {
                $jrno = $resp["txnJrno"];
                AdCorporateGateway::where('id', $data->id)
                    ->where('instid', $data->instid)
                    ->where('statusid', '<>', -1)->update([
                            'statusid' => 2,
                            'txn_jrno' => $jrno,
                            'acntno' => $tP['contacntno'],
                            'reason' => 'Амжилттай'
                        ]);

                $corporateGateway = AdCorporateGateway::where('id', $data->id)->where('instid', $this->instid)->first();
                if (isset($this->config['pay_loan']) && ($this->config['pay_loan'] == '1' || $this->config['pay_loan'] == 1)) {
                    $tP['acntno'] = $client_acnt->acntno;

                    if (isset($client_acnt->loan_accountno)) {
                        $lnAcnt = LnAccount::where('acntno', $client_acnt->loan_accountno)
                            ->where('instid', $this->instid)
                            ->whereNotIn('statusid', [-1, 0, 9])
                            ->orderBy('repaypriority', 'ASC')->first();

                        if ($lnAcnt) {
                            $tP['contacntno'] = $lnAcnt->acntno;

                            try {
                                $payLoanRes = $this->payLoan($tP, $lnAcnt);
                                if (isset($payLoanRes) && isset($payLoanRes['txnJrno']) && $payLoanRes['isPreview'] == 0) {
                                    $jrno = $payLoanRes["txnJrno"];
                                    $corporateGateway->update([
                                        'statusid' => 2,
                                        'txn_jrno' => $jrno,
                                        'acntno' => $tP['contacntno'],
                                        'reason' => 'Зээл төлөлт амжилттай'
                                    ]);
                                }
                            } catch (MeException $ex) {
                                Log::error($ex);
                                $this->markAsFailed($data, $ex->getMessage());
                            }
                        } else {
                            $this->markAsFailed($data, 'Зээлийн данс олдсонгүй!');
                        }
                    }
                } else {
                    $this->markAsProcessed($data, 'Депозит дансанд амжилттай гүйлгээ хийв.');
                }
                return $corporateGateway;
            } else {
                $this->markAsFailed($data, 'Гүйлгээ хийх үед алдаа гарлаа.');
            }
        } catch (MeException $ex) {
            Log::error($ex);
            $this->markAsFailed($data, $ex->getMessage());
        }

        return null;
    }

    /**
     * Дотоод гүйлгээг боловсруулах.
     * Энэ функц нь харилцагчийн данснаас байгууллагын дотоод данс руу мөнгө шилжүүлэхэд ашиглагдана.
     *
     * @param mixed $data Гүйлгээний мэдээлэл.
     * @param mixed $client_acnt Харилцагчийн данс.
     * @param string $credit_accountno Байгууллагын (provider) дотоодын данс.
     * @param array $this->config Тохиргооны мэдээлэл.
     * @return AdCorporateGateway|null
     */
    public function processInternalTxn($data, $client_acnt, $credit_accountno)
    {
        $acntno = $client_acnt->acntno;
        $contacntno = $credit_accountno;

        if ($data->sign == '+') {
            $acntno = $credit_accountno;
            $contacntno = $client_acnt->acntno;
        }

        $tP = [
            'txnamount' => abs($data->txnamount),
            'txndesc' => $data->txndesc,
            'curcode' => $data->curcode,
            'acntno' => $acntno,
            'contacntno' => $contacntno,
            'rtypecode' => '1',
            'ispreview' => 0,
            'rate' => $this->getRate($data->curcode, '1'),
        ];

        try {
            $resp = $this->internalTxn($tP);
            if (isset($resp['txnJrno'])) {
                $jrno = $resp["txnJrno"];
                AdCorporateGateway::where('id', $data->id)
                    ->where('instid', $data->instid)
                    ->where('statusid', '<>', -1)->update([
                            'statusid' => 2,
                            'txn_jrno' => $jrno,
                            'acntno' => $tP['acntno'],
                            'reason' => 'Амжилттай'
                        ]);
                return AdCorporateGateway::where('id', $data->id)->first();
            } else {
                $this->markAsFailed($data, 'Дотоодын дансанд гүйлгээ хийх үед алдаа гарлаа.');
            }
        } catch (MeException $ex) {
            Log::error($ex);
            $this->markAsFailed($data, $ex->getMessage());
        }
        return null;
    }

    /**
     * Гүйлгээг "боловсруулсан" төлөвт оруулах.
     *
     * @param AdCorporateGateway $data Гүйлгээний мэдээлэл.
     * @param string $reason Шалтгаан.
     * @return null
     */
    private function markAsProcessed($data, $reason)
    {
        if ($data->exists) {
            AdCorporateGateway::where('id', $data->id)->where('statusid', '<>', -1)->update([
                'statusid' => 2,
                'reason' => $reason
            ]);
        }
        return null;
    }

    /**
     * Гүйлгээний алдааны мэдээллийг хадгалах.
     *
     * @param AdCorporateGateway $data Гүйлгээний мэдээлэл.
     * @param string $reason Алдааны шалтгаан.
     * @return null
     */
    private function markAsFailed($data, $reason)
    {
        $data->reason = $reason;
        if ($data->exists) {
            $data->save();
        }
        return null;
    }

    /**
     * Гүйлгээ хийх дотоодын дансыг тодорхойлох.
     * Банкны кодоос хамаарч тохиргооноос зөв дотоодын дансыг олно.
     *
     * @param array $this->config Тохиргооны мэдээлэл.
     * @param AdCorporateGateway $data Гүйлгээний мэдээлэл.
     * @param string|null $default Өгөгдмөл данс.
     * @return string|null
     */
    private function resolveCreditAccount($data, $default)
    {
        $code = $data->bankcode;
        $map = [
            '05' => 'khan_credit_accountno',
            '04' => 'tdb_credit_accountno',
            '15' => 'golomt_credit_accountno',
            '34' => 'state_credit_accountno',
            '32' => 'xac_credit_accountno'
        ];
        return $this->config[$map[$code]] ?? $default;
    }

    private function isSameSystemDate($data)
    {
        $sysdate = Carbon::parse(CoreService::getTxnDate($data->instid));
        $txndate = Carbon::parse($data->banktxndate);
        return $txndate->isSameDay($sysdate);
    }

    public function resolveClientAccount($data)
    {
        $this->is_use_acnt_name = false;
        $descr = $data['txndesc'];
        $matches = [];

        $inst = GPInstList::where('id', $data->instid)->first();

        $regex = '/[0-9]{12,}/u';

        if (isset($inst)) {
            $length = strlen($inst->acntbegno);
            $regex = '/[0-9]{' . $length . ',}/u';
        }

        preg_match($regex, $descr, $matches);
        $acntno = $matches[0] ?? null;

        $regnum         = $this->extractRegistrationNumbers($descr);
        $regnumCyrillic = $regnum['cyrillic'] ?? null;
        $regnumLatin    = $regnum['latin'] ?? null;

        if ($acntno) {
            $account = $this->findAcntDp('acntno', $acntno, $data->instid, null, false, $regnumCyrillic);
            if ($account)
                return $account;

            $lnAccounts = $this->findAcntLn('acntno', $acntno, $data->instid);
            if (count($lnAccounts) === 1) {
                $repayAcnt = $lnAccounts[0]['repayacntno'];
                $account = $this->findAcntDp('acntno', $repayAcnt, $data->instid, null, true, $regnumCyrillic);
                if ($account) {
                    $account['loan_accountno'] = $lnAccounts[0]['acntno'];
                    return $account;
                } else {
                    $this->markAsFailed($data, 'Түр харилцах данс хаагдсан байна.');
                }
            }
        }

        if (isset($this->config['use_acnt_name']) && ($this->config['use_acnt_name'] == '1' || $this->config['use_acnt_name'] == 1)) {
            $accounts = $this->findAcntByAcntname($descr, $data->instid);
            if (count($accounts) === 1) {
                $this->is_use_acnt_name = true;
                return $accounts[0];
            } // Таарч байгаа 1 дансыг буцаана
        }

        if (empty($this->config['disable_regnum'])) {
            if (!empty($regnum)) {
                // Тооны хэсгээр DB-д урьдчилан шүүж, PHP-д Кирилл→Латин харьцуулалт хийнэ.
                // Энэ арга нь "И","Й","Ь","Ъ" зэрэг олон Кирилл үсэг нэг Латин "I"-д харгалзах
                // Латин→Кирилл хөрвүүлэлтийн хоёрдмол утгаас зайлсхийнэ.
                $numericPart = preg_replace('/\D/', '', $regnumLatin ?? $regnumCyrillic ?? '');

                if ($numericPart && strlen($numericPart) >= 7) {
                    $candidates = CrCustInd::select('custno', 'id1')
                        ->whereRaw('"id1" ILIKE ?', ['%' . $numericPart . '%'])
                        ->where('instid', $data->instid)
                        ->where('statusid', 1)
                        ->get();

                    $matched = $candidates->filter(function ($cust) use ($regnumLatin, $regnumCyrillic) {
                        // Голлох арга: id1 (Кирилл)-ийг Латин болгоод харьцуулах
                        if (!empty($regnumLatin)) {
                            $id1Latin = strtoupper(cyrillic2latin($cust->id1));
                            if ($id1Latin === strtoupper($regnumLatin)) {
                                return true;
                            }
                        }
                        // Нөөц арга: Кирилл шууд харьцуулалт
                        if (!empty($regnumCyrillic)) {
                            if (strtoupper($cust->id1) === strtoupper($regnumCyrillic)) {
                                return true;
                            }
                        }
                        return false;
                    });

                    if ($matched->count() === 1) {
                        $account = $this->findAcntDp('custno', $matched->first()->custno, $data->instid, $this->config['prodcode'] ?? null, true, $regnumCyrillic);
                        if ($account) {
                            $lnAccounts = $this->findAcntLn('repayacntno', $account['acntno'], $data->instid);
                            if ($lnAccounts->count() === 1) {
                                $account['loan_accountno'] = $lnAccounts[0]['acntno'];
                            } else {
                                $this->markAsFailed($data, 'Түр харилцах данс 1-ээс олон байна.');
                            }
                            return $account;
                        } else {
                            $this->markAsFailed($data, 'Түр харилцах данс хоосон эсвэл хаагдсан байна.');
                        }
                    } elseif ($matched->count() > 1) {
                        Log::warning('CGW: Регистрийн дугаараар олон харилцагч олдлоо', [
                            'instid'          => $data->instid,
                            'regnum_latin'    => $regnumLatin,
                            'regnum_cyrillic' => $regnumCyrillic,
                            'count'           => $matched->count(),
                            'txndesc'         => mb_substr($descr, 0, 200),
                        ]);
                    } else {
                        Log::warning('CGW: Регистрийн дугаараар харилцагч олдсонгүй', [
                            'instid'          => $data->instid,
                            'regnum_latin'    => $regnumLatin,
                            'regnum_cyrillic' => $regnumCyrillic,
                            'txndesc'         => mb_substr($descr, 0, 200),
                        ]);
                    }
                }
            }
        }

        if (isset($this->config['enable_phone']) && ($this->config['enable_phone'] == '1' || $this->config['enable_phone'] == 1)) {
            // 8 оронтой утасны дугаараар хайх
            $phoneNumbers = $this->extractPhoneNumbers($descr);

            if (!empty($phoneNumbers)) {
                $phone = $phoneNumbers[0]; // Эхний утасны дугаарыг ашиглах
                $cust = VwCrCustList::select('custno')
                    ->whereRaw('"phone" ILIKE ?', ['%' . $phone . '%'])
                    ->where('instid', $data->instid)
                    ->where('statusid', 1)
                    ->get();

                if ($cust->count() === 1) {
                    // Түр данс олох
                    $account = $this->findAcntDp('custno', $cust[0]->custno, $data->instid, $this->config['prodcode'] ?? null, true);
                    if ($account) {
                        $lnAccounts = $this->findAcntLn('repayacntno', $account['acntno'], $data->instid);

                        if ($lnAccounts->count() === 1) {
                            $account['loan_accountno'] = $lnAccounts[0]['acntno'];
                        } else {
                            $this->markAsFailed($data, 'Түр харилцах данс 1-ээс олон байна.');
                        }

                        return $account;
                    } else {
                        $this->markAsFailed($data, 'Түр харилцах данс хоосон эсвэл хаагдсан байна.');
                    }
                } elseif ($cust->count() > 1) {
                    Log::warning('CGW: Утасны дугаараар олон харилцагч олдлоо', [
                        'instid'  => $data->instid,
                        'phone'   => $phone,
                        'count'   => $cust->count(),
                        'txndesc' => mb_substr($descr, 0, 200),
                    ]);
                } else {
                    Log::warning('CGW: Утасны дугаараар харилцагч олдсонгүй', [
                        'instid'  => $data->instid,
                        'phone'   => $phone,
                        'txndesc' => mb_substr($descr, 0, 200),
                    ]);
                }
            }
        }

        if (isset($this->config['enable_add_value']) && ($this->config['enable_add_value'] == '1' || $this->config['enable_add_value'] == 1)) {
            $addValue = $this->getAddFieldValue($this->config['add_field'] ?? '', $descr);

            if (!empty($addValue)) {
                $lnAccounts = $this->findAcntLn('acntno', $addValue['acntno'], $this->instid);
                if (count($lnAccounts) === 1) {
                    $repayAcnt = $lnAccounts[0]['repayacntno'];
                    $account = $this->findAcntDp('acntno', $repayAcnt, $this->instid, null, true);
                    if ($account) {
                        $account['loan_accountno'] = $lnAccounts[0]['acntno'];
                        return $account;
                    } else {
                        $this->markAsFailed($data, 'Түр харилцах данс хаагдсан байна.');
                    }
                }
            }
        }




        // 4. Зээлийн дансны дугаараар (acntno) хайх
        // Гүйлгээний утга дахь токенуудаас LnAccount.acntno-тэй таарах зээл олно.
        $lnAcnt = $this->resolveByLoanAcntno($descr, $data->instid);
        if ($lnAcnt) {
            $account = $this->findAcntDp('acntno', $lnAcnt->repayacntno, $data->instid, null, true, $regnumCyrillic);
            if ($account) {
                $account['loan_accountno'] = $lnAcnt->acntno;
                return $account;
            } else {
                $this->markAsFailed($data, 'Зээлийн дансны дугаараар түр харилцах данс олдсонгүй.');
            }
        }

        $acntno = $this->extractAcntnoByCgwCombination($descr);
        if ($acntno) {
            $account = $this->findAcntDp('acntno', $acntno, $data->instid, null, false, $regnumCyrillic);
            if ($account)
                return $account;
        }

        return null;
    }

    /**
     * Гүйлгээний утга дахь токенуудаас LnAccount.acntno-тэй таарах зээлийн данс олох.
     * Гүйлгээний утгыг зайгаар тусгаарлаж, тус бүр нь зээлийн дансны дугаар эсэхийг шалгана.
     * Яг 1 данс олдвол буцаана.
     */
    private function resolveByLoanAcntno(string $descr, $instid): ?object
    {
        $tokens = array_values(array_filter(
            preg_split('/\s+/', trim($descr)),
            fn($w) => !empty(trim($w))
        ));

        if (empty($tokens)) {
            return null;
        }

        $lnAccounts = LnAccount::where('instid', $instid)
            ->whereIn('acntno', $tokens)
            ->whereNotIn('statusid', [-1, 0, 9])
            ->get();

        if ($lnAccounts->count() === 1) {
            return $lnAccounts->first();
        }

        if ($lnAccounts->count() > 1) {
            Log::warning('CGW: Зээлийн дансны дугаараар олон данс олдлоо', [
                'instid'  => $instid,
                'count'   => $lnAccounts->count(),
                'txndesc' => mb_substr($descr, 0, 200),
            ]);
        }

        return null;
    }

    public function getAddFieldValue($code, $text)
    {
        // Текст-ийг зайгаар тусгаарлаад бүх үгүүдийг авна
        $words = preg_split('/\s+/', trim($text));
        $words = array_filter($words, function ($word) {
            return !empty(trim($word));
        });

        if (empty($words)) {
            return null;
        }

        $query = VwLnAccountAdd::where('code', $code)
            ->where('instid', $this->instid)
            ->where('statusid', 1);

        // Бүх үгүүдээр нь хайх (itemvalue нь текст доторх үгүүдтэй таарах ёстой)
        $query->where(function ($q) use ($words) {
            foreach ($words as $word) {
                $q->orWhereRaw('UPPER("itemvalue") = UPPER(?)', [trim($word)]);
            }
        });

        $addValue = $query->get();

        if ($addValue->count() === 1) {
            return $addValue[0];
        }

        return null;
    }

    /**
     * Гүйлгээний утгыг регистр тулгалтад зориулж нормчлох.
     * Дундуур зураас, цэг, таслал, цагираг хаалт зэрэг тусгаарлагч тэмдэгтүүдийг
     * хоосон зайгаар солино — ингэснээр "АА-12345678", "АА.12345678" хэлбэрүүд бүгд таних болно.
     */
    private function normalizeForRegMatch(string $text): string
    {
        return preg_replace('/[-.,;:()\[\]\/\\\\]+/', ' ', $text);
    }

    /**
     * Гүйлгээний утгаас регистрийн дугаар олох.
     *
     * Нормчилсны дараа Кирилл болон Латин хэлбэрийн регистрийг хайж,
     * ['cyrillic' => 'АА12345678', 'latin' => 'AA12345678'] хэлбэрийн массив буцаана.
     * Кирилл→Латин хөрвүүлэлт нь нэг утгатай тул тулгалтын голлох арга болно.
     *
     * @return array{cyrillic: string|null, latin: string|null}|array
     */
    public function extractRegistrationNumbers(string $text): array
    {
        $normalized = $this->normalizeForRegMatch($text);

        // 1. Кирилл үсэгтэй регистр (жишээ: АА12345678 эсвэл АА 12345678)
        if (preg_match('/(?<![А-Яа-яӨөҮүЭэA-Za-z\d])([А-Яа-яӨөҮүЭэ]{2})\s*(\d{8})(?!\d)/u', $normalized, $matches)) {
            $cyrillic = $matches[1] . $matches[2];
            $latin    = strtoupper(cyrillic2latin($matches[1])) . $matches[2];
            return ['cyrillic' => $cyrillic, 'latin' => $latin];
        }

        // 2. Латин үсэгтэй регистр (жишээ: AA12345678 эсвэл AA 12345678)
        if (preg_match('/(?<![А-Яа-яӨөҮүЭэA-Za-z\d])([A-Za-z]{2,3})\s*(\d{8})(?!\d)/u', $normalized, $matches)) {
            $latin    = strtoupper($matches[1]) . $matches[2];
            $converted = latin2cyrillic(strtoupper($matches[1]));
            return ['cyrillic' => $converted ? $converted . $matches[2] : null, 'latin' => $latin];
        }

        return [];
    }

    public function extractProdCode($text)
    {
        $GPinst = AdCgwTxnDescCombination::where('statusid', '<>', -1)
            ->where('instid', $this->instid)
            ->where('type', '1') // 1 - prodcode
            ->orderByraw('CHAR_LENGTH(value) DESC')->get();
        if ($GPinst) {
            foreach ($GPinst as $instance) {
                if (str_contains($text, $instance['value'])) {
                    return $instance['prodcode'];
                }
            }
        }

        if (isset($this->providerConfig['prodcode'])) {
            return $this->providerConfig['prodcode'];
        }
    }

    /**
     * Харилцагч бүртгэгдсэн данс болон хуулга дээр орж ирсэн дансыг шалгах
     *
     * @param string $client_acnt Ми кор дээрх гүйлгээ хүлээн авах гэж буй дансны дугаар
     * @param mixed $data AdCorporateGateway хуулга дээрх гүйлгээ
     * @return boolean
     */

    public function checkAccount($client_acnt, $data)
    {
        if (empty($client_acnt) || empty($data) || empty($data->instid) || empty($data->bankacntno)) {
            return false;
        }

        $account = VwCrCustAllAcntList::where('acntno', $client_acnt['acntno'])
            ->where('instid', $data->instid)
            ->where('statusid', '<>', -1)
            ->first();

        if (!$account) {
            return false;
        }

        $key = 'acnt_code';
        if ($this->isValidIBAN($data->bankacntno)) {
            $key = 'iban';
        }

        $acnt = CrCustBankAccount::where('custno', $account->custno)
            ->where('instid', $data->instid)
            ->where($key, $data->bankacntno)
            ->where('statusid', '<>', -1)
            ->first();

        return $acnt !== null;
    }


    function isValidIBAN($iban)
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            if (ctype_alpha($char)) {
                $numeric .= ord($char) - 55;
            } else {
                $numeric .= $char;
            }
        }

        $remainder = intval(substr($numeric, 0, 1));
        for ($i = 1; $i < strlen($numeric); $i++) {
            $remainder = ($remainder * 10 + intval($numeric[$i])) % 97;
        }

        return $remainder === 1;
    }


    public function extractAcntnoByCgwCombination($text)
    {
        $GPinst = AdCgwTxnDescCombination::where('statusid', '<>', -1)
            ->where('instid', $this->instid)
            ->where('type', '2') // 2 - acntno
            ->orderByraw('CHAR_LENGTH(value) DESC')->get();
        if ($GPinst) {
            foreach ($GPinst as $instance) {
                if (str_contains($text, $instance['value'])) {

                    return $instance['acntno'];
                }
            }
        }
        return null;
    }


    public function extractPhoneNumbers($text)
    {
        // Use regular expression to extract phone numbers (8 digits)
        preg_match_all('/\b\d{8}\b/', $text, $matches);

        // Return the extracted phone numbers
        return $matches[0] ?? [];
    }


    public function findAcntByAcntname($name, $instid)
    {
        $query = DpAccount::whereRaw("upper(name) = upper(?)", [$name])->where('instid', $instid)->whereNotIn('statusid', [-1, 0]);

        $list = $query->get();

        if (count($list) > 0) {
            return $list;
        } else {
            return [];
        }
    }

    /**
     * Депозит дансны мэдээлэл хайх.
     *
     * @param string $field Хайх талбар (жишээ нь: 'acntno', 'custno' гэх мэт)
     * @param mixed $value Хайх утга
     * @param int $instid Байгууллагын ID
     * @param string|null $prodcode Бүтээгдэхүүний код (заавал биш)
     * @param bool $isRepayAcnt Зөвхөн зээлийн төлөлтийн данс хайх эсэх (заавал биш)
     * @return array
     */
    public function findAcntDp($field, $value, $instid, $prodcode = null, $isRepayAcnt = false, $id1 = null)
    {
        $query = VwDpAccountDetail::where($field, $value)
            ->where('instid', $instid)
            ->whereNotIn('statusid', [-1, 0]);

        // Зээлийн төлөлтийн данс хайж байгаа үед procflag != 'T' нөхцөл нэмнэ
        if ($isRepayAcnt) {
            $query = $query->where('procflag', '!=', 'T');
        }

        // prodcode байгаа үед prodcode-оор шүүнэ
        if (!empty($prodcode)) {
            $query = $query->where('prodcode', $prodcode);
        }

        if (isset($this->config['enable_account_reg_check']) && ($this->config['enable_account_reg_check'] == '1' || $this->config['enable_account_reg_check'] == 1)) {
            $query = $query->whereRaw('UPPER("id1") ILIKE ?', ['%' . strtoupper($id1) . '%']);
        }

        $list = $query->get();
        if (count($list) == 1) {
            return $list[0];
        } else {
            return null;
        }
    }


    public function findAcntLn($field, $value, $instid)
    {
        return LnAccount::where($field, $value)->where('instid', $instid)->get();
    }

    public function findAcntIa($field, $value, $instid, )
    {
        return IaAccount::where($field, $value)->where('instid', $instid)->get();
    }

    public function depositTxn($data)
    {
        try {
            $cashController = new IaTxnService();
            $tP = $cashController->setParamToJrnEntity($data);
            return $cashController->doInternalToDeposit($tP)->jsonSerialize();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function payLoan($data, $lnAcnt)
    {
        try {
            // $cashController = new IaTxnService();
            // $tP = $cashController->setParamToJrnEntity($data);
            // return $cashController->doInternalToDeposit($tP)->jsonSerialize();

            $service = new LnTxnService();
            $data['MAINPOSITION'] = 'CONT';
            $tP = $service->setParamToJrnEntity($data);

            $tP->setAddparams(['CONTACNTTYPE' => AccountTypeEnum::dp]);
            $txncode = 'ln902011';

            if ($lnAcnt['statusid'] != LnStatusCodeEnum::closed && $lnAcnt['statusid'] != LnStatusCodeEnum::new && $lnAcnt['statusid'] != LnStatusCodeEnum::approved) {
                if (isset($this->config['close_loan']) && ($this->config['close_loan'] == '1' || $this->config['close_loan'] == 1)) {
                    $closeAmount = $lnAcnt['princbal'] +
                        $lnAcnt['capbint'] +
                        $lnAcnt['capcint'] +
                        $lnAcnt['capfint'] +
                        $lnAcnt['baseint2cap'] +
                        $lnAcnt['comint2cap'] +
                        $lnAcnt['fineint2cap'] +
                        $lnAcnt['adjbint2cap'] +
                        $lnAcnt['adjcint2cap'] +
                        $lnAcnt['adjfint2cap'] +
                        $lnAcnt['ctacntno'] +
                        $lnAcnt['ctcomacntno'] +
                        $lnAcnt['ctfineacntno'] +
                        $lnAcnt['recbal'];

                    if ($data['txnamount'] >= $closeAmount) {
                        $txncode = 'ln902091';
                        $tP->setTxncode($txncode);
                        $tP->setTxnAmount($closeAmount);
                        $response = $service->loanClosePaymentTxn($tP)->jsonSerialize();
                    } else {
                        $txncode = 'ln902011';
                        $tP->setTxncode($txncode);
                        $response = $service->loanPaymentTxn($tP)->jsonSerialize();
                    }
                } else {
                    $txncode = 'ln902011';
                    $tP->setTxncode($txncode);
                    $response = $service->loanPaymentTxn($tP)->jsonSerialize();
                }

                EBarimtJob::dispatch($txncode, $response, auth()->user())->onQueue("sendVAT");
                return $response;
            } else {
                throw new MeException("RC000052", [
                    'status' => __('messages.' . LnStatusCodeEnum::toString($lnAcnt['statusid'])),
                    'acntno' => $lnAcnt['acntno']
                ]);
            }

        } catch (Exception $e) {
            throw $e;
        }
    }

    public function internalTxn($data)
    {
        try {
            $cashController = new IaTxnService();
            $tP = $cashController->setParamToJrnEntity($data);
            return $cashController->doInternalToInternal($tP)->jsonSerialize();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function storeCorporateGateway($data)
    {
        $push = new AdCorporateGateway();
        foreach ($push->getFillable() as $field) {
            if (array_key_exists($field, $data)) {
                $push->$field = $data[$field];
            }
        }
        $push->statusid = 1;
        $push->save();
        return $push;
    }

    /**
     * getRate
     *
     * @param  mixed $curcode
     * @param  mixed $rtypecode
     * @param  mixed $txndate
     * @param  mixed $side BUY|SELL Зарах болон авах ханшийн алийг нь буцаахаа шийднэ.
     * @return double
     */
    public function getRate($curcode, $rtypecode, $txndate = null, $side = null)
    {
        $ratetype = GPInstCurRate::where('rtypecode', $rtypecode)
            ->where('curcode', $curcode)
            ->where('instid', $this->instid)
            ->where('statusid', 1)->first();
        if (empty($ratetype)) {
            $this->error('RC000045', ['curcode' => $curcode]);
        }
        if ($side == 'BUY') {
            return $ratetype->buyrate;
        } else {
            return $ratetype->salerate;
        }
    }




    /**
     * Mobile app дээр ашиглаж байгаа
     * createTransaction - CGW руу хийх гүйлгээний бичилт
     *
     * @param  mixed $senddata [
     *  "journal_no": "string",
     *  "fromAccount": "string",
     *  "toAccount": "string",
     *  "toCurrency": "string",
     *  "toAccountName": "string",
     *  "toBank": "string"
     *  "amount": decimal,
     *  "description": "string",
     *  "currency": "string",
     *  "transferid":" string ",
     *  "system_date":" date",
     *  "uuid":" string"
     * ]
     * @return AdCgwTransaction
     */

    public function createTransaction($data)
    {

        $bankAccount = ApCustBankAccount::where('acnt_code', $data['toAccount'])
            ->where('statusid', '<>', -1)->first();

        $cgwTransaction = AdCgwTransaction::create([
            'jrno' => $data['journal_no'] ?? null,
            'from_account' => $data['fromAccount'] ?? null,
            'amount' => $data['amount'],
            'curcode' => $data['currency'],
            'description' => $data['description'] ?? null,
            'to_bank' => $data['toBank'] ?? "05",
            'to_account' => $data['toAccount'],
            'to_account_name' => $bankAccount->acnt_name ?? null,
            'transferid' => $data['transferid'] ?? null,
            'system_date' => $data['system_date'] ?? Carbon::now(),
            'uuid' => $data['uuid'] ?? '0',
            'source' => 1, // 1 - MeApp
            'statusid' => $data['statusid'],
            'instid' => $this->instid,
            'created_by' => $this->userid
        ]);
        return $cgwTransaction;
    }
}
