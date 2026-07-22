<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstContact;
use Modules\Gp\Entities\Views\VwGpInstContact;
use Modules\Gp\Http\Requests\GpInstContactRequest;

class GpInstContactController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gp010002(Request $request)
    {
        $validate = $this->validateMe($request, [
            'instid' => 'required'
        ], [
            'instid.required' => "RC000011"
        ]);
        $user = auth()->user();
        $instid = $validate['instid'];
        if (empty($validate['instid'])) {
            $validate['instid'] = auth()->user()->instid;
        } else {
            if ($user->isadmin != 1) {
                $validate['instid'] = auth()->user()->instid;
            }
        }

        return $this->getGridData($request, VwGpInstContact::where('statusid', 1)
            ->where('instid', $instid), [['field' => 'created_at', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp010202(GpInstContactRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if (isset($validated['instid'])) {
            if ($user->isadmin != 1) {
                $validated['instid'] = $user->instid;
            }
        } else {
            $validated['instid'] = $user->instid;
        }
        $validated['fname'] = Str::upper($validated['fname'] ?? '');
        $validated['lname'] = Str::upper($validated['lname'] ?? '');
        $validated['statusid'] = 1;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        return GpInstContact::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function gp010102(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $dtl = GpInstContact::where('id', $validate['id'])
            ->where('statusid', 1);
        if ($user->isadmin != 1) {
            $dtl = $dtl->where('instid', $user->instid);
        }
        $dtl = $dtl->first();
        if ($dtl) {
            return $dtl;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp010302(GpInstContactRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        $validated['fname'] = Str::upper($validated['fname'] ?? '');
        $validated['lname'] = Str::upper($validated['lname'] ?? '');
        $validated['updated_by'] = $user->id;
        $dtl = GpInstContact::where('id', $validated['id'])
            ->where('statusid', 1);
        if ($user->isadmin != 1) {
            $dtl = $dtl->where('instid', $user->instid);
        }
        $dtl = $dtl->first();
        $dtl->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function gp010402(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $user = auth()->user();
        $dtl = GpInstContact::where('id', $validate['id'])
            ->where('statusid', 1);
        if ($user->isadmin != 1) {
            $dtl = $dtl->where('instid', $user->instid);
        }
        $dtl = $dtl->first();
        $dtl->update([
            'statusid' =>  -1,
            'updated_by' => $user->id,
        ]);
    }
}
