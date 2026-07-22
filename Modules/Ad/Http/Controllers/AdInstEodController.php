<?php

namespace Modules\Ad\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEodLog;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Ad\Http\Services\TrEodService;
use Modules\Gp\Entities\GPInstEodSteps;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstSeq;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\EodProcessJob;

class AdInstEodController extends Controller
{


    /**
     * Display a listing of the resource.
     * Өдөр өндөрлөх процесийн жагсаалт тухайн өдөр үүсээгүй бол үүсгээд буцаана
     * @AC ad011000
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'eoddate' => 'nullable'
        ]);
        $instid = auth()->user()->instid;

        if (!isset($validated['eoddate'])) {
            $txndate = CoreService::getEodSysdate($instid);
        } else {
            $txndate = $validated['eoddate'];
            $carbneoddate = new Carbon($txndate);
            $carbntxndate = new Carbon(CoreService::getTxnDate($instid));
            if ($carbntxndate->lt($carbneoddate)) {
                return [];
            }
        }
        $adeod = AdEodLog::where('instid', $instid)->first();
        if (empty($adeod)) {
            (new TrEodService())->createEodList();
        }

        $data = AdEodLog::where('instid', $instid)
            ->where('eoddate', $txndate)->orderBy('stepno', 'asc')->get();
        return $data;
    }

    public function isOnEodJob()
    {
        return app(\App\Services\QueueJobInspector::class)
            ->has('EodJob', EodProcessJob::class, auth()->user()->instid);
    }

    /**
     * runEod Өдөр өндөрлөлт эхлүүлэх
     * @AC ad011200
     *
     * @return void
     */
    public function runEod()
    {
        $instid = auth()->user()->instid;
        if ($this->isOnEodJob()) {
            $this->error('RC000108');
        }
        $allowEod = GPInstGp::select('itemvalue')->where('instid', $instid)->where('itemname', 'ISEODALLOWED')->first();
        if (empty($allowEod)) {
            $this->error('RC000182');
        }
        $lastEodDateTime = AdEodLog::select('enddate')
            ->where('instid', $instid)
            ->where('function', 'ad800118')
            ->where('statusid', 0)
            ->orderby('id', 'DESC')->first();
        if (!empty($lastEodDateTime)) {
            $allowEodHours = (int) $allowEod->itemvalue;
            $lastEodDateTime = Carbon::parse($lastEodDateTime->enddate);
            $allowEodRun = Carbon::now()->diffInHours($lastEodDateTime);

            // log::debug([$allowEodHours, $lastEodDateTime, $allowEodRun]);
            if ($allowEodHours > $allowEodRun) {
                $this->error('RC000183', ['hours' => $allowEodHours - $allowEodRun]);
            }
        }
        EodProcessJob::dispatch(
            auth()->user()->id,
            $instid,
            CoreService::getEodSysdate($instid)
        )->onQueue('EodJob');
    }

