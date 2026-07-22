<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdPermReport;
use Modules\Ad\Entities\Views\VwAdPermReport;
use Modules\Ad\Http\Requests\AdPermReportRequest;
use Modules\Gp\Entities\GPInstBrch;
use Modules\Gp\Entities\GPInstPerm;
use Modules\Gp\Entities\GPInstRole;
use Modules\Gp\Entities\GPInstUser;

class AdPermReportController extends Controller
{
    /**
     * Display a listing of the resource.
     * index
     * @return Response
     */
    public function ad020003(Request $request)
    {
        return $this->getGridData($request, VwAdPermReport::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * store
     * @param Request $request
     * @return Response
     */
    public function ad020203(AdPermReportRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        /**
         * AC шалгах, хэрэглэгч, салбар, харах салбар шалгах
         */
        $checkpc = GPInstPerm::where('instid', $user->instid)->where('AC', $validated['AC'])->where('statusid', 1)->first();
        if (empty($checkpc)) {
            $this->error('RC000010', [
                'id' => $validated['AC']
            ]);
        }
        if ($validated['showbrchno'] != 'ALL') {
            $showbrnch = GPInstBrch::where('instid', $user->instid)->where('brchno', $validated['showbrchno'])->where('statusid', 1)->first();
            if (empty($showbrnch)) {
                $this->error('RC000010', [
                    'id' => $validated['showbrchno']
                ]);
            }
        }
        switch ($validated['valuetype']) {
            case 'U':
                $valuetypeU = GPInstUser::where('instid', $user->instid)->where('id', $validated['userid'])->where('statusid', 1)->first();
                if (empty($valuetypeU)) {
                    $this->error('RC000010', [
                        'id' => $validated['userid']
                    ]);
                }
                break;
            case 'B':
                $valuetypeB = GPInstBrch::where('instid', $user->instid)->where('brchno', $validated['brchno'])->where('statusid', 1)->first();
                if (empty($valuetypeB)) {
                    $this->error('RC000010', [
                        'id' => $validated['brchno']
                    ]);
                }
                break;
            case 'R':
                $valuetypeB = GPInstRole::where('instid', $user->instid)
                    ->where('id', $validated['roleid'])->where('statusid', 1)->first();
                if (empty($valuetypeB)) {
                    $this->error('RC000010', [
                        'id' => $validated['roleid']
                    ]);
                }
                break;
            default:
                # code...
                break;
        }
        $validated['statusid'] = 1;
        $validated['instid'] = $user->instid;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        try {
            DB::beginTransaction();
            AdPermReport::create($validated);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Show the specified resource.
     * show
     * @param int $id
     * @return Response
     */
    public function ad020103(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $GPinst = VwAdPermReport::where('id', $validate['id'])->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * update
     * @param Request $request
     * @return Response
     */
    public function ad020303(AdPermReportRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        /**
         * AC шалгах, хэрэглэгч, салбар, харах салбар шалгах
         */
        $checkpc = GPInstPerm::where('instid', $user->instid)
            ->where('AC', $validated['AC'])
            ->where('statusid', 1)
            ->first();
        if (empty($checkpc)) {
            $this->error('RC000010', [
                'id' => $validated['AC']
            ]);
        }

        if ($validated['showbrchno'] != 'ALL') {
            $showbrnch = GPInstBrch::where('instid', $user->instid)
                ->where('brchno', $validated['showbrchno'])->where('statusid', 1)->first();
            if (empty($showbrnch)) {
                $this->error('RC000010', [
                    'id' => $validated['showbrchno']
                ]);
            }
        }
        switch ($validated['valuetype']) {
            case 'U':
                $valuetypeU = GPInstUser::where('instid', $user->instid)->where('id', $validated['userid'])->where('statusid', 1)->first();
                if (empty($valuetypeU)) {
                    $this->error('RC000010', [
                        'id' => $validated['userid']
                    ]);
                }
                break;
            case 'B':
                $valuetypeB = GPInstBrch::where('instid', $user->instid)->where('brchno', $validated['brchno'])->where('statusid', 1)->first();
                if (empty($valuetypeB)) {
                    $this->error('RC000010', [
                        'id' => $validated['brchno']
                    ]);
                }
                break;
            default:
                # code...
                break;
        }

        $validated['userid'] ? $validated['userid'] : null;
        $validated['brchno'] ? $validated['brchno'] : null;
        $validated['updated_by'] = $user->id;
        $inst = AdPermReport::where('instid', $user->instid)
            ->where('statusid', 1)
            ->find($validated['id']);
        if ($checkpc->AC == $inst->AC) {
            $inst->update($validated);
        }
    }

    /**
     * Remove the specified resource from storage.
     * destroy
     * @return Response
     */
    public function ad020403(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $user = auth()->user();
        $dtl = AdPermReport::where('instid', $user->instid)
            ->where('id', $validated['id'])
            ->where('statusid', 1)->first();

        $dtl->update([
            'statusid' => -1,
            'updated_by' => $user->id,
        ]);
    }
}
