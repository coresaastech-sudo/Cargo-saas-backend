<?php

namespace Modules\Re\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Re\Http\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Gp\Entities\GPInstRole;
use Modules\Gp\Entities\GPInstRolePerms;
use Modules\Gp\Entities\GPInstUserRole;
use Modules\Re\Entities\ReInstReportTemp;
use Modules\Re\Entities\ReInstReportTempDim;
use Modules\Re\Entities\ReInstReportTempParam;
use Modules\Re\Entities\ReInstReportTempContent;
use Modules\Re\Entities\ReInstReportTempParamIn;
use Modules\Re\Http\Requests\ReInstReportTempRequest;
use Modules\Gp\Http\Controllers\GPController;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\ReportJob;
use Modules\Re\Entities\Views\VwReInstReportTemp;
use Modules\Re\Http\Services\re080018;
use Modules\Re\Http\Services\re080299;
use Modules\Re\Http\Services\ReportServiceV2;

class ReInstReportTempController extends Controller
{
    /**
     * re010010
     * Display a listing of Group of Report.
     * @return Response
     */
    public function re010010(Request $request)
    {
        return $this->getGridData(
            $request,
            VwReInstReportTemp::select(
                'groupid',
                DB::raw('MAX(groupid_name) AS name'),
                DB::raw('MAX(groupid_name2) AS name2'),
                DB::raw('MAX(instid) AS instid'),
                DB::raw('count(*)')
            )
                ->whereIn('ACTION_CODE', function ($query) {
                    $query->select('AC')
                        ->from(with(new GPInstRolePerms())->getTable())
                        ->whereIn('roleid', function ($query) {
                            $query->select('roleid')
                                ->from(with(new GPInstUserRole())->getTable())
                                ->where('instid', auth()->user()->instid)
                                ->where('userid', auth()->user()->id)
                                ->where('statusid', '<>', -1);
                        })
                        ->where('statusid', '<>', -1);
                })
                ->where('statusid', 1)
                ->groupBy('groupid'),
            [['field' => 'groupid', 'dir' => 'ASC']]
        );
    }

    /**
     * @AC re010001
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwReInstReportTemp::where('statusid', 1)
                ->whereIn('ACTION_CODE', function ($query) {
                    $query->select('AC')
                        ->from(with(new GPInstRolePerms())->getTable())
                        ->whereIn('roleid', function ($query) {
                            $query->select('roleid')
                                ->from(with(new GPInstUserRole())->getTable())
                                ->where('instid', auth()->user()->instid)
                                ->where('userid', auth()->user()->id)
                                ->where('statusid', '<>', -1);
                        })
                        ->where('statusid', '<>', -1);
                }),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );
    }

    /**
     * Бүх тайлангийн жагсаалт авах
     * @return Response
     */
    public function re010011()
    {
        return VwReInstReportTemp::select([
            'id',
            'name',
            'name2',
            'groupid',
            'ACTION_CODE'
        ])->where('statusid', 1)
            ->whereIn('ACTION_CODE', function ($query) {
                $query->select('AC')
                    ->from(with(new GPInstRolePerms())->getTable())
                    ->whereIn('roleid', function ($query) {
                        $query->select('id')
                            ->from(with(new GPInstRole())->getTable())
                            ->where('instid', auth()->user()->instid)
                            ->where('statusid', '<>', -1);
                    })
                    ->where('statusid', '<>', -1);
            })->get();
    }

