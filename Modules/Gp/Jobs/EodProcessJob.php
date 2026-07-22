<?php

namespace Modules\Gp\Jobs;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEodLog;
use Modules\Ad\Entities\AdEodLogDetail;
use Modules\Gp\Entities\GpInstSeq;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Http\Services\CoreService;

class EodProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userid;
    protected $txndate;
    public $instid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userid, $instid, $txndate)
    {
        $this->userid = $userid;
        $this->instid = $instid;
        $this->txndate = $txndate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('EodProcessJob');
        $user = GpInstUser::find(CoreService::getInstGp($this->instid, 'SYSTEMTELLERNUMBER'));
        if (empty($user) || $user->instid != $this->instid) {
            throw new MeException('RC000119');
        }
        App::setLocale('mn');
        // Set the user as the authenticated user
        Auth::setUser($user);
        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }
        $eodison = GpInstSeq::where('instid', auth()->user()->instid)
            ->where('seqid', 'EODISON')->where('seqno', 1)->first();
        if ($eodison) {
            $bluestep = AdEodLog::where('instid', auth()->user()->instid)
                ->where('function', 'ad800098')
                ->orderBy('eoddate', 'desc')->first();
            if ($bluestep && !empty($bluestep->enddate)) {
                $tmpdate = new Carbon($this->txndate);
                $this->txndate = $tmpdate->subDay();
            }
        }
        $stepsallcount = AdEodLog::where('instid', auth()->user()->instid)
            ->where('eoddate', $this->txndate)->count();
        $steps = AdEodLog::where('instid', auth()->user()->instid)
            ->where('eoddate', $this->txndate)
            ->whereNull('enddate')
            ->orderBy('stepno', 'asc')->get();
        $workedstepcount = $stepsallcount - count($steps);
        foreach ($steps as $keyeod => $step) {

            if ($step->sendemail == 1) {
                try {
                    event(new \Modules\Gp\Events\SendNotification([
                        'title' => 'Өдөр өндөрлөлт',
                        'description' => $step->name . (empty($step->stepdesc) ? ""  : (": " . $step->stepdesc)),
                    ], $user));
                } catch (Exception $ex) {
                    Log::channel('eod_log')->debug($ex);
                }
                $emails = explode(';', CoreService::getInstGp($this->instid, 'EODNOTIFMAILS'));
                foreach ($emails as $email) {
                    if (!empty($email)) {
                        $emailData = [
                            "to" => $email,
                            "subject" => $step->name,
                            "data" => [
                                'description' => $step->stepdesc,
                                'title' => $step->name,
                            ],
                            "template" => "GP::emails.notification"
                        ];
                        dispatch(new SendMailJob($emailData));
                    }
                }
            }

            if ($step->sendsms == 1) {
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
                    ->where('instid', auth()->user()->instid)
                    ->where('errtype', 'A')
                    ->count('acntno', 'distinct');
                $step->stepdesc = 'Selected- ' . $step->allcount .
                    ', Requested- ' . ($step->allcount - $detailcount) .
                    ', Success- ' . $step->succount;
                if ($detailcount == 0) {
                    $step->statusid = 0;
                } else {
                    $step->statusid = 1;
                }
                $workedstepcount++;
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
                try {
                    event(new \Modules\Gp\Events\EodProcessEvent([
                        'isfinished' => false,
                        'status' => $step->statusid == 7 ? 7 : 8,
                        'workedstepcount' => $workedstepcount,
                        'stepsallcount' => $stepsallcount,
                        'percent' => (100 * $workedstepcount) / $stepsallcount,
                    ], auth()->user()));
                } catch (Exception $ex) {
                    Log::channel('eod_log')->debug($ex);
                }

                if ($step->statusid == 7) {
                    try {
                        event(new \Modules\Gp\Events\SendNotification([
                            'title' => 'Өдөр өндөрлөлт',
                            'description' => $step->name . ": " . $step->stepdesc,
                        ], $user));
                    } catch (Exception $ex) {
                        Log::channel('eod_log')->debug($ex);
                    }
                }

                if (strlen($step->stepdesc) > 1000) {
                    $step->stepdesc = substr($step->stepdesc, 0, 1000);
                }
                $step->save();
            }
        }
        endJobInfo('EodProcessJob');
        try {
            event(new \Modules\Gp\Events\EodProcessEvent([
                'isfinished' => true,
                'status' => 0,
                'workedstepcount' => $workedstepcount,
                'stepsallcount' => $stepsallcount,
                'percent' => 100,
            ], auth()->user()));
        } catch (Exception $ex) {
            Log::channel('eod_log')->debug($ex);
        }
    }
}
