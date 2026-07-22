<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEbarimt;
use Modules\Ad\Entities\Views\VwAdEbarimt;
use Modules\Ad\Http\Services\AdCreditInfoBueroService;
use Modules\Ap\Entities\ApCustInquiry;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApEbarimtProfile;
use Modules\Ap\Entities\Views\VwApCustInquiry;
use Modules\Ap\Http\Services\ApAcntService;
use Modules\Ap\Http\Services\ApQpayService;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Enums\ResponseCodeEnum;

class ApEbarimtController extends Controller
{

    /**
     * oi000730 - "Ибаримт жагсаалт"
     *
     * @return
     */
    public function oi000730(Request $request)
    {
        $user = auth()->user();

        $query = ApCustUser::where("id", $user->id)
            ->where('statusid', 1)
            ->whereNotNull('ebarimt_consumerno')
            ->first(['ebarimt_consumerno']);

        if(!$query){
            return['ebarimt_consumerno' => ''];
        }
        
        $v = AdEbarimt::where("ebarimt_consumerno", $query->ebarimt_consumerno)
            ->where("statusid", 1)->get();

        return $v;
    }


    /**
     * oi000740 - "Ибаримт бүртгэл үүсгэх"
     *
     * @return 
     */
    public function oi000740(Request $request)
    {
        $data = $this->validate($request, [
            'ebarimt_consumerno' => 'required|string|size:8',
        ], [
            'ebarimt_consumerno.required' => ResponseCodeEnum::required,
        ]);

        $user = auth()->user();
        $apCustUser = ApCustUser::where('id', $user->id)->first();

        if (!$apCustUser) {
            $this->error("RCE000015");
        }

        $apCustUser->ebarimt_consumerno = $data['ebarimt_consumerno'];
        $apCustUser->updated_by = $user->id;
        $apCustUser->save();
    }

    /**
     * oi000750 - "Ибаримт бүртгэл дэлгэрэнгүй"
     *
     * @return 
     */
    public function oi000750(Request $request)
    {
        $user = auth()->user();

        $v = ApCustUser::where('id', $user->id)
            ->where('statusid', 1)->first();

        if ($v) {
            return ['ebarimt_consumerno' => $v->ebarimt_consumerno];
        } else {
            return ['ebarimt_consumerno' => ''];
        }
    }
}
