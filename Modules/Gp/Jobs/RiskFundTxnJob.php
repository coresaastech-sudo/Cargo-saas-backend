<?php

namespace Modules\Gp\Jobs;

use App\Exceptions\MeException;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstUser;
use Modules\Ad\Entities\AdResAccountBal;
use Modules\Gp\Http\Services\CoreService;
use Modules\Tr\Entities\TxnJrnlEntity;
use Modules\Tr\Http\Services\IaTxnService;
use Illuminate\Support\Str;

class RiskFundTxnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userid;
    public $instid;
    public $acnttype;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userid, $instid, $acnttype)
    {
        $this->userid = $userid;
        $this->instid = $instid;
        $this->acnttype = $acnttype;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('RiskFundTxnJob');
        $user = GpInstUser::find($this->userid);
        if (empty($user) || $user->instid != $this->instid) {
            throw new MeException('RC000119');
        }
        App::setLocale('mn');
        // Set the user as the authenticated user
        Auth::setUser($user);
        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }
        $txns = AdResAccountBal::where('statusid', 0)->where('instid', $this->instid)
            ->where('acnttype', $this->acnttype)->get();
        foreach ($txns as $key => $txn) {
            try {
                if ($this->hasCompletedDuplicateWithSameAmount($txn)) {
                    $txn->statusid = -1;
                    $txn->errordesc = 'Duplicate completed risk fund record exists';
                    $txn->save();
                    continue;
                }

                $p = new TxnJrnlEntity();
                if ($txn->amount < 0) {
                    $p->setTxnAcntCode($txn->res_acntno);
                    $p->setContAcntCode($txn->cont_acntno);
                    if (Str::upper($this->acnttype) == 'LN') {
                        $p->setTxnDesc('Зээлийн эрсдэлийн сангийн буцаалт ' . $txn->acntno . ' ' . $txn->rescls . '->' . $txn->clscode);
                    } else if (Str::upper($this->acnttype) == 'IA') {
                        $p->setTxnDesc('ӨБХ эрсдэлийн сангийн буцаалт ' . $txn->acntno . ' ' . $txn->rescls . '->' . $txn->clscode);
                    } else {
                        $p->setTxnDesc('Авлагын эрсдэлийн сангийн буцаалт ' . $txn->acntno . ' ' . $txn->rescls . '->' . $txn->clscode);
                    }
                    $p->setTxnAmount($txn->amount * -1);
                } else {
                    $p->setTxnAcntCode($txn->cont_acntno);
                    $p->setContAcntCode($txn->res_acntno);
                    if (Str::upper($this->acnttype) == 'LN') {
                        $p->setTxnDesc('Зээлийн эрсдэлийн сангийн гүйлгээ ' . $txn->acntno . ' ' . $txn->rescls . '->' . $txn->clscode);
                    } else if (Str::upper($this->acnttype) == 'IA') {
                        $p->setTxnDesc('ӨБХ эрсдэлийн сангийн гүйлгээ ' . $txn->acntno . ' ' . $txn->rescls . '->' . $txn->clscode);
                    } else {
                        $p->setTxnDesc('Авлагын эрсдэлийн сангийн гүйлгээ ' . $txn->acntno . ' ' . $txn->rescls . '->' . $txn->clscode);
                    }
                    $p->setTxnAmount($txn->amount);
                }
                $p->setTxndate(CoreService::getTxnDate($this->instid));
                $p->setInstid($this->instid);
                $p->setSourcecode('2');
                $p->setPostdate(getNow());
                $p->setUserid($this->userid);
                $txnservice = new IaTxnService();
                $txnservice->doInternalToInternal(clone $p);
                if ($this->hasCompletedDuplicateWithSameAmount($txn)) {
                    $txn->statusid = -1;
                    $txn->errordesc = 'Duplicate completed risk fund record exists';
                    $txn->save();
                    continue;
                }
                $txn->statusid = 1;
                $txn->save();
            } catch (\Throwable $th) {
                $txn->statusid = 3;
                $txn->errordesc = $th->getMessage();
                Log::error($th);
                //throw $th;
            } finally {
                $txn->save();
            }
        }
        endJobInfo('RiskFundTxnJob');
    }

    private function hasCompletedDuplicateWithSameAmount(AdResAccountBal $txn)
    {
        return AdResAccountBal::where('id', '<>', $txn->id)
            ->where('acntno', $txn->acntno)
            ->where('instid', $txn->instid)
            ->where('statusid', 1)
            ->where('resdate', $txn->resdate)
            ->where('rescls', $txn->rescls)
            ->where('clscode', $txn->clscode)
            ->where('amount', $txn->amount)
            ->exists();
    }
}
