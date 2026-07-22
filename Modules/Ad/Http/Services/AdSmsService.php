<?php

namespace Modules\Ad\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GPLogRequestList;
use Modules\Gp\Entities\Views\VwGPConnConf;
use Modules\Gp\Entities\Views\VwGPProviderConf;

class AdSmsService
{

    private $smsprovider = null;
    private $conn = null;

    public function __construct($instid)
    {

        $pp = VwGPProviderConf::where("code", "3")->where('instid', $instid)->first();

        if (!$pp) {
            throw new MeException('RC000171');
        }

        $connConf = VwGPConnConf::where("id", $pp->connid)->where('instid', $instid)->first();

        if (!$this->conn = json_decode($connConf->config)) {
            throw new MeException('RC000170');
        }

        $this->smsprovider = json_decode($pp->config);
        if (!$this->smsprovider) {
            throw new MeException('RC000171');
        }
    }

    public function getOperator($phonenumber)
    {
        foreach ($this->smsprovider as $key => $provider) {
            foreach ($provider->prefix as $prefix) {
                if (Str::startsWith($phonenumber, $prefix)) {
                    return $key;
                }
            }
        }
        return 'callpro';
    }

    public function sendSms($phonenumber, $message)
    {
        $startTime = Carbon::now()->getTimestampMs();
        $operator = $this->getOperator($phonenumber);
        if (isset($this->conn->$operator) && isset($this->conn->$operator->url)) {
            $user = auth()->user();
            $url = Str::replace('{username}', $this->smsprovider->$operator->username, $this->conn->$operator->url);
            $url = Str::replace('{phone}', $this->smsprovider->$operator->phone, $url);
            $url = Str::replace('{password}', $this->smsprovider->$operator->password, $url);
            $url = Str::replace('{user_phone}', $phonenumber, $url);
            $url = Str::replace('{msg}', $message, $url);
            $r = new GPLogRequestList();
            $r->userid = $user ? $user->userid : 1;
            $r->url = $url;
            $r->method = 'POST';
            $r->save();
            try {
                if ($operator == 'unitel') {
                    $url = Str::replace('{enc}', $this->smsprovider->$operator->enc, $url);
                    $data = ['to' => $phonenumber, 'message' => $message];
                    $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $response = Http::post($url, $data);
                    $r->response = @json_encode($response->body(), JSON_UNESCAPED_UNICODE);
                    $r->responsecode = $response->status();
                } else if ($operator == 'callpro') {
                    $apiKey = $this->smsprovider->$operator->password ?? $this->smsprovider->$operator->key ?? $this->smsprovider->$operator->api_key ?? '';
                    $fromNumber = $this->smsprovider->$operator->phone ?? '133888';
                    $data = [
                        'from' => $fromNumber,
                        'to' => $phonenumber,
                        'text' => $message
                    ];
                    $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $response = Http::withHeaders([
                        'x-api-key' => $apiKey
                    ])->post($url, $data);
                    $r->response = @json_encode($response->body(), JSON_UNESCAPED_UNICODE);
                    $r->responsecode = $response->status();
                } else {
                    $response = Http::get($url);
                    $r->response = @json_encode($response->body(), JSON_UNESCAPED_UNICODE);
                    $r->responsecode = $response->status();
                }
            } catch (Exception $ex) {
                $r->response = $ex->getMessage();
                $r->responsecode = 500;
            }
            $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
            $r->save();
            if ($r->responsecode != 200) {
                throw new MeException($r->responsecode . ' - ' . $r->getMessage());
            }
        } else {
            throw new MeException('RC000172');
        }
    }
}
