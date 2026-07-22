<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpResponseCode;
use Modules\Gp\Http\Requests\GpResponseCodeRequest;

class GpResponseCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp070000
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            GpResponseCode::select('GP_response_msg.*', 'GP_response_msg.code as id')->where('statusid', '<>', -1),
            [['field' => 'code', 'dir' => 'ASC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpResponseCodeRequest $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validated = $request->validated();
        $validated['statusid'] = 1;
        return GpResponseCode::create($validated);
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
        $GPinst = GpResponseCode::select('GP_response_msg.*', 'GP_response_msg.code as id')->where('code', $validate['id'])->where('statusid', '<>', -1)->first();
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
    public function update(GpResponseCodeRequest $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validate = $request->validated();
        if (empty($validate['code'])) {
            $this->error("RC000011");
        }
        GpResponseCode::where('code', $validate['code'])->where('statusid', '<>', -1)->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        GpResponseCode::where('code', $validate['id'])->where('statusid', '<>', -1)->update([
            'statusid' => -1,
        ]);
    }
}
