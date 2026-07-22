<?php

namespace Modules\Gp\Jobs;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Http\Services\AdCorporateGatewayKhanService;
use Modules\Gp\Entities\GpInstInvoice;
use Modules\Gp\Entities\GpInstUser;
use Illuminate\Support\Str;
use Modules\Ad\Entities\AdCorporateGateway;
use Modules\Gp\Entities\GpInstAdd;
use Modules\Gp\Entities\GpInstAddField;
use Modules\Gp\Entities\GpInstList;
use Modules\Gp\Http\Controllers\GpInstInvoiceController;

class PaidInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userid;
    protected $backupid;
    public $instid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userid, $instid)
    {
        $this->userid = $userid;
        $this->instid = $instid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('PaidInvoiceJob');
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

        $corprateService = new AdCorporateGatewayKhanService(auth()->user()->instid, auth()->user()->id);

        $accounts = $corprateService->getAccountList();
        if (is_array($accounts['accounts']) && count($accounts['accounts']) > 0) {
            $invoices = GpInstInvoice::where('statusid', '<', 3)->where('statusid', '>', 0)->get();
            if (count($invoices) > 0) {
                $invoicecontroller = new GpInstInvoiceController();
                foreach ($accounts['accounts'] as $account) {
                    $transactions = $corprateService->getAccountStatement($account['number']);
                    if (is_array($transactions) && $transactions['transactions']) {
                        foreach ($transactions['transactions'] as $transaction) {
                            if (isset($transaction['relatedAccount']) && $transaction['amount'] > 0) {
                                $tran = AdCorporateGateway::where('bankcode', '05')
                                    ->where('bankjrno', $transaction['journal'])
                                    ->where('txnamount', $transaction['amount'])
                                    ->where('bankacntno', $transaction['relatedAccount'] ?? '')
                                    ->where('statusid', '<>', -1)->first();
                                if (!$tran) {
                                    $descr = Str::upper($transaction['description']);
                                    foreach ($invoices as $key => $invoice) {
                                        $isinclude = false;
                                        if (str_contains($descr, $invoice->invoiceno)) {
                                            $isinclude = true;
                                        } else {
                                            $inst = GpInstList::select('regno', 'name')->where('id', $invoice->instid)->first();
                                            if ($inst) {
                                                $orgName = trim(str_ireplace(['ХЗХ', 'ББСБ'], '', Str::upper($inst->name)));
                                                // Нэмэлт талбар дээрээс байгууллагын дансыг шалгаж тулгана.
                                                $instaccount = GpInstAdd::whereIn('keyfield', function ($query) use ($invoice) {
                                                    $query->select('id')
                                                        ->from(with(new GpInstAddField)->getTable())
                                                        ->where('code', 'like', 'Bank_Account_%')
                                                        ->where('statusid', 1)
                                                        ->where('instid', $invoice->instid);
                                                })->where('itemvalue', $transaction['relatedAccount'])
                                                    ->where('statusid', 1)->first();

                                                if (str_contains($descr, $inst->regno)) {
                                                    $isinclude = true;
                                                } else if ($instaccount) {
                                                    $isinclude = true;
                                                } else if (str_contains($descr, $orgName)) {
                                                    $isinclude = true;
                                                }
                                            }
                                        }

                                        if ($isinclude) {
                                            try {
                                                $invoicecontroller->paymentInvoice([
                                                    'id' => $invoice->id,
                                                    'payment_date' => $transaction['postDate'],
                                                    'payment_amount' => $transaction['amount'],
                                                ]);

                                                $inv = GpInstInvoice::where('id', $invoice->id)->where('statusid', '>', 0)->first();
                                                if (empty($inv->taxid)) {
                                                    $invoicecontroller->sendToEbarimt([
                                                        'id' => $invoice->id,
                                                        'instid' => $invoice->instid,
                                                    ]);
                                                }
                                            } catch (\Throwable $th) {
                                                Log::debug($th);
                                            }
                                        }
                                    }
                                    $carbonDate = Carbon::parse($transaction['postDate']);

                                    $hours = substr($transaction['time'], 0, 2);
                                    $minutes = substr($transaction['time'], 2, 2);
                                    $seconds = substr($transaction['time'], 4, 2);

                                    $carbonDate->setTime($hours, $minutes, $seconds);

                                    $storeData = [
                                        "instid" => $this->instid,
                                        "bankcode" => '05',
                                        'banktxndate' => $carbonDate,
                                        "bankacntno" => $transaction['relatedAccount'],
                                        "bankfromacntno" => $account['number'],
                                        "sign" => '+',
                                        "bankjrno" => $transaction['journal'],
                                        "txnamount" => $transaction['amount'],
                                        "curcode" =>  $transaction['curcode'] ?? "MNT",
                                        "txndesc" => $transaction['description'],
                                        "balance" => $transaction['balance'],
                                        'created_by' => $this->userid,
                                    ];
                                    //Push тайбл рүү бүртгэх
                                    $corprateService->storeCorporateGateway($storeData);
                                }
                            }
                        }
                    }
                }
            }
        }

        endJobInfo('PaidInvoiceJob');
    }
}
