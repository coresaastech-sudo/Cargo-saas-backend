<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpAppList;
use Modules\Gp\Http\Requests\GpWhitelabelRequest;


class GpWhitelabelController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function gp093000(Request $request) // list avah
    {
        $query = GpAppList::where('statusid', 1);

        if (auth()->user()->isadmin != 1) {
            $this->error('RC000026');
        }
        return $this->getGridData($request, $query, [['field' => 'id', 'dir' => 'ASC']]);
    }
    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function gp093200(GpWhitelabelRequest $request)  // burtgeh
    {
        $validated = $request->validated();
        $user = auth()->user();

        if (!isset($validated['instid'])) {
            $validated['instid'] = $user->instid;
        } elseif ($user->isadmin != 1) {
            $validated['instid'] = $user->instid;
        }

        $newData = [
            'app_name' => $validated['app_name'],
            'app_identifier' => $validated['app_identifier'],
            'app_secret' => $validated['app_secret'],
            'app_data' => $validated['app_data'],
            'instid' => (int) $validated['instid'],
            'statusid' => 1,
            'created_by' => $user->id
        ];
        return GpAppList::create($newData);
    }

    public function gp093100(Request $request)  // detail
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);

        $GPinst =  GpAppList::where('id', $validate['id']);
        if (auth()->user()->isadmin != 1) {
            $GPinst->where('instid', auth()->user()->instid);
        }
        $GPinst = $GPinst->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    public function gp093300(GpWhitelabelRequest $request)  // update
    {
        $validated = $request->validated();

        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $validated['updated_by'] = auth()->user()->id;
        $validated['instid'] = (int) $validated['instid'];

        $inst = GpAppList::where('statusid', 1)
            ->where('id', $validated['id'])
            ->first();

        if (!$inst) {
            $this->error("RC000012");
        }
        $inst->update($validated);
        return $inst;
    }
    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function gp093400(Request $request)  // ustgah
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $dtl = GpAppList::where('statusid', 1)
            ->where('id', $validate['id'])->first();
            if(!$dtl){
                $this->error('RC000010');
            }
        $count = GpAppList::where('statusid', '<>',  1)
            ->where('app_identifier', $dtl->app_identifier)->count();

        $dtl->update([
            'statusid' => ($count + 1) * -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
