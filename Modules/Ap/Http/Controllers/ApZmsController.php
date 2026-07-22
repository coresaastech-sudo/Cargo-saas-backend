<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Http\Services\AdCreditInfoBueroService;
use Modules\Ap\Entities\ApCustInquiry;
use Modules\Ap\Entities\Views\VwApCustInquiry;
use Modules\Ap\Http\Services\ApAcntService;
use Modules\Ap\Http\Services\ApQpayService;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;

class ApZmsController extends Controller
{

    /**
     * oi000500 - "ЗМС лавлагаа жагсаалт"
     *
     * @return
     */
    public function oi000500(Request $request)
    {
        $user = auth()->user();
        $userId = $user->id;
        $fiveMinutesAgo = Carbon::now()->subMinutes(10);

        $results = VwApCustInquiry::where(function ($query) use ($fiveMinutesAgo) {
            $query->whereIn('statusid', [1, 2])
                ->orWhere(function ($query) use ($fiveMinutesAgo) {
                    $query->where('statusid', 0)
                        ->where('created_at', '>=', $fiveMinutesAgo);
                });
        })
            ->where('userid', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();
        return $results;
    }


    /**
     * oi000510 - "ЗМС лавлагаа авах"
     *
     * @return
     */

    public function oi000510(Request $request)
    {
        $validated = $this->validate($request, [
            'productno' => 'required|string',
            'purptypeid' => 'required|string',
            'purposedesc' => 'required|string'
        ]);

        $product = GPInstConst::where('parent_code', 'ME_APP_ZMS_PRODUCTS')
            ->where('value', $validated['productno'])
            ->where('statusid', 1)
            ->where('instid', 1)
            ->orderBy('value_add1', 'DESC')->first();

        if (!$product) {
            throw new MeException('RC000198');
        }

        $apCustInquiry = ApCustInquiry::create([
            'productno' => $validated['productno'],
            'purptypeid' => $validated['purptypeid'],
            'purposedesc' => $validated['purposedesc'],
            'regno' => auth()->user()->regno,
            'custtypeid' => '01',
            'pdf_url' =>   null,
            'servicecode' =>  null,
            'service_detail_date' =>  null,
            'price' =>  $product->value_add1,
            'inquiry' =>  null,
            'userid' => auth()->user()->id,
            'created_by' => auth()->user()->id,
            'statusid' => 0,
        ]);

        $instconst = GPInstConst::where('code', 'MAIN_APP_INST_ID')
            ->where('statusid', '<>', -1)
            ->first();

        if ($instconst) {
            // createInvoice функцыг дуудах
            $qpay = new ApQpayService($instconst->value);
            $provider = VwGPProviderConf::where('code', '7')->where('instid', $instconst->value)->where('statusid', '<>', -1)->first();
            if ($provider) {
                $providerConfig = json_decode($provider->config, true);

                $data = [
                    'inquiry_id' => $apCustInquiry->id,
                    'typeid' => 4,
                    'cur_code' => 'MNT',
                    'amount' => $product->value_add1,
                    'created_by' => auth()->user()->id,
                    'instid' => $instconst->value,
                    'contAcntCode' => $providerConfig['acntno'],
                ];
                $invoice = $qpay->createInvoice($data, null);

                return $invoice['data'];
            } else {
                throw new MeException("RC000173", [
                    'inst' => $instconst->value,
                    'code' => '7'
                ]);
            }
        } else {
            throw new MeException('RC000211');
        }
    }
}
