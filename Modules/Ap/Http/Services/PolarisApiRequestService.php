<?php

namespace Modules\Ap\Http\Services;

use App\Exceptions\MeException;
use App\Models\ConnConf;
use App\Models\CorrSys;
use App\Models\LogRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SimpleException;
use Modules\Gp\Entities\GPConnConf;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\GPProviderConf;
use Modules\Gp\Http\Services\CoreService;

class PolarisApiRequestService
{
    private $nessession = '';
    private $company = '';
    private $role = '';
    private $host = '';
    private $lang = '';
    public $internalAccount = '';
    public $repay_susp_accountno = '';
    public $brchCode = '';
    public $savingLoan;
    public $savingTd;
    public $is_use_cust_susp_acnt;
    public $susp_acnt_prod_code;
    public $cgw;
    public $fee;
    function __construct($instid = 0)
    {
        try {
            $this->initConnection($instid);
        } catch (Exception $exp) {
            Log::error($instid . " дугаартай байгууллага дээр суурь системийн тохиргоо буруу байна." . $exp);
            return [
                'data' => [],
                'status' => 200
            ];
        }
    }

    public function initConnection($instid = 0)
    {
        if ($instid == 0) {
            // $pp = CorrSys::where('typeid', '01')->where('instid', auth()->user()->instid)->first();
            $instid = auth()->user()->instid;
        }
        $pp = GPProviderConf::where('code', 2)
            ->where('instid', $instid)
            ->where('statusid', 1)->first();

        if ($pp) {
            $corr_system = json_decode($pp->config);
            $this->nessession = $corr_system->cookie ?? '';
            $this->company = $corr_system->company ?? '';
            $this->role = $corr_system->role ?? '';
            $this->lang = $corr_system->lang ?? '';
            $this->internalAccount = $corr_system->internalAccount;
            $this->repay_susp_accountno = $corr_system->repay_susp_accountno;
            $this->brchCode = $corr_system->brchCode;
            $this->savingTd = $corr_system->savingTd;
            if (isset($corr_system->savingLoan)) {
                $this->savingLoan = $corr_system->savingLoan;
            } else {
                throw new MeException('Суурь системийн тохиргоо дээр savingLoan талбар утгагүй байна.');
            }
            if (isset($corr_system->is_use_cust_susp_acnt)) {
                $this->is_use_cust_susp_acnt = $corr_system->is_use_cust_susp_acnt;
            } else {
                throw new MeException('Суурь системийн тохиргоо дээр is_use_cust_susp_acnt талбар утгагүй байна.');
            }
            if (isset($corr_system->susp_acnt_prod_code)) {
                $this->susp_acnt_prod_code = $corr_system->susp_acnt_prod_code;
            } else {
                throw new MeException('Суурь системийн тохиргоо дээр susp_acnt_prod_code талбар утгагүй байна.');
            }
            if (isset($corr_system->cgw)) {
                $this->cgw = $corr_system->cgw;
            } else {
                throw new MeException('Суурь системийн тохиргоо дээр cgw талбар утгагүй байна.');
            }
            if (isset($corr_system->fee)) {
                $this->fee = $corr_system->fee;
            } else {
                throw new MeException('Суурь системийн тохиргоо дээр fee талбар утгагүй байна.');
            }
            // if (isset($corr_system->host)) {
            //     $this->host = $corr_system->host;
            // } else {
            //     throw new SimpleException("Polaris ГСМ суурь системд холболтын тохиргоо холбоно уу!");
            // }
            $connConf = GPConnConf::where("id", $pp->connid)->first();
            if (empty($connConf)) {
                throw new MeException("RC000174");
            }
            if (!$conn = json_decode($connConf->config)) {
                throw new MeException("RC000174");
            }
            $this->host = @$conn->host;
        } else {
            throw new MeException("RC000173", [
                'inst' => $instid,
                'code' => '2'
            ]);
        }
    }

    public function sendRequest($operation, $params, $instid = 0)
    {
        if (empty($this->nessession)) {
            $this->initConnection($instid);
        }
        $startTime = Carbon::now()->getTimestampMs();
        $user = auth()->user();
        $header = [
            'Content-Type' => 'application/json',
            // 'Content-Length' => 14,
            'Cookie' => $this->nessession,
            'op' => $operation,
            'company' => $this->company,
            'role' => $this->role,
            'lang' => $this->lang,
        ];
        $r = new GPLogRequestList();
        $r->userid = $user ? $user->userid : 1;
        $r->url = $this->host;
        $r->method = 'POST';
        $r->request = json_encode([
            'operation' => $operation,
            'data' => $params,
            'header' => $header
        ], JSON_UNESCAPED_UNICODE);
        $r->save();
        try {
            $response = Http::withHeaders(
                $header
            )->timeout(20)->post($this->host, $params);
            // Log::debug($response);
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
            // Log::debug($data);
            throw new MeException($data);
        }
        // return response()->json($data, $response->status());
        return $data;
    }

    public function getDate($instid)
    {
        return $this->sendRequest(13619000, [], $instid);
    }
}
