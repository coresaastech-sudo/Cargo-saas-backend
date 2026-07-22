<?php

namespace Modules\Gp\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstCurRate;
use Modules\Gp\Entities\GpInstCurRateHist;
use Modules\Gp\Http\Requests\GpInstCurRateRequest;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpConnConf;
use Modules\Gp\Entities\GpInstCur;
use Modules\Gp\Entities\GpLogRequestList;
use Modules\Gp\Entities\GpActionCode;
use Modules\Gp\Entities\Views\VwGpInstCurRate;
use Modules\Gp\Entities\Views\VwGpInstCurRateSidebar;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\TrCurRateHist;

class GpInstCurRateController extends Controller
{

    private string $ratecachekey = 'changedcurcoderate_';
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwGpInstCurRate::where('instid', auth()->user()->instid)
                ->where('statusid', 1),
            [['field' => 'listorder', 'dir' => 'ASC']]
        );
    }

    /**
     * indexSideBar
     *
     * @param  mixed $request
     * @AC gp013502
     * @return void
     */
    public function indexSideBar(Request $request)
    {
        $v = $this->validate($request, [
            'rtypecode' => 'required'
        ]);
        $data = VwGpInstCurRateSidebar::where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->where('rtypecode', $v['rtypecode'])
            ->orderBy('listorder', 'ASC')->get();
        return $data;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstCurRateRequest $request)
    {
        $validated = $request->validated();
        $userid = auth()->user()->id;
        $instid = auth()->user()->instid;
        $validated['statusid'] = 1;
        if (!isset($validated['instid']) || auth()->user()->isadmin != 1) {
            $validated['instid'] = $instid;
        }


        $GPinstcurrate = null;
        try {
            DB::beginTransaction();
            if (!empty($validated['id'])) {
                $GPinstcurrate = GpInstCurRate::where('instid', $instid)->where('statusid', 1)
                    ->where("id", $validated['id'])->first();
                if ($GPinstcurrate) {
                    $validated['updated_by'] = $userid;
                    $GPinstcurrate->update($validated);
                } else {
                    $validated['created_by'] = $userid;
                    $validated['updated_by'] = $userid;
                    $GPinstcurrate = GpInstCurRate::create($validated);
                }
            } else {
                $GPinstcurrate = GpInstCurRate::where('instid', $instid)
                    ->where('curcode', $validated['curcode'])
                    ->where('rtypecode', $validated['rtypecode'])
                    ->where('statusid', 1)->first();
                if ($GPinstcurrate) {
                    $validated['updated_by'] = $userid;
                    $GPinstcurrate->update($validated);
                } else {
                    $validated['created_by'] = $userid;
                    $validated['updated_by'] = $userid;
                    $GPinstcurrate = GpInstCurRate::create($validated);
                }
            }
            $validated['created_by'] = $userid;
            $validated['updated_by'] = $userid;
            $validated['date'] = CoreService::getTxnDate($instid);

            GpInstCurRateHist::create($validated);
            $avg = GpInstCurRate::select(DB::raw('avg(salerate + buyrate)/2 avgrate'))
                ->where('curcode', $validated['curcode'])
                ->where('instid', $instid)
                ->where('statusid', 1)->first();
            GpInstCur::where('curcode', $validated['curcode'])
                ->where('instid', $instid)
                ->where('statusid', 1)->update([
                    'avgrate' => $validated['avgrate'],
                    'avgrateend' => $validated['avgrateend'],
                    'midrate' => $avg->avgrate,
                    'updated_by' => $userid
                ]);
            $dupl = TrCurRateHist::where('date', $validated['date'])
                ->where('curcode', $validated['curcode'])
                ->where('instid', $instid)->first();

            if ($dupl) {
                $dupl->updated_by = $userid;
                $dupl->avgrate = $validated['avgrate'];
                $dupl->avgrateend = $validated['avgrateend'];
                $dupl->save();
            } else {
                TrCurRateHist::create([
                    'date' =>  $validated['date'],
                    'curcode' =>  $validated['curcode'],
                    'avgrate' =>  $validated['avgrate'],
                    'avgrateend' =>  $validated['avgrateend'],
                    'statusid' =>  1,
                    'instid' =>  $instid,
                    'created_by' =>  $userid,
                    'updated_by' =>  $userid
                ]);
            }
            DB::commit();
            $ratedata = [
                'buyrate' => $validated['buyrate'],
                'salerate' => $validated['salerate'],
                'curcode' => $validated['curcode'],
                'id' => $GPinstcurrate->id,
            ];
            $key = $this->ratecachekey . $instid;
            if (!Cache::has($key)) {
                Cache::put($key, [$GPinstcurrate->id => $ratedata]);
            } else {
                $cacheKeys = Cache::get($key);
                $cacheKeys[$GPinstcurrate->id] = $ratedata;
                Cache::put($key, $cacheKeys);
            }
            return $GPinstcurrate->id;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinstcurrate = VwGpInstCurRate::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if ($GPinstcurrate) {
            return $GPinstcurrate;
        } else {
            $this->error("RC000021");
        }
    }

    /**
     * Cache цэвэрлэх
     * @AC gp013902
     *
     * @return void
     */
    public function activate()
    {
        $user = auth()->user();
        $instid = $user->instid;
        $key = $this->ratecachekey . $instid;
        if (Cache::has($key)) {
            $cacheKeys = Cache::get($key);
            foreach ($cacheKeys as $key => $value) {
                event(new \App\Events\CurRateEvent($value, $user));
            }
        }

        Cache::forget($key);

        CoreService::clearCacheDataWithGroup(
            $instid,
            CacheGroupEnum::GP_inst_cur_rate
        );
    }

    /**
     * Монгол банкнаас валютын ханш татах
     * @AC gp013602
     *
     * @return array
     * @throws MeException
     */
    public function getBomRate()
    {
        try {
            $instid = auth()->user()->instid;
            // Тохиргооны мэдээллийг авах
            $connConf = GpConnConf::where('code', 'BOM')
                ->where('instid', 1)
                ->first();

            if (!$connConf) {
                throw new MeException('RC000203', ['type' => 'BOM']);
            }

            $conn = json_decode($connConf->config);
            if (!$conn) {
                throw new MeException('RC000203', ['type' => 'BOM_CONFIG']);
            }

            $sysDate = CoreService::getEodSysdate($instid);
            $sysDate = Carbon::parse($sysDate)->subDay()->format('Y-m-d');

            $baseUrl = $conn->url ?: 'https://www.mongolbank.mn/mn/currency-rates/data';
            $url = $baseUrl . '?startDate=' . $sysDate . '&endDate=' . $sysDate;

            $res = $this->makeHttpRequest($url, $sysDate);

            $bomCurRates = [];

            if ($res && $res['success']) {
                $curRateList = GpInstCurRate::where('rtypecode', 1)->where('instid', $instid)->where('statusid', '<>', -1)->where('curcode', '<>', 'MNT')->get();
                DB::beginTransaction();
                $bomCurRates = @$res['data'][0];
                foreach (@$curRateList as $currency) {

                    if ($bomCurRates["RATE_DATE"] !== $sysDate) {
                        throw new Exception("Date mismatch!  RateDate: " . $bomCurRates["RATE_DATE"] . ", SendDate:" . $sysDate);
                    }

                    $value = @$bomCurRates[mb_strtoupper($currency['curcode'])];
                    $number = floatval(str_replace(',', '', $value));
                    $req = [
                        "rtypecode" => "1",
                        "curcode" => $currency['curcode'],
                        "salerate" => $number,
                        "buyrate" => $number,
                        "avgrateend" => $number,
                        "avgrate" => $number,
                        "ispreview" => 1
                    ];

                    // gp013202 - Ханш бүртгэх
                    $process = GpActionCode::where('ACTION_CODE', 'gp013202')
                        ->where('statusid', 1)->first();
                    $route = $process->controller . '@' . $process->function;
                    request()->replace($req);
                    App::call($route);
                }

                DB::commit();
                $curRateList = GpInstCurRate::where('rtypecode', 1)->where('instid', $instid)->where('statusid', '<>', -1)->get();

                return $curRateList;
            } else {
                throw new Exception($res['data']);
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            throw new MeException('RC000260', ['details' => $ex->getMessage()]);
        }
    }

    private function makeHttpRequest($url, $sysDate)
    {
        try {

            // Лог бичилт эхлүүлэх
            $startTime = Carbon::now()->getTimestampMs();
            $logRequest = GpLogRequestList::create([
                'userid' => auth()->id() ?: 1,
                'url' => $url,
                'method' => 'POST',
                'instid' => auth()->user()->instid ?? 1,
                'request' => "{}",
            ]);


            $response = Http::retry(3)->post($url)->throw();

            // Лог хүсэлтийг шинэчлэх
            $logRequest->update([
                'responsetime' => (Carbon::now()->getTimestampMs() - $startTime) / 1000,
                'response' => $response->body(),
                'responsecode' => $response->status(),
            ]);

            // Хариуг шалгах
            if (!$response->successful()) {
                throw new MeException('RC000260', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

            $body = json_decode($response->body(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response");
            }

            if (empty($body)) {
                throw new Exception("Empty response from BOM");
            }
            return $body;
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }
}
