<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Gp\Entities\GpInstEodSteps;
use Modules\Gp\Entities\Views\VwGpInstEodStepDetail;
use Modules\Gp\Entities\Views\VwGpInstEodStepList;
use Modules\Gp\Http\Requests\GpInstEodStepsRequest;

class GpInstEodProcessController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp017000
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwGpInstEodStepList::where('instid', auth()->user()->instid)
            ->where('statusid', '>=', 0)
            ->orderBy('orderno', 'asc')
        );
    }

    /**
     * Store a newly created resource in storage.
     * @AC gp017200
     * @param GpInstEodStepsRequest $request
     * @return Response
     */
    public function store(GpInstEodStepsRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();
        try {
            $user = auth()->user();
            if ($user->instid == 1) {
                $modifyopt = 0;
            } else {
                $modifyopt = 9;
            }
            GpInstEodSteps::where('instid', auth()->user()->instid)
                ->where('orderno', '>=', $validated['orderno'])
                ->orderBy('orderno', 'desc')
                ->get()
                ->each(function ($row, $index) use ($user) {
                    $row->orderno += 1;
                    $row->updated_by = $user->id;
                    $row->save();
                });
            $validated['instid'] = $user->instid;
            $validated['statusid'] = 1;
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;
            $validated['modifyopt'] = $modifyopt;
            GpInstEodSteps::create($validated);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
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
            'id.required' => "RC000011"
        ]);

        $instid =  auth()->user()->instid;
        $GPinstqual = VwGpInstEodStepDetail::where('id', $validated['id'])
            ->where('instid', $instid)
            ->first();
        if ($GPinstqual) {
            return $GPinstqual;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(GpInstEodStepsRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user =  auth()->user();
        $gpeodstep = GpInstEodSteps::where('id', $validated['id'])
            ->where('instid', $user->instid)
            ->first();

        if (!$gpeodstep) {
            $this->error('RC000027');
        } else {
            if ($gpeodstep->modifyopt != 9) {
                $this->error('RC000073');
            }
            $validated['updated_by'] = auth()->user()->id;
            DB::beginTransaction();
            try {
                if ($gpeodstep->orderno != $validated['orderno']) {
                    GpInstEodSteps::where('id', $validated['id'])->update(['orderno' => -1]);
                    if ($validated['orderno'] > $gpeodstep->orderno) {
                        GpInstEodSteps::where('instid', auth()->user()->instid)
                            ->where('orderno', '<=', $validated['orderno'])
                            ->where('orderno', '>', $gpeodstep->orderno)
                            ->orderBy('orderno', 'asc')
                            ->get()
                            ->each(function ($row, $index) use ($user) {
                                $row->orderno -= 1;
                                $row->updated_by = $user->id;
                                $row->save();
                            });
                    } else {
                        GpInstEodSteps::where('instid', auth()->user()->instid)
                            ->where('orderno', '>=', $validated['orderno'])
                            ->where('orderno', '<', $gpeodstep->orderno)
                            ->orderBy('orderno', 'desc')
                            ->get()
                            ->each(function ($row, $index) use ($user) {
                                $row->orderno += 1;
                                $row->updated_by = $user->id;
                                $row->save();
                            });
                    }
                }
                GpInstEodSteps::where('id', $validated['id'])->update($validated);
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $gpeodstep = GpInstEodSteps::where('instid', auth()->user()->instid)->where('statusid', 1)->find($validated['id']);
        if (!$gpeodstep) {
            $this->error('RC000027');
        }
        $gpeodstep->update([
            'statusid' => 0,
            'updated_by' => auth()->user()->id
        ]);
    }

    /**
     * Restore the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function restore(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $gpeodstep = GpInstEodSteps::where('instid', auth()->user()->instid)->where('statusid', 0)->find($validated['id']);
        if (!$gpeodstep) {
            $this->error('RC000027');
        }
        $gpeodstep->update([
            'statusid' => 1,
            'updated_by' => auth()->user()->id
        ]);
    }

    /**
     * Create the specified resource from storage.
     * @AC gp017600
     * @return Response
     */
    public function create()
    {
        $instid = auth()->user()->instid;
        $user = auth()->user();
        if ($instid == 1) {
            return;
        }
        $tmpsteps = GpInstEodSteps::where('instid', $instid)
            ->where('modifyopt', 9)
            ->where('statusid', '>=', 0)
            ->get();

        try {
            DB::beginTransaction();
            GpInstEodSteps::where('instid', $instid)
                ->where('statusid', '>=', 0)->delete();

            $eodsteps = GpInstEodSteps::where('instid', 1)
                ->where('statusid', 1)->get();

            foreach ($eodsteps as $step) {
                GpInstEodSteps::create([
                    'orderno' => $step->orderno,
                    'name' => $step->name,
                    'name2' => $step->name2,
                    'stepdesc' => $step->stepdesc,
                    'controller' => $step->controller,
                    'function' => $step->function,
                    'exturl' => $step->exturl,
                    'statusid' => $step->statusid,
                    'runfreq' => $step->runfreq,
                    'modifyopt' => $step->modifyopt,
                    'proctype' => $step->proctype,
                    'sqlscript' => $step->sqlscript,
                    'runmonth' => $step->runmonth,
                    'runday' => $step->runday,
                    'sendsms' => $step->sendsms,
                    'sendemail' => $step->sendemail,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ]);
            }

            foreach ($tmpsteps as $key => $step) {

                GpInstEodSteps::where('instid', $instid)
                    ->where('orderno', '>=', $step->orderno)
                    ->orderBy('orderno', 'desc')
                    ->get()
                    ->each(function ($row, $index) use ($user) {
                        $row->orderno += 1;
                        $row->updated_by = $user->id;
                        $row->save();
                    });

                GpInstEodSteps::create([
                    'orderno' => $step->orderno,
                    'name' => $step->name,
                    'name2' => $step->name2,
                    'stepdesc' => $step->stepdesc,
                    'controller' => $step->controller,
                    'function' => $step->function,
                    'exturl' => $step->exturl,
                    'statusid' => $step->statusid,
                    'runfreq' => $step->runfreq,
                    'modifyopt' => $step->modifyopt,
                    'proctype' => $step->proctype,
                    'sqlscript' => $step->sqlscript,
                    'runmonth' => $step->runmonth,
                    'runday' => $step->runday,
                    'sendsms' => $step->sendsms,
                    'sendemail' => $step->sendemail,
                    'instid' => $instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ]);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