    /**
     * re010301
     * Update Report Temp
     * @param ReInstReportTempRequest $request
     * @return Response
     */
    public function update(ReInstReportTempRequest $request)
    {
        $validate = $request->validated();
        $user = auth()->user();

        if (empty($validate['id'])) {
            $this->error('RC000011');
        }

        if (array_key_exists('name', $validate) && !empty($validate['name'])) {
            $validate['name'] = mb_strtoupper($validate['name']);
        }

        if (array_key_exists('name2', $validate) && !empty($validate['name2'])) {
            $validate['name2'] = mb_strtoupper($validate['name2']);
        }

        $reportTemp = ReInstReportTemp::where('instid', 1)->where("statusid", 1)->where("id", $validate['id'])->first();

        if ($reportTemp && GPController::checkActionCode($reportTemp->ACTION_CODE, $user->id)) {
            $validate['updated_by'] = auth()->user()->id;
            $update = ReInstReportTemp::where('instid', 1)->where("statusid", 1)->find($validate['id']);
            $update->update($validate);
            return $update;
        } else {
            $this->error('RC000002', $validate);
        }
    }

    /**
     * re010201
     * Store Report Temp
     * @param ReInstReportTempRequest $request
     * @return Response
     */
    public function store(ReInstReportTempRequest $request)
    {
        $validate = $request->validated();
        $user = auth()->user();
        $last_created = ReInstReportTemp::where("statusid", "<>", -1)
            ->orderBy('ACTION_CODE', 'desc')->first();

        $validate['name'] = mb_strtoupper($validate['name']);
        $validate['name2'] = mb_strtoupper($validate['name2']);

        if ($last_created) {
            $order_code = intval(substr($last_created->ACTION_CODE, -5));
            $order_code++;
            $validate['ACTION_CODE'] = "re0" . strval($order_code);
        } else {
            $validate['ACTION_CODE'] = "re080000";
        }
        $validate['statusid'] = 1;
        $validate['instid'] = 1;
        $validate['created_by'] = $user->id;
        $validate['updated_by'] = $user->id;
        return ReInstReportTemp::create($validate);
    }

    /**
     * re010401
     * Destroy Report Temp
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $tempParam = ReInstReportTemp::where("instid", 1)->where("id", $validate['id'])->where('statusid', 1)->first();

        $tempParam->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);
    }

    /**
     * re010101
     * Show Report Temp
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $user = auth()->user();

        $tempParam = VwReInstReportTemp::where('id', $validate["id"])->where("statusid", 1)->first();

        if ($tempParam && GPController::checkActionCode($tempParam->ACTION_CODE, $user->id)) {
            $params = ReInstReportTempParam::where("instid", 1)
                ->where("templateid", $validate["id"])->where("statusid", 1)
                ->whereNull("parentid")->orderBy("created_at", "ASC")->get();

            $contents = ReInstReportTempContent::with("children")
                ->where("instid", 1)->where("templateid", $validate["id"])
                ->where("statusid", 1)->whereNull("parentid")->orderBy("listorder", "asc")->get();

            $dimension = ReInstReportTempDim::where("instid", 1)
                ->where("id", $tempParam['dimensionid'])->where('statusid', 1)->first();

            $inputs = ReInstReporttempParamIn::where("instid", 1)
                ->where("templateid", $validate["id"])->where("statusid", 1)
                ->orderBy("listorder", "ASC")->get();

            $tempParam['params'] = $params;
            $tempParam['contents'] = $contents;
            $tempParam['dimension'] = $dimension;
            $tempParam['inputs'] = $inputs;

            return $tempParam;
        } else if ($tempParam) {
            return $this->error("RC000026");
        } else {
            return $this->error("RC000010", $validate);
        }
    }

    /**
     * re010501
     * Generate Report Temp
     * @param Request $request
     * @return Response
     */
    public function generate(Request $request)
    {
        $validate = $this->validateMe($request, [
            'ACTION_CODE' => 'required',
            'inputs.*' => 'required',
            'exporttype' => 'nullable',
            'reportkey' => 'nullable',
        ], [
            'ACTION_CODE.required' => "RC000011",
            'inputs.*.required' => "RC000011"
        ]);

        $user = auth()->user();

        $report = ReInstReportTemp::where("instid", 1)
            ->where("statusid", 1)
            ->where("ACTION_CODE", $validate['ACTION_CODE'])->first();
        if ($report) {
            if (GPController::checkActionCode($report->ACTION_CODE, $user->id)) {
                if (isset($validate['reportkey']) && !empty($validate['reportkey'])) {
                    return Cache::get($validate['reportkey']);
                }
                $reportkey = $report->name . '_' . $report->ACTION_CODE . '_' . time();
                if ($report->isbackground) {
                    ReportJob::dispatch(
                        $validate,
                        $report,
                        $user,
                        $reportkey
                    )->onQueue("ReportJob");
                    Cache::put($reportkey, [
                        'exporttype' => $report->exporttype,
                        'isbackground' => true,
                        'reportkey' => $reportkey,
                    ]);
                    return Cache::get($reportkey);
                } else {
                    return $this->generateProcess($validate, $report, $user, $reportkey);
                }
            } else {
                $this->error('RC000014', [
                    'AC' => $validate['ACTION_CODE']
                ]);
            }
        } else {
            $this->error('RC000002', [
                'proc_code' => $validate['ACTION_CODE']
            ]);
        }
    }

