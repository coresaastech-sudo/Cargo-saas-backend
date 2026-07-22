<?php

namespace Modules\Ad\Http\Services;

use Exception;
use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdCreditInfoBuero;
use Modules\Gp\Entities\GPInstConst;
use Modules\Ad\Entities\AdCreditInfoBueroAction;
use Modules\Ad\Entities\Views\VwAdCreditBueroSendDetail;
use Modules\Ad\Entities\AdCreditInfoBueroDetail;
use Modules\Gp\Enums\SendZmsActionCodesEnum;
use Modules\Ad\Entities\AdCreditInfoBueroHist;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;
use Modules\Cr\Entities\CrCustRelation;
use Modules\Cr\Entities\CrCustShare;
use Modules\Cr\Entities\Views\VwCrCustAddress;
use Modules\Cr\Entities\CrCustAdd;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Entities\CrCustContact;
use Modules\Gp\Entities\GPInstCurRate;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GPInstAddField;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Enums\CreditInfoBueroTypeEnum;
use Modules\Gp\Jobs\SendBueroJob;
use Modules\Ln\Entities\LnAccount;
use Modules\Ln\Entities\LnAccountType;
use Modules\Ln\Entities\LnAccountMor;
use Modules\Ln\Entities\LnMor;
use Modules\Ln\Entities\LnAccountCust;
use Modules\Ln\Entities\LnNrs;
use Modules\Tr\Entities\LnTxn;
use Modules\Ln\Entities\LnMorAdd;
use Modules\Gp\Entities\GPInstAdd;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Ia\Entities\IaCtAccountAdd;
use Modules\Ln\Entities\LnMorOwner;
use Modules\Gp\Http\Services\CoreService;
use PDO;
use SimpleXMLElement;
use Modules\Gp\Entities\GPInstGp;

