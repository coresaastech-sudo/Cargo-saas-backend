<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpAuditLog;
use Modules\Gp\Entities\GpAuditLogDetail;
use Modules\Gp\Entities\GpLogRequestList;
use Modules\Gp\Entities\GpLogChangesList;
use Modules\Gp\Entities\GpLogErrorList;

class GpLogController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function indexRequestLog(Request $request)
    {
        $user = auth()->user();
        if ($user->instid === 1) {
            return $this->getGridData(
                $request,
                GpLogRequestList::select(['id', 'userid', 'created_at', 'ip', 'responsecode', 'responsetime', 'updated_at', 'url', 'AC']),
                [['field' => 'id', 'dir' => 'DESC']]
            );
        } else {
            return $this->getGridData(
                $request,
                GpLogRequestList::select(['id', 'userid', 'created_at', 'ip', 'responsecode', 'responsetime', 'updated_at', 'url', 'AC'])
                    ->where('instid', $user->instid),
                [['field' => 'id', 'dir' => 'DESC']]
            );
        }
    }

    public function showRequestLog(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = GpLogRequestList::where('id', $validate['id'])->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function indexChangeLog(Request $request)
    {
        $user = auth()->user();
        if ($user->instid == 1) {
            return $this->getGridData(
                $request,
                GpLogChangesList::select(['id', 'created_at', 'ip_address', 'user_agent', 'url', 'updated_at']),
                [['field' => 'id', 'dir' => 'DESC']]
            );
        } else {
            return $this->getGridData(
                $request,
                GpLogChangesList::select(['id', 'created_at', 'ip_address', 'user_agent', 'url', 'updated_at']),
                [['field' => 'id', 'dir' => 'DESC']]
            );
        }
    }

    /**
     * Display a listing of the resources of deposit account's change log.
     * @AC gp080013
     * @return Response
     */
    public function gp080013(Request $request)
    {
        $query = GpAuditLog::where(function ($query) {
            $query->where('instid', auth()->user()->instid)
                ->orWhereNull('instid');
        })
            ->leftJoin('GP_ACTION_CODE', 'GP_audit_log.AC', '=', 'GP_ACTION_CODE.ACTION_CODE')
            ->selectRaw('GP_audit_log.*, GP_ACTION_CODE.name, GP_ACTION_CODE.name2,
                        substring(GP_audit_log.object_type from \'[^\\\\]*$\')');

        return $this->getGridData(
            $request,
            $query,
            [['field' => 'GP_audit_log.id', 'dir' => 'DESC']],
            ['object_type']
        );
    }

    /**
     * Show the specified resource.
     * @AC gp080113
     * @param int $audit_logid
     * @return Response
     */
    public function gp080113(Request $request)
    {
        $validate = $this->validateMe($request, [
            'audit_logid' => 'required'
        ], [
            'audit_logid.required' => "RC000011",
        ]);

        return $this->getGridData($request, GpAuditLogDetail::where('audit_logid', $validate['audit_logid'])
            ->orderBy('id', 'DESC'));
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function showChangeLog(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = GpLogChangesList::where('id', $validate['id'])->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }
    public function indexErrorLog(Request $request)
    {
        $user = auth()->user();
        if ($user->instid === 1) {
            return $this->getGridData(
                $request,
                GpLogErrorList::where('instid', '!=',null ),
                [['field' => 'id', 'dir' => 'DESC']]
            );
        } else {
            return $this->getGridData(
                $request,
                GpLogErrorList::
                    where('instid', $user->instid),
                [['field' => 'id', 'dir' => 'DESC']]
            );
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function showErrorLog(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = GpLogErrorList::where('id', $validate['id'])->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
