<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Ad\Entities\Views\VwAdAutoJob;
use Modules\Ad\Entities\AdAutoJob;

class AdAutoJobController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdAutoJob::where('instid', auth()->user()->instid)->where('statusid', '<>', -1),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $validated = $this->validate($request, [
            'name' => 'required|string|max:100',
            'name2' => 'required|string|max:100',
            'job_type' => 'required',
            'execfreq' => 'nullable',
            'execday' => 'nullable',
            'exectime' => 'nullable',
            'formulaid' => 'nullable',
            'ACTION_CODE' => 'nullable',
            'startdate' => 'nullable',
            'enddate' => 'nullable',
            'hastimelimit' => 'required'
        ]);

        $user = auth()->user();

        $validated['instid'] = $user->instid;
        $validated['statusid'] = 1;
        $validated['created_by'] = $user->id;
        AdAutoJob::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011",
        ]);


        $autoJob = VwAdAutoJob::where('instid', auth()->user()->instid)
            ->where('id', $validated['id'])
            ->where('statusid', '<>', -1)->first();
        if ($autoJob) {
            return $autoJob;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(Request $request)
    {
        $validated = $this->validate($request, [
            'name' => 'required|string|max:100',
            'name2' => 'required|string|max:100',
            'job_type' => 'required',
            'execfreq' => 'nullable',
            'execday' => 'nullable',
            'exectime' => 'nullable',
            'formulaid' => 'nullable',
            'ACTION_CODE' => 'nullable',
            'startdate' => 'nullable',
            'enddate' => 'nullable',
            'hastimelimit' => 'required',
            'id' => 'nullable'
        ]);

        if (empty($validated['id'])) {
            $this->error("RC000011");
        }

        AdAutoJob::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        AdAutoJob::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
    }
}