class AdCreditInfoBueroService
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    private $instid;
    private $userid;
    private $provider;
    private $adCreditInfoBueroHist;
    private $providerConfig;
    private $connection;
    private $acntno;
    private $inst;

    private $o_c_related_orgs = [];
    private $o_c_related_customers = [];
    private $o_c_coll_information = [];

    public function __construct($instid, $userid, $acntno = null)
    {
        $this->instid = $instid;
        $this->userid = $userid;
        $this->acntno = $acntno;

        $this->inst = GPInstList::where('id', $this->instid)->where('statusid', '<>', -1)->first();

        $this->adCreditInfoBueroHist = AdCreditInfoBueroHist::where('instid', $this->instid)->orderBy('lastexecuteddate', 'DESC')->first();

        $this->provider = VwGPProviderConf::where('code', '7')->where('instid', $instid)->where('statusid', '<>', -1)->first();
        if ($this->provider) {
            $this->providerConfig = json_decode($this->provider->config, true);

            $connConf = VwGPConnConf::where("id", $this->provider->connid)->where('instid', $instid)->where('statusid', '<>', -1)->first();
            if ($connConf) {
                $this->connection = json_decode($connConf->config, true);
            } else {
                throw new MeException("RC000174");
            }
        } else {
            throw new MeException("RC000173", [
                'inst' => $instid,
                'code' => '7'
            ]);
        }
    }

    public function post($data, $AC = 'in9410')
    {
        // $AC = 'in9410'; // Лавлагаа авах
        if ($AC == 'in9410') {
            $data['acnttypeid'] = $this->providerConfig['acnttypeid'] ?? '01';
        }
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();
        $r->userid = $this->userid;
        $r->instid = $this->instid;
        $r->url = $this->connection['url'] . '/api/v2/process';
        $r->method = 'POST';
        $r->AC = $AC;
        $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
        $r->save();
        try {
            $response = Http::withHeaders(
                [
                    'X-Username' => $this->providerConfig['username'],
                    'X-Signature' => safeDecrypt($this->provider['sec1']),
                    'Content-Type' => 'application/json',
                    'AC' => $AC
                ]
            )->timeout(120)->post($this->connection['url'] . '/api/v2/process', $data);
        } catch (Exception $ex) {
            Log::debug($ex);
            $r->response = $ex->getMessage();
            $r->responsecode = 500;
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();
            throw $ex;
        }

        if ($response->status() == 200) {
            $data = json_decode((string) $response->body(), true);
        } else {
            $data = $response->body();
        }
        $r->response = @json_encode($data, JSON_UNESCAPED_UNICODE);
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();
        if ($response->status() != 200) {
            throw new MeException($data);
        }
        return $data;
    }

    public function sendApprovedLoan($acntno)
    {
        $this->acntno = $acntno;

        $loanaccount = LnAccount::where('acntno', $acntno)
            ->where('instid', $this->instid)
            ->where('statusid', '!=', 5)
            ->first();

        if (!$loanaccount) {
            throw new MeException("RC000011");
        }

        $isapploan = CoreService::sendApprovedLoan();

        if ($isapploan === 0 || $isapploan === '0') {
            if ($loanaccount['statusid'] === 1) {
                throw new MeException("Батлагдсан зээл илгээх тохиргоо идэвхгүй байна");
            }
        }

        $isLine = LnAccountType::where('prodcode', $loanaccount['prodcode'])->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
        $detail = AdCreditInfoBueroDetail::where('custno', $loanaccount['custno'])->where('acntno', $loanaccount['acntno'])->where('instid', $this->instid)->where('statusid', '<>', -1)->first();


        $loantype = $loanaccount->purpcode . $loanaccount->lnsubtype;

        $type = CreditInfoBueroTypeEnum::loan;
        $lineType = null;
        if ($loanaccount->redrawlimit > 0 && $isLine->redraw == 1) {
            $type = CreditInfoBueroTypeEnum::line;
            $lineType = ($this->providerConfig['type'] ?? 'XML') == 'XML' ? '04' : '02';
        }

        $firstTxn = LnTxn::where('acntno', $acntno)
            ->where('instid', $this->instid)
            ->where('txncode', 'ln800001')
            ->where('corr', 0)
            ->orderBy('txndate', 'asc')
            ->first();

        if ($firstTxn && $firstTxn->postdate) {
            $postdate = Carbon::parse($firstTxn->postdate);
            $advdate = Carbon::parse($loanaccount->advdate);

            $starteddate = $advdate->setTime(
                $postdate->hour,
                $postdate->minute,
                $postdate->second
            );
        } else {
            $starteddate = Carbon::parse($loanaccount->begdate);
        }

        $action = 'add';

        if ($detail) {
            $this->sendData(1, $acntno);
        } else {
            /// LOAN CONTRACT UUSGEH HAMGIIN CHUHAL
            /// Байгууллага регистер + custno + acntno;

            $loan_contract_no = $this->inst->regno . $loanaccount['custno'] . $loanaccount['acntno'];

            AdCreditInfoBueroDetail::create([
                'type' => $type,
                'loan_contract_date' => $loanaccount['created_at'],
                'loan_contract_no' => $loan_contract_no,
                'loan_contract_change_reason' => '',
                'loan_paid_date' => $loanaccount['closeddate'],
                'loan_decide_status' => null,
                'loan_int_balance' => $loanaccount['capbint'],
                'loan_additional_int_balance' => intval($loanaccount['capfint']) > 0 ? $loanaccount['capfint'] : 0,
                'loan_additional_interest' => $loanaccount['intratefine'] ?? 0,
                'action' => $action,
                'custno' => $loanaccount['custno'],
                'acntno' => $loanaccount['acntno'],
                'status' => '01',
                'advamount' => $loanaccount['approvamount'],
                'starteddate' => $starteddate,
                'expiredate' => $loanaccount['enddate'],
                'curcode' => $loanaccount['curcode'],
                'balance' => $loanaccount['princbal'],
                'loanprovenance' => '02',
                'interestinperc' => round(floatval($loanaccount['intrate'] ?? 0), 2),
                'loaninterest' => round(floatval($loanaccount['intrate'] ?? 0), 2),
                'commissionperc' => round(floatval($loanaccount['intratecom'] ?? 0), 2),
                'sectorcode' => $loanaccount['purpcode'] . $loanaccount['subpurpcode'],
                'fee' => 0,
                'loanclasscode' => '0' . $loanaccount['clscode'],
                'loanintype' => $loantype,
                'linetype' => $lineType,
                'isapproved' => 1,
                'statusid' => 1,
                'instid' => $this->instid,
                'created_at' => getNow(),
                'created_by' => auth()->user()->id,
            ]);
            $this->sendData(1, $acntno);
        }

        return ["status" => 'success'];
    }

    public function sendData($addjob = 0, $acntno = null, )
    {
        $this->generateBueroDetails($acntno);
        // Select distinct customer numbers from credit bureau details
        $sql = AdCreditInfoBueroDetail::select('ad_credit_info_buero_detail.custno', 'ad_credit_info_buero_detail.acntno')
            // Join with view to get additional credit bureau details
            ->join(
                'vw_ad_credit_buero_send_detail',
                'vw_ad_credit_buero_send_detail.acntno',
                '=',
                'ad_credit_info_buero_detail.acntno'
            )
            ->whereIn('ad_credit_info_buero_detail.statusid', [1, 3, 4])
            ->where('ad_credit_info_buero_detail.instid', $this->instid)
            ->where('vw_ad_credit_buero_send_detail.instid', $this->instid);

        if ($acntno) {
            $sql = $sql->where('ad_credit_info_buero_detail.acntno', $acntno);
        } else {
            $isSendBadLoan = CoreService::sendBadLoanCategoryRegistry();

            if ($isSendBadLoan == 0) {
                $sql = $sql->where(function ($query) {
                    $query->where('ad_credit_info_buero_detail.loanclasscode', '01')
                        ->orWhere('ad_credit_info_buero_detail.balance', '!=', 0);
                });
            }
        }

        $bueroDetails = $sql->get();

        if ((@$this->providerConfig['disable_job'] ?? 0) != 1 && $addjob == 1) {
            foreach ($bueroDetails as $key => $bueroDetail) {
                SendBueroJob::dispatch(
                    $this->instid,
                    $bueroDetail->custno,
                    $this->userid,
                    (($key + 1) * 100) / count($bueroDetails),
                    $bueroDetail->acntno,
                )->onQueue('sendZMS');
            }
        }
    }

    public function upload($custno, $acntno)
    {

        if (isset($this->providerConfig['type'])) {
            if ($this->providerConfig['type'] == "XML") {
                $this->uploadXml($custno, $acntno);
            } else {
                $this->uploadJson($custno);
            }
        } else {
            $this->uploadXml($custno, $acntno);
        }
    }

    public function uploadJson($custno)
    {
        $vwcust = VwCrCustList::where('custno', $custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();

        if (isset($vwcust)) {
            if ($vwcust->custtypecode == 0) {
                $cust = CrCustInd::where('custno', $custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
            } else {
                $cust = CrCustOrg::where('custno', $custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
            }
        }
        $this->inst = GPInstList::where('id', $this->instid)->where('statusid', '<>', -1)->first();

        $action = $this->getAction('customer_data', $cust->id, $cust->id1, $cust->id1);

        if ($action == 'add') {
            try {
                $res = $this->post(['o_c_regnum' => $cust->id1], 'fp2515');

                if ($res['response_code'] == 'SR0000') {
                    $action = 'update';
                    $c_civil_id = @$res['response']['c_civil_id'];
                    if ($c_civil_id) {
                        $cust->update(['id2' => $c_civil_id, 'id2typecode' => '999999999999']);
                        $cust->id2 = $c_civil_id;
                        $cust->id2typecode = '999999999999';
                    }
                } else {
                    $action = 'add';
                }
            } catch (Exception $ex) {
                Log::error($ex);
            }
        }


        if (isset($cust)) {
            $adCreditInfoBuero = AdCreditInfoBuero::create([
                'custno' => $cust->custno,
                'datapackageno' => '',
                'request' => "",
                'response' => "",
                'statusid' => 0,
                'type' => "JSON",
                'instid' => $this->instid,
                'created_by' => $this->userid,
                'acntno' => $this->acntno,
            ]);
            // datapackageno үүсгэв.
            $length = 20;

            $datapackageno = str_pad($adCreditInfoBuero->id, $length, '0', STR_PAD_LEFT);

            // generateJSON дотор шиддэг бүх алдааг (жнь EBARIMT-ээс олдоогүй
            // MeException) барьж response талбарт JSON хэлбэрээр хадгална.
            // Ингэснээр ажиллагаа зогсохгүй бөгөөд ажилтан алдааг харж засах боломжтой.
            try {
                $data = $this->generateJSON($action, $cust, $this->inst, $vwcust->custtypecode, $datapackageno, $adCreditInfoBuero->id);
            } catch (\Throwable $ex) {
                Log::error('generateJSON алдаа', [
                    'instid' => $this->instid,
                    'custno' => $cust->custno ?? null,
                    'acntno' => $this->acntno,
                    'buero_id' => $adCreditInfoBuero->id,
                    'error' => $ex->getMessage(),
                ]);

                $errorPayload = [
                    'success' => false,
                    'stage'   => 'generateJSON',
                    'error'   => $ex->getMessage(),
                    'action'  => $vwcust->custtypecode == 0 ? 'citizen' : 'entity',
                ];

                $adCreditInfoBuero->update([
                    'datapackageno' => $datapackageno,
                    'response'      => json_encode($errorPayload, JSON_UNESCAPED_UNICODE),
                    'statusid'      => 2, // буеро бичлэгийг "амжилтгүй" төлөвт
                    'updated_by'    => $this->userid,
                    'acntno'        => $this->acntno,
                ]);

                // Detail-ийг алдаатай төлөвт оруулна (3 = алдаа)
                AdCreditInfoBueroDetail::where('buero_id', $adCreditInfoBuero->id)
                    ->where('instid', $this->instid)
                    ->where('acntno', $this->acntno)
                    ->update(['statusid' => 3, 'updated_by' => $this->userid]);

                return; // илгээлгүй буцна
            }

            $adCreditInfoBuero->update([
                'datapackageno' => $datapackageno,
                'request' => $data,
                'updated_by' => $this->userid,
                'acntno' => $this->acntno,
            ]);

            $response = $this->sendBueroJSON($data, $vwcust->custtypecode == 0 ? "citizen" : "entity");

            $statusid = 1;
            if (@$response['success'] == true) {
                $statusid = 1;
                // Амжилттай мэдээ нийлүүлсэн үед add action-уудыг update болгох
                $this->updateUserAction($vwcust->id1, $this->acntno);

                // Хаагдсан зээл (status = '02') амжилттай мэдээлэгдсэн бол терминал
                // төлөв (5) болгож, дахин нийлүүлэх жагсаалтаас бүрмөсөн хасна.
                AdCreditInfoBueroDetail::where('acntno', $this->acntno)
                    ->where('instid', $this->instid)
                    ->where('status', '02')
                    ->update(['statusid' => 5, 'action' => 'update', 'updated_by' => $this->userid]);

                // Бусад (нээлттэй буюу status != '02', үүнд status NULL орно) зээл
                // амжилттай мэдээлэгдсэн бол 2 (нийлүүлсэн) төлөвт.
                // Тэмдэглэл: SQL-д `status <> '02'` нь status NULL мөрийг тааруулдаггүй
                // тул NULL-ийг тусад нь хамруулна (үгүй бол statusid 2 болж чадахгүй гацна).
                AdCreditInfoBueroDetail::where('acntno', $this->acntno)
                    ->where('instid', $this->instid)
                    ->where(function ($q) {
                        $q->where('status', '<>', '02')->orWhereNull('status');
                    })
                    ->update(['statusid' => 2, 'action' => 'update']);

                // Барьцаа/хамтран зээлдэгчийн төлөвийн hash-ийг шинэчилнэ. Дараагийн
                // generateBueroDetails-д энэ hash-аар БОДИТ өөрчлөлт байгаа эсэхийг
                // мэдэх тул updated_at touch-аас үл хамааран ажиллана.
                $this->refreshStateHashes($this->acntno);
            } else {
                $hasDBE1005 = false;
                $hasDBE1006 = false;

                if (!empty($response['errors']) && is_array(@$response['errors'])) {
                    $errors = array_values($response['errors']);
                    $hasDBE1005 = in_array('DBE1005', $errors);
                    $hasDBE1006 = in_array('DBE1006', $errors);

                    // Алдааны код тус бүрээс аль хэсэг (action) дээр алдсаныг таньж,
                    // ad_credit_info_buero_action дахь зөв мөрийг олж action-ийг засна.
                    $this->correctBueroActions($response['errors'], $vwcust);
                }

                // RTE1017 — талбарын түвшний баталгаажуулалтын алдаа. Энэ нь дата
                // өөрөө буруу (имэйл, регистр, төлбөрийн хуваарийн үлдэгдэл г.м) тул
                // action засаж зассан болохгүй. Аль талбар буруугаа бүтэцлэн логдож,
                // ажилтан дата засах боломжтой болгоно.
                if (!empty($response['validate']) && is_array(@$response['validate'])) {
                    Log::warning('Credit info buero validation errors (RTE1017)', [
                        'instid' => $this->instid,
                        'custno' => $vwcust->custno ?? null,
                        'regno' => $vwcust->id1 ?? null,
                        'acntno' => $this->acntno,
                        'buero_id' => $adCreditInfoBuero->id,
                        'validate' => $response['validate'],
                    ]);
                }

                $detail = AdCreditInfoBueroDetail::where('buero_id', $adCreditInfoBuero->id)
                    ->where('instid', $this->instid)
                    ->where('acntno', $this->acntno)
                    ->first();
                $isBalanceZero = $detail && (float)$detail->balance == 0;

                $detailStatusId = 3; // default нь алдаатай (statusid = 3)

                if ($hasDBE1005 || $hasDBE1006) {
                    $detailStatusId = $isBalanceZero ? 5 : 3;
                }

                $statusid = 2; // Буерогийн үндсэн бичлэгийг амжилтгүй төлөвт оруулна

                AdCreditInfoBueroDetail::where('buero_id', $adCreditInfoBuero->id)
                    ->where('instid', $this->instid)
                    ->where('acntno', $this->acntno)
                    ->update([
                        'statusid' => $detailStatusId
                    ]);
            }

            $adCreditInfoBuero->update([
                'response' => $response,
                'statusid' => $statusid,
                'updated_by' => $this->userid
            ]);
        }
    }

    public function uploadXml($custno, $acntno)
    {
        $vwcust = VwCrCustList::where('custno', $custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();

        if ($vwcust->custtypecode == 0) {
            $cust = CrCustInd::where('custno', $custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
        } else {
            $cust = CrCustOrg::where('custno', $custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
        }

        $inst = GPInstList::where('id', $this->instid)->first();

        $action = $this->getAction('customer_data', $cust->id, $cust->id1, $cust->id1);

        $adCreditInfoBuero = AdCreditInfoBuero::create([
            'custno' => $cust->custno,
            'datapackageno' => '',
            'acntno' => $acntno,
            'request' => "",
            'response' => "",
            'statusid' => 0,
            'type' => "XML",
            'instid' => $this->instid,
            'created_by' => $this->userid,
        ]);

        // datapackageno үүсгэв. 00001 -> 99999
        $length = 5;
        $adCreditbueroId = $adCreditInfoBuero->id;
        if ($adCreditbueroId > 99999) {
            $adCreditbueroId = $adCreditbueroId % 100000;
        }

        $datapackageno = str_pad($adCreditbueroId, $length, '0', STR_PAD_LEFT);

        $adCreditInfoBuero->update([
            'datapackageno' => $datapackageno,
        ]);

        $data = $this->generateXML($action, $cust, $inst, $vwcust->custtypecode, $datapackageno, $adCreditInfoBuero->id, $acntno);

        $adCreditInfoBuero->update([
            'request' => $data,
        ]);
        $response = $this->sendBuero($data);
        $responseBody = method_exists($response, 'body') ? $response->body() : (string) $response;

        $adCreditInfoBuero->update([
            'response' => $responseBody,
        ]);

        $resxml = null;
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $resxml = simplexml_load_string($responseBody);
        if ($resxml === false) {
            Log::error('Credit info buero invalid XML response', [
                'instid' => $this->instid,
                'custno' => $cust->custno ?? null,
                'acntno' => $acntno,
                'errors' => collect(libxml_get_errors())->map(function ($error) {
                    return trim($error->message);
                })->values()->all(),
                'response' => mb_substr($responseBody, 0, 1000),
            ]);
            $resxml = null;
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);


        $results = [];
        $i = 0;
        $successnum = 0;
        $statusid = 1;
        try {
            if ($resxml && $resxml->result == 'OK') {
                $this->updateUserAction($vwcust->id1, $this->acntno);

                // Зээлийн мэдээллийг жагсаалт хэлбэрт оруулав.
                foreach ($resxml->children() as $name => $value) {
                    if ($name == 'loanresult') {
                        $results[$i]['loanresult'] = (string) $value;
                    } elseif ($name == 'errors') {
                        $i--;
                        // errors доторх error элементийг авах
                        foreach ($value->error as $error) {
                            $results[$i]['error_code'] = (string) $error['code'];
                            $results[$i]['error_message'] = (string) $error;
                        }
                        $i++;
                    } elseif ($name == 'loancode') {
                        $results[$i]['loancode'] = (string) $value;
                        $i++;
                    }
                }


                foreach ($results as $result) {
                    if ($result['loanresult'] == "OK") {
                        $successnum++;

                        $adCreditInfoBueroDetail = AdCreditInfoBueroDetail::where('loancode', $result['loancode'])->orderBy('created_at', 'DESC')->where('instid', $this->instid)->first();
                        $adCreditInfoBueroDetail->update(['statusid' => 2, 'updated_by' => $this->userid, 'action' => 'update']);
                    } else {
                        $updateData = ['statusid' => 3, 'updated_by' => $this->userid];
                        if (isset($result['error_code']) && ($result['error_code'] == 'E32001')) {
                            $updateData['statusid'] = 2;
                        }

                        /// Зээл давхардсан байна гэсэн алдаа гарах үед тухайн зээлийн action -> update болгов.
                        if (isset($result['error_code']) && ($result['error_code'] == 'ME6013')) {
                            $updateData['statusid'] = 2;
                            $updateData['action'] = 'update';
                        }

                        $adCreditInfoBueroDetail = AdCreditInfoBueroDetail::where('loancode', $result['loancode'])->orderBy('created_at', 'DESC')->where('instid', $this->instid)->first();
                        if ($adCreditInfoBueroDetail) {
                            $adCreditInfoBueroDetail->update($updateData);
                        }
                    }
                }
            } else {
                $statusid = 2;
                if ($resxml) {
                    foreach ($resxml->children() as $name => $value) {
                        $errorCode = (string) $value->error['code'];
                        if ($errorCode == 'ME6015') {
                            $action = $this->getAction('customer_data', $cust->id, $cust->id1, $cust->id1, null, 'add');
                        } else if ($errorCode == 'ME4030') {
                            $action = $this->getAction('customer_data', $cust->id, $cust->id1, $cust->id1, null, 'update');
                        }
                    }

                    $customercode = (string) $resxml->customercode;

                    if (!empty($customercode)) {
                        $adCreditInfoBueroDetail = AdCreditInfoBueroDetail::where('loancode', 'like', '%' . $customercode . '%')->orderBy('created_at', 'DESC')->where('instid', $this->instid)->first();
                        if ($adCreditInfoBueroDetail) {
                            $adCreditInfoBueroDetail->update(['statusid' => 3, 'updated_by' => $this->userid]);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            Log::error($ex);
            $statusid = 2;
        }



        $adCreditInfoBuero->update([
            'datapackageno' => $datapackageno,
            'request' => $data,
            'response' => $responseBody,
            'totalnum' => count($results),
            'successnum' => $successnum,
            'statusid' => $statusid,
            'instid' => $this->instid,
            'updated_by' => $this->userid
        ]);
    }


    public function sendBuero($data)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();

        try {
            $r->userid = $this->userid;
            $r->instid = $this->instid;
            $r->url = $this->connection['url'];
            $r->method = 'POST';
            $r->request = $data;
            $r->save();

            $response = Http::withHeaders(
                [
                    'X-Username' => $this->providerConfig['username'],
                    'X-Signature' => safeDecrypt($this->provider['sec1']),
                    'Content-Type' => 'text/xml'
                ]
            )->withBody($data, 'text/xml')->timeout(300)->post($this->connection['url']);


            $r->response = (string) $response->getBody();
            $r->responsecode = $response->status();
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();

            return $response;
        } catch (\Exception $ex) {
            $r->response = $ex->getMessage();
            $r->responsecode = 500;
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();
            throw new MeException('RC000218');
        }
    }


    public function sendBueroJSON($data, $custtype = "entity")
    {
        $suburl = '/upload-entity';
        if ($custtype == "citizen") {
            $suburl = '/upload-citizen';
        }
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();

        try {
            $r->userid = $this->userid;
            $r->instid = $this->instid;
            $r->url = $this->connection['url'];
            $r->method = 'POST';
            $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
            $r->save();

            $response = Http::withHeaders(
                [
                    'X-Username' => $this->providerConfig['username'],
                    'X-Signature' => safeDecrypt($this->provider['sec1']),
                    'Content-Type' => 'application/json'
                ]
            )->post($this->connection['url'] . $suburl, $data);
            $r->response = (string) $response->getBody();
            $r->responsecode = $response->status();
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();
            return $response;
        } catch (\Exception $ex) {
            $r->response = $ex->getMessage();
            $r->responsecode = 500;
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();
            throw new MeException('RC000218');
        }
    }

    public function generateBueroDetails($acntno = null)
    {
        $changedSet = [];
        $date = null;
        if (isset($this->providerConfig['history_date'])) {
            $date = Carbon::parse($this->providerConfig['history_date']);
        } else {
            if (isset($this->adCreditInfoBueroHist)) {
                $date = Carbon::parse($this->adCreditInfoBueroHist->lastexecuteddate);
            }
        }

        $sql = VwAdCreditBueroSendDetail::where('instid', $this->instid)->whereIn('statusid', [0, 2, 3, 4, 8, 9])->whereNotNull('advdate');

        if (isset($acntno)) {
            $sql = $sql->where('acntno', $acntno);
        } else {
            // 0) Барьцаа/хамтран зээлдэгчийн БОДИТ state өөрчлөгдсөн зээлийг
            //    state hash-аар илрүүлж 2→4 болгоно. Legacy (hash NULL) detail-уудыг
            //    flip хийхгүй, зөвхөн backfill хийнэ — өмнө амжилттай нийлүүлсэн
            //    зээлүүд бөөнөөр нь дахин илгээгдэхгүйн баталгаа.
            $this->applyStateHashFlip();

            // 1) Дахин илгээх хүлээгдэж буй (шинэ/алдаатай/өөрчлөгдсөн) detail-тэй зээлүүд.
            //    (applyStateHashFlip дуудсаны дараа тул шинээр 4 болсон нь энд багтана.)
            $pendingAcntnos = AdCreditInfoBueroDetail::where('instid', $this->instid)
                ->whereIn('statusid', [1, 3, 4])
                ->pluck('acntno')
                ->unique()
                ->toArray();

            // 2) Амжилттай илгээгдсэн (statusid = 2) атлаа одоогийн утга нь сүүлд мэдээ
            //    нийлүүлсэн утгаас ӨӨРЧЛӨГДСӨН зээлүүдийг DB талд (view vs detail diff)
            //    хурдан илрүүлнэ. Энэ нь superset — PHP доорх isDirty эцэслэн шалгана.
            $changedAcntnos = DB::table('vw_ad_credit_buero_send_detail as v')
                ->join('ad_credit_info_buero_detail as d', function ($j) {
                    $j->on('d.acntno', '=', 'v.acntno')->on('d.instid', '=', 'v.instid');
                })
                ->where('v.instid', $this->instid)
                ->whereIn('v.statusid', [0, 2, 3, 4, 8, 9])
                ->whereNotNull('v.advdate')
                ->where('d.statusid', 2)
                ->where(function ($q) {
                    $q->whereRaw("round(coalesce(case when coalesce(v.princbal,0)=0 and not (coalesce(v.redrawlimit,0)>0 and v.redraw=1) then 0.1 else v.princbal end,0)::numeric,2) is distinct from round(coalesce(d.balance,0)::numeric,2)")
                        ->orWhereRaw("(case when v.statusid in (0,9) then '02' else '01' end) is distinct from nullif(trim(coalesce(d.status,'')),'')")
                        ->orWhereRaw("v.closeddate::date is distinct from d.loan_paid_date::date")
                        ->orWhereRaw("v.enddate::date is distinct from d.expiredate::date")
                        ->orWhereRaw("(coalesce(v.purpcode,'')||coalesce(v.subpurpcode,'')) is distinct from trim(coalesce(d.sectorcode,''))")
                        ->orWhereRaw("('0'||trim(v.clscode::text)) is distinct from trim(coalesce(d.loanclasscode,''))")
                        ->orWhereRaw("round(coalesce(v.intrate,0)::numeric,2) is distinct from round(coalesce(d.loaninterest,0)::numeric,2)")
                        ->orWhereRaw("round(coalesce(v.intratecom,0)::numeric,2) is distinct from round(coalesce(d.commissionperc,0)::numeric,2)")
                        ->orWhereRaw("round(coalesce(v.intratefine,0)::numeric,2) is distinct from round(coalesce(d.loan_additional_interest,0)::numeric,2)")
                        ->orWhereRaw("(case when coalesce(v.capbint,0)::int>0 then round(coalesce(v.capbint,0)::numeric,2) else 0 end) is distinct from round(coalesce(d.loan_int_balance,0)::numeric,2)")
                        ->orWhereRaw("(case when coalesce(v.capfint,0)::int>0 then round(coalesce(v.capfint,0)::numeric,2) else 0 end) is distinct from round(coalesce(d.loan_additional_int_balance,0)::numeric,2)");
                })
                ->pluck('v.acntno')
                ->toArray();

            // Хоёуланг нэгтгэж, updated_at/гүйлгээнээс үл хамааран үргэлж сонгоно.
            $pendingAcntnos = array_values(array_unique(array_merge($pendingAcntnos, $changedAcntnos)));

            // statusid = 2 → 4 шилжилтийг зөвхөн SQL diff баталсан зээлд хийнэ
            // (PHP isDirty нь огноо/precision-ийн false positive-той тул найдваргүй).
            $changedSet = array_flip($changedAcntnos);

            if (isset($date)) {
                // config  дээр тохиргоо огноо оруулаагүй тохиолдолд сүүлчийн огноогоос хойш гүйлгээнүүдээс шүүж үзнэ
                $useTxnFilter = !isset($this->providerConfig['history_date']);
                $txnAccounts = collect();

                if ($useTxnFilter) {
                    $txnCodes = SendZmsActionCodesEnum::getValues();

                    $txnAccounts = LnTxn::where('instid', $this->instid)->where('created_at', '>=', $date)->where('statusid', '<>', -1)->whereIn('txncode', $txnCodes)->distinct()->pluck('acntno');

                    $new_accounts = VwAdCreditBueroSendDetail::where('instid', $this->instid)
                        ->whereIn('statusid', [0, 2, 3, 4, 8, 9])
                        ->whereNotNull('advdate')
                        ->get();

                    $created_acntnos = AdCreditInfoBueroDetail::where('instid', $this->instid)
                        ->pluck('acntno');

                    $new_accounts_except_created = $new_accounts->whereNotIn('acntno', $created_acntnos);

                    $txnAccounts = $txnAccounts->merge($new_accounts_except_created->pluck('acntno')->toArray());
                }

                $sql = $sql->where(function ($q) use ($date, $useTxnFilter, $txnAccounts, $pendingAcntnos) {
                    $q->where(function ($q2) use ($date, $useTxnFilter, $txnAccounts) {
                        if ($useTxnFilter) {
                            $q2->whereIn('acntno', $txnAccounts->all());
                        }
                        $q2->whereDate('updated_at', '>=', $date);
                    });

                    // Дахин илгээх хүлээгдэж буй зээлүүдийг (огнооноос үл хамааран) оруулна.
                    if (!empty($pendingAcntnos)) {
                        $q->orWhereIn('acntno', $pendingAcntnos);
                    }
                });
            } elseif (!empty($pendingAcntnos)) {
                $sql = $sql->whereIn('acntno', $pendingAcntnos);
            }
        }

        $loanaccounts = $sql->get();

        if (isset($loanaccounts)) {
            foreach ($loanaccounts as $loanaccount) {
                $sendBuero = true;

                if (@$this->providerConfig['is_test'] == 1 && !in_array($loanaccount['acntno'], @$this->providerConfig['test_account'] ?? [])) {
                    $sendBuero = false;
                }

                if ($sendBuero) {
                    $action = 'add';

                    // advdate + postdate Time  = advdate
                    $postdate = Carbon::parse($loanaccount->postdate ?? now());
                    $dateString = ($loanaccount->advdate ?? now()->format('Y-m-d')) . ' ' . $postdate->format('H:i:s');
                    $date = Carbon::parse($dateString);

                    $contractdate = $loanaccount->approvedate ? Carbon::parse($loanaccount->approvedate) : null;

                    $loantype = ($loanaccount->lntype ?? '') . ($loanaccount->lnsubtype ?? '');

                    $status = '01'; // Annex 6 - 01 Нээлттэй

                    $type = CreditInfoBueroTypeEnum::loan;
                    $lineType = "04";
                    if ($loanaccount->redrawlimit > 0 && $loanaccount->redraw == 1) {
                        $type = CreditInfoBueroTypeEnum::line;
                        $lineType = ($this->providerConfig['type'] ?? 'XML') == 'XML' ? '04' : '02';
                    }

                    /// TODO Зээл хаах үед тусгай flag шалгаж гүйлгээ хийсний дараа дараах төрлүүдийг ашиглах
                    // 04	Шүүхийн шийдвэрээр төлөгдөж дууссан
                    // 05	Шүүхийн шийдвэр гүйцэтгэлээр төлөгдөж дууссан
                    // 06	Шинэ зээлрүү шилжсэн
                    $loan_decide_status = null;


                    // Зээл хаагдсан эсвэл худалдагдсан хаагдсан төлөвтэй байвал үлдэгдэлийг 0 болгож илгээнэ.
                    if ($loanaccount['statusid'] == 0 || $loanaccount['statusid'] == 9) {
                        $status = '02'; // Annex 6 - 02 Төлөгдөж дууссан

                        $cls = (int) $loanaccount['clscode'];

                        if ($cls === 1) {
                            $loan_decide_status = '02'; // Хэвийн төлөгдсөн
                        } else if (in_array($cls, [2, 3, 4, 5])) {
                            $loan_decide_status = '03'; // Хугацаа хэтэрч төлөгдсөн
                        } else {
                            // clscode = 0 (ангилалгүй) эсвэл бусад тохиолдолд хаагдсан
                            // зээлд decide_status заавал байх ёстой тул хэвийн төлөгдсөн гэж үзнэ.
                            // Үгүй бол ZMS рүү хоосон явж LIE1041 алдаа гарна.
                            $loan_decide_status = '02'; // Хэвийн төлөгдсөн
                        }
                    } else {
                        // Зээлийн данс хаасан төлөв оруулаагүй үлдэгдэл 0 болсон үед 0.1 хөгвүүлж зээлийн хаахгүй болгоно
                        if ($loanaccount['princbal'] == 0 && $type == CreditInfoBueroTypeEnum::loan) {
                            $loanaccount['princbal'] = 0.1;
                        }
                    }

                    $advamount = 0;
                    if (@$this->providerConfig['useAdvAmount'] == '1') {
                        if ($type == CreditInfoBueroTypeEnum::line) {
                            $advamount = (($loanaccount['approvamount'] != 0 ? $loanaccount['approvamount'] : $loanaccount['advamount']) ?? 0);
                        } else {
                            $advamount = (($loanaccount['advamount'] != 0 ? $loanaccount['advamount'] : $loanaccount['approvamount']) ?? 0);
                        }
                    } else {
                        $advamount = (($loanaccount['approvamount'] != 0 ? $loanaccount['approvamount'] : $loanaccount['advamount']) ?? 0);
                    }

                    $detail = AdCreditInfoBueroDetail::where('custno', $loanaccount['custno'])->where('acntno', $loanaccount['acntno'])->where('instid', $this->instid)->where('statusid', '<>', -1)->first();

                    // Хаагдсан зээл амжилттай мэдээлэгдсэн (терминал төлөв 5) бол
                    // дахин боловсруулж, нийлүүлэх жагсаалтад оруулахгүй.
                    if ($detail && $detail->statusid == 5) {
                        continue;
                    }

                    if ($detail) {
                        $action = 'update';
                        $detail->balance = $loanaccount['princbal'];
                        $detail->loan_paid_date = $loanaccount['closeddate'];
                        $detail->timestoloan = $loanaccount['lonnum'];
                        $detail->status = $status;

                        if ($advamount != 0) {
                            $detail->advamount = $advamount;
                        }
                        $detail->expiredate = $loanaccount['enddate'];
                        $detail->sectorcode = $loanaccount['purpcode'] . $loanaccount['subpurpcode'];
                        $detail->loanclasscode = '0' . $loanaccount['clscode'];
                        // $detail->loanintype = $loantype;
                        $detail->loaninterest = round(floatval($loanaccount['intrate'] ?? 0), 2);
                        $detail->interestinperc = round(floatval($loanaccount['intrate'] ?? 0), 2);
                        $detail->commissionperc = round(floatval($loanaccount['intratecom'] ?? 0), 2);
                        $detail->loan_int_balance = intval($loanaccount['capbint']) > 0 ? round(floatval($loanaccount['capbint'] ?? 0), 2) : 0;
                        $detail->loan_additional_int_balance = intval($loanaccount['capfint']) > 0 ? round(floatval($loanaccount['capfint'] ?? 0), 2) : 0;
                        $detail->loan_additional_interest = round(floatval($loanaccount['intratefine'] ?? 0), 2);
                        $detail->linetype = $lineType;
                        $detail->loan_decide_status = $loan_decide_status;

                        // statusid = 2 (амжилттай нийлүүлсэн) зээлийг зөвхөн үнэхээр
                        // өөрчлөгдсөн (SQL diff баталсан) эсвэл гар аргаар дахин илгээх
                        // (acntno шууд дамжсан) үед л дахин илгээх төлөвт (4) оруулна.
                        if ($detail->statusid == 2 && (isset($acntno) || isset($changedSet[$loanaccount['acntno']]))) {
                            $detail->statusid = 4;
                        }

                        $detail->save();
                    } else {

                        // $loan_contract_no = \6075657 0701 ЛЮ69053075 20251213115522";

                        /// LOAN CONTRACT UUSGEH HAMGIIN CHUHAL
                        /// Байгууллага регистер + custno + acntno;

                        $loan_contract_no = $this->inst->regno . $loanaccount['custno'] . $loanaccount['acntno'];
                        if ($advamount != 0) {
                            AdCreditInfoBueroDetail::create([
                                'type' => $type,
                                'loan_contract_date' => $contractdate, //
                                'loan_contract_no' => $loan_contract_no,
                                'loan_contract_change_reason' => '',
                                'loan_paid_date' => $loanaccount['closeddate'],
                                'loan_decide_status' => $loan_decide_status,
                                'loan_int_balance' => $loanaccount['capbint'],
                                'loan_additional_int_balance' => intval($loanaccount['capfint']) > 0 ? $loanaccount['capfint'] : 0, //
                                'loan_additional_interest' => $loanaccount['intratefine'] ?? 0, //
                                'action' => $action,
                                'custno' => $loanaccount['custno'],
                                'acntno' => $loanaccount['acntno'],
                                'status' => $status,
                                'advamount' => $advamount,
                                'starteddate' => $date,
                                'expiredate' => $loanaccount['enddate'],
                                'curcode' => $loanaccount['curcode'],
                                'balance' => $loanaccount['princbal'],
                                'loanprovenance' => '02', // Annex 5 - 02 - Зээл авахаар өргөдөл гаргасан
                                'interestinperc' => round(floatval($loanaccount['intrate'] ?? 0), 2),
                                'loaninterest' => round(floatval($loanaccount['intrate'] ?? 0), 2),
                                'commissionperc' => round(floatval($loanaccount['intratecom'] ?? 0), 2),
                                'sectorcode' => $loanaccount['purpcode'] . $loanaccount['subpurpcode'],
                                'fee' => 0,
                                'loanclasscode' => '0' . $loanaccount['clscode'],
                                'loanintype' => $loantype,
                                'linetype' => $lineType,
                                'isapproved' => 1,
                                'statusid' => 1,
                                'instid' => $this->instid,
                                'created_by' => auth()->user()->id,
                            ]);
                        }
                    }
                }
            }
        }

        if (!isset($acntno) && @$this->providerConfig['is_test'] != 1) {
            AdCreditInfoBueroHist::create([
                'lastexecuteddate' => Carbon::now(),
                'instid' => $this->instid,
                'created_by' => $this->userid,
            ]);
        }
    }


    public function generateJSON($custaction, $cust, $inst, $custtypecode, $datapackageno, $buero_id)
    {
        $data = [];
        $customer = [];

        $data['patch_number'] = $datapackageno;
        $data['data_provider_regnum'] = $inst->regno;

        // Санамж: Монгол банк зөвлөмж data_provider_branch: 001+байгууллагын регистр
        $data['data_provider_branch'] = '001' . $inst->regno;

        /// Customer үндсэн мэдээлэл үүсгэх
        $customer = $this->generateCustDetail($custtypecode, $cust, $custaction);

        // Зээлийн данс нэмэх
        $loanaccounts = AdCreditInfoBueroDetail::where('custno', $cust->custno)->whereIn('type', [CreditInfoBueroTypeEnum::loan, CreditInfoBueroTypeEnum::line])->where('instid', $this->instid)->whereIn('statusid', [1, 3, 4]);

        if (isset($this->acntno)) {
            $loanaccounts = $loanaccounts->where('acntno', $this->acntno);
        }

        $loanaccounts = $loanaccounts->get();

        $loans = collect();
        $lines = collect();
        // $o_c_coll_information = collect();

        foreach ($loanaccounts as $loanaccount) {
            // Loancode үүсгэв
            $loancode = $loanaccount->loanintype . preg_replace("/[^0-9]/", "", $loanaccount->starteddate);

            $loanaccount->update([
                'loancode' => $loancode,
                'buero_id' => $buero_id
            ]);

            $loanDetail = $this->generateLoan($loanaccount, $cust, $custtypecode);

            // Шугмын зээлийн данс нэмэх
            if ($loanaccount['type'] == CreditInfoBueroTypeEnum::line) {
                $curRate = GPInstCurRate::where('rtypecode', "1")->where('curcode', $loanaccount->curcode)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();

                $loanDetail['loan']['o_c_loan_line_contractno'] = $loanDetail['loan']['o_c_loan_contractno'];
                $loanlineaction = $loanDetail['loan']['action'] ?? $loanaccount->action;

                $line = [
                    "action" => $loanlineaction,
                    "o_c_loanline_contract_date" => Carbon::parse($loanaccount->loan_contract_date)->format('Y-m-d'),
                    "o_c_loanline_contractno" => $loanDetail['loan']['o_c_loan_contractno'],
                    "o_c_loanline_contract_change_reason" => $loanaccount->loan_contract_change_reason,
                    "o_c_loanline_type" => $loanaccount->linetype,
                    "o_c_loanline_amount_lcy" => number_format($loanaccount->advamount, 2, '.', ''),
                    "o_c_loanline_amount_fcy" => number_format($loanaccount->advamount, 2, '.', ''),
                    "o_c_loanline_starteddate" => $loanaccount->starteddate,
                    "o_c_loanline_expdate" => $loanaccount->expiredate,
                    "o_c_loanline_currency" => $loanaccount->curcode,
                    "o_c_loanline_currency_rate" => number_format($curRate->buyrate, 2, '.', ''),
                    "o_c_loanline_sector" => $loanaccount->sectorcode,
                    "o_c_loanline_interest_rate" => number_format($loanaccount->loaninterest, 2, '.', ''),
                    "o_c_loanline_commitment_interest_rate" => number_format($loanaccount->commissionperc, 2, '.', ''),
                    "o_c_loanline_balance_lcy" => number_format($loanaccount->balance, 2, '.', ''),
                    "o_c_loanline_balance_fcy" => number_format($loanaccount->balance, 2, '.', ''),
                    "o_c_loanline_paiddate" => $loanaccount->loan_paid_date,
                    "o_c_loanline_status" => $loanaccount->status,
                    "o_c_loanline_description" => $loanaccount->status,
                    // "o_c_loanline_collateral_indexes" => $loanDetail['loan']['o_c_loan_collateral_indexes'] ?? collect()
                ];

                $lines->push($line);
            }

            $loans->push($loanDetail['loan']);
        }

        AdCreditInfoBuero::where('id', $buero_id)->where('statusid', '<>', -1)->where('instid', $inst->id)->update(['totalnum' => count($loanaccounts ?? [])]);

        $customer['o_c_loanline'] = $lines;
        $customer['o_c_loan_information'] = $loans;
        $customer['o_c_related_customer'] = $this->o_c_related_customers;
        $customer['o_c_related_org'] = $this->o_c_related_orgs;
        $customer["o_c_coll_information"] = $this->o_c_coll_information;

        $customers = collect();

        $customers->push($customer);
        $data['customer_data'] = $customers;

        return $data;
    }

    public function generateXML($custaction, $cust, $inst, $custtypecode, $datapackageno, $buero_id, $acntno)
    {

        try {

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><customers/>');

            $customer = $xml->addChild('customer');
            $customer->addAttribute('action', $custaction);
            $xml->addChild('datapackageno', $datapackageno);
            // Хэрэглэгчийн мэдээлэл
            $o_c_customer_information = $customer->addChild('o_c_customer_information');
            $o_c_customer_information->addAttribute('action', $custaction);

            $isforeign = 0;

            if ($custtypecode == 0) {
                // Иргэн
                $mobileno = $cust->handphone;

                if ($cust->id1typecode == 'YY99999999') {
                    $isforeign = 1;
                    $customercode = "01" . $cust->id1;
                } else {
                    $isforeign = 0;
                    $customercode = "02" . $cust->id1;
                }
            } else {
                // Байгууллага
                $mobileno = $cust->workphone;
                if ($cust->countrycode == 496) {
                    $isforeign = 1;
                }
                $customercode = '03' . $cust->id1; // 03 - Бизнесийн байгууллага
            }

            $address = "";
            $custaddr = VwCrCustAddress::where('custid', $cust->id)->where('custtypecode', $custtypecode)->where('addrtypecode', '1')->where('statusid', '<>', -1)->first();
            if (isset($custaddr)) {
                $address = $custaddr->state_name . " " . $custaddr->region_name . " " . $custaddr->sub_region_name . " " . $custaddr->address;
            } else {
                $custaddr = VwCrCustAddress::where('custid', $cust->id)->where('custtypecode', $custtypecode)->where('statusid', '<>', -1)->first();
                if (isset($custaddr)) {
                    $address = $custaddr->state_name . " " . $custaddr->region_name . " " . $custaddr->sub_region_name . " " . $custaddr->address;
                } else {
                    $address = "Хаяг";
                }
            }

            $o_c_customer_information->addChild('o_c_customercode', $customercode);
            $o_c_customer_information->addChild('o_c_bankCode', @$this->providerConfig['o_c_bankCode'] ?? $inst->regno);

            $o_c_customer_information->addChild('o_c_branchcode', @$this->providerConfig['o_c_branchcode'] ?? $cust->brchno);
            $o_c_customer_information->addChild('o_c_isorganization', $custtypecode == 1 ? 0 : 1);
            $o_c_customer_information->addChild('o_c_customername', $cust->name);
            $o_c_customer_information->addChild('o_c_birthdate', $cust->birthdate);
            $o_c_customer_information->addChild('o_c_registerno', $cust->id1);
            $o_c_customer_information->addChild('o_c_email', $cust->email);
            $o_c_customer_information->addChild('o_c_mobileno', $mobileno);
            $o_c_customer_information->addChild('o_c_isforeign', $isforeign);

            if ($custtypecode == 0) {
                $o_c_customer_information->addChild('c_lastname', $cust->lname);
            } else {
                $companycode = "";
                if ($cust->segcode == 23 || $cust->segcode == 25 || $cust->segcode == 31 || $cust->segcode == 71 || $cust->segcode == 73 || $cust->segcode == 75) {
                    $companycode = "01"; // ХК
                } else if ($cust->segcode == 24 || $cust->segcode == 26 || $cust->segcode == 32 || $cust->segcode == 72 || $cust->segcode == 74 || $cust->segcode == 76) {
                    $companycode = "02"; // ХХК
                } else if ($cust->segcode == 54 || $cust->segcode == 55) {
                    $companycode = "03"; // ХЗХ
                } else if ($cust->segcode == 33 || $cust->segcode == 94) {
                    $companycode = "04"; // Хоршоо
                } else if ($cust->segcode == 34) {
                    $companycode = "08"; //Нөхөрлөл
                } else {
                    $companycode = "08"; // Бусад
                }

                $o_c_customer_information->addChild('o_companytypecode', $companycode);
                $o_c_customer_information->addChild('o_c_president_family_firstname', $cust->dirname);
                $o_c_customer_information->addChild('o_c_president_family_lastname', $cust->dirlname);
                // Монгол улсын иргэн
                if ($cust->diridcode == "YY99999999") {
                    $o_c_customer_information->addChild('o_c_president_family_isforeign', 0);
                } else if ($cust->diridcode == "AAAAAAAAAAAAAAAA") {
                    $o_c_customer_information->addChild('o_c_president_family_isforeign', 1);
                }
                $o_c_customer_information->addChild('o_c_president_family_registerno', $cust->dirid);
            }

            $o_c_customer_information->addChild('o_c_address', $address);

            // Зээлийн мэдээлэл
            $o_c_onus_information = $customer->addChild('o_c_onus_information');
            // Барьцаа хөрөнгө

            $o_c_mortgage_information = $customer->addChild('o_c_mortgage_information');

            // Шугмын зээлийн данс нэмэх
            $lineaccounts = AdCreditInfoBueroDetail::where('custno', $cust->custno)->where('type', CreditInfoBueroTypeEnum::line)->where('instid', $this->instid)->whereIn('statusid', [1, 3, 4]);

            // Log::debug($acntno);
            // if (isset($this->acntno)) {
            $lineaccounts = $lineaccounts->where('acntno', $acntno);
            // }

            $lineaccounts = $lineaccounts->get();

            foreach ($lineaccounts as $lineaccount) {
                if ($lineaccount->advamount != 0) {
                    $loantype = "07";

                    // Loancode үүсгэв
                    $loancode = $loantype . $customercode . preg_replace("/[^0-9]/", "", $lineaccount->starteddate);
                    $lineaccount->update([
                        'loancode' => $loancode,
                        'buero_id' => $buero_id
                    ]);

                    $o_c_loanline = $o_c_onus_information->addChild('o_c_loanline');
                    $o_c_loanline->addAttribute('action', $lineaccount->action);

                    $o_c_loanline->addChild('o_c_loanline_acntno', $lineaccount->acntno);
                    $o_c_loanline->addChild('o_c_loanline_type', $lineaccount->linetype);
                    $o_c_loanline->addChild('o_c_loanline_status', $lineaccount->status);
                    $o_c_loanline->addChild('o_c_loanline_advamount', number_format($lineaccount->advamount, 2, '.', ''));
                    $o_c_loanline->addChild('o_c_loanline_starteddate', $lineaccount->starteddate);
                    $o_c_loanline->addChild('o_c_loanline_expdate', $lineaccount->expiredate);
                    $o_c_loanline->addChild('o_c_loanline_currencycode', $lineaccount->curcode);
                    $sectorcode = "";

                    if ($lineaccount->sectorcode) {
                        // Зээлийн зориулалт Монгол банкны шинэ протоколыг хуучин xml протокол руу хөрвүүлэв.
                        // value_add1 утгыг авч залгана.
                        // Example: M01 -> Q78
                        //          M02 -> Q79
                        //          M03 -> S80 байвал value_add2 талбарын утгыг ашиглана

                        $sectorPrefix = substr($lineaccount->sectorcode, 0, 1);
                        $sectorSuffix = substr($lineaccount->sectorcode, 1, 2);

                        $result = DB::table('GP_const as g1')
                            ->leftJoin('GP_const as g2', 'g2.parent_code', '=', 'g1.code')
                            ->where('g1.parent_code', 'loan_industry')
                            ->where('g1.value', $sectorPrefix)
                            ->where('g2.value', $sectorSuffix)
                            ->select('g1.value_add1 as level1_add', 'g2.value_add1 as level2_add', 'g2.value_add2 as value_add2')
                            ->first();

                        // Append the value_add1 fields if results are found
                        if ($result) {
                            $sectorcode .= $result->level1_add ?? '';
                            if ($result->value_add2 != null) {
                                $sectorcode = $result->value_add2 ?? '';
                            }
                            $sectorcode .= $result->level2_add ?? '';
                        }
                    }
                    $o_c_loanline->addChild('o_c_loanline_sectorcode', $sectorcode);
                    $o_c_loanline->addChild('o_c_loanline_loaninterest', $lineaccount->loaninterest);
                    $o_c_loanline->addChild('o_c_loanline_timestoloan', $lineaccount->timestoloan);

                    if ($lineaccount->extdate != null) {
                        $o_c_loanline->addChild('o_c_loanline_extdate', $lineaccount->extdate);
                    }

                    if ($lineaccount->loan_paid_date != null) {
                        $o_c_loanline->addChild('o_c_loanline_paiddate', $lineaccount->loan_paid_date);
                    }

                    $o_c_loanline->addChild('o_c_loanline_interestinperc', $lineaccount->interestinperc);
                    $o_c_loanline->addChild('o_c_loanline_commissionperc', $lineaccount->commissionperc);
                    $o_c_loanline->addChild('o_c_loanline_fee', $lineaccount->fee);
                    $o_c_loanline->addChild('o_c_loanline_loanclasscode', $lineaccount->loanclasscode);
                    $o_c_loanline->addChild('o_c_loanline_balance', number_format($lineaccount->balance, 2, '.', ''));
                    $o_c_loanline->addChild('o_c_loanline_isapproved', $lineaccount->isapproved);
                }
            }

            // Зээлийн данс нэмэх
            $loanaccounts = AdCreditInfoBueroDetail::where('custno', $cust->custno)->where('type', CreditInfoBueroTypeEnum::loan)->where('instid', $this->instid)->whereIn('statusid', [1, 3, 4]);
            $totalindex = 0;

            // Log::debug($acntno);
            // if (isset($this->acntno)) {
            $loanaccounts = $loanaccounts->where('acntno', $acntno);
            // }

            $loanaccounts = $loanaccounts->get();

            foreach ($loanaccounts as $loanaccount) {
                if ($loanaccount->advamount != 0) {
                    $loantype = "01";

                    // Loancode үүсгэв
                    $loancode = $loantype . $customercode . preg_replace("/[^0-9]/", "", $loanaccount->starteddate);

                    $loanaccount->update([
                        'loancode' => $loancode,
                        'buero_id' => $buero_id
                    ]);

                    $o_c_loan_information = $o_c_onus_information->addChild('o_c_loan_information');
                    $o_c_loan_information->addAttribute('action', $loanaccount->action);

                    $o_c_loan_information->addChild('o_c_loan_acntno', $loanaccount->acntno);
                    $o_c_loan_information->addChild('o_c_loan_status', $loanaccount->status);
                    $o_c_loan_information->addChild('o_c_loan_balance', number_format($loanaccount->balance, 2, '.', ''));
                    $o_c_loan_information->addChild('o_c_loan_provideLoanSize', number_format($loanaccount->advamount, 2, '.', ''));
                    $o_c_loan_information->addChild('o_c_loan_loanProvenance', $loanaccount->loanprovenance);
                    $o_c_loan_information->addChild('o_c_loan_starteddate', $loanaccount->starteddate);
                    $o_c_loan_information->addChild('o_c_loan_expdate', $loanaccount->expiredate);
                    $o_c_loan_information->addChild('o_c_loan_currencycode', $loanaccount->curcode);
                    $sectorcode = "";
                    if ($loanaccount->sectorcode) {
                        $sectorPrefix = substr($loanaccount->sectorcode, 0, 1);
                        $sectorSuffix = substr($loanaccount->sectorcode, 1, 2);

                        $result = DB::table('GP_const as g1')
                            ->leftJoin('GP_const as g2', 'g2.parent_code', '=', 'g1.code')
                            ->where('g1.parent_code', 'loan_industry')
                            ->where('g1.value', $sectorPrefix)
                            ->where('g2.value', $sectorSuffix)
                            ->select('g1.value_add1 as level1_add', 'g2.value_add1 as level2_add', 'g2.value_add2 as value_add2')
                            ->first();

                        // Append the value_add1 fields if results are found
                        if ($result) {
                            $sectorcode .= $result->level1_add ?? '';
                            if ($result->value_add2 != null) {
                                $sectorcode = $result->value_add2 ?? '';
                            }
                            $sectorcode .= $result->level2_add ?? '';
                        }
                    }
                    $o_c_loan_information->addChild('o_c_loan_sectorcode', $sectorcode);
                    $o_c_loan_information->addChild('o_c_loan_interestinperc', $loanaccount->interestinperc);
                    $o_c_loan_information->addChild('o_c_loan_commissionperc', $loanaccount->commissionperc);
                    $o_c_loan_information->addChild('o_c_loan_fee', $loanaccount->fee);

                    if ($loanaccount->extdate != null) {
                        $o_c_loan_information->addChild('o_c_loan_extdate', $loanaccount->extdate);
                    }

                    $o_c_loan_information->addChild('o_c_loan_loanclasscode', $loanaccount->loanclasscode);
                    $o_c_loan_information->addChild('o_c_loan_isapproved', $loanaccount->isapproved);
                    $o_c_loan_information->addChild('o_c_loan_loanintype', '01');

                    // $morts = LnAccountMor::where('acntno', $loanaccount->acntno)->where('instid', $this->instid)->get();
                    $morts = LnAccountMor::where('acntno', $loanaccount->acntno)
                        ->where('instid', $this->instid)
                        ->whereIn('id', function ($query) use ($loanaccount) {
                            $query->selectRaw('MAX(id)')
                                ->from('ln_account_mor')
                                ->where('acntno', $loanaccount->acntno)
                                ->groupBy('morno');
                        })
                        ->get();

                    $o_c_loanmrtnos = $o_c_loan_information->addChild('o_c_loanmrtnos');

                    // Барьцаа хөрөнгө байвал
                    if (!empty($morts)) {
                        foreach ($morts as $index => $mort) {
                            $lnmor = LnMor::where('morno', $mort->morno)->where('statusid', 1)->where('instid', $this->instid)->first();


                            $paramaction = null;
                            if ($mort->statusid != 1) {
                                $paramaction = 'delete';
                            }
                            $totalindex++;
                            $o_c_loanmrtnos->addChild('o_c_loanmrtno', $totalindex);
                            $action = $this->getAction('o_c_coll_information', $mort->morno, $cust->id1, $cust->id1, $mort, $paramaction);


                            if ($action) {
                                $o_c_mortgage = $o_c_mortgage_information->addChild('o_c_mortgage');
                                $o_c_mortgage->addAttribute('action', $action);

                                $o_c_mortgage->addChild('o_c_mrtno', $totalindex);
                                $o_c_mortgage->addChild('o_c_mrtno_internal', $mort->morno);

                                $o_c_mrtcode = "";
                                if ($lnmor->mrtcode && $lnmor->subcode) {
                                    $sectorPrefix = $lnmor->mrtcode;
                                    $sectorSuffix = $lnmor->subcode;

                                    $result = DB::table('GP_const as g1')
                                        ->leftJoin('GP_const as g2', 'g2.parent_code', '=', 'g1.code')
                                        ->where('g1.parent_code', 'coll_type')
                                        ->where('g1.value', $sectorPrefix)
                                        ->where('g2.value', $sectorSuffix)
                                        ->select('g1.value_add1 as level1_add', 'g2.value_add1 as level2_add')
                                        ->first();

                                    // Append the value_add1 fields if results are found
                                    if ($result) {
                                        $o_c_mrtcode .= $result->level1_add ?? '';
                                        $o_c_mrtcode .= $result->level2_add ?? '';
                                    }
                                }

                                $o_c_mortgage->addChild('o_c_mrtcode', $o_c_mrtcode);
                                $o_c_mortgage->addChild('o_c_mrtdescription', $lnmor->docdesc);
                                $o_c_mortgage->addChild('o_c_is_real_estate', $lnmor->mrtcode == "01" ? 1 : 0);
                                if (isset($mort->registered_by)) {
                                    if ($mort->registered_by == "01") {
                                        $o_c_registeredtoauthority = $o_c_mortgage->addChild('o_c_registeredtoauthority');
                                        $o_c_registeredtoauthority->addChild('o_c_mrtregistereddate', Carbon::parse($mort->registered_date)->format('Y-m-d'));
                                        $o_c_registeredtoauthority->addChild('o_c_mrtstateregisterno', $lnmor->regno);
                                        $o_c_registeredtoauthority->addChild('o_c_mrtcertificateno', $lnmor->certno);
                                        $o_c_registeredtoauthority->addChild('o_c_mrtconfirmeddate', Carbon::parse($mort->registered_date)->format('Y-m-d'));
                                    } else {
                                        $o_c_authorityofimmovable = $o_c_mortgage->addChild('o_c_authorityofimmovable');
                                        $o_c_authorityofimmovable->addChild('o_c_mrtregistereddatefim', Carbon::parse($mort->registered_date)->format('Y-m-d'));

                                        $o_c_authorityofimmovable->addChild('o_c_mrtregisterno', $lnmor->regno);

                                        $o_c_authorityofimmovable->addChild('o_c_mrtcertificatenofim', $lnmor->certno);
                                        $o_c_authorityofimmovable->addChild('o_c_mrtorgname', $mort->registered_by == "05" ? "02" : $mort->registered_by);
                                    }
                                }

                                $o_c_mortgage->addChild('o_c_dateofvaluation', Carbon::parse($lnmor->costingdate)->format('Y-m-d'));
                                $o_c_mortgage->addChild('o_c_mrtvalue', number_format($lnmor->morprice, 2, '.', ''));
                                $o_c_mortgage->addChild('o_c_mrtmaxlimit', number_format($lnmor->costamount, 2, '.', ''));
                                $o_c_mortgage->addChild('o_c_mrt_address', $lnmor->addr1 . ' ' . $lnmor->addr2 . ' ' . $lnmor->addr3);

                                $o_c_organization = $o_c_mortgage->addChild('o_c_organization');
                                $o_c_organization->addChild('o_c_organization_orgname', $cust->name);
                                $o_c_organization->addChild('o_c_organization_localregistered', $isforeign);
                                $o_c_organization->addChild('o_c_organization_orgregisterno', $cust->id1);
                            }
                        }
                    }

                    $ln_custs = LnAccountCust::where('acntno', $loanaccount->acntno)
                        ->where('instid', $this->instid)
                        ->whereIn('statusid', function ($query) use ($loanaccount) {
                            $query->selectRaw('MAX(statusid)')
                                ->from('ln_account_cust')
                                ->where('acntno', $loanaccount->acntno)
                                ->groupBy('custno');
                        })
                        ->get();

                    //Log::debug($ln_custs);
                    $o_c_related_customer_indexes = collect();
                    $o_c_related_org_indexes = collect();

                    $o_c_relationorgs = $o_c_customer_information->addChild('o_c_relationorgs');
                    $o_c_relationcustomers = $o_c_customer_information->addChild('o_c_relationcustomers');
                    $o_c_loanrelnos = $o_c_loan_information->addChild('o_c_loanrelnos');

                    foreach ($ln_custs as $index => $ln_cust) {
                        $vwcust = VwCrCustList::where('custno', $ln_cust->custno)->where('instid', $this->instid)->first();

                        $is_foreign = 1;
                        if ($vwcust) {
                            $o_c_loanrelnos->addChild('o_c_loanrelno', $index);
                            if ($vwcust->custtypecode == 0) {
                                // Иргэн
                                if ($cust->segcode == 81 && $cust->segcode == 83) {
                                    $is_foreign = 0;
                                }
                            } else {
                                // Байгууллага
                                if ($cust->countrycode == 496) {
                                    $is_foreign = 0;
                                }
                            }

                            $paramaction = null;
                            if ($ln_cust->statusid != 1) {
                                $paramaction = 'delete';
                            }

                            if ($vwcust->custtypecode == 0) {
                                $relatedcust = CrCustInd::where('custno', $ln_cust->custno)->where('statusid', '<>', -1)->where('instid', $this->instid)->first();

                                $relation = CrCustRelation::where('custid', $relatedcust->id)->where('custtypecode', $vwcust->custtypecode)->where('reltypecode', '2')->where('statusid', '<>', -1)->where('instid', $this->instid)->first();
                                $o_c_related_customer_isfinancial_onus = 0;

                                if (isset($relation)) {
                                    $o_c_related_customer_isfinancial_onus = 1;
                                }


                                $action = $this->getAction('o_c_related_customers', $relatedcust->id, $cust->id1, $cust->id1, $ln_cust, $paramaction);
                                if (isset($action)) {
                                    $o_c_relationcustomer = $o_c_relationcustomers->addChild('o_c_relationcustomer');

                                    $o_c_relationcustomer->addAttribute('action', $action);
                                    $o_c_relationcustomer->addChild('o_c_relationcustomer_firstName', $relatedcust->name);
                                    $o_c_relationcustomer->addChild('o_c_relationcustomer_isforeign', $is_foreign);
                                    $o_c_relationcustomer->addChild('o_c_relationcustomer_registerno', $relatedcust->id1);
                                    $o_c_relationcustomer->addChild('o_c_relationcustomer_citizenrelation', $ln_cust->rolecode == 1 ? "04" : "05"); // (Хавсралт Д)  04 - Зээлийн гэрээгээрх үүргээс хүлээлцэж байгаа эсэх 05 - Хамтран үүрэг гүйцэтгэгч 06 - Батлан даагч
                                    $o_c_relationcustomer->addChild('o_c_relationcustomer_isfinancialonus', $o_c_related_customer_isfinancial_onus);
                                    $o_c_relationcustomer->addChild('o_c_relationcustomer_relno', $index);
                                }
                            } else {
                                $relatedcust = CrCustOrg::where('custno', $ln_cust->custno)->where('statusid', '<>', -1)->where('instid', $this->instid)->first();

                                $relation = CrCustRelation::where('custid', $relatedcust->id)->where('custtypecode', '2')->where('statusid', '<>', -1)->where('instid', $this->instid)->first();
                                $o_c_related_org_isfinancial_onus = 0;
                                if (isset($relation)) {
                                    $o_c_related_org_isfinancial_onus = 1;
                                }

                                $action = $this->getAction('o_c_related_orgs', $relatedcust->id, $cust->id1, $cust->id1, $relatedcust, $paramaction);

                                if (isset($action)) {

                                    $o_c_relationorg = $o_c_relationorgs->addChild('o_c_relationorg');

                                    $o_c_relationorg->addAttribute('action', $action);
                                    $o_c_relationorg->addChild('o_c_relationorg_orgname', $relatedcust->name);
                                    $o_c_relationorg->addChild('o_c_relationorg_isforeign', $is_foreign);
                                    $o_c_relationorg->addChild('o_c_relationorg_registerno', $relatedcust->id1);
                                    $o_c_relationorg->addChild('o_c_relationorg_orgrelation', $ln_cust->rolecode == 1 ? "03" : "05"); // (Хавсралт Г) 03 - Зээлийн гэрээгээрх үүргээс хүлээлцэж байгаа 05 - Батлан даагч
                                    $o_c_relationorg->addChild('o_c_relationorg_isfinancialonus', $o_c_related_org_isfinancial_onus);
                                    $o_c_relationorg->addChild('o_c_relationorg_relno', $index);

                                    $o_c_relationorg_sectorcodes = $o_c_relationorg->addChild('o_c_relationorg_sectorcodes');


                                    $sectorcode = "";
                                    if ($relatedcust->inducode) {
                                        $sectorPrefix = $relatedcust->inducode;
                                        $sectorSuffix = $relatedcust->indusubcode;

                                        $result = DB::table('GP_const as g1')
                                            ->leftJoin('GP_const as g2', 'g2.parent_code', '=', 'g1.code')
                                            ->where('g1.parent_code', 'loan_industry')
                                            ->where('g1.value', $sectorPrefix)
                                            ->where('g2.value', $sectorSuffix)
                                            ->select('g1.value_add1 as level1_add', 'g2.value_add1 as level2_add')
                                            ->first();

                                        // Append the value_add1 fields if results are found
                                        if ($result) {
                                            $sectorcode .= $result->level1_add ?? '';
                                            $sectorcode .= $result->level2_add ?? '';
                                        }
                                    }
                                    $o_c_relationorg_sectorcode = $o_c_relationorg_sectorcodes->addChild('o_c_relationorg_sectorcode', $sectorcode);

                                    $sectorcodeAction = $this->getAction('o_c_relationorg_sectorcode', $relatedcust->id, $cust->id1, $cust->id1, $relatedcust);
                                    $o_c_relationorg_sectorcode->addAttribute('action', $sectorcodeAction);
                                }
                            }
                        }
                    }

                    $loanaction = $this->getAction('o_c_loan_information', $loanaccount->acntno, $cust->id1, $cust->id1, $loanaccount);

                    $lnAccount = LnAccount::where('instid', $this->instid)->where('acntno', $loanaccount->acntno)->where('statusid', '<>', -1)->first();

                    $schedules = LnNrs::where('acntno', $loanaccount->acntno)->where('instid', $this->instid)->where('statusid', '<>', -1)->get();
                    $o_c_loantransactions = $o_c_loan_information->addChild('o_c_loantransactions');
                    $o_c_loandetails = $o_c_loantransactions->addChild('o_c_loandetails');
                    $o_c_loaninterestdetails = $o_c_loantransactions->addChild('o_c_loaninterestdetails');

                    // $o_c_loan_information->addChild('o_c_loan_updatedexpdate', Carbon::now()->format('Y-m-d'));

                    if ($lnAccount) {
                        if ($lnAccount->repaytype == 3) {
                            $loancharttype = '06'; // Хавсралт Й - Тэгш бус
                        } else {
                            switch ($lnAccount->payfreq) {
                                case 'M':
                                    $loancharttype = '04'; // Хавсралт Й - Сараар
                                case 'Q':
                                    $loancharttype = '03'; // Хавсралт Й - Улирлаар
                                case 'Y':
                                    $loancharttype = '01'; // Хавсралт Й - Жилээр
                                default:
                                    $loancharttype = '04';
                            }
                        }
                    }

                    $o_c_loantransactions->addChild('o_c_loan_loancharttype', $loancharttype);
                    $o_c_loantransactions->addChild('o_c_loan_interestcharttype', $loancharttype);

                    foreach ($schedules as $schedule) {

                        /// Зээлдэгчийн мэдээллийг ADD хийх үед o_c_loan_schedule action ADD байна

                        $action = $this->getAction('o_c_loan_schedule', $schedule->id, $cust->id1, $loanaccount->acntno, $schedule, $loanaction);

                        if (isset($action)) {
                            $o_c_loandetail = $o_c_loandetails->addChild('o_c_loandetail');
                            $o_c_loandetail->addAttribute('action', $action);
                            $o_c_loandetail->addChild('o_c_loandetail_datetopay', $schedule->payday);
                            $o_c_loandetail->addChild('o_c_loandetail_amounttopay', number_format($schedule->payamount, 2, '.', ''));

                            $o_c_loaninterestdetail = $o_c_loaninterestdetails->addChild('o_c_loaninterestdetail');
                            $o_c_loaninterestdetail->addAttribute('action', $action);
                            $o_c_loaninterestdetail->addChild('o_c_loaninterestdetail_datetopay', $schedule->payday);
                            $o_c_loaninterestdetail->addChild('o_c_loaninterestdetail_amounttopay', number_format($schedule->intamount, 2, '.', ''));
                        }
                    }

                    /**
                     * Зээл төлөх
                     * Бэлнээр төлөх - ln902010
                     * Бэлэн бусаар төлөх - ln902011
                     * Хүү урьдчилан төлөх бэлнээр - ln902036
                     * Хүү урьдчилан төлөх бэлэн бусаар - ln902037
                     * Зээлийн данс хаах бэлнээр - ln902090
                     * Зээлийн данс хаах бэлэн бусаар - ln902091
                     */

                    $payments = LnTxn::where('acntno', $loanaccount->acntno)->whereIn('txncode', ['ln902010', 'ln902011', 'ln902036', 'ln902037', 'ln902090', 'ln902091'])->where('instid', $this->instid)->where('statusid', '<>', -1)->get();
                    $paidamount = 0;

                    $o_c_loanperformances = $o_c_loantransactions->addChild('o_c_loanperformances');

                    foreach ($payments as $payment) {
                        $o_c_payment_due_date = null;
                        $o_c_loanperformance = $o_c_loanperformances->addChild('o_c_loanperformance');
                        // Төлөх ёстой огноог олох
                        $paidamount = $paidamount + $payment->txnamount;
                        $scheduleAmount = 0;
                        foreach ($schedules as $schedule) {

                            $scheduleAmount = $scheduleAmount + $schedule->payamount;
                            if ($paidamount < $scheduleAmount) {
                                $o_c_payment_due_date = $schedule->payday;
                                break;
                            }
                        }

                        if ($o_c_payment_due_date === null && $schedules->isNotEmpty()) {
                            $o_c_payment_due_date = $schedules->last()->payday;
                        }
                        /// Зээлдэгчийн мэдээллийг ADD хийх үед o_c_loan_payment action ADD байна

                        if ($loanaction == "update") {
                            $loanaction = null;
                        }

                        $action = $this->getAction('o_c_loan_payment', $payment->jrno, $cust->id1, $loanaccount->acntno, $payment, $loanaction);

                        if (isset($action)) {
                            $o_c_loanperformance->addAttribute('action', $action);
                            $o_c_loanperformance->addChild('o_c_loanperformance_datetopay', $o_c_payment_due_date);
                            $o_c_loanperformance->addChild('o_c_loanperformance_amounttopay', number_format($payment->txnamount, 2, '.', ''));
                        }
                    }
                }
            }

            if (count($o_c_mortgage_information->children()) === 0) {
                // Remove the child element
                $dom = dom_import_simplexml($o_c_mortgage_information);
                $dom->parentNode->removeChild($dom);
            }

            return $xml->asXML();
        } catch (Exception $ex) {
            Log::error($ex);
            throw $ex;
        }
    }

    public function getFullAddress($custid, $custtypecode, $addrtypecode = '1')
    {
        $address = "";
        $custaddr = VwCrCustAddress::where('custid', $custid)->where('custtypecode', $custtypecode)->where('addrtypecode', $addrtypecode)->where('statusid', '<>', -1)->first();
        if (isset($custaddr)) {
            $address = $custaddr->state_name . " " . $custaddr->region_name . " " . $custaddr->sub_region_name . " " . $custaddr->address;
            if (Str::of($address)->wordCount() > 50) {
                $custaddr['fullAddress'] = substr($address, 0, 50);
            }
            $custaddr['fullAddress'] = $address;
        } else {
            $address = "Хаяг";
        }

        return $custaddr;
    }

    public function getContact($custid, $custtypecode, $contacttypecode = 1)
    {
        $custcontact = CrCustContact::where('custid', $custid)->where('custtypecode', $custtypecode)->where('contacttypecode', $contacttypecode)->where('statusid', '<>', -1)->first();

        if (isset($custcontact)) {
            return $custcontact->contact;
        } else {
            return "";
        }
    }

    /**
     * Тухайн зээлийн барьцаа холбоосын одоогийн state hash-ийг SQL дотор тооцох
     * subquery. Барьцаагүй бол md5('') буцаана.
     */
    private function collStateHashSql()
    {
        // (acntno, instid) параметртэй SELECT хэлбэртэй expression.
        return "(SELECT md5(coalesce(string_agg(
            coalesce(lam.morno::text,'')||'|'||coalesce(lam.statusid::text,'')||'|'||
            coalesce(lm.morprice::text,'')||'|'||coalesce(lm.costamount::text,'')||'|'||
            coalesce(lam.registered_by::text,'')||'|'||coalesce(lam.registered_date::text,''),
            ';' ORDER BY lam.morno
        ),''))
        FROM ln_account_mor lam
        LEFT JOIN ln_mor lm ON lm.morno = lam.morno AND lm.instid = lam.instid AND lm.statusid = 1
        WHERE lam.acntno = ? AND lam.instid = ?)";
    }

    /**
     * Хамтран зээлдэгчийн холбоосын одоогийн state hash subquery.
     */
    private function custStateHashSql()
    {
        return "(SELECT md5(coalesce(string_agg(
            coalesce(lac.custno::text,'')||'|'||coalesce(lac.rolecode::text,'')||'|'||coalesce(lac.statusid::text,''),
            ';' ORDER BY lac.custno
        ),''))
        FROM ln_account_cust lac
        WHERE lac.acntno = ? AND lac.instid = ?)";
    }

    /**
     * Барьцаа болон хамтран зээлдэгчийн state hash зөрсөн зээлийг 2→4 flip хийнэ.
     * - Stored hash NULL (legacy) → flip хийхгүй, зөвхөн backfill (одоогийн hash-аар суурилуулна).
     * - Stored hash <> current → flip + hash шинэчлэгдэнэ.
     * 300k зээлд багц SQL хэлбэрээр ажиллана (CTE+UPDATE FROM).
     */
    private function applyStateHashFlip()
    {
        $instid = (int) $this->instid;
        $userid = (int) $this->userid;

        $collHashCte = "WITH coll_hashes AS (
            SELECT lam.acntno, lam.instid,
                md5(string_agg(
                    coalesce(lam.morno::text,'')||'|'||coalesce(lam.statusid::text,'')||'|'||
                    coalesce(lm.morprice::text,'')||'|'||coalesce(lm.costamount::text,'')||'|'||
                    coalesce(lam.registered_by::text,'')||'|'||coalesce(lam.registered_date::text,''),
                    ';' ORDER BY lam.morno
                )) AS h
            FROM ln_account_mor lam
            LEFT JOIN ln_mor lm ON lm.morno = lam.morno AND lm.instid = lam.instid AND lm.statusid = 1
            WHERE lam.instid = {$instid}
            GROUP BY lam.acntno, lam.instid
        )";

        $custHashCte = "WITH cust_hashes AS (
            SELECT lac.acntno, lac.instid,
                md5(string_agg(
                    coalesce(lac.custno::text,'')||'|'||coalesce(lac.rolecode::text,'')||'|'||coalesce(lac.statusid::text,''),
                    ';' ORDER BY lac.custno
                )) AS h
            FROM ln_account_cust lac
            WHERE lac.instid = {$instid}
            GROUP BY lac.acntno, lac.instid
        )";

        // === Барьцаа ===
        // 1) Legacy (stored NULL) → backfill, flip үгүй
        DB::statement("{$collHashCte}
            UPDATE ad_credit_info_buero_detail d
            SET coll_state_hash = coalesce(c.h, md5('')), updated_by = {$userid}
            FROM (
                SELECT d2.acntno, d2.instid, ch.h
                FROM ad_credit_info_buero_detail d2
                LEFT JOIN coll_hashes ch ON ch.acntno = d2.acntno AND ch.instid = d2.instid
                WHERE d2.instid = {$instid} AND d2.statusid = 2 AND d2.coll_state_hash IS NULL
            ) c
            WHERE d.instid = {$instid} AND d.statusid = 2 AND d.acntno = c.acntno");

        // 2) Stored <> current → flip 2→4 + hash шинэчлэх
        DB::statement("{$collHashCte}
            UPDATE ad_credit_info_buero_detail d
            SET statusid = 4, coll_state_hash = coalesce(c.h, md5('')), updated_by = {$userid}
            FROM (
                SELECT d2.acntno, d2.instid, ch.h
                FROM ad_credit_info_buero_detail d2
                LEFT JOIN coll_hashes ch ON ch.acntno = d2.acntno AND ch.instid = d2.instid
                WHERE d2.instid = {$instid} AND d2.statusid = 2
                  AND d2.coll_state_hash IS NOT NULL
                  AND d2.coll_state_hash IS DISTINCT FROM coalesce(ch.h, md5(''))
            ) c
            WHERE d.instid = {$instid} AND d.statusid = 2 AND d.acntno = c.acntno");

        // === Хамтран зээлдэгч ===
        // 1) Legacy backfill
        DB::statement("{$custHashCte}
            UPDATE ad_credit_info_buero_detail d
            SET cust_state_hash = coalesce(c.h, md5('')), updated_by = {$userid}
            FROM (
                SELECT d2.acntno, d2.instid, ch.h
                FROM ad_credit_info_buero_detail d2
                LEFT JOIN cust_hashes ch ON ch.acntno = d2.acntno AND ch.instid = d2.instid
                WHERE d2.instid = {$instid} AND d2.statusid = 2 AND d2.cust_state_hash IS NULL
            ) c
            WHERE d.instid = {$instid} AND d.statusid = 2 AND d.acntno = c.acntno");

        // 2) Stored <> current → flip
        DB::statement("{$custHashCte}
            UPDATE ad_credit_info_buero_detail d
            SET statusid = 4, cust_state_hash = coalesce(c.h, md5('')), updated_by = {$userid}
            FROM (
                SELECT d2.acntno, d2.instid, ch.h
                FROM ad_credit_info_buero_detail d2
                LEFT JOIN cust_hashes ch ON ch.acntno = d2.acntno AND ch.instid = d2.instid
                WHERE d2.instid = {$instid} AND d2.statusid = 2
                  AND d2.cust_state_hash IS NOT NULL
                  AND d2.cust_state_hash IS DISTINCT FROM coalesce(ch.h, md5(''))
            ) c
            WHERE d.instid = {$instid} AND d.statusid = 2 AND d.acntno = c.acntno");
    }

    /**
     * Амжилттай илгээсний дараа тухайн зээлийн detail-ийн state hash-уудыг
     * одоогийн state-ээр шинэчилнэ. Дараа нь updated_at touch-аас үл хамааран
     * зөвхөн бодит state өөрчлөлт л flip үүсгэнэ.
     */
    private function refreshStateHashes($acntno)
    {
        if (empty($acntno)) {
            return;
        }
        $collSql = $this->collStateHashSql();
        $custSql = $this->custStateHashSql();

        DB::update("UPDATE ad_credit_info_buero_detail
            SET coll_state_hash = {$collSql},
                cust_state_hash = {$custSql},
                updated_by = ?
            WHERE acntno = ? AND instid = ? AND statusid <> -1", [
            $acntno,
            $this->instid,   // coll subquery
            $acntno,
            $this->instid,   // cust subquery
            $this->userid,
            $acntno,
            $this->instid,
        ]);
    }

    /**
     * Алдааны код → засах action-ийн map.
     *
     * Утга бүр:
     *   'types' => ad_credit_info_buero_action.type-ийн жагсаалт
     *   'to'    => зөв action ('add' | 'update')
     *
     * Логик:
     *   - "Update хийж буй ... байхгүй/устгагдсан" → бид update явуулсан → зөв нь 'add'
     *   - "Шинээр нэмж буй ... давхардаж байна"    → бид add явуулсан → зөв нь 'update'
     *
     * Тэмдэглэл: DBE1005 (зээл байхгүй), DBE1014 (зээл давхардсан) нь тусдаа
     * (хаагдсан гэж үзэх) логиктой тул энд оруулаагүй.
     */
    private function bueroErrorActionMap()
    {
        $relatedTypes = ['o_c_related_orgs', 'o_c_related_customers', 'o_shareholder_customer', 'o_shareholder_org', 'o_c_customer_bank_relation'];

        return [
            // ===== update → add (бичлэг олдсонгүй) =====
            'DBE1006' => ['types' => ['o_c_loan_information'], 'to' => 'add'],
            'DBE1007' => ['types' => ['customer_data'], 'to' => 'add'],
            'DBE1008' => ['types' => ['customer_data'], 'to' => 'add'],
            'DBE1009' => ['types' => $relatedTypes, 'to' => 'add'],
            'DBE1010' => ['types' => ['o_c_coll_information'], 'to' => 'add'],
            'DBE1011' => ['types' => ['o_c_coll_customer', 'o_c_coll_org'], 'to' => 'add'],
            'DBE1012' => ['types' => ['o_c_loan_payment'], 'to' => 'add'],
            'DBE1013' => ['types' => ['o_c_loan_schedule'], 'to' => 'add'],
            'DBE1047' => ['types' => ['o_c_coll_information'], 'to' => 'add'],
            'DBE1048' => ['types' => ['o_c_coll_customer', 'o_c_coll_org'], 'to' => 'add'],
            'RTE1001' => ['types' => ['customer_data'], 'to' => 'add'],

            // ===== add → update (давхардаж байна) =====
            'DBE1015' => ['types' => ['o_c_loan_information'], 'to' => 'update'],
            'DBE1016' => ['types' => ['customer_data'], 'to' => 'update'],
            'DBE1017' => ['types' => ['customer_data'], 'to' => 'update'],
            'DBE1018' => ['types' => $relatedTypes, 'to' => 'update'],
            'DBE1019' => ['types' => ['o_c_coll_information'], 'to' => 'update'],
            'DBE1020' => ['types' => ['o_c_coll_customer', 'o_c_coll_org'], 'to' => 'update'],
            'DBE1021' => ['types' => ['o_c_loan_payment'], 'to' => 'update'],
            'DBE1022' => ['types' => ['o_c_loan_schedule'], 'to' => 'update'],
            'DBE1037' => ['types' => ['o_shareholder_customer'], 'to' => 'update'],
            'DBE1038' => ['types' => ['o_shareholder_org'], 'to' => 'update'],
            'DBE1039' => ['types' => ['o_c_related_customers'], 'to' => 'update'],
            'DBE1040' => ['types' => ['o_c_related_orgs'], 'to' => 'update'],
            'DBE1041' => ['types' => ['o_c_customer_bank_relation'], 'to' => 'update'],
            'DBE1045' => ['types' => ['o_c_coll_information'], 'to' => 'update'],
            'DBE1046' => ['types' => ['o_c_coll_customer', 'o_c_coll_org'], 'to' => 'update'],
            'RTE1000' => ['types' => ['customer_data'], 'to' => 'update'],
            'RTE1013' => ['types' => ['o_c_customer_bank_relation'], 'to' => 'update'],

            // ===== RTE1003-1012: зээлдэгчийг ADD хийх үед хүүхэд section-ийн
            // action заавал ADD байх ёстой (өөр байвал алдаа). Тиймээс хүүхдийг 'add' болгоно.
            'RTE1003' => ['types' => ['o_c_customer_bank_relation'], 'to' => 'add'],
            'RTE1004' => ['types' => ['o_c_loan_information'], 'to' => 'add'],
            'RTE1005' => ['types' => ['o_c_loan_schedule'], 'to' => 'add'],
            'RTE1006' => ['types' => ['o_c_loan_payment'], 'to' => 'add'],
            'RTE1007' => ['types' => ['o_c_related_customers'], 'to' => 'add'],
            'RTE1008' => ['types' => ['o_c_related_orgs'], 'to' => 'add'],
            'RTE1009' => ['types' => ['o_c_coll_customer'], 'to' => 'add'],
            'RTE1010' => ['types' => ['o_c_coll_org'], 'to' => 'add'],
            'RTE1011' => ['types' => ['o_c_coll_information'], 'to' => 'add'],
            'RTE1012' => ['types' => ['o_c_loan_information'], 'to' => 'add'],
        ];
    }

    /**
     * Алдааны хариунаас (errors) аль action дээр алдсаныг таньж зөв action болгоно.
     *
     * $errors нь [ errorKey => errorCode ] хэлбэртэй. errorKey нь ихэвчлэн
     * loan_contract_no (= байгууллагын регистр + custno + acntno) болон
     * холбогдогчийн регистрийг агуулсан байдаг тул түүгээр зөв мөрийг тааруулна.
     */
    private function correctBueroActions($errors, $vwcust)
    {
        if (empty($errors) || !is_array($errors)) {
            return;
        }

        $map = $this->bueroErrorActionMap();

        foreach ($errors as $errorKey => $errorCode) {
            if (!isset($map[$errorCode])) {
                continue;
            }

            $conf = $map[$errorCode];
            $errorKey = (string) $errorKey;

            $candidates = AdCreditInfoBueroAction::where('regno', $vwcust->id1)
                ->whereIn('type', $conf['types'])
                ->where('instid', $this->instid)
                ->where('statusid', '<>', -1)
                ->get();

            if ($candidates->isEmpty()) {
                continue;
            }

            // (A) Тухайн илгээж буй зээлийн (acntno) action-уудыг ялгаж, олон
            //     барьцаа/хуваарь/төлөлттэй зээлд буруу үл хамаарагч мөрийг шүүж хаяна.
            $loanScoped = false;
            if (!empty($this->acntno)) {
                $narrowed = $this->narrowByLoanContext($candidates, $this->acntno);
                if ($narrowed !== null) {
                    $candidates = $narrowed;
                    $loanScoped = true;
                    if ($candidates->isEmpty()) {
                        continue;
                    }
                }
            }

            // (B) Алдааны түлхүүрт тохирох мөрүүдийг шүүх:
            //   - substring matching (key/parent_key/регистр агуулагдсан)
            //   - ЗМС-ийн шинэ pipe-делимитер форматын хувьд (жнь DBE1009:
            //     "contractno|related_type|regnum|relation_type") сегмент тус бүрийг
            //     token-той ялгаж/нийцэх эсэхийг шалгана.
            $errorSegments = strpos($errorKey, '|') !== false
                ? array_filter(array_map('trim', explode('|', $errorKey)), fn($s) => $s !== '')
                : [];

            $matched = $candidates->filter(function ($action) use ($errorKey, $errorSegments) {
                foreach ($this->bueroActionTokens($action) as $token) {
                    if ($token === '' || mb_strlen($token) < 4) {
                        continue;
                    }
                    // 1) substring match (хуучин ба нийлмэл форматт)
                    if (Str::contains($errorKey, $token)) {
                        return true;
                    }
                    // 2) pipe-сегмент яг тэнцэх (шинэ форматт)
                    foreach ($errorSegments as $seg) {
                        if ($seg === $token) {
                            return true;
                        }
                    }
                }
                return false;
            });

            // (C) Тохирох мөр олдвол түүнийг, эс бөгөөс цор ганц нэр дэвшигч байвал
            //     түүнийг, эс бөгөөс loan-scope-аар хязгаарлагдсан бол тэдгээрийг бүгдийг засна.
            $matchType = 'token';
            if ($matched->isNotEmpty()) {
                $targets = $matched;
            } elseif ($candidates->count() === 1) {
                $targets = $candidates;
                $matchType = 'single_fallback';
            } elseif ($loanScoped) {
                // Тухайн зээлд хамаарагч action-ууд — DBE/RTE алдаа уг зээлийн илгээлтийн
                // үед гарсан тул бүгдийг засна. Эсрэг алдаа гарвал автоматаар буцаагдана.
                $targets = $candidates;
                $matchType = 'loan_scoped';
            } else {
                Log::warning('Credit buero action correction skipped (ambiguous)', [
                    'instid' => $this->instid,
                    'regno' => $vwcust->id1 ?? null,
                    'acntno' => $this->acntno,
                    'errorCode' => $errorCode,
                    'errorKey' => $errorKey,
                    'types' => $conf['types'],
                    'candidate_ids' => $candidates->pluck('id')->all(),
                ]);
                continue;
            }

            foreach ($targets as $target) {
                if ($target->action !== $conf['to']) {
                    Log::debug('Credit buero action corrected', [
                        'instid' => $this->instid,
                        'regno' => $vwcust->id1 ?? null,
                        'errorCode' => $errorCode,
                        'errorKey' => $errorKey,
                        'action_id' => $target->id,
                        'type' => $target->type,
                        'from' => $target->action,
                        'to' => $conf['to'],
                        'match' => $matchType,
                    ]);

                    $target->update([
                        'action' => $conf['to'],
                        'statusid' => 1,
                        'updated_by' => $this->userid,
                    ]);
                }
            }
        }
    }

    /**
     * Тухайн buero_action мөрийг алдааны түлхүүртэй тааруулахад ашиглах
     * таних тэмдэгтүүд (key, parent_key, регистр (id1), иргэний бүртгэлийн дугаар (id2)).
     *
     * ЗМС-ийн шинэ форматтай алдаа (жнь DBE1009: pipe-делимитер
     * "contractno|related_type|regnum|relation_type") дотор регистр эсвэл иргэний
     * дугаар сегмент байж болзошгүй тул хоёуланг ч token-д оруулна.
     */
    private function bueroActionTokens($action)
    {
        $tokens = [(string) $action->key, (string) $action->parent_key];

        // Холбоотой этгээдийн хувьд key нь DB id байх тул жинхэнэ регистр (id1)
        // болон иргэний бүртгэлийн дугаар (id2) хоёуланг олж token-д нэмнэ.
        if ($action->type === 'o_c_related_orgs') {
            $org = CrCustOrg::where('id', $action->key)
                ->where('instid', $this->instid)
                ->where('statusid', '<>', -1)->first();
            if ($org) {
                if (!empty($org->id1)) $tokens[] = (string) $org->id1;
                if (!empty($org->id2)) $tokens[] = (string) $org->id2;
            }
        } elseif ($action->type === 'o_c_related_customers') {
            $ind = CrCustInd::where('id', $action->key)
                ->where('instid', $this->instid)
                ->where('statusid', '<>', -1)->first();
            if ($ind) {
                if (!empty($ind->id1)) $tokens[] = (string) $ind->id1;
                if (!empty($ind->id2)) $tokens[] = (string) $ind->id2;
            }
        } elseif ($action->type === 'o_shareholder_customer') {
            // key нь аль хэдийн регистр (id1). Иргэний дугаарыг (id2) лавлаж нэмнэ.
            $ind = CrCustInd::where('id1', $action->key)
                ->where('instid', $this->instid)
                ->where('statusid', '<>', -1)->first();
            if ($ind && !empty($ind->id2)) $tokens[] = (string) $ind->id2;
        } elseif ($action->type === 'o_shareholder_org') {
            $org = CrCustOrg::where('id1', $action->key)
                ->where('instid', $this->instid)
                ->where('statusid', '<>', -1)->first();
            if ($org && !empty($org->id2)) $tokens[] = (string) $org->id2;
        }
        // o_c_customer_bank_relation-д key нь аль хэдийн регистр.

        return array_values(array_unique(array_filter($tokens, fn($t) => $t !== '')));
    }

    /**
     * Тухайн илгээж буй зээлийн (acntno) хүрээнд candidate action-уудыг хязгаарлана.
     * Зөвхөн "зээлд хамаарагч" type-ууд (loan_information / loan_schedule / loan_payment /
     * coll_information / coll_customer / coll_org) дээр л шүүлт хийнэ. Бусад customer-level
     * type-уудад нөлөөлөхгүй.
     *
     * @return \Illuminate\Support\Collection|null  null хариу нь "narrowing хамаагүй" (бүх
     *         candidate-ууд нь customer-level — token matching-аар шийднэ) гэсэн утгатай.
     */
    private function narrowByLoanContext($candidates, $acntno)
    {
        // type → стратеги: candidate тухайн зээлд хамааралтай эсэхийг хэрхэн шалгах
        $loanScopedTypes = [
            'o_c_loan_information' => 'key',           // key = acntno
            'o_c_loan_schedule' => 'parent_key',    // parent_key = acntno
            'o_c_loan_payment' => 'parent_key',    // parent_key = acntno
            'o_c_coll_information' => 'key_contains',  // key = morno+acntno+id
            'o_c_coll_customer' => 'morno_set',     // parent_key = morno (тухайн зээлийн)
            'o_c_coll_org' => 'morno_set',     // parent_key = morno
        ];

        // Хэрэв ямар ч candidate нь loan-scoped type-тай биш бол narrowing хамаагүй.
        $hasLoanScoped = $candidates->contains(fn($a) => isset($loanScopedTypes[$a->type]));
        if (!$hasLoanScoped) {
            return null;
        }

        $loanMornos = null;     // lazy load — зөвхөн coll_customer/org үед хэрэгтэй
        $acntnoStr = (string) $acntno;

        return $candidates->filter(function ($a) use ($acntnoStr, $loanScopedTypes, &$loanMornos) {
            $strategy = $loanScopedTypes[$a->type] ?? null;
            if ($strategy === null) {
                // Customer-level — шүүлтгүй үлдээнэ.
                return true;
            }
            switch ($strategy) {
                case 'key':
                    return (string) $a->key === $acntnoStr;
                case 'parent_key':
                    return (string) $a->parent_key === $acntnoStr;
                case 'key_contains':
                    return $acntnoStr !== '' && Str::contains((string) $a->key, $acntnoStr);
                case 'morno_set':
                    if ($loanMornos === null) {
                        $loanMornos = LnAccountMor::where('acntno', $acntnoStr)
                            ->where('instid', $this->instid)
                            ->where('statusid', '<>', -1)
                            ->pluck('morno')
                            ->map(fn($m) => (string) $m)
                            ->all();
                    }
                    return in_array((string) $a->parent_key, $loanMornos, true);
            }
            return true;
        })->values();
    }

    public function getAction($type, $key, $regno, $parentkey, $data = null, $paramaction = null)
    {
        $action = "add";
        $bueroAction = AdCreditInfoBueroAction::where('type', $type)->where('key', $key)->where('parent_key', $parentkey)->where('regno', $regno)->where('statusid', '<>', -1)->where('instid', $this->instid)->first();

        if ($bueroAction) {
            if ($bueroAction->action === 'deleted') {
                if ($paramaction !== 'delete') {
                    $bueroAction->update(['action' => 'add', 'statusid' => 1, 'updated_by' => $this->userid]);
                    return 'add';
                }
                return null;
            }

            if ($bueroAction->action == 'add' && $paramaction == 'delete') {
                $bueroAction->update(['action' => 'deleted', 'updated_by' => $this->userid, 'statusid' => 1]);

                return null;
            }

            if ($paramaction === 'delete' && $bueroAction->action == 'update') {
                $bueroAction->update(['action' => 'delete', 'updated_by' => $this->userid, 'statusid' => 1]);

                return 'delete';
            }

            if ($paramaction) {
                $bueroAction->update(['action' => $paramaction, 'updated_by' => $this->userid, 'statusid' => 1]);
                $action = $paramaction;
            } else {
                $action = $bueroAction->action;
            }
        } else {
            $bueroAction = AdCreditInfoBueroAction::create(['type' => $type, 'action' => 'add', 'key' => $key, 'parent_key' => $parentkey, 'regno' => $regno, 'statusid' => 1, 'created_by' => $this->userid, 'instid' => $this->instid]);
            $action = 'add';
        }

        return $action;
    }


    public function updateUserAction($regno, $acntno)
    {
        $base = fn($action) => AdCreditInfoBueroAction::where('regno', $regno)
            ->where('action', $action)
            ->where('statusid', '<>', -1)
            ->where('instid', $this->instid);

        $base('add')
            ->where('parent_key', $acntno)
            ->update(['action' => 'update', 'statusid' => 2, 'updated_by' => $this->userid]);

        $base('delete')
            ->where('parent_key', $acntno)
            ->update(['action' => 'deleted', 'statusid' => 2, 'updated_by' => $this->userid]);

        $base('add')
            ->where('regno', $regno)
            ->update(['action' => 'update', 'statusid' => 2, 'updated_by' => $this->userid]);

        $base('delete')
            ->where('regno', $regno)
            ->update(['action' => 'deleted', 'statusid' => 2, 'updated_by' => $this->userid]);
    }


    public function getAddValue($code, $custid)
    {
        $GPInstAddField = GPInstAddField::where('code', $code)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
        if (isset($GPInstAddField)) {
            if ($GPInstAddField->typecode == "cr" || $GPInstAddField->typecode == "crorg") {
                $crCustAdd = CrCustAdd::where('custid', $custid)->where('keyfield', $GPInstAddField->id)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
                if (isset($crCustAdd)) {
                    return $crCustAdd->itemvalue;
                } else {
                    return null;
                }
            } else if ($GPInstAddField->typecode == "ln") {
                $lnMorAdd = LnMorAdd::where('morno', $custid)->where('keyfield', $GPInstAddField->id)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
                if (isset($lnMorAdd)) {
                    return $lnMorAdd->itemvalue;
                } else {
                    return null;
                }
            } else if ($GPInstAddField->typecode == "gp") {
                $GPInstAdd = GPInstAdd::where('custid', $custid)->where('keyfield', $GPInstAddField->id)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
                if (isset($GPInstAdd)) {
                    return $GPInstAdd->itemvalue;
                } else {
                    return null;
                }
            } else if ($GPInstAddField->typecode == "ia") {
                $iaCtAccountAdd = IaCtAccountAdd::where('custid', $custid)->where('keyfield', $GPInstAddField->id)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
                if (isset($iaCtAccountAdd)) {
                    return $iaCtAccountAdd->itemvalue;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return null;
    }


    /**
     * id2 (иргэний бүртгэлийн дугаар / байгууллагын улсын бүртгэлийн дугаар)-ийг
     * EBARIMT-аас (env дэх URL) регистрээр лавлаж авна.
     *
     * Лавлах нөхцөл (дараахын аль нэг үнэн бол EBARIMT-ыг заавал шалгана):
     *   1. currentId2 хоосон
     *   2. currentId2 == regno (адил утга — алдаатай дата)
     *   3. CrCustInd-ийн id2 нь яг 12 урттай биш
     *   4. CrCustOrg-ийн id2 нь 9-12 урт хүрээнд биш
     *
     * EBARIMT амжилттай data буцаавал id2-г шинэчилж олсон утгыг буцаана.
     * Үгүй бол MeException шиднэ:
     *   - CrCustInd: "Иргэний бүртгэлийн дугаар олдсонгүй. Регистерийн утга шалгана уу."
     *   - CrCustOrg: "Улсын бүртгэлийн дугаар олдсонгүй. Регистерийн утга шалгана уу."
     *
     * @param  string $regno       Регистр (id1)
     * @param  string $currentId2  Одоо байгаа id2
     * @param  string $modelClass  CrCustInd::class эсвэл CrCustOrg::class
     * @return string|null
     */
    private function fetchEbarimtId2($regno, $currentId2, $modelClass)
    {
        $isOrg     = ($modelClass === CrCustOrg::class);
        $regnoStr  = (string) ($regno ?? '');
        $currentId2 = (string) ($currentId2 ?? '');

        if ($currentId2 !== '' && preg_match('/^(0+|1+|2+)$/', $currentId2)) {
            if ($isOrg) {
                throw new MeException('Улсын бүртгэлийн дугаар буруу байна (' . $currentId2 . ')');
            }
            throw new MeException('Иргэний бүртгэлийн дугаар буруу байна (' . $currentId2 . ')');
        }

        // Регистр огт өгөгдөөгүй бол шалгах зүйлгүй
        if ($regnoStr === '') {
            return $currentId2 !== '' ? $currentId2 : null;
        }

        // Хүчинтэй гэж үзэх нөхцөл — лавлах хэрэггүй
        $isValid = $this->isValidId2($currentId2, $regnoStr, $isOrg);

        if ($isValid) {
            return $currentId2;
        }

        // EBARIMT-аас лавлах
        try {
            $ebarimtUrl = env('EBARIMT', 'https://api.ebarimt.mn/api/info/check/getTinInfo?regNo=');
            $resp = Http::timeout(5)->get($ebarimtUrl . Str::upper($regnoStr));

            if ($resp->successful() && !empty($resp['data'])) {
                $id2 = (string) $resp['data'];

                // Шинэ утга мөн адил шалгуурыг хангах ёстой
                if ($this->isValidId2($id2, $regnoStr, $isOrg)) {
                    $modelClass::where('id1', $regnoStr)
                        ->where('instid', $this->instid)
                        ->where('statusid', '<>', -1)
                        ->update(['id2' => $id2, 'updated_by' => $this->userid]);
                    return $id2;
                }

                Log::warning('EBARIMT хариу буруу форматтай', [
                    'instid' => $this->instid,
                    'regno' => $regnoStr,
                    'model' => $modelClass,
                    'received_id2' => $id2,
                ]);
            } else {
                Log::warning('EBARIMT getTinInfo failed', [
                    'instid' => $this->instid,
                    'regno' => $regnoStr,
                    'model' => $modelClass,
                    'status' => method_exists($resp, 'status') ? $resp->status() : null,
                    'body'   => method_exists($resp, 'body')   ? $resp->body()   : null,
                ]);
            }
        } catch (Exception $ex) {
            Log::error($ex);
        }

        // Лавлаж олдоогүй — тодорхой алдаа шиднэ
        if ($isOrg) {
            throw new MeException('Улсын бүртгэлийн дугаар олдсонгүй. Регистерийн утга шалгана уу (' . $regnoStr . ')');
        }
        throw new MeException('Иргэний бүртгэлийн дугаар олдсонгүй. Регистерийн утга шалгана уу (' . $regnoStr . ')');
    }

    /**
     * id2 нь регистрийн төрөлд тохирох уртын шалгуурыг хангах эсэх.
     *  - CrCustInd: яг 12 урттай
     *  - CrCustOrg: 9-12 урттай
     * Мөн id2 нь регистртэй ижил байж болохгүй.
     */
    private function isValidId2($id2, $regno, $isOrg)
    {
        if ($id2 === '' || $id2 === null) {
            return false;
        }
        if (preg_match('/^(0+|1+|2+)$/', (string) $id2)) {
            return false;
        }
        if ((string) $id2 === (string) $regno) {
            return false;
        }
        $len = mb_strlen((string) $id2);
        if ($isOrg) {
            return $len >= 9 && $len <= 12;
        }
        return $len === 12 || $len === 11;
    }

    /**
     * Иргэний хувийн дугаар (c_civil_id) хоосон бол EBARIMT-аас лавлаж авна.
     */
    public function resolveCivilId($regno, $currentId2, $isForeign = 0)
    {
        if ($isForeign == 1) {
            return $currentId2;
        }
        return $this->fetchEbarimtId2($regno, $currentId2, CrCustInd::class);
    }

    /**
     * Байгууллагын улсын бүртгэлийн дугаар (state_regnum) хоосон бол EBARIMT-аас лавлаж авна.
     */
    public function resolveStateRegnum($regno, $currentId2, $isForeign = 0)
    {
        if ($isForeign == 1) {
            return $currentId2;
        }
        return $this->fetchEbarimtId2($regno, $currentId2, CrCustOrg::class);
    }

    public function generateCustDetail($custtypecode, $cust, $custaction = "add")
    {
        $isforeign = 0;
        if ($custtypecode == 0) {
            // Иргэн
            $mobileno = $cust->handphone;
            if ($cust->segcode == 81 || $cust->segcode == 83) {
                $isforeign = 0;
            } else if ($cust->segcode == 82 || $cust->segcode == 84) {
                $isforeign = 1;
            }
        } else {
            // Байгууллага
            $mobileno = $cust->workphone;
            if ($cust->countrycode != 496) {
                $isforeign = 1;
            }
        }
        $o_c_customer_bank_relation_action = $this->getAction('o_c_customer_bank_relation', $cust->id1, $cust->id1, $cust->id1);

        $o_c_customer_bank_relation = [
            'action' => $custaction == "add" ? $custaction : $o_c_customer_bank_relation_action,
            'relation' => '01'
        ];

        $custrelation = CrCustRelation::where('custid', $cust->id)->where('custtypecode', $custtypecode)->where('reltypecode', '2')->where('statusid', '<>', -1)->where('instid', $this->instid)->first();

        if (isset($custrelation)) {
            $o_c_customer_bank_relation['relation'] = '05';
        }

        $address = $this->getFullAddress($cust->id, $custtypecode);

        if (isset($address)) {
            $o_c_address = [
                "o_c_address_full" => mb_substr($address->fullAddress ?? '-', 0, 50),
                "o_c_address_aimag_city_name" => $address->state_name ?? '-',
                "o_c_address_aimag_city_code" => $address->state ?? '-',
                "o_c_address_soum_district_name" => $address->region_name ?? '-',
                "o_c_address_soum_district_code" => $address->region ?? '-',
                "o_c_address_bag_khoroo_name" => $address->subregion_name ?? '-',
                "o_c_address_bag_khoroo_code" => $address->subregion ?? '-',
                "o_c_address_street_name" => $address->state_name ?? '-',
                "o_c_address_region_name" => $address->region_name ?? '-',
                "o_c_address_town_name" => $address->state_name ?? '-',
                "o_c_address_apartment_name_number" => $address->state_name ?? '-',
                "o_c_address_zipcode" => $address->zipcode ?? '99999',
            ];
        } else {
            $o_c_address = [
                "o_c_address_full" => "-",
                "o_c_address_aimag_city_name" => "-",
                "o_c_address_aimag_city_code" => "-",
                "o_c_address_soum_district_name" => "-",
                "o_c_address_soum_district_code" => "-",
                "o_c_address_bag_khoroo_name" => "-",
                "o_c_address_bag_khoroo_code" => "-",
                "o_c_address_street_name" => "-",
                "o_c_address_region_name" => "-",
                "o_c_address_town_name" => "-",
                "o_c_address_apartment_name_number" => "-",
                "o_c_address_zipcode" => "99999",
            ];
        }


        $c_tax_number = $this->getAddValue(@$this->providerConfig['c_tax_number'] ?? 'c_tax_number', $cust->id);
        $c_family_numof_unemployed = $this->getAddValue(@$this->providerConfig['c_family_numof_unemployed'] ?? 'c_family_numof_unemployed', $cust->id);

        if ($custtypecode == 0) {
            $c_isemployed = 0;
            if ($cust->inducode == '99') {
                if ($cust->indusubcode == '01') {
                    $c_isemployed = 1;
                }
            }

            if ($c_isemployed == 1) {
                $c_job_address = $this->getAddValue(@$this->providerConfig['c_job_address'] ?? 'c_job_address', $cust->id);
                $c_job_phone = $this->getAddValue(@$this->providerConfig['c_job_phone'] ?? 'c_job_phone', $cust->id);
                $c_job_mail = $this->getAddValue(@$this->providerConfig['c_job_mail'] ?? 'c_job_mail', $cust->id);

                $c_job = [
                    "c_job_position" => $cust->position,
                    "c_job_name" => $cust->workplace,
                    "c_job_address" => mb_substr($c_job_address ?? '-', 0, 50),
                    "c_job_phone" => $c_job_phone ?? '999999',
                    "c_job_mail" => $c_job_mail ?? '-@-.mn'
                ];
            } else {
                $c_job = null;
            }

            $c_civil_id = $this->resolveCivilId($cust->id1, $cust->id2, $isforeign);


            $customer_data = [
                "action" => $custaction,
                "c_civil_id" => $c_civil_id,
                "o_c_regnum" => $cust->id1,
                "o_c_customer_name" => $cust->name,
                "c_lastname" => $cust->lname,
                "c_familyname" => $cust->familyname,
                "o_c_isforeign" => $isforeign,
                "o_c_birthdate" => $cust->birthdate,
                "o_c_address" => $o_c_address,
                "o_c_phone" => $mobileno,
                "o_c_email" => $cust->email,
                "c_tax_number" => $c_tax_number,
                "c_family_numof_members" => $cust->familymembercount ?? 0,
                "c_family_numof_unemployed" => $c_family_numof_unemployed ?? 0,
                "c_isemployed" => $c_isemployed,
                "c_job" => $c_job,
                "o_c_customer_bank_relation" => $o_c_customer_bank_relation
            ];

            return $customer_data;
        } else {
            $o_shareholder_org = collect();
            $o_shareholder_customer = collect();

            $shareholders = CrCustShare::where('custid', $cust->id)->where('custtypecode', $custtypecode)->where('statusid', '<>', -1)->where('instid', $this->instid)->get();

            foreach ($shareholders as $shareholder) {
                if ($shareholder->custid2typecode == 0) {
                    // Иргэн
                    $custshareholder = CrCustInd::where('id', $shareholder->custid2)->where('statusid', '<>', -1)->where('instid', $this->instid)->first();

                    $shareholderisforeign = 1;

                    if ($custshareholder->segcode != 81 && $custshareholder->segcode != 83) {
                        $shareholderisforeign = 0;
                    }

                    $o_shareholder_customer_address = $this->getFullAddress($custshareholder->id, 0, '1');

                    $action = $this->getAction('o_shareholder_customer', $custshareholder->id1, $cust->id1, $cust->id1, $custshareholder);

                    if (isset($action)) {
                        $o_shareholder_customer->push([
                            "action" => $action,
                            "o_shareholder_customer_civil_id" => $this->resolveCivilId($custshareholder->id1, $custshareholder->id2, $shareholderisforeign),
                            "o_shareholder_customer_regnum" => $custshareholder->id1,
                            "o_shareholder_customer_firstname" => $custshareholder->name,
                            "o_shareholder_customer_lastname" => $custshareholder->lname,
                            "o_shareholder_customer_familyname" => $custshareholder->familyname,
                            "o_shareholder_customer_isforeign" => $shareholderisforeign,
                            "o_shareholder_customer_address" => mb_substr($o_shareholder_customer_address->fullAddress ?? '', 0, 50),
                            "o_shareholder_customer_phone" => $custshareholder->handphone,
                            "o_shareholder_customer_email" => $custshareholder->email,
                        ]);
                    }
                } else {
                    /// Байгууллага
                    $custshareholder = CrCustOrg::where('id', $shareholder->custid2)->where('statusid', '<>', -1)->where('instid', $this->instid)->first();

                    $shareholderisforeign = 1;

                    if ($cust->countrycode != 496) {
                        $shareholderisforeign = 0;
                    }

                    // $o_shareholder_org_address = $this->getAddValue(@$this->providerConfig['o_shareholder_org_address'] ?? 'o_shareholder_org_address', $cust->id);

                    $o_shareholder_org_address = $this->getFullAddress($custshareholder->id, 1, '1');

                    $action = $this->getAction('o_shareholder_org', $custshareholder->id1, $cust->id1, $cust->id1, $shareholder);

                    if (isset($action)) {
                        $o_shareholder_org->push([
                            "action" => $action,
                            "o_shareholder_org_name" => $custshareholder->name,
                            "o_shareholder_org_regnum" => $custshareholder->id1,
                            "o_shareholder_org_state_regnum" => $this->resolveStateRegnum($custshareholder->id1, $custshareholder->id2, $shareholderisforeign),
                            "o_shareholder_org_isforeign" => $shareholderisforeign,
                            "o_shareholder_org_address" => mb_substr($o_shareholder_org_address->fullAddress ?? '', 0, 50),
                            "o_shareholder_org_phone" => $custshareholder->workphone,
                            "o_shareholder_org_email" => $custshareholder->email,
                        ]);
                    }
                }
            }
            $o_orgrate_rating = $this->getAddValue(@$this->providerConfig['o_orgrate_rating'] ?? 'o_orgrate_rating', $cust->id);
            $o_orgrate_agency = $this->getAddValue(@$this->providerConfig['o_orgrate_agency'] ?? 'o_orgrate_agency', $cust->id);
            $o_orgrate_expdate = $this->getAddValue(@$this->providerConfig['o_orgrate_rating_expdate'] ?? 'o_orgrate_rating_expdate', $cust->id);

            // Үнэлгээ талбар бөглөөгүй үед default утга тавив
            if ($o_orgrate_agency == "") {
                $o_orgrate_agency = "01"; // Annex 2 - 01	Үнэлгээ хийлгээгүй
            }
            $o_orgrate = [
                "o_orgrate_agency" => $o_orgrate_agency,
                "o_orgrate_rating" => $o_orgrate_rating,
                "o_orgrate_rating_expdate" => $o_orgrate_expdate
            ];

            $ceoisforeign = 1;
            if ($cust->diridcode == "YY99999999") {
                $ceoisforeign = 0;
            } else {
                $ceoisforeign = 1;
            }

            $o_ceo_civil_id = $this->resolveCivilId($cust->dirid, $cust->dirid2 ?? '', $ceoisforeign);
            $o_ceo_familyname = $this->getAddValue(@$this->providerConfig['o_ceo_familyname'] ?? 'o_ceo_familyname', $cust->id);
            $o_ceo_email = $this->getAddValue(@$this->providerConfig['o_ceo_email'] ?? 'o_ceo_email', $cust->id);
            $o_ceo_address = $this->getAddValue(@$this->providerConfig['o_ceo_address'] ?? 'o_ceo_address', $cust->id);
            $o_ceo_phone = $this->getAddValue(@$this->providerConfig['o_ceo_phone'] ?? 'o_ceo_phone', $cust->id);
            $o_ceo_email = $this->getAddValue(@$this->providerConfig['o_ceo_email'] ?? 'o_ceo_email', $cust->id);

            $o_ceo = [
                "o_ceo_civil_id" => $o_ceo_civil_id,
                "o_ceo_regnum" => $cust->dirid,
                "o_ceo_firstname" => $cust->dirname,
                "o_ceo_lastname" => $cust->dirlname,
                "o_ceo_familyname" => $o_ceo_familyname,
                "o_ceo_isforeign" => $ceoisforeign,
                "o_ceo_address" => mb_substr($o_ceo_address ?? '', 0, 50),
                "o_ceo_phone" => $o_ceo_phone,
                "o_ceo_email" => $o_ceo_email,
            ];

            $o_company_type = "";

            if ($cust->segcode == 23 || $cust->segcode == 25 || $cust->segcode == 31 || $cust->segcode == 71 || $cust->segcode == 73 || $cust->segcode == 75) {
                $o_company_type = "1000"; // ХК
            } else if ($cust->segcode == 24 || $cust->segcode == 26 || $cust->segcode == 32 || $cust->segcode == 72 || $cust->segcode == 74 || $cust->segcode == 76) {
                $o_company_type = "1100"; // ХХК
            } else if ($cust->segcode == 54 || $cust->segcode == 55) {
                $o_company_type = "3031"; // ХЗХ
            } else if ($cust->segcode == 33 || $cust->segcode == 94) {
                $o_company_type = "3000"; // Хоршоо
            } else if ($cust->segcode == 34) {
                $o_company_type = '2000'; // Нөхөрлөл
            } else {
                $o_company_type = "0100"; // Бусад
            }

            $empcount = $cust->empcount;

            $emp = GPInstConst::where('parent_code', 'employees_count')
                ->orderBy('listorder')
                ->get();

            $emp_type = '01';

            foreach ($emp as $range) {
                if ($range->code === 'employees_count_01')
                    continue;

                $min = $range->value_add1;
                $max = $range->value_add2;

                if (($min === null || $empcount >= $min) && ($max === null || $empcount <= $max)) {
                    $emp_type = $range->value;
                    break;
                }
            }

            if ($emp_type === '01') {
                foreach ($emp as $range) {
                    if ($range->code === 'employees_count_01') {
                        $emp_type = $range->value;
                        break;
                    }
                }
            }

            $customer_data = [
                "action" => $custaction,
                "o_state_regnum" => $this->resolveStateRegnum($cust->id1, $cust->id2, $isforeign),
                "o_c_regnum" => $cust->id1,
                "o_c_customer_name" => $cust->name,
                "o_c_isforeign" => $isforeign,
                "o_c_birthdate" => $cust->birthdate,
                "o_c_address" => $o_c_address,
                "o_c_phone" => $mobileno,
                "o_c_email" => $cust->email,
                "o_numof_employee" => $emp_type,
                "o_company_type" => $o_company_type,
                "o_orgrate" => $o_orgrate,
                "o_ceo" => $o_ceo,
                "o_numof_shareholder_org" => count($o_shareholder_org ?? []),
                "o_shareholder_org" => $o_shareholder_org,
                "o_numof_shareholder_customer" => count($o_shareholder_customer ?? []),
                "o_shareholder_customer" => $o_shareholder_customer,
                "o_c_customer_bank_relation" => $o_c_customer_bank_relation
            ];

            return $customer_data;
        }
    }

    /**
     * Хуучин XML протоколоор мэдээлсэн зээлдэгчийн customercode (socialcode + регистр).
     * XML үеийн логик: иргэн → 01 (гадаад) / 02 (дотоод), байгууллага → 03 угтвартай.
     */
    public function legacyCustomerCode($cust, $custtypecode)
    {
        if ($custtypecode == 0) {
            $socialcode = ($cust->id1typecode == 'YY99999999') ? '01' : '02';
        } else {
            $socialcode = '03';
        }
        return $socialcode . $cust->id1;
    }

    /**
     * Хуучин XML loancode үүсгэх.
     * Бүтэц: Зээлийн төрөл(2) + socialcode(2) + регистр + огноо(YYYYMMDDhhmmss)
     *   - энгийн зээл → loantype '01'
     *   - зээлийн шугам → loantype '07'
     * Жишээ: 0101АА1234567820240514091012
     */
    public function legacyXmlLoancode($loanaccount, $cust, $custtypecode, $isLine = false)
    {
        $loantype = $isLine ? '07' : '01';
        $date = preg_replace('/[^0-9]/', '', (string) $loanaccount->starteddate);
        return $loantype . $this->legacyCustomerCode($cust, $custtypecode) . $date;
    }

    /**
     * Хуучин XML зээлийг JSON протоколд таниулах o_c_loan_contractno.
     * Баримтаар: o_c_loan_contractno = data_provider_regnum + XML loancode.
     */
    public function legacyJsonContractno($loanaccount, $cust, $custtypecode, $isLine = false)
    {
        return $this->inst->regno . $this->legacyXmlLoancode($loanaccount, $cust, $custtypecode, $isLine);
    }

    /**
     * Тухайн зээл хуучин XML протоколоор мэдээлэгдсэн (contractno хөрвүүлэх
     * шаардлагатай) эсэх. providerConfig['xml_migration_date']-аас өмнө олгогдсон
     * зээл бол XML-ээр мэдээлэгдсэн гэж үзнэ.
     */
    public function isLegacyXmlLoan($loanaccount)
    {
        $migrationDate = $this->providerConfig['xml_migration_date'] ?? null;
        if (empty($migrationDate)) {
            return false;
        }
        try {
            return Carbon::parse($loanaccount->starteddate)->lt(Carbon::parse($migrationDate));
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * Зээлд илгээх o_c_loan_contractno-г шийднэ:
     *   - Хуучин XML зээл бол → regnum + XML loancode
     *   - Шинэ зээл бол → одоо хадгалагдсан loan_contract_no
     */
    public function resolveLoanContractno($loanaccount, $cust, $custtypecode, $isLine = false)
    {
        if ($this->isLegacyXmlLoan($loanaccount)) {
            return $this->legacyJsonContractno($loanaccount, $cust, $custtypecode, $isLine);
        }
        return $loanaccount->loan_contract_no;
    }

    public function generateLoan($loanaccount, $cust, $custtypecode = null)
    {
        // custtypecode дамжуулаагүй бол моделийн төрлөөр тодорхойлно.
        if (is_null($custtypecode)) {
            $custtypecode = ($cust instanceof CrCustOrg) ? 1 : 0;
        }

        // Зээлийн action-ийг эртхэн тогтоох: 'add' бол хүүхэд (барьцаа,
        // холбогдох иргэн/байгууллага) бүгд 'add' байх ёстой (ЗМС-ийн
        // RTE1003-1012 дүрэм). getAction нь идемпотент тул дараа дахин
        // дуудагдах нь асуудалгүй.
        $loanaction = $loanaccount->action;
        $forceChildAdd = ($loanaction === 'add');

        $curRate = GPInstCurRate::where('rtypecode', "1")->where('curcode', $loanaccount->curcode)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();

        // $morts = LnAccountMor::where('acntno', $loanaccount->acntno)->where('instid', $this->instid)->get();

        $morts = LnAccountMor::where('acntno', $loanaccount->acntno)
            ->where('instid', $this->instid)
            ->whereIn('id', function ($query) use ($loanaccount) {
                $query->selectRaw('MAX(id)')
                    ->from('ln_account_mor')
                    ->where('acntno', $loanaccount->acntno)
                    ->groupBy('morno');
            })
            ->get();
        // Барьцаа хөрөнгө байвал
        if (!empty($morts)) {
            $o_c_loan_collateral_indexes = collect();
            foreach ($morts as $index => $mort) {
                $lnmor = LnMor::where('morno', $mort->morno)->where('statusid', 1)->where('instid', $this->instid)->first();


                $paramactionMort = null;
                if ($mort->statusid != 1) {
                    $paramactionMort = 'delete';
                } elseif ($forceChildAdd) {
                    // Зээлийн action add бол барьцаа хөрөнгөнийх ч add байх ёстой
                    $paramactionMort = 'add';
                }

                $o_c_coll_contractno = $mort->morno . $mort->acntno . $mort->id;

                $o_c_coll_information_action = $this->getAction('o_c_coll_information', $o_c_coll_contractno, $cust->id1, $cust->id1, $mort, $paramactionMort);

                if (isset($o_c_coll_information_action)) {
                    $index++;
                    $o_c_loan_collateral_indexes->push($index);

                    $item = [
                        "action" => $o_c_coll_information_action,
                        "o_c_coll_index" => $index,
                        'o_c_coll_contractno' => $o_c_coll_contractno,
                        'o_c_coll_internalno' => $mort->morno,
                        "o_c_coll_type" => $lnmor->mrtcode . $lnmor->subcode,
                        'o_c_coll_description' => $lnmor->docdesc ?? '',
                        'o_c_coll_valuation_date' => $lnmor->costingdate,
                        'o_c_coll_value' => number_format($lnmor->morprice, 2, '.', ''),
                        'o_c_coll_max_value' => number_format($lnmor->costamount, 2, '.', ''),
                        'o_c_coll_address' => mb_substr(($lnmor->addr1 . ", " . $lnmor->addr2 . ", " . $lnmor->addr3 . ", " . $lnmor->loc) ?? '-', 0, 50),
                        "o_c_coll_zipcode" => $lnmor->zipcode ?? '99999',
                        "o_c_coll_is_real_estate" => $lnmor->mrtcode == "01" ? 1 : 0,
                    ];
                    if ($mort->registered_by == "01") {
                        $item['o_c_coll_state_registration'] = [
                            "o_c_coll_certificateno" => mb_substr(($lnmor->certno ?? $lnmor->regno) ?? '', 0, 16),
                            "o_c_coll_state_regnum" => $lnmor->regno,
                            "o_c_coll_registered_date" => formatDate($mort->registered_date, false),
                            "o_c_coll_confirmed_date" => $mort->txndate != null ? formatDate($mort->txndate, false) : formatDate($mort->registered_date, false),
                        ];
                    } else {
                        $item['o_c_coll_other_registration'] = [
                            "o_c_coll_other_certificateno" => mb_substr(($lnmor->certno ?? $lnmor->regno) ?? '', 0, 16),
                            "o_c_coll_other_regnum" => $lnmor->regno,
                            "o_c_coll_other_name" => $lnmor->name,
                            "o_c_coll_other_registered_date" => formatDate($mort->registered_date, false)
                        ];
                    }

                    $mortowners = LnMorOwner::where('morno', $mort->morno)->where('instid', $this->instid)->where('statusid', '<>', -1)->get()->toArray();

                    // lnmor->custno mortowners жагсаалтад байгаа эсэхийг шалгах
                    $custnoExists = false;
                    foreach ($mortowners as $owner) {
                        if ($owner['custno'] == $lnmor->custno) {
                            $custnoExists = true;
                            break;
                        }
                    }

                    // Хэрэв lnmor->custno байхгүй бол нэмэх
                    if (!$custnoExists) {
                        $mortowners[] = [
                            'morno' => $mort->morno,
                            'custno' => $lnmor->custno,
                            'instid' => $this->instid,
                            'statusid' => 1,
                            'id' => 1,
                        ];
                    }

                    if (!empty($mortowners)) {
                        foreach ($mortowners as $index => $owner) {
                            $custOwner = VwCrCustList::where('custno', $owner['custno'])->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
                            if ($custOwner) {
                                $is_foreign = 0;

                                $paramaction = null;
                                if ($owner['statusid'] != 1) {
                                    $paramaction = 'delete';
                                }

                                if ($o_c_coll_information_action == 'add') {
                                    $paramaction = 'add';
                                }

                                $key = $owner['morno'] . $owner['custno'] . $owner['id'];

                                if ($custOwner->custtypecode == 0) {
                                    $action = $this->getAction('o_c_coll_customer', $key, $cust->id1, $mort->morno, $owner, $paramaction);

                                    if (isset($action)) {
                                        $collCust = CrCustInd::where('custno', $custOwner->custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();

                                        if ($collCust) {
                                            $is_foreign = 0;
                                            if (!in_array($collCust->segcode, [81, "81", 83, "83"], true)) {
                                                $is_foreign = 1;
                                            }

                                            $item['o_c_coll_customer'][] = [
                                                "action" => $action,
                                                "o_c_coll_customer_firstname" => $custOwner->name,
                                                "o_c_coll_customer_lastname" => $custOwner->lname,
                                                "o_c_coll_customer_familyname" => $collCust->familyname,
                                                "o_c_coll_customer_isforeign" => $is_foreign,
                                                "o_c_coll_customer_civil_id" => $this->resolveCivilId($custOwner->id1, $collCust->id2, $is_foreign),
                                                "o_c_coll_customer_regnum" => $custOwner->id1
                                            ];
                                        }
                                    }
                                } else {
                                    $collOrg = CrCustOrg::where('custno', $custOwner->custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
                                    $paramaction = null;
                                    if ($owner['statusid'] != 1) {
                                        $paramaction = 'delete';
                                    }

                                    if ($o_c_coll_information_action == 'add') {
                                        $paramaction = 'add';
                                    }
                                    if ($collOrg) {
                                        $is_foreign = 0;
                                        if ($collOrg->countrycode != 496 && $collOrg->countrycode != "496") {
                                            $is_foreign = 1;
                                        }

                                        $action = $this->getAction('o_c_coll_org', $key, $cust->id1, $mort->morno, $owner, $paramaction);

                                        if (isset($action)) {
                                            $item['o_c_coll_org'][] = [
                                                "action" => $action,
                                                "o_c_coll_org_name" => $custOwner->name,
                                                "o_c_coll_org_isforeign" => $is_foreign,
                                                "o_c_coll_org_regnum" => $custOwner->id1,
                                                "o_c_coll_org_state_regnum" => $this->resolveStateRegnum($custOwner->id1, $collOrg->id2, $is_foreign)
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $this->o_c_coll_information[] = $item;
                }
            }
        }

        $ln_custs = LnAccountCust::where('acntno', $loanaccount->acntno)
            ->where('instid', $this->instid)
            ->whereIn('statusid', function ($query) use ($loanaccount) {
                $query->selectRaw('MAX(statusid)')
                    ->from('ln_account_cust')
                    ->where('acntno', $loanaccount->acntno)
                    ->groupBy('custno');
            })
            ->get();

        $o_c_related_customer_indexes = collect();
        $o_c_related_org_indexes = collect();

        foreach ($ln_custs as $ln_cust) {
            $vwcust = VwCrCustList::where('custno', $ln_cust->custno)->where('instid', $this->instid)->where('statusid', '<>', -1)->first();
            $is_foreign = 0;
            if ($vwcust->custtypecode == 0) {
                $relatedcust = CrCustInd::where('custno', $ln_cust->custno)->where('statusid', '<>', -1)->where('instid', $this->instid)->first();
                if (!in_array($relatedcust->segcode, [81, "81", 83, "83"], true)) {
                    $is_foreign = 1;
                }
                $relation = CrCustRelation::where('custid2', $relatedcust->id)->where('custid', $cust->id)->where('custid2typecode', 0)->where('reltypecode', '2')->where('statusid', '<>', -1)->where('instid', $this->instid)->first();
                $o_c_related_customer_isfinancial_onus = 0;

                if (isset($relation)) {
                    $o_c_related_customer_isfinancial_onus = 1;
                }



                $paramaction = null;
                if ($ln_cust->statusid != 1) {
                    $paramaction = 'delete';
                } elseif ($forceChildAdd) {
                    // Зээлийн action add бол хамтран зээлдэгчийн ч add байх ёстой
                    $paramaction = 'add';
                }

                $action = $this->getAction('o_c_related_customers', $relatedcust->id, $cust->id1, $loanaccount->acntno, $ln_cust, $paramaction);
                if (isset($action)) {
                    $o_c_related_customer_indexes->push(count($this->o_c_related_customers ?? []));

                    $this->o_c_related_customers[] = [
                        "action" => $action,
                        "o_c_related_customer_index" => count($this->o_c_related_customers ?? []),
                        "o_c_related_customer_firstname" => $relatedcust->name,
                        "o_c_related_customer_lastname" => $relatedcust->lname,
                        "o_c_related_customer_familyname" => $relatedcust->familyname,
                        "o_c_related_customer_isforeign" => $is_foreign,
                        "o_c_related_customer_civil_id" => $this->resolveCivilId($relatedcust->id1, $relatedcust->id2, $is_foreign),
                        "o_c_related_customer_regnum" => $relatedcust->id1,
                        "o_c_related_customer_relation" => $ln_cust->rolecode == 1 ? "07" : "09", // Annex 3;  07 - Хамтран зээлдэгч, 09 - Батлан даагч
                        "o_c_related_customer_isfinancial_onus" => $o_c_related_customer_isfinancial_onus,
                    ];
                }
            } else {
                $relatedorg = CrCustOrg::where('custno', $ln_cust->custno)->where('statusid', '<>', -1)->where('instid', $this->instid)->first();
                if ($relatedorg->countrycode != 496 && $relatedorg->countrycode != "496") {
                    $is_foreign = 1;
                }
                $o_c_related_org_isfinancial_onus = 0;


                $paramaction = null;
                if ($ln_cust->statusid != 1) {
                    $paramaction = 'delete';
                } elseif ($forceChildAdd) {
                    // Зээлийн action add бол холбогдох байгууллагын ч add байх ёстой
                    $paramaction = 'add';
                }

                $action = $this->getAction('o_c_related_orgs', $relatedorg->id, $cust->id1, $loanaccount->acntno, [], $paramaction);

                if (isset($action)) {
                    $o_c_related_org_isfinancial_onus = 1;

                    $o_c_related_org_indexes->push(count($this->o_c_related_orgs ?? []));

                    $this->o_c_related_orgs[] = [
                        "action" => $action,
                        "o_c_related_org_index" => count($this->o_c_related_orgs ?? []),
                        "o_c_related_org_name" => $relatedorg->name,
                        "o_c_related_org_isforeign" => $is_foreign,
                        "o_c_related_org_regnum" => $relatedorg->id1,
                        "o_c_related_org_state_regnum" => $this->resolveStateRegnum($relatedorg->id1, $relatedorg->id2, $is_foreign),
                        "o_c_related_org_relation" => $ln_cust->rolecode == 1 ? "07" : "09", // Annex 3;  07 - Хамтран зээлдэгч, 09 - Батлан даагч
                        "o_c_related_org_isfinancial_onus" => $o_c_related_org_isfinancial_onus,
                    ];
                }
            }
        }

        $loanaction = $loanaccount->action;

        $o_c_loan_schedule = collect();
        $o_c_loan_payment = collect();

        $schedules = LnNrs::where('acntno', $loanaccount->acntno)->where('instid', $this->instid)->where('statusid', '<>', -1)->get();

        // Зээлийн хуваарь дахин тооцогдоход ln_schd мөрүүд шинэ id авдаг тул хуучин
        // (одоо байхгүй) хуваарийн action мөрүүд хуримтлагдсаар байдаг. Тэдгээрийг
        // (одоогийн хуваарийн id-д ороогүйг) идэвхгүй болгож цэвэрлэнэ.
        $scheduleRenewed = false;
        if ($schedules->isNotEmpty()) {
            $currentScheduleIds = $schedules->pluck('id')->map(fn($id) => (string) $id)->all();

            // ЭТХ шинэчлэгдсэн эсэхийг илрүүлэх:
            // Хуваарь өмнө нь бүртгэгдсэн (амжилттай 2 эсвэл хүлээгдэж буй 1/3/4)
            // боловч одоогийн ln_schd id жагсаалтад орохгүй идэвхтэй action байгаа бол
            // → шинэ хуваарь үүссэн (re-amortization).
    
            $scheduleRenewed = AdCreditInfoBueroAction::where('type', 'o_c_loan_schedule')
                ->where('parent_key', $loanaccount->acntno)
                ->where('regno', $cust->id1)
                ->where('instid', $this->instid)
                ->where('statusid', '<>', -1)
                ->whereNotIn('key', $currentScheduleIds)
                ->exists();

            AdCreditInfoBueroAction::where('type', 'o_c_loan_schedule')
                ->where('parent_key', $loanaccount->acntno)
                ->where('regno', $cust->id1)
                ->where('instid', $this->instid)
                ->where('statusid', '<>', -1)
                ->whereNotIn('key', $currentScheduleIds)
                ->update(['statusid' => -1, 'updated_by' => $this->userid]);
        }

        // Хуваарийн action-уудыг урьдчилан ачаалж, амжилттай илгээгдсэнийг (statusid = 2)
        // ялгахад ашиглана. Амжилттай илгээгдсэн хуваарийг дахин илгээх шаардлагагүй.
        $sentScheduleKeys = AdCreditInfoBueroAction::where('type', 'o_c_loan_schedule')
            ->where('parent_key', $loanaccount->acntno)
            ->where('regno', $cust->id1)
            ->where('instid', $this->instid)
            ->where('statusid', 2)
            ->pluck('key')
            ->map(fn($k) => (string) $k)
            ->all();

        $hasAdd = false;
        $hasUpdate = false;
        foreach ($schedules as $schedule) {

            /// Зээлдэгчийн мэдээллийг ADD хийх үед o_c_loan_schedule action ADD байна
            if ($loanaction == "update") {
                $loanaction = null;
            }

            // Өмнө нь амжилттай илгээгдсэн (statusid = 2) бөгөөд ЭТХ шинэчлэгдээгүй бол
            // тухайн хуваарийг дахин илгээхгүй. Гэвч зээлийн action 'add' байвал заавал илгээнэ.
            if (!$forceChildAdd && !$scheduleRenewed && in_array((string) $schedule->id, $sentScheduleKeys, true)) {
                continue;
            }

            $action = $this->getAction('o_c_loan_schedule', $schedule->id, $cust->id1, $loanaccount->acntno, $schedule, $loanaction);
            if (isset($action)) {
                if ($action == "add") {
                    $hasAdd = true;
                } else {
                    $hasUpdate = true;
                }


                $o_c_loan_schedule->push(
                    [
                        "action" => $action,
                        "o_c_schedule_due_date" => $schedule->payday,
                        "o_c_schedule_principal" => number_format($schedule->payamount < 0 ? 0 : $schedule->payamount, 2, '.', ''),
                        "o_c_schedule_interest" => number_format($schedule->intamount < 0 ? 0 : $schedule->intamount, 2, '.', ''),
                        "o_c_schedule_additional" => number_format(0, 2, '.', ''),
                        "o_c_schedule_balance" => number_format($schedule->theorbal < 0 ? 0 : $schedule->theorbal, 2, '.', ''),
                    ]
                );
            }
        }

        // Төлөлтийн "төлөх ёстой огноо"-г тооцоход хуваарийн БҮРЭН жагсаалт хэрэгтэй
        // (илгээх/үл илгээхээс үл хамааран). Иймд $o_c_loan_schedule (шүүгдсэн)-ийг бус
        // бүх ln_schd мөрийг payday-аар эрэмбэлж ашиглана.
        $fullScheduleForDue = $schedules
            ->sortBy('payday')
            ->map(fn($s) => [
                'due_date' => $s->payday,
                'principal' => (float) $s->payamount,
            ])
            ->values();

        // o_c_loan_schedule_status:
        //   - Хуваарь шинэчлэгдсэн (өмнө илгээгдсэн хуваарь байж шинэ хуваарь үүссэн) бол 1
        //   - Үгүй бол 0
        $o_c_loan_schedule_status = $scheduleRenewed ? 1 : 0;
        if ($o_c_loan_schedule_status == 1) {
            // Шинэчлэлт үед бүх хуваарийн мөрийг 'add' болгож дахин илгээнэ.
            $o_c_loan_schedule = $o_c_loan_schedule->map(function ($lschd) {
                $lschd['action'] = 'add';
                return $lschd;
            });
        }

        /**
         * Зээл төлөх
         * Бэлнээр төлөх - ln902010
         * Бэлэн бусаар төлөх - ln902011
         * Хүү урьдчилан төлөх бэлнээр - ln902036
         * Хүү урьдчилан төлөх бэлэн бусаар - ln902037
         * Зээлийн данс хаах бэлнээр - ln902090
         * Зээлийн данс хаах бэлэн бусаар - ln902091
         */

        // $payments = LnTxn::where('acntno', $loanaccount->acntno)->whereIn('txncode', ['ln902010', 'ln902011', 'ln902036', 'ln902037', 'ln902090', 'ln902091'])->where('instid', $this->instid)->where('statusid', '<>', -1)->get();
        $payments = DB::table('ln_txn as a')
            ->select(
                DB::raw("to_char(a.postdate, 'YYYY-MM-DD HH24:MI:SS.US') as postdate"),
                'a.jrno',
                'a.acntno',
                DB::raw("ROUND(SUM(CASE WHEN a.txncode IN ('ln902010','ln902011','ln802011') THEN a.txnamount ELSE 0 END)::numeric, 2) as o_c_payment_principal"),
                DB::raw("ROUND(SUM(CASE WHEN a.txncode IN ('ln902030','ln902031','ln902036','ln902037') THEN a.txnamount ELSE 0 END)::numeric, 2) as o_c_payment_interest"),
                DB::raw("ROUND(SUM(CASE WHEN a.txncode IN ('ln902032','ln902033','ln902034','ln902035') THEN a.txnamount ELSE 0 END)::numeric, 2) as o_c_payment_additional")
            )
            ->where('a.instid', $this->instid)
            ->where('a.corr', '<>', 1)
            ->where('a.acntno', $loanaccount->acntno)
            ->whereIn('a.txncode', ['ln902010', 'ln902011', 'ln802011', 'ln902030', 'ln902031', 'ln902036', 'ln902037', 'ln902032', 'ln902033', 'ln902034', 'ln902035', 'ln902090', 'ln902091'])
            ->groupBy('a.jrno', 'a.postdate', 'a.acntno')
            ->get();

        $paidamount = 0;

        foreach ($payments as $payment) {
            $o_c_payment_due_date = null;
            // Төлөх ёстой огноог олох
            $paidamount = $paidamount + ($payment->o_c_payment_principal ?? 0);
            $scheduleAmount = 0;
            foreach ($fullScheduleForDue as $schedule) {

                $scheduleAmount = $scheduleAmount + $schedule['principal'];
                if ($paidamount < $scheduleAmount) {
                    $o_c_payment_due_date = $schedule['due_date'];
                    break;
                }
            }

            if ($o_c_payment_due_date === null && $fullScheduleForDue->isNotEmpty()) {
                $o_c_payment_due_date = $fullScheduleForDue->last()['due_date'];
            }

            /// Зээлдэгчийн мэдээллийг ADD хийх үед o_c_loan_payment action ADD байна
            if ($loanaction == "update") {
                $loanaction = null;
            }

            $action = $this->getAction('o_c_loan_payment', $payment->jrno, $cust->id1, $loanaccount->acntno, $payment, $loanaction);

            if ($o_c_loan_schedule_status == 1) {
                $action = 'add';
            }

            // Зөвхөн add төрөлтэй төлөлтийн мэдээлэл илгээнэ
            if (isset($action) && $action == 'add') {
                $o_c_loan_payment->push(
                    [
                        "action" => $action,
                        "o_c_payment_date" => $payment->postdate,
                        "o_c_payment_due_date" => $o_c_payment_due_date,
                        "o_c_payment_principal" => number_format(($payment->o_c_payment_principal ?? 0) < 0 ? 0 : ($payment->o_c_payment_principal ?? 0), 2, '.', ''),
                        "o_c_payment_interest" => number_format(($payment->o_c_payment_interest ?? 0) < 0 ? 0 : ($payment->o_c_payment_interest ?? 0), 2, '.', ''),
                        "o_c_payment_additional" => number_format(($payment->o_c_payment_additional ?? 0) < 0 ? 0 : ($payment->o_c_payment_additional ?? 0), 2, '.', ''),
                    ]
                );
            }
        }

        $o_c_loan_schedule_type = '01'; // Annex 12 - 01 Бусад төлөлтийн хэлбэр



        // Хуучин XML зээл бол o_c_loan_contractno-г regnum + XML loancode болгож хөрвүүлнэ.
        $isLine = ($loanaccount->type == CreditInfoBueroTypeEnum::line);
        $o_c_loan_contractno = $this->resolveLoanContractno($loanaccount, $cust, $custtypecode, $isLine);

        $loan = [
            "action" => $loanaccount->action,
            "o_c_loan_contract_date" => Carbon::parse($loanaccount->loan_contract_date)->format('Y-m-d'),
            "o_c_loan_contractno" => $o_c_loan_contractno,
            "o_c_loan_contract_change_reason" => $loanaccount->loan_contract_change_reason,
            "o_c_loan_amount_lcy" => number_format($loanaccount->advamount * $curRate->buyrate, 2, '.', ''),
            "o_c_loan_amount_fcy" => number_format($loanaccount->advamount, 2, '.', ''),
            "o_c_loan_balance_lcy" => number_format($loanaccount->balance * $curRate->buyrate, 2, '.', ''),
            "o_c_loan_balance_fcy" => number_format($loanaccount->balance, 2, '.', ''),
            "o_c_loan_acntno" => $loanaccount->acntno,
            "o_c_loan_collateral_indexes" => $o_c_loan_collateral_indexes,
            "o_c_loan_related_org_indexes" => $o_c_related_org_indexes,
            "o_c_loan_related_customer_indexes" => $o_c_related_customer_indexes,
            "o_c_loan_interest_balance_lcy" => number_format($loanaccount->loan_int_balance * $curRate->buyrate, 2, '.', ''),
            "o_c_loan_interest_balance_fcy" => number_format($loanaccount->loan_int_balance, 2, '.', ''),
            "o_c_loan_additional_interest_balance_lcy" => number_format($loanaccount->loan_additional_int_balance * $curRate->buyrate, 2, '.', ''),
            "o_c_loan_additional_interest_balance_fcy" => number_format($loanaccount->loan_additional_int_balance, 2, '.', ''),
            "o_c_loan_currency_rate" => number_format($curRate->buyrate, 2, '.', ''),
            "o_c_loan_loan_provenance" => $loanaccount->loanprovenance,
            "o_c_loan_starteddate" => $loanaccount->starteddate,
            "o_c_loan_expdate" => $loanaccount->expiredate,
            "o_c_loan_status" => $loanaccount->status,
            "o_c_loan_decide_status" => $loanaccount->loan_decide_status,
            "o_c_loan_paiddate" => $loanaccount->loan_paid_date,
            "o_c_loan_currency" => $loanaccount->curcode,
            "o_c_loan_sector" => $loanaccount->sectorcode,
            "o_c_loan_interest_rate" => number_format($loanaccount->interestinperc ?? 0, 2, '.', ''),
            "o_c_loan_additional_interest_rate" => number_format(($loanaccount->loan_additional_interest ?? 0) * 1, 2, '.', ''),
            "o_c_loan_commission" => number_format(($loanaccount->commissionperc ?? 0) * 1, 2, '.', ''),
            "o_c_loan_fee" => $loanaccount->fee,
            "o_c_loan_class" => $loanaccount->loanclasscode,
            "o_c_loan_type" => $loanaccount->type == CreditInfoBueroTypeEnum::line ? "A02" : $loanaccount->loanintype,
            "o_c_loan_line_contractno" => "",
            "o_c_loan_transactions" => [
                "o_c_loan_schedule_type" => $o_c_loan_schedule_type,
                "o_c_loan_schedule_status" => $o_c_loan_schedule_status,
                "o_c_loan_schedule_change_reason" => ($o_c_loan_schedule_status == 1) ? "ЭТХ шинэчлэв" : "", // TODO
                "o_c_loan_schedule" => $o_c_loan_schedule,
                "o_c_loan_payment" => $o_c_loan_payment,
            ]
        ];



        return ['loan' => $loan,];
    }

    public function sendLoanInfoToZms($acntno, $AC)
    {
        if (in_array($AC, (@$this->providerConfig['ActionCodes'] ?? []))) {
            if ($acntno) {
                $this->sendData(1, $acntno);
            }
        }
    }

    public function isOnSendZMSJob()
    {
        return app(\App\Services\QueueJobInspector::class)
            ->has('sendZMS', SendBueroJob::class, auth()->user()->instid);
    }
}