    public function generateProcess($validate, $report, $user, $reportkey)
    {
        if (isset($validate['inputs'])) {
            foreach ($validate['inputs'] as $key => $inputs) {
                if (isset($inputs['value']) && empty($inputs['value'])) {
                    if ($inputs['input'] == 'branch') {
                        $validate['inputs'][$key]['value'] = "0";
                    }
                }
            }
        }

        if ("re080018" == $report->ACTION_CODE) {
            $path = "exports/$reportkey.xlsx";
            Excel::store(
                new re080018($user, $validate),
                $path,
                'public', // 🔥 ЗААВАЛ public
                \Maatwebsite\Excel\Excel::XLSX
            );
            return [
                'exporttype' => 2,
                'source' => config("app.backoffice_url") . "/api/v1/exports/{$reportkey}"
            ];
        } else if ($report->version == 2) {
            $service = new ReportServiceV2();
            $report = $service->generateReport($validate);
        } else if ($report->version == 3) {
            if ($report->code == "re080299") {
                return (new re080299())->generateReport($user, $validate);
            }
        } else {
            $service = new ReportService();
            $report = $service->generateReport($validate, $user->instid, $user);
        }

        if ($report) {
            return $report;
        } else {
            $this->error('RC000010', [
                'id' => $validate['ACTION_CODE']
            ]);
        }
    }

    public function generateBulk(Request $request)
    {
        $validate = $this->validateMe($request, [
            'bulk.*.ACTION_CODE' => 'required',
            'bulk.*.inputs.*' => 'required'
        ], [
            'bulk.*.ACTION_CODE.required' => "RC000011",
            'bulk.*.inputs.*.required' => "RC000011"
        ]);

        $user = auth()->user();

        $bulk = [];

        foreach ($validate['bulk'] as $reportinfo) {
            $report = ReInstReportTemp::where("instid", 1)->where("statusid", 1)->where("ACTION_CODE", $reportinfo['ACTION_CODE'])->first();

            if ($report && GPController::checkActionCode($report->ACTION_CODE, $user->id)) {
                $bulk[] = $reportinfo;
            } else {
                return $this->error('RC000002', $validate);
            }
        }

        $service = new ReportService();

        $value = ['exporttype' => 2, 'validate' => $bulk];

        $report = $service->generateReportMod($value, $user->instid, $user);

        if ($report) return $report;
        else return $this->error('RC000002', $validate);
    }

    public function download(string $reportkey)
    {
        // exports/reportkey.*
        $files = Storage::disk('public')->files('exports');

        $filePath = collect($files)->first(
            fn($f) =>
            pathinfo($f, PATHINFO_FILENAME) === $reportkey
        );

        if (!$filePath) {
            return response()->json([
                'status' => 'error',
                'message' => 'Файл олдсонгүй.'
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($filePath);

        return response()->download(
            $fullPath,
            basename($filePath)
        )->deleteFileAfterSend(true); // 🔥 татагдсаны дараа устгана
    }
}
