<?php

namespace Modules\Ad\Http\Services;

use Exception;
use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Modules\Ad\Entities\AdCreditInfoBueroHist;

use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;

use Modules\Gp\Entities\GPLogRequestList;


class AdZmsService
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public $instid;
    public $userid;
    public $provider;
    public $adCreditInfoBueroHist;
    public $providerConfig = [];
    public $connection;
    public $acntno;

    public  $o_c_related_orgs = [];
    public  $o_c_related_customers = [];
    public  $o_c_coll_information = [];

    public function __construct($instid, $userid, $acntno = null)
    {
        $this->instid = $instid;
        $this->userid = $userid;
        $this->acntno = $acntno;

        $this->adCreditInfoBueroHist = AdCreditInfoBueroHist::where('instid', $this->instid)->orderBy('lastexecuteddate', 'DESC')->first();

        $this->provider = VwGPProviderConf::where('code', '21')->where('instid', $instid)->where('statusid', '<>', -1)->first();
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
                'code' => '21'
            ]);
        }
    }

    public function getFeeCode()
    {
        return isset($this->providerConfig['inquiry_feecode']) ? $this->providerConfig['inquiry_feecode'] : null;
    }

    public function getConnection()
    {
        return isset($this->connection) ? $this->connection : null;
    }

    public function post($data, $AC = 'in9410', $is_multi_part = false, $files = [], $fieldname = null)
    {
        // $AC = 'in9410'; // Лавлагаа авах
        if ($AC == 'in9410') {
            $data['acnttypeid'] = $this->providerConfig['acnttypeid'] ?? '01';
        }
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();
        $r->userid =  $this->userid;
        $r->instid =  $this->instid;
        $r->url = $this->connection['url_sainscore'] . '/v2/process';
        $r->method = 'POST';
        $r->AC = $AC;
        $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
        $r->save();
        try {

            if ($is_multi_part) {
                $multiPart = [];

                foreach ($data as $key => $value) {
                    $multiPart[] = ['name' => $key, 'contents' => $value];
                }
                foreach ($files as $file) {
                    $multiPart[] = [
                        'name' => $fieldname,
                        'contents' => fopen($file->getPathname(), 'r'),
                        'filename' => $file->getClientOriginalName()
                    ];
                }

                $response = Http::withHeaders([
                    'X-Username' => $this->providerConfig['username'],
                    'X-Signature' => safeDecrypt($this->provider['sec1']),
                    'AC' => $AC
                ])
                    ->timeout(120)
                    ->asMultipart()
                    ->post($this->connection['url_sainscore'] . '/v2/process', $multiPart);
            } else {
                $response = Http::withHeaders(
                    [
                        'X-Username' => $this->providerConfig['username'],
                        'X-Signature' => safeDecrypt($this->provider['sec1']),
                        'Content-Type' => 'application/json',
                        'AC' => $AC
                    ]
                )->timeout(120)->post($this->connection['url_sainscore'] . '/v2/process', $data);
            }
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
}
