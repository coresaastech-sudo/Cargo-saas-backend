<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEbarimtActionCode;
use Modules\Ad\Http\Requests\AdEbarimtActionCodeRequest;
use Modules\Ad\Http\Services\AdEbarimtService;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\GpctionCode;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Entities\GPLogRequestList;
use Illuminate\Support\Facades\Http;
use App\Exceptions\MeException;

class AdEbarimtActionCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            AdEbarimtActionCode::select(
                "ad_ebarimt_ACTION_CODE.id",
                "ad_ebarimt_ACTION_CODE.ACTION_CODE",
                "ad_ebarimt_ACTION_CODE.instid",
                "ad_ebarimt_ACTION_CODE.statusid",
                "ad_ebarimt_ACTION_CODE.classification_code",
                "GP_ACTION_CODE.name",
                "GP_ACTION_CODE.name2",
            )->join("GP_ACTION_CODE", function ($join) {
                $join->on("ad_ebarimt_ACTION_CODE.ACTION_CODE", "=", "GP_ACTION_CODE.ACTION_CODE");
            })->where('instid', auth()->user()->instid)
                ->where('ad_ebarimt_ACTION_CODE.statusid', '<>', -1)
                ->where('ad_ebarimt_ACTION_CODE.parent_ACTION_CODE', $request->parent_ACTION_CODE),
                [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(AdEbarimtActionCodeRequest $request)
    {
        $validated = $request->validated();

        $parent_ACTION_CODE = null;
        if (@$validated['parentid']) {
            $parent = AdEbarimtActionCode::where('id', $validated['parentid'])->where('instid', auth()->user()->instid)->first();
            if ($parent) {
                $parent_ACTION_CODE = $parent->ACTION_CODE;
                // if ($parent_ACTION_CODE == $validated['ACTION_CODE']) {
                //     throw new MeException("RC000060");
                // }
            }
        }

        AdEbarimtActionCode::create([
            'instid' => auth()->user()->instid,
            'ACTION_CODE' => $validated['ACTION_CODE'],
            'created_by' => auth()->user()->id,
            'statusid' => 1,
            'parent_ACTION_CODE' => $parent_ACTION_CODE,
            'classification_code' => $validated['classification_code'],
        ]);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $ebarimt_pc = AdEbarimtActionCode::select(
            "ad_ebarimt_ACTION_CODE.id",
            "ad_ebarimt_ACTION_CODE.ACTION_CODE",
            "ad_ebarimt_ACTION_CODE.instid",
            "ad_ebarimt_ACTION_CODE.statusid",
            "ad_ebarimt_ACTION_CODE.classification_code",
            "GP_ACTION_CODE.name",
            "GP_ACTION_CODE.name2",
        )->join("GP_ACTION_CODE", function ($join) {
            $join->on("ad_ebarimt_ACTION_CODE.ACTION_CODE", "=", "GP_ACTION_CODE.ACTION_CODE");
        })->where('id', $validate['id'])
            ->where('instid', auth()->user()->instid)
            ->where('ad_ebarimt_ACTION_CODE.statusid', '<>', -1)->first();

        if ($ebarimt_pc) {
            return $ebarimt_pc;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(AdEbarimtActionCodeRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        AdEbarimtActionCode::where('id', $validate['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        AdEbarimtActionCode::where("id", $validate['id'])->where("instid", auth()->user()->instid)->update(['statusid' => -1, 'updated_at' => getNow()]);
    }

    /**
     * Send a Tax bill.
     * ad052500
     * @param Request $request
     * @return Response
     */
    public function ad052500(Request $request)
    {
        $validated = $request->validate([
            'instid' => 'required',
        ]);

        $onlineteller = CoreService::getInstGp($validated['instid'], 'ONLINETELLERNUMBER');
        $user = GPInstUser::where('instid', $validated['instid'])->find(
            $onlineteller
        );

        $ebarimt_service = new AdEbarimtService($validated['instid'], $user);
        $response = $ebarimt_service->sendData();
        return $response;
    }



    /**
     * Get address form Ebarimt api.
     * ad052600
     * @param Request $request
     * @return Response
     */
    public function ad052600(Request $request)
    {

        $validated = $request->validate([
            'branchCode' => 'nullable',
        ]);
        $response = Http::get('https://api.ebarimt.mn/api/info/check/getBranchInfo');
        $response_array = (array) $response->json();
        $startTime = Carbon::now()->getTimestampMs();
        $r = new GPLogRequestList();
        $r->response = json_encode($response_array, JSON_UNESCAPED_UNICODE);
        $r->responsecode = $response->status();
        $r->responsetime = (Carbon::now()->getTimestampMs() - $startTime) / 1000;
        $r->save();
        if ($response_array['status'] == 200) {
            $main_res = $response_array['data'];

            $collection = collect($main_res);

            if (isset($validated['branchCode'])) {

                $filtered = $collection->whereIn('branchCode', $validated['branchCode']);
                $filtered = $filtered->values()->all();

                $subresponse = array_map(function ($branch) {
                    return array(
                        'subBranchCode' => $branch['subBranchCode'],
                        'subBranchName' => $branch['subBranchName']
                    );
                }, $filtered);

                return $subresponse;
            } else {

                $unique = $collection->unique('branchCode');
                return $unique->values()->all();
            }
        };
    }
}