    public function runStep(Request $request)
    {
        $validated = $this->validate($request, [
            'function' => 'required',
            'instid' => 'required',
            'orderno' => 'nullable'
        ]);

        $step = AdEodLog::where('instid', $validated['instid'])
            ->where('eoddate', CoreService::getEodSysdate($validated['instid']))
            ->where('function', $validated['function'])
            ->when(isset($validated['orderno']), function ($query) use ($validated) {
                return $query->where('orderno', $validated['orderno']);
            })
            ->first();
        if (empty($step)) {
            throw new MeException('Функц олдсонгүй.');
        }
        $user = GPInstUser::find(CoreService::getInstGp($validated['instid'], 'SYSTEMTELLERNUMBER'));
        if (empty($user) || $user->instid != $validated['instid']) {
            throw new MeException('RC000119');
        }
        Auth::setUser($user);
        $instid = auth()->user()->instid;
        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }
        try {
            $step->statusid = 8;
            $step->save();
            if (empty($step->startdate)) {
                $step->startdate = getNow();
            }
            if (!empty($step->controller) && !empty($step->function)) {
                App::call($step->controller . '@' . $step->function, ['step' => $step]);
            }
            $step->enddate = getNow();
            $detailcount = AdEodLogDetail::where('eoddate', $step->eoddate)
                ->where('orderno', $step->orderno)
                ->where('instid', $instid)
                ->where('errtype', 'A')->count();
            $step->stepdesc = 'Selected- ' . $step->allcount . ', Requested- ' . ($step->allcount - $detailcount);
            if ($detailcount == 0) {
                $step->statusid = 0;
            } else {
                $step->statusid = 1;
            }
        } catch (MeException $ex) {
            Log::channel('eod_log')->debug($ex);
            $step->stepdesc = $ex->getMessage();
            $step->statusid = 7;
            throw $ex;
        } catch (\Throwable $th) {
            Log::channel('eod_log')->error($th);
            $step->stepdesc = $th->getMessage();
            $step->statusid = 7;
            throw $th;
        } finally {
            if (strlen($step->stepdesc) > 1000) {
                $step->stepdesc = substr($step->stepdesc, 0, 1000);
            }
            $step->save();
            return $step;
        }
    }

    /**
     * Өдөр өндөрлөлтийн эхэлсэн эсэх мэдээлэл авах
     *
     * @AC ad011100
     * @return void
     */
    public function getStatus()
    {
        $eodstatus = 8;
        $instid = auth()->user()->instid;
        $txndate = CoreService::getEodSysdate($instid);
        if (!$this->isOnEodJob()) {
            $eodison = GPInstSeq::where('instid', $instid)
                ->where('seqid', 'EODISON')->where('seqno', 1)->first();
            if ($eodison) {
                $bluestep = AdEodLog::where('instid', $instid)
                    ->where('function', 'ad800098')
                    ->orderBy('eoddate', 'desc')->first();
                if ($bluestep && !empty($bluestep->enddate)) {
                    $endstep = AdEodLog::where('instid', $instid)
                        ->where('function', 'ad800118')
                        ->orderBy('eoddate', 'desc')->first();
                    if ($endstep && empty($endstep->enddate)) {
                        $tmpdate = new Carbon($txndate);
                        $txndate = $tmpdate->subDay();
                    }
                }
                $isstop = AdEodLog::where('instid', $instid)
                    ->where('eoddate', $txndate)->where('statusid', 7)->first();
                if ($isstop) {
                    $eodstatus = 7;
                } else {
                    $isproc = AdEodLog::where('instid', $instid)
                        ->where('eoddate', $txndate)->where('statusid', 8)->first();
                    if (!$isproc) {
                        $eodstatus = 0;
                    } else {
                        $eodstatus = 7;
                    }
                }
            }
        } else {
            $eodison = true;
        }

        return [
            'eodison' => $eodison ? 1 : 0,
            'eodstatus' => $eodison ? $eodstatus : 0,
            'txndate' => $txndate
        ];
    }

    /**
     * Өдөр өндөрлөхөөр бэлтгэсэн Өдөр өндөрлөлтийн жагсаалтыг устгах
     * @AC ad011300
     * @return void
     */
    public function ad011300()
    {
        $instid = auth()->user()->instid;
        $lastEodDate = AdEodLog::select('eoddate')
            ->where('instid', $instid)
            ->where('function', 'ad800001')
            ->where('statusid', 9)
            ->orderby('id', 'DESC')->first();
        // Log:: debug($lastEodDate);
        if ($lastEodDate) {
            AdEodLog::where('instid', $instid)
                ->where('eoddate', $lastEodDate->eoddate)
                ->where('statusid', 9)
                ->delete();
        }
        // Дараагийн өдөр өндөрлөх жагсаалтыг бэлдэнэ.
        (new TrEodService())->createEodList();
    }
}
