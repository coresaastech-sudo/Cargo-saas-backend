<?php

namespace Modules\Ap\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Modules\Ap\Entities\ApCustUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Http\Services\ApAuthService;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ap\Entities\ApInstStopService;
use Modules\Ap\Http\Services\ApAcntService;
use Modules\Ap\Http\Services\ApStopService;
use Modules\Ap\Http\Services\PolarisApiRequestService;
use Modules\Gp\Entities\GppiActionCode;
use Modules\Gp\Entities\GPDicMain;
use Modules\Gp\Entities\GPInstAdd;
use Modules\Gp\Entities\GPInstAddField;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GpctionCode;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;

class ApInstController extends Controller
{
    public function oi000200(Request $request)
    {
        $GPinst = GPInstList::select(
            'id',
            'name',
            'name2',
            'regno',
            'nationid',
            'stabledate',
            'color',
            'logo'
        )->whereIn('id', function ($query) {
            $user = auth()->user();
            $query->select('instid')
                ->from(with(new ApInstCustUserLink)->getTable())
                ->where('statusid', 1)
                ->where('cust_userid', $user->id);
        })->where('statusid', 1)->get();

        $service = new ApAuthService();
        $app = $service->checkMobileApp($request);

        if ($app->app_identifier != 'MeApp') {
            $GPinst = $GPinst->filter(function($item) use ($app) {
                return $item->id == $app->instid;
            });
        }

        $service = new ApAcntService();
        foreach ($GPinst as $inst) {
            $GPInstAddField = GPInstAddField::where('code', "inst_color")->where('instid', $inst->id)->where('statusid', '<>', -1)->first();

            if (isset($GPInstAddField)) {
                $GPInstAdd = GPInstAdd::where('keyfield', $GPInstAddField->id)->where('instid', $inst->id)->where('statusid', '<>', -1)->first();

                if (isset($GPInstAdd)) {
                    $inst->color =  $GPInstAdd->itemvalue;
                }
            }

            $elem = $inst;
            $elem['avail_bal'] = 0;
            $elem['all_bal'] = 0;

            $process = GppiActionCode::where('api_ACTION_CODE', 'oi000250')->first();

            $route = $process->controller . '@' . $process->function;

            request()->merge([
                "instid" => $inst->id,
            ]);
            try {
                $accounts = App::call($route);

                // Log::debug($accounts);
                foreach ($accounts as $key => $acnt) {
                    if ($acnt['acnt_type'] == 'LINE') {
                        $lineAcnt = $service->getLoanAccountDetail($acnt['acnt_code'], $elem['id']);
                        if ($lineAcnt) {
                            $elem['all_bal'] = $elem['all_bal'] + $lineAcnt['limit'];
                            $elem['avail_bal'] = $elem['avail_bal'] + $lineAcnt['avail_com_bal'];
                        }
                    }
                }
                $inst = $elem;
            } catch (Exception $ex) {
                Log::debug($ex);
            }
            // Log::debug($elem);
        }

        // Log::debug($GPinst);
        return  $GPinst;
    }

    /**
     * oi000180 - Системийн огноо авах
     *
     * @param  mixed $request
     * @return string
     */
    public function oi000180(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required'
        ], [
            'instid.required' => ResponseCodeEnum::required
        ]);

        $providertype = CoreService::getInstGp($validated['instid'], 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
            return CoreService::getTxnDate($validated['instid']);
        } else {
            $polaris = new PolarisApiRequestService($validated['instid']);
            return $polaris->getDate($validated['instid']);
        }
    }

    /**
     * oi000190 - Үйлчилгээ шалгах
     *
     * * Үйлчилгээ зогссон байгаа эсэхийг шалгах
     *
     * @param  mixed $validated = [
     *  serviceCode - Үйлчилгээний дугаар
     *  instid - байгууллагын дугаар
     *  acntCode - LINE үед дансны дугаар
     *  prodCode - td нээх үед бүтээгдэхүүний дугаар
     * ]
     * @return array
     */
    public function oi000190(Request $request)
    {
        $validated = $this->validate($request, [
            'serviceCode' => 'required|numeric|digits:8',
            'instid' => 'required',
            'acntCode' => 'nullable',
            'prodCode' => 'nullable',
        ], [
            'instid.required' => ResponseCodeEnum::required,
            'serviceCode.required' => ResponseCodeEnum::required,
        ]);

        $service = new ApStopService();
        return $service->checkStopSrevice($validated);
    }


    /**
     * oi000240 - Get dictionary
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000240(Request $request)
    {
        $validated = $this->validateMe($request, [
            'dic_code' => 'required',
            'parentValue' => 'nullable',
            'parentDicCode' => 'nullable',
            'instid' => 'nullable'
        ]);
        $instid = 1;
        if (isset($validated['instid']) && $validated['instid'] != null) {
            $instid = $validated['instid'];
        }
        $dicmain = GPDicMain::where('dic_code', $validated['dic_code'])->first();
        $admininsid = 1;
        if ($dicmain) {
            if ($validated['dic_code'] == 'DIC_002' || !empty($validated['parentValue'])) {
                if (!empty($validated['parentValue']) && !empty($validated['parentDicCode'])) {
                    $dicmaintmp = GPDicMain::where('dic_code', $validated['parentDicCode'])->first();
                    if ($dicmaintmp) {
                        if ($validated['dic_code'] == 'DIC_002') {
                            if (auth()->user()->isadmin == 1) {
                                $rawdata = DB::select(
                                    'select * from ' . $dicmaintmp->vw_name . ' where value = ?',
                                    [$validated['parentValue']]
                                );
                            } else {
                                $rawdata = DB::select(
                                    'select * from ' . $dicmaintmp->vw_name . ' where instid in (?, ?) and value = ?',
                                    [$admininsid, $instid, $validated['parentValue']]
                                );
                            }
                            if (count($rawdata) > 0) {
                                $const = GPInstConst::where('id', $rawdata[0]->id)
                                    ->where('statusid', '<>', -1)->first();
                                if ($const) {
                                    $validated['parent_code'] = $const->code;
                                } else {
                                    return [];
                                }
                            }
                        } else {
                            $validated['parent_code'] = $validated['parentValue'];
                        }

                        return DB::select(
                            'select * from ' . $dicmain->vw_name . ' where parent_code = ? and instid in (?, ?)',
                            [$validated['parent_code'] ?? '', $admininsid, $instid]
                        );
                    } else {
                        $this->error("RC000009", ['dic_code' => $validated['parentDicCode']]);
                    }
                } else {
                    $this->error("RC000009", $validated);
                }
            }

            // Log::debug($instid);

            return DB::select('select * from ' . $dicmain->vw_name . ' where instid in (?, ?)', [$admininsid, $instid]);
        } else {
            $this->error("RC000009", $validated);
        }
    }
}
