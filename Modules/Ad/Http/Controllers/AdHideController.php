<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdHide;
use Modules\Ad\Entities\Views\VwAdHide;
use Modules\Ad\Http\Requests\AdHideRequest;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Dp\Entities\DpAccount;
use Modules\Gp\Entities\GPInstBrch;
use Modules\Gp\Entities\GPInstRole;
use Modules\Gp\Entities\GPInstUser;
use Modules\Ia\Entities\IaAccount;
use Modules\Ia\Entities\IaCtAccount;
use Modules\Ln\Entities\LnAccount;
use Illuminate\Support\Str;

class AdHideController extends Controller
{
    /**
     * Display a listing of the resource.
     * index
     * @return Response
     */
    public function ad020001(Request $request)
    {
        return $this->getGridData($request, VwAdHide::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * store
     * @param Request $request
     * @return Response
     */
    public function ad020201(AdHideRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        /**
         * Данс шалгах, хэрэглэгч, салбар, role шалгах, нууцлалтай болгох код бичих
         */
        $modulekey = null;
        switch (Str::upper($validated['module'])) {
            case 'DP':
                $modulekey = DpAccount::where('instid', $user->instid)->where('acntno', $validated['modulekey'])->first();
                break;
            case 'LN':
                $modulekey = LnAccount::where('instid', $user->instid)->where('acntno', $validated['modulekey'])->first();
                break;
            case 'CT':
                $modulekey = IaCtAccount::where('instid', $user->instid)->where('acntno', $validated['modulekey'])->first();
                break;
            case 'IA':
                $modulekey = IaAccount::where('instid', $user->instid)->where('acntno', $validated['modulekey'])->first();
                break;
            case 'CR':
                $modulekey = VwCrCustList::where('instid', $user->instid)->where('custno', $validated['modulekey'])->first();
                break;

            default:
                # code...
                break;
        }
        if (empty($modulekey)) {
            $this->error('RC000010', [
                'id' => $validated['modulekey']
            ]);
        }
        $valuetypeU = null;
        $valuetypeB = null;
        $valuetypeR = null;
        switch ($validated['valuetype']) {
            case 'U':
            case 'BU':
            case 'UR':
                $valuetypeU = GPInstUser::where('instid', $user->instid)->where('id', $validated['userid'])->where('statusid', 1)->first();
                if (empty($valuetypeU)) {
                    $this->error('RC000010', [
                        'id' => $validated['userid']
                    ]);
                }
                break;
            case 'B':
            case 'BU':
            case 'BR':
                $valuetypeB = GPInstBrch::where('instid', $user->instid)->where('brchno', $validated['brchno'])->where('statusid', 1)->first();
                if (empty($valuetypeB)) {
                    $this->error('RC000010', [
                        'id' => $validated['brchno']
                    ]);
                }
                break;
            case 'R':
            case 'UR':
            case 'BR':
                $valuetypeR = GPInstRole::where('instid', $user->instid)->where('id', $validated['roleid'])->where('statusid', 1)->first();
                if (empty($valuetypeR)) {
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
        if ($validated['module'] == 'CR') {
            if ($modulekey->custtypecode == 0) {
                $modulekey = CrCustInd::where('instid', $user->instid)->where('custno', $validated['modulekey'])->first();
            } else {
                $modulekey = CrCustOrg::where('instid', $user->instid)->where('custno', $validated['modulekey'])->first();
            }
            if (empty($modulekey)) {
                $this->error('RC000010', [
                    'id' => $validated['modulekey']
                ]);
            }
        }
        try {
            DB::beginTransaction();

            AdHide::create($validated);
            $modulekey->update([
                $validated['module'] == 'CR' ? 'hidden' : 'hide' => '1',
                'updated_by' => $user->id
            ]);

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
    public function ad020101(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $GPinst = VwAdHide::where('id', $validate['id'])->where('instid', auth()->user()->instid)
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
    public function ad020301(AdHideRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        /**
         * Данс шалгах, хэрэглэгч, салбар, role шалгах, нууцлал үгүй болгох, нууцлалтай болгох код бичих
         */
        $modulekey = AdHide::where('instid', $user->instid)
            ->where('module', $validated['module'])
            ->where('modulekey', $validated['modulekey'])
            ->where('id', $validated['id'])
            ->first();

        if (empty($modulekey)) {
            $this->error('RC000010', [
                'id' => $validated['modulekey']
            ]);
        }

        $valuetypeU = null;
        $valuetypeB = null;
        $valuetypeR = null;
        switch ($validated['valuetype']) {
            case 'U':
            case 'BU':
            case 'UR':
                $valuetypeU = GPInstUser::where('instid', $user->instid)->where('id', $validated['userid'])->where('statusid', 1)->first();
                if (empty($valuetypeU)) {
                    $this->error('RC000010', [
                        'id' => $validated['userid']
                    ]);
                }
                break;
            case 'B':
            case 'BU':
            case 'BR':
                $valuetypeB = GPInstBrch::where('instid', $user->instid)->where('brchno', $validated['brchno'])->where('statusid', 1)->first();
                if (empty($valuetypeB)) {
                    $this->error('RC000010', [
                        'id' => $validated['brchno']
                    ]);
                }
                break;
            case 'R':
            case 'UR':
            case 'BR':
                $valuetypeR = GPInstRole::where('instid', $user->instid)->where('id', $validated['roleid'])->where('statusid', 1)->first();
                if (empty($valuetypeR)) {
                    $this->error('RC000010', [
                        'id' => $validated['roleid']
                    ]);
                }
                break;
            default:
                # code...
                break;
        }

        $validated['userid'] ? $validated['userid'] : null;
        $validated['brchno'] ? $validated['brchno'] : null;
        $validated['roleid'] ? $validated['roleid'] : null;
        $validated['updated_by'] = $user->id;
        $inst = AdHide::where('instid', $user->instid)
            ->where('statusid', 1)
            ->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * destroy
     * @return Response
     */
    public function ad020401(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $user = auth()->user();
        /**
         * Данс шалгах, хэрэглэгч, салбар, role шалгах, нууцлал үгүй болгох код бичих
         */
        $dtl = AdHide::where('instid', $user->instid)
            ->where('id', $validated['id'])
            ->where('statusid', 1)->first();

        $modulekey = null;
        switch (Str::upper($dtl->module)) {
            case 'DP':
                $modulekey = DpAccount::where('instid', $user->instid)->where('acntno', $dtl->modulekey)->first();
                break;
            case 'LN':
                $modulekey = LnAccount::where('instid', $user->instid)->where('acntno', $dtl->modulekey)->first();
                break;
            case 'CT':
                $modulekey = IaCtAccount::where('instid', $user->instid)->where('acntno', $dtl->modulekey)->first();
                break;
            case 'IA':
                $modulekey = IaAccount::where('instid', $user->instid)->where('acntno', $dtl->modulekey)->first();
                break;
            case 'CR':
                $modulekey = VwCrCustList::where('instid', $user->instid)->where('custno', $dtl->modulekey)->first();
                break;
            default:
                # code...
                break;
        }
        if (empty($modulekey)) {
            $this->error('RC000010', [
                'id' => $dtl->modulekey
            ]);
        }
        if ($dtl->module == 'CR') {
            if ($modulekey->custtypecode == 0) {
                $modulekey = CrCustInd::where('instid', $user->instid)->where('custno', $dtl->modulekey)->first();
            } else {
                $modulekey = CrCustOrg::where('instid', $user->instid)->where('custno', $dtl->modulekey)->first();
            }
            if (empty($modulekey)) {
                $this->error('RC000010', [
                    'id' => $dtl->modulekey
                ]);
            }
        }

        try {
            DB::beginTransaction();
            $checkMore  = AdHide::where('instid', $user->instid)
                ->where('modulekey', $dtl->modulekey)
                ->where('module', $dtl->module)
                ->where('id',  '!=', $dtl->id)
                ->where('statusid', 1)->first();
            if (empty($checkMore)) {

                $modulekey->update([
                    $dtl->module == 'CR' ? 'hidden' : 'hide' => null,
                    'updated_by' => $user->id
                ]);
            }
            $dtl->update([
                'statusid' => -1,
                'updated_by' => $user->id,
            ]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
