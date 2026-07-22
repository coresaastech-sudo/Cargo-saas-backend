<?php

namespace Modules\Gp\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEbarimt;
use Modules\Ad\Http\Services\AdEbarimtService;
use Modules\Cr\Entities\CrCustNotifications;
use Modules\Cr\Entities\Views\VwCrCustNotifications;
use Modules\Gp\Entities\GpInstAdd;
use Modules\Gp\Entities\GpInstAddField;
use Modules\Gp\Entities\GpInstContact;
use Modules\Gp\Entities\GpInstFormula;
use Modules\Gp\Entities\GpInstInvoice;
use Modules\Gp\Entities\GpInstList;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\GpActionCode;
use Modules\Gp\Entities\Views\VwGpConnConf;
use Modules\Gp\Entities\Views\VwGpInstBrch;
use Modules\Gp\Entities\Views\VwGpInstInvoice;
use Modules\Gp\Entities\Views\VwGpProviderConf;
use Modules\Gp\Http\Requests\GpInstInvoiceRequest;
use Modules\Gp\Jobs\SendMailJob;
use Modules\Gp\Jobs\PaidInvoiceJob;

class GpInstInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gp010004(Request $request)
    {
        $sql = VwGpInstInvoice::where('statusid', '>', 0);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp010204(Request $request)
    {
        $user = auth()->user();
        if ($user->instid != 1) {
            $this->error("RC000090");
        }
        $validated = $request->validate([]);

        $formula = GpInstFormula::where('name2', 'LIKE', '%-InvoiceCalculation')
            ->where("instid", 1)->where("statusid", '>', 0)->first();

        $sqldata = DB::select(
            $formula->formula,
            [
                'instid' => $user->instid,
            ]
        );

        $now = Carbon::now();
        $inflationCodes = GpInstAddField::where('instid', 1)
            ->where('statusid', '>', 0)
            ->where('code', 'LIKE', 'inflation_%')->get();
        $inflations = [];
        foreach ($inflationCodes as $item) {
            $inflations[substr($item->code, 10)] = GpInstAdd::where('instid', 1)
                ->where('statusid', '>', 0)
                ->where('keyfield',  $item->id)->first()->itemvalue;
        }
        $bankname = GpInstAdd::where('instid', 1)
            ->where('statusid', '>', 0)
            ->where('keyfield', function ($query) {
                $query->select('id')
                    ->from('GP_inst_add_field')
                    ->where('instid', 1)
                    ->where('statusid', '>', 0)
                    ->where('code', 'Fiba_Bank_Name')
                    ->first();
            })->first()->itemvalue;
        $bankaccount = GpInstAdd::where('instid', 1)
            ->where('statusid', '>', 0)
            ->where('keyfield', function ($query) {
                $query->select('id')
                    ->from('GP_inst_add_field')
                    ->where('instid', 1)
                    ->where('statusid', '>', 0)
                    ->where('code', 'Fiba_Bank_Account')
                    ->first();
            })->first()->itemvalue;
        $FibaBankAccount = $bankname . ':' . $bankaccount;
        $thisMonthBegin = $now->startOfMonth()->format('Y-m-d');
        $nextMonthBegin = $now->addMonth()->startOfMonth()->format('Y-m-d');

        $sendNotifUsers = [];
        try {
            DB::beginTransaction();
            foreach ($sqldata as $inv) {
                $directam = 0.00;
                foreach ($inv as $key => $value) {
                    if ($value == 0) continue;
                    if (str_ends_with($key, 'direct')) { // direct -r duusj bganuud ni NUAT, inflation tootsohgui
                        $directam += (float) $value;
                    }
                }
                $curinstid = $inv->instid;
                if ($inv->totalam + $directam == 0) continue;
                $invoiceNo = sprintf('%d%02d%04d', date('Y'), date('n'), $curinstid);
                if (GpInstInvoice::where('statusid', '>', 0)->where('invoiceno', $invoiceNo)->exists()) continue;
                if (!GpInstList::where('id', $curinstid)->first()->iscreate_invoice) continue;
                if (!empty(GpInstList::where('id', $curinstid)->first()->billstartdate) && GpInstList::where('id', $curinstid)->first()->billstartdate->isFuture()) continue;


                $tempinv = [];
                $freqtype = 1; // Haa neg gazraas avah

                $calculations = $this->calculateInvoice([
                    'instid' => $curinstid,
                    'base_amount' => $inv->totalam,
                    'discount_amount' => 0,
                    'direct_amount' => $directam,
                ], $inflations);

                // Tailbar hesegt zadargaa bichih
                $descr = '';
                foreach ($inv as $key => $value) {
                    if ($value == '0') continue;
                    if (str_ends_with($key, 'am') && $key != 'totalam') { // am -r duusj bganuud ni tootsoonii zadargaa gej avn
                        $descr .= $key . ':' . $value . ';';
                    }
                    if (str_ends_with($key, 'direct')) { // direct -r duusj bganuud ni NUAT, inflation tootsohgui
                        $descr .= $key . '*' . $value . ';';
                    }
                }
                $tempinv = [
                    'invoiceno' => $invoiceNo,
                    'startdate' => $thisMonthBegin,
                    'enddate' => $nextMonthBegin,
                    'expirydate' => $nextMonthBegin,
                    'instid' => $curinstid,
                    'cutoffday' => 1,
                    'statusid' => 1,
                    'base_amount' => (float) $inv->totalam,
                    'inflation_rate' => $calculations['inflation_rate'],
                    'discount_amount' => 0,
                    'tax_amount' => $calculations['tax_amount'],
                    'invoice_amount' => $calculations['invoice_amount'],
                    'apfee' => $directam,
                    'freq' => $freqtype,
                    'gracepriod' => 5,
                    'bankaccountno' => $FibaBankAccount,
                    'description' => $descr,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ];
                GpInstInvoice::create($tempinv);

                // Web notification
                $sendNotifUsers = [...$sendNotifUsers, ...DB::table('vw_ad_notification_users')
                    ->select('custid', 'type')
                    ->whereIn('custid', function ($query) {
                        $query->select('userid')
                            ->from('GP_inst_user_roles')
                            ->where('statusid', '<>', -1)
                            ->whereIn('roleid', function ($subquery) {
                                $subquery->select('roleid')
                                    ->from('GP_inst_role_perms')
                                    ->where('AC', 'gp010804')
                                    ->where('statusid', '<>', -1);
                            });
                    })
                    ->where('type', 'ADMIN')
                    ->where('instid', $curinstid)
                    ->where('statusid', '<>', -1)
                    ->get()->toArray()];
            }
            DB::commit();
            $month = date('m');
            $notification = [
                "title" => "Me-Core Системийн $month-р сарын нэхэмжлэх",
                "description" => "Танай байгууллагын системийн түрээсийн $month-р сарын нэхэмжлэх ирсэн байна. Систем автоматаар боловсруулж буй тул та нэхэмжлэхийн дүнгээ шалгана уу. Гэрээнд заасан хугацаанд төлбөр төлөгдөөгүй тохиолдолд автоматаар систем хаагдахыг анхаарна уу.",
                "is_all" => 0,
                "notiftype" => "WEB",
                "usetemp" => 0,
                "reportActionCode" => 0,
                "execfreq" => 1,
                "autojobid" => 0,
                "users" => json_decode(json_encode($sendNotifUsers), true),
                'url' => '/menu/gp/gp010004'
            ];
            $process = GpActionCode::where('ACTION_CODE', 'ad030200')
                ->where('statusid', 1)->first();
            $route = $process->controller . '@' . $process->function;
            request()->replace($notification);
            App::call($route);
            return;
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
    public function gp010104(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $sql = VwGpInstInvoice::where('statusid', '>', 0);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid)
                ->where('id', $validate['id']);
        } else {
            $sql = $sql->where('id', $validate['id']);
        }
        $GPinst = $sql->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp010304(GpInstInvoiceRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if ($user->instid != 1) {
            $this->error("RC000090");
        }

        if (isset($validated['description'])) {
            $directam = 0.00;
            foreach (explode(';', $validated['description']) as $deet) {
                if ($deet == '') continue;
                if (str_contains($deet, '*')) {
                    $exp = explode('*', $deet);
                    $directam += $exp[1];
                }
            }
        }

        $calculations = $this->calculateInvoice([
            'instid' => $validated['instid'],
            'base_amount' => $validated['base_amount'],
            'discount_amount' => $validated['discount_amount'],
            'direct_amount' => isset($validated['description']) ? $directam : $validated['apfee'],
        ]);
        $inv = GpInstInvoice::where('statusid', '>', 0)->find($validated['id']);

        if ($calculations['invoice_amount'] - $inv->paid_amount < 0) $this->error('RC000059');
        if ($calculations['invoice_amount'] - $inv->paid_amount == 0) $validated['statusid'] = 4;

        $validated['inflation_rate'] = $calculations['inflation_rate'];
        $validated['apfee'] = isset($validated['description']) ? $directam : $validated['apfee'];
        $validated['tax_amount'] = $calculations['tax_amount'];
        $validated['invoice_amount'] = $calculations['invoice_amount'];
        $validated['updated_by'] = $user->id;
        if (!$inv) {
            $this->error("RC000010", $validated);
        }
        return $inv->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function gp010404(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $user = auth()->user();
        if ($user->instid != 1) {
            $this->error("RC000090");
        }
        $dtl = GpInstInvoice::where('id', $validate['id'])->where('statusid', '>', 0)->first();
        if (!$dtl) {
            $this->error("RC000010", $validate);
        }
        if ($dtl->statusid !== 1) {
            $this->error('RC000225');
        }

        // Notification untraah
        $crcustids = VwCrCustNotifications::select('id')
            ->where('instid', $dtl->instid)
            ->where('notifinstid', 1)
            ->where('statusid', '<>', -1)
            ->where('notifstatusid', '<>', -1)
            ->where('is_read', 0)
            ->where('notiftype', 'WEB')
            ->where('url', '/menu/gp/gp010004')
            ->orderBy('id')->get()->toArray();
        foreach ($crcustids as $id) {
            $res = CrCustNotifications::find($id)->first();
            if (!empty($res)) $res->update(['is_read' => 1]);
        }

        $count = GpInstInvoice::where('instid', $dtl->instid)
            ->where('invoiceno', $dtl->invoiceno)
            ->where('statusid', '<', 1)->count();

        $dtl->update([
            'statusid' =>  $count ? ($count + 1) * -1 : -1,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Make Payment for the invoice
     * @param Request $request
     * @return Response
     */
    public function gp010504(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required',
            'payment_date' => 'required',
            'payment_amount' => ['required', 'numeric', 'regex:/^(0\.[1-9]\d*|[1-9]\d*(\.\d+)?)$/']
        ], [
            'id.required' => "RC000011",
            'payment_date.required' => "VC000008",
            'payment_amount.required' => "VC000008",
            'payment_amount.regex' => "RC000042"
        ]);

        return $this->paymentInvoice($validate);
    }

    public function paymentInvoice($validate)
    {
        $user = auth()->user();
        if ($user->instid != 1) {
            $this->error("RC000090");
        }
        $inv = GpInstInvoice::where('id', $validate['id'])->where('statusid', '>', 0)->first();
        if (!$inv) {
            $this->error("RC000010", $validate);
        }

        $totalpaid = $inv->paid_amount + $validate['payment_amount'];
        // if ($inv->invoice_amount - $totalpaid < 0) $this->error('RC000059');

        $status = 2; // Dutuu
        if ($inv->invoice_amount - $totalpaid <= 0) { //Tologdoj duussan
            $status = 4;
            // Notification untraah
            $crcustids = VwCrCustNotifications::select('id')
                ->where('instid', $inv->instid)
                ->where('notifinstid', 1)
                ->where('statusid', '<>', -1)
                ->where('notifstatusid', '<>', -1)
                ->where('is_read', 0)
                ->where('notiftype', 'WEB')
                ->where('url', '/menu/gp/gp010004')
                ->orderBy('id')->get()->toArray();
            foreach ($crcustids as $id) {
                $res = CrCustNotifications::find($id)->first();
                if (!empty($res)) {
                    $res->update(['is_read' => 1]);
                }
            }

            GpInstList::where('id', $inv->instid)->update([
                'statusid' => 1,
                'updated_by' => auth()->user()->id
            ]);
        }

        $laterDate = $validate['payment_date'];
        $paidDate = $inv->paiddate ? Carbon::parse($inv->paiddate) : null;
        $paymentDate = $validate['payment_date'] ? Carbon::parse($validate['payment_date']) : null;

        if ($paidDate && $paymentDate && $paidDate->gt($paymentDate)) {
            $laterDate = $inv->paiddate;
        }

        $update = [
            'paiddate' => $laterDate,
            'paid_amount' => $totalpaid,
            'statusid' => $status
        ];
        return $inv->update($update);
    }

    /**
     * Send E-Barimt
     * @param Request $request
     * @return Response
     */
    public function gp010604(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required',
            'instid' => 'required'
        ], [
            'id.required' => "RC000011",
            'instid.required' => "VC000008",
        ]);

        return $this->sendToEbarimt($validate);
    }

    public function sendToEbarimt($validate)
    {
        // Checks
        $user = auth()->user();
        if ($user->instid != 1) {
            $this->error("RC000090");
        }
        $inv = GpInstInvoice::where('statusid', '>', 0)->find($validate['id']);
        if (!$inv) $this->error("RC000010", $validate);
        $taxamount = $inv->paid_amount;
        $diffamount = abs($inv->paid_amount - $inv->invoice_amount);
        if ($diffamount > 0.99) {
            $taxamount = $inv->invoice_amount;
            if ($inv->paid_amount < $inv->invoice_amount) {
                $this->error("RC000090");
            }
        }

        // Get Configs
        $provider = VwGpProviderConf::where("code", '6')->where("instid", $user->instid)->first();
        if (!$provider) throw new MeException("RC000173", [
            'inst' =>  $user->instid,
            'code' => '6'
        ]);
        $providerConf = json_decode($provider->config, true);

        $conn = VwGpConnConf::where("id", $provider->connid)->first();
        if (!$conn) throw new MeException("RC000174");
        $connConfig = json_decode($conn->config, true);

        // Define Variables
        $AC = "gp010505";
        $txnCode = "gp010504";
        $txndate = $inv->paiddate;
        $txndesc = $providerConf['invoice_vat_desc'];
        $classification_code = $providerConf['invoice_vat_classCode'];
        $curcode = "MNT";
        $formattedSum = sprintf("%.2f", $taxamount);
        $formattedTax = sprintf("%.4f", $taxamount / 11);
        $region = VwGpInstBrch::select('taxregion', 'taxsubregion')->where('statusid', '>', 0)
            ->where('brchno', $user->brchno)->where('instid', $user->instid)->first();
        if (!empty($region)) {
            $districtCode = $region->taxregion . $region->taxsubregion;
        } else {
            $districtCode = "";
        }
        $customerTin = null;
        $institution = GpInstList::where('statusid', '>', 0)->find($inv->instid);
        $response = Http::get($connConfig['apiGetTin'] . $institution->regno);
        if ($response->ok()) {
            $response_array = (array) $response->json();
            if ($response_array['status'] == 200) {
                $customerTin = $response_array['data'];
            }
        }

        $stock[] = [
            "code" => $txnCode,
            "name" => $txndesc,
            "measureUnit" => 'Ш',
            "qty" => '1.00',
            "unitPrice" => $formattedSum,
            "totalAmount" => $formattedSum,
            "vat" => $formattedTax,
            "cityTax" => '0.00',
            "code_name" => (GpActionCode::where('ACTION_CODE', $txnCode)
                ->where('statusid', 1)->first())->name,
            "classification_code" => $classification_code,
        ];

        $cust = (object)[
            'custtypecode' => 1,
            'tin' => $customerTin,
            'id1' => $institution->regno
        ];

        $data = [
            'amount' => $formattedSum,
            'nonCashAmount' => $formattedSum,
            'vat' => $formattedTax,
            "billType" => "3",
            'cashAmount' => "0.00",
            "customerNo" => '',
            'cityTax' => "0.00",
            "districtCode" => $districtCode,
            "stocks" => $stock,
            'cust' => $cust,
            "brchno" => $user->brchno,
        ];

        $ebarimt = AdEbarimt::where('instid', 1)
            ->where('jrno', $inv->invoiceno)
            ->where('res_success', '<>', 1)->first();

        if ($ebarimt) {
            $ebarimt->update([
                'vat' => $formattedTax,
                'amount' => $formattedSum,
                'nonCashAmount' => $formattedSum,
                'cashAmount' => "0.00",
            ]);
        }

        $moduleid = substr($AC, 0, 2);
        $service = new AdEbarimtService($user->instid, $user);
        $putinfo = $service->put(["data" => $data, "classification_code" => $classification_code], $inv->invoiceno, $moduleid, $txndate, $curcode, $AC, $ebarimt);

        if ($putinfo['response']['status'] == 'ERROR') {
            throw new MeException('RC000222', ['field' => $putinfo['response']['message']]);
        } else {
            $update = [];
            $update['taxid'] = $putinfo['tax']['res_billid'];
            return $inv->update($update);
        }
    }


    /**
     * Download Invoice PDF
     * @param Request $request
     * @return array
     */
    public function gp010704(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        return $this->getPdfInvoice($validate['id'], false, auth()->user()->id);
    }

    public function getPdfInvoice($invoiceid, $isattach, $userid, $filename = "")
    {
        // Checks
        $user = GpInstUser::where('id', $userid)->first();
        $sql = VwGpInstInvoice::where('statusid', '>', 0);
        if ($user->isadmin != 1) {
            $sql = $sql->where('instid', $user->instid)
                ->where('id', $invoiceid);
        } else {
            $sql = $sql->where('id', $invoiceid);
        }
        $inv = $sql->first();
        if (!$inv) {
            $this->error("RC000010", ['id' => $invoiceid]);
        }

        $nameTranslations = [
            'custam' => 'Me-Core суурь систем',
            'acntam' => 'Me-Core суурь систем',
            'apam' => 'Me-App Суурь хураамж',
            'apfeedirect' => 'Me-App шимтгэл',
            'lpam' => 'Me-Lp систем'
        ];

        $details = [];
        $directdetails = [];
        foreach (explode(';', $inv->description) as $deet) {
            if ($deet == '') continue;
            if (str_contains($deet, ':')) {
                $exp = explode(':', $deet);
                $details[] = [
                    'name' => $nameTranslations[$exp[0]],
                    'unit' => 'ш',
                    'quantity' => 1,
                    'price' => number_format($exp[1] * 1, 2),
                    'totalprice' => number_format($exp[1] * 1, 2)
                ];
            } else {
                $exp = explode('*', $deet);
                $directdetails[] = [
                    'name' => $nameTranslations[$exp[0]],
                    'unit' => 'ш',
                    'quantity' => 1,
                    'price' => number_format($exp[1] * 1, 2),
                    'totalprice' => number_format($exp[1] * 1, 2)
                ];
            }
        }
        $bankdeet = explode(':', $inv->bankaccountno);

        $billStart = GpInstList::where('id', $inv->instid)->first()->billstartdate;
        $billStartYear = date('Y', strtotime($billStart));

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('tempDir', storage_path());
        $options->set('chroot', __DIR__);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $html = view('pages.pdf.invoice', [
            'year' => $inv->created_at->year,
            'month' => $inv->created_at->month,
            'day' => $inv->created_at->day,
            'invoiceno' => $inv->invoiceno,
            'instname' => substr($inv->instid_name, 3),
            'details' => $details,
            'directdetails' => $directdetails,
            'startdate' => $inv->startdate,
            'enddate' => $inv->enddate,
            'contractam' => number_format($inv->base_amount, 2),
            'discountam' => number_format($inv->discount_amount, 2),
            'inflation' => $inv->inflation_rate,
            'billStartYear' => $billStartYear,
            'tax' => number_format($inv->tax_amount, 2),
            'totalam' => number_format($inv->invoice_amount, 2),
            'inflationamount' => number_format(($inv->base_amount - $inv->discount_amount) * $inv->inflation_rate / 100, 2),
            'bankname' => isset($bankdeet[1]) ? $bankdeet[0] : '',
            'bankacnt' => isset($bankdeet[1]) ? $bankdeet[1] : $bankdeet[0]
        ])->render();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        if ($isattach) {
            file_put_contents($filename, $dompdf->output());
        } else {
            $base64EncodedPdf = base64_encode($dompdf->output());
            return ['pdf' => 'data:application/pdf;base64,' . $base64EncodedPdf];
        }
    }

    /**
     * Send Invoice email
     * @param Request $request
     * @return array
     */
    public function gp010904(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'nullable',
            'isall' => 'required'
        ], [
            'isall.required' => "VC000008"
        ]);
        $user = auth()->user();
        if ($user->instid != 1) {
            $this->error("RC000090");
        }

        $emails = [];

        if ($validate['isall']) {
            $invs = GpInstInvoice::select('instid', 'id', 'invoiceno', 'created_at')
                ->where('statusid', '>', 0)
                ->where('statusid', '<>', 4)
                ->where('is_sendmail', 0)
                ->get();
            foreach ($invs as $inv) {
                $instid = $inv->instid;
                $GpInstContacts = GpInstContact::where('statusid', 1)
                    ->where('instid', $instid)
                    ->where('contacttype', '02')
                    ->get();
                if (count($GpInstContacts) > 0) {
                    $emails = [];
                    foreach ($GpInstContacts as $key => $GpInstContact) {
                        $emails[] = $GpInstContact->email;
                    }
                    $this->sendInvoicePdfMail($emails, $inv->id, $inv->invoiceno, (new Carbon($inv->created_at))->month);
                    $inv->update(['is_sendmail' => 1, 'updated_by' => $user->id]);
                }
            }
        } else {
            $inv = GpInstInvoice::where('id', $validate['id'])->first();
            if (!$inv) {
                $this->error("RC000010", $validate);
            }
            $GpInstContacts = GpInstContact::select('email')->where('statusid', 1)
                ->where('instid', $inv->instid)
                ->where('contacttype', '02')
                ->get();
            if (count($GpInstContacts) > 0) {
                $emails = [];
                foreach ($GpInstContacts as $key => $GpInstContact) {
                    $emails[] = $GpInstContact->email;
                }
                $this->sendInvoicePdfMail($emails, $validate['id'], $inv->invoiceno, (new Carbon($inv->created_at))->month);
                $inv->update(['is_sendmail' => 1, 'updated_by' => $user->id]);
            }
        }
    }

    private function sendInvoicePdfMail($email, $invoiceid, $invoiceno, $month)
    {

        $filename = $invoiceno . '-fiba.pdf';

        $emailData = [
            "to" => $email,
            "subject" => "Me-Core Системийн $month-р сарын нэхэмжлэх",
            "data" => [
                'description' => "Фиба компаниас танай байгууллагын системийн түрээсийн $month-р сарын нэхэмжлэхийг хавсралтаар хүргүүлж байна. Систем автоматаар боловсруулж буй тул та нэхэмжлэхийн дүнгээ шалгана уу. Гэрээнд заасан хугацаанд төлбөр төлөгдөөгүй тохиолдолд автоматаар систем хаагдахыг анхаарна уу."
            ],
            "template" => "GP::emails.notification",
            "invoiceid" => $invoiceid,
            'filename' => $filename,
            'userid' => auth()->user()->id
        ];
        dispatch(new SendMailJob($emailData));
    }



    private function calculateInvoice($array, $inflations = [])
    {
        $user = auth()->user();
        if ($user->instid != 1) {
            $this->error("RC000090");
        }
        if (!isset($array['instid']) || !isset($array['base_amount']) || !isset($array['discount_amount'])) $this->error('VC000008', []);

        // Get Inflations
        if ($inflations == []) {
            $inflationCodes = GpInstAddField::where('instid', 1)
                ->where('statusid', '>', 0)
                ->where('code', 'LIKE', 'inflation_%')->get();
            $inflations = [];
            foreach ($inflationCodes as $item) {
                $inflations[substr($item->code, 10)] = GpInstAdd::where('instid', 1)
                    ->where('statusid', '>', 0)
                    ->where('keyfield',  $item->id)->first()->itemvalue;
            }
        }

        // Calculate total inflation from start of billing
        $billStart = GpInstList::where('id', $array['instid'])->first()->billstartdate;
        $createdYear = date('Y', strtotime($billStart)); //Нэхэмжлэх бодогдож эхэлсэн он
        $totalInflation = 1.00;
        for ($i = $createdYear; $i < (int) date("Y"); $i++) {
            if (isset($inflations[(string) $i])) {
                $totalInflation *= 1 + (float) $inflations[(string) $i] / 100;
            }
        }
        $totalInflation = round($totalInflation, 4);
        // Get tax(vat) percentage
        $provider = VwGpProviderConf::where("code", '6')->where("instid", $user->instid)->first();
        if (!$provider) throw new MeException("RC000173", [
            'inst' =>  $user->instid,
            'code' => '6'
        ]);
        $providerConf = json_decode($provider->config, true);
        $vatPerc = $providerConf['vat_percentage'];
        // Final calculations
        $discounted = $array['base_amount'] - $array['discount_amount'];
        $inflated = floatval($discounted) * $totalInflation;
        $tax = $inflated * $vatPerc / 100; // NUAT
        $finalamount = $inflated + $tax;
        $invoiceamount = $finalamount + $array['direct_amount'];

        return [
            'inflation_rate' => $totalInflation * 100 - 100,
            'tax_amount' => $tax,
            'invoice_amount' => $invoiceamount
        ];
    }

    public function gp011104()
    {
        $gp = GpInstAddField::select('id')->where('code', 'inactive_day_inst')
            ->where('typecode', 'gp')
            ->where('instid', 1)
            ->first();
        $day = 20;
        if ($gp) {
            $gp = GpInstAdd::where('keyfield', $gp->id)->where('instid', 1)->where('statusid', 1)->first();
            if ($gp) {
                $day = $gp->itemvalue;
            }
        }
        if (Carbon::now()->day == $day) {
            $invoices = GpInstInvoice::where('statusid', '<', 3)->where('statusid', '>', 0)->get();
            foreach ($invoices as $key => $invoice) {
                try {
                    GpInstList::where('id', $invoice->instid)->update([
                        'statusid' => 0,
                        'updated_by' => auth()->user()->id
                    ]);
                } catch (\Throwable $th) {
                    Log::debug($th);
                }
            }
        }
    }

    /**
     * gp011004 - Нэхэмжлэх төлөгдсөн эсэхийг шалгах
     *
     * @return void
     */
    public function gp011004()
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }

        if ($this->isOnPaidInvoiceJob()) {
            $this->error('RC000181');
        }

        PaidInvoiceJob::dispatch(
            auth()->user()->id,
            auth()->user()->instid
        )->onQueue('PaidInvoiceJob');
    }

    public function isOnPaidInvoiceJob($isstatus = false)
    {
        $jobInspector = app(\App\Services\QueueJobInspector::class);

        if (!$isstatus) {
            return $jobInspector->has('PaidInvoiceJob');
        }

        return $jobInspector->has('PaidInvoiceJob', PaidInvoiceJob::class, auth()->user()->instid);
    }
}